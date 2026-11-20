<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\CodeWorkspace;
use App\Models\CodingMatch;
use App\Models\LessonProgress;
use App\Models\QuizBeeMatch;
use App\Models\Student;
use App\Models\TeacherAccount;
use App\Models\TeacherQuiz;
use App\Services\CourseAssessments;
use App\Services\LearningCourses;
use App\Services\ReadyMadeActivities;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class CreativeTechnologyCoursesTest extends TestCase
{
    use RefreshDatabase;

    private function room(string $grade): Classroom
    {
        $teacher = TeacherAccount::create(['first_name' => 'Creative', 'last_name' => 'Teacher', 'school' => 'Sample school', 'email' => fake()->unique()->safeEmail(), 'password' => 'Password123']);

        return Classroom::create(['teacher_account_id' => $teacher->id, 'name' => 'Design and technology', 'grade' => $grade, 'section' => 'AM', 'join_code' => fake()->unique()->bothify('CT#######')]);
    }

    private function learner(Classroom $room): Student
    {
        return Student::create(['classroom_id' => $room->id, 'first_name' => 'Sample', 'nickname' => fake()->unique()->bothify('Creative####'), 'student_id' => fake()->unique()->bothify('CT######'), 'password' => 'Password123', 'xp' => 100]);
    }

    private function studentSession(Student $student): static
    {
        return $this->withSession(['student_id' => $student->id, 'student_class_verified' => true, 'teacher_id' => null]);
    }

    private function teacherSession(Classroom $room): static
    {
        return $this->withSession(['teacher_id' => $room->teacher_account_id, 'student_id' => null]);
    }

    private function complete(Student $student, string $assessment): void
    {
        DB::table('course_assessment_results')->insert(['student_id' => $student->id, 'assessment' => $assessment, 'score' => 0, 'total_questions' => 50, 'skill_scores' => '{}', 'completed_at' => now()]);
    }

    public function test_both_curricula_have_complete_original_learning_and_assessment_packs(): void
    {
        foreach (['ct7' => 'Grade 7', 'ct10' => 'Grade 10'] as $course => $grade) {
            $library = app(ReadyMadeActivities::class);
            $lessons = LearningCourses::lessons($course);
            $packs = $library->catalog($course);
            $this->assertCount(25, $lessons);
            $this->assertCount(25, $packs);
            foreach ($lessons as $key => $lesson) {
                $this->assertSame($grade, $lesson['grade_level']);
                $this->assertCount(5, $lesson['checks']);
                $this->assertCount(5, array_unique(array_column($lesson['checks'], 'prompt')));
                foreach ($lesson['checks'] as $question) {
                    $this->assertCount(3, array_unique($question['options']));
                    $this->assertArrayHasKey($question['correct'], $question['options']);
                }
                $this->assertSame($course, $packs[$key]['course']);
                $this->assertCount(5, $packs[$key]['recitation']);
                $this->assertCount(10, $packs[$key]['quiz']['questions']);
                foreach ($packs[$key]['quiz']['questions'] as $question) {
                    $this->assertCount(4, array_unique($question['options']));
                    $this->assertContains($question['correct'][0], $question['options']);
                }
                $this->assertNotEmpty($packs[$key]['article']['body']);
                $this->assertNotEmpty($packs[$key]['assignment']['sample_output']);
                $this->assertNotEmpty($packs[$key]['challenge']['objective']);
                $this->assertSame(LearningCourses::title($course), $lesson['ilaw']['learning_area']);
                $this->assertSame(LearningCourses::environment($course), config('code_lab.activities.'.$key.'.environment'));
            }
            $exams = $library->termExams($course);
            $this->assertCount(9, $exams);
            foreach (['Term1', 'Term2', 'Term3'] as $term) {
                $sets = collect($exams)->where('term', $term);
                $this->assertCount(3, $sets);
                $this->assertCount(75, $sets->flatMap(fn ($exam) => array_column($exam['quiz']['questions'], 'prompt'))->unique());
            }
            $assessments = app(CourseAssessments::class);
            $pre = $assessments->questions(LearningCourses::pretest($course));
            $post = $assessments->questions(LearningCourses::posttest($course));
            $this->assertCount(50, $pre);
            $this->assertCount(50, $post);
            $this->assertSame([], array_intersect(array_column($pre, 'prompt'), array_column($post, 'prompt')));
            $bee = QuizBeeMatch::questionBank($course);
            foreach ($bee as $level => $bank) {
                $this->assertCount(15, $bank['regular']);
                $this->assertCount(5, $bank['tiebreak']);
                if ($level === 'difficult') {
                    $this->assertContains('fill_blank', array_column($bank['regular'], 'type'));
                }
            }
        }
    }

    public function test_pretest_gates_rewards_and_analytics_are_course_specific(): void
    {
        $room = $this->room('Grade 7');
        $student = $this->learner($room);
        $this->studentSession($student);
        $lesson = route('student.lesson.show', 'ct7-applications');
        $this->get($lesson)->assertRedirect(route('student.assessments.show', ['reasoning', 'course' => 'ct7']));
        $this->complete($student, CourseAssessments::REASONING);
        $this->get($lesson)->assertRedirect(route('student.assessments.show', 'ct7-pre-test'));
        $questions = app(CourseAssessments::class)->questions('ct7-pre-test');
        $this->get(route('student.assessments.show', 'ct7-pre-test'))->assertOk()->assertSee('Creative Technology 7 Pre-Test');
        $answers = array_map(fn ($question) => $question['correct'][0], $questions);
        $this->post(route('student.assessments.submit', 'ct7-pre-test'), ['answers' => $answers])->assertRedirect();
        $this->assertDatabaseHas('course_assessment_results', ['student_id' => $student->id, 'assessment' => 'ct7-pre-test', 'score' => 50]);
        $this->assertSame(350, $student->fresh()->xp);
        $this->get($lesson)->assertOk()->assertSee('Design Notebook')->assertDontSee('data-onecompiler-editor="lesson-main-example"', false);
        $this->get(route('student.lesson.show', 'ct10-robotics-systems'))->assertRedirect(route('student.assessments.show', 'ct10-pre-test'));
        $this->get(route('student.learn', ['course' => 'ct7']))->assertOk()->assertViewHas('lessonPath', fn ($path) => $path['course'] === 'ct7' && $path['total'] === 25 && ! $path['pretest_required']);
        $this->teacherSession($room)->get(route('teacher.analytics', ['kind' => 'pre', 'course' => 'ct7']))->assertOk()
            ->assertViewHas('insight', fn ($insight) => $insight['metrics']['mps'] === 100.0 && count($insight['items']) === 50);
        $this->get(route('teacher.analytics', ['kind' => 'pre', 'course' => 'ct10']))->assertOk()->assertViewHas('insight', fn ($insight) => $insight['metrics']['count'] === 0);
    }

    public function test_teacher_drafts_plans_and_subject_filter_preserve_course(): void
    {
        $room = $this->room('Grade 7');
        $this->withoutMiddleware(ThrottleRequests::class);
        $this->teacherSession($room);
        $key = 'ct7-modeling';
        $libraryPage = $this->get(route('teacher.library', ['course' => 'ct7']))->assertOk()->assertSee('Creative Technology 7 · Term 3 · Term Exam');
        if ($preview = getenv('CREATIVE_SUBJECT_PREVIEW')) {
            file_put_contents($preview.'/library.html', $libraryPage->getContent());
        }
        $this->get(route('teacher.content', ['course' => 'ct7']))->assertOk()->assertSee('From 2D sketches to 3D models');
        $this->get(route('teacher.content.edit', $key))->assertOk()->assertSee('Worked design example')->assertSee('Embed a lesson video')->assertDontSee('data-onecompiler-editor="lesson-example-editor"', false);
        $this->get(route('teacher.ilaw.download', $key))->assertOk()->assertDownload('ilaw-'.$key.'.html')->assertSee('Creative Technology 7');
        foreach (['quiz', 'assignment', 'challenge', 'article'] as $kind) {
            $this->get(route('teacher.library.show', [$key, $kind]))->assertOk();
            $this->post(route('teacher.library.store', [$key, $kind]), ['classroom_ids' => [$room->id], 'school_term' => 'Term2'])->assertRedirect()->assertSessionHasNoErrors();
        }
        $this->assertDatabaseHas('teacher_assignments', ['unit' => 'Creative Technology 7 Design', 'status' => 'draft']);
        $this->assertDatabaseHas('teacher_quizzes', ['course' => 'ct7', 'status' => 'draft']);
        $this->post(route('teacher.library.store', ['ct10-term1-term-exam', 'quiz']), ['classroom_ids' => [$room->id]])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertCount(25, TeacherQuiz::where('course', 'ct10')->sole()->questions);
        TeacherQuiz::query()->update(['status' => 'published']);
        $this->get(route('teacher.analytics', ['kind' => 'quiz', 'course' => 'ct7']))->assertOk()->assertViewHas('exams', fn ($exams) => $exams->count() === 1 && $exams->first()->course === 'ct7');
        $this->get(route('teacher.quiz-bee.create', ['course' => 'ct10']))->assertOk()->assertSee('Creative Technology 10 Quiz Bee')->assertSee('Fill in the blank');
    }

    public function test_design_notebook_saves_and_submits_and_grade_ten_uses_wokwi(): void
    {
        $room = $this->room('Grade 7');
        $student = $this->learner($room);
        $this->studentSession($student);
        $notebook = $this->get(route('student.code-lab', ['activity' => 'ct7-profiles']))->assertOk()->assertSee('design-notebook.txt')->assertDontSee('data-onecompiler-editor="codeEditor"', false)->assertDontSee('Run C++');
        if ($preview = getenv('CREATIVE_SUBJECT_PREVIEW')) {
            file_put_contents($preview.'/notebook.html', $notebook->getContent());
        }
        $workspace = CodeWorkspace::sole();
        $this->assertSame('design', $workspace->environment());
        $design = 'Profile: rectangular base 50 mm by 30 mm. Center circle diameter 10 mm. Verified dimensions.';
        $this->putJson(route('student.code-lab.save', $workspace), ['revision' => $workspace->revision, 'code' => $design, 'reflection' => 'I compared measurements with the specified dimensions.'])->assertOk();
        $workspace->refresh();
        $this->postJson(route('student.code-runs.store', $workspace), ['revision' => $workspace->revision, 'mode' => 'run', 'request_key' => (string) Str::uuid()])->assertUnprocessable()->assertJsonValidationErrors('compiler');
        $this->postJson(route('student.code-lab.submit', $workspace), ['revision' => $workspace->revision])->assertOk();
        $this->assertSame($design, $workspace->submissions()->sole()->code);
        $this->get(route('student.code-lab', ['activity' => 'ct10-timers']))->assertOk()->assertSee('sketch.ino')->assertSee('Open in Wokwi');
        $this->teacherSession($room)->get(route('teacher.code-lab'))->assertOk()->assertSee('design-notebook.txt');
    }

    public function test_certification_is_independent_and_uses_the_existing_timed_exam_rules(): void
    {
        $room = $this->room('Grade 10');
        $student = $this->learner($room);
        $this->complete($student, CourseAssessments::REASONING);
        $this->complete($student, 'ct10-pre-test');
        foreach (LearningCourses::lessons('ct10') as $key => $lesson) {
            LessonProgress::create(['student_id' => $student->id, 'lesson_key' => $key, 'started_at' => now(), 'last_opened_at' => now(), 'completed_at' => now()]);
        }
        $query = ['course' => 'ct10'];
        $this->studentSession($student)->post(route('student.post-test.request', $query))->assertRedirect();
        $attempt = DB::table('course_post_test_attempts')->sole();
        $this->assertSame('ct10', $attempt->course);
        $this->teacherSession($room)->post(route('teacher.post-test.issue', [$attempt->id] + $query))->assertRedirect();
        $code = session('post_test_code.code');
        $this->studentSession($student)->post(route('student.post-test.start', $query), ['code' => $code])->assertRedirect();
        $attempt = DB::table('course_post_test_attempts')->sole();
        $this->assertSame(90, $attempt->question_seconds);
        $questions = json_decode($attempt->questions, true);
        $this->get(route('student.post-test.show', $query))->assertOk()->assertSee('Question 1 of 50')->assertDontSee($questions[1]['prompt']);
        $this->withoutMiddleware(ThrottleRequests::class);
        foreach ($questions as $index => $question) {
            $this->postJson(route('student.post-test.question', $query), ['attempt_id' => $attempt->id, 'question_index' => $index, 'answer' => $question['correct'][0]])->assertOk();
        }
        $this->get(route('student.post-test.show', $query))->assertOk()->assertSee('50 / 50')->assertSee('Passed')->assertDontSee($questions[0]['prompt']);
        $this->assertDatabaseHas('course_certificates', ['student_id' => $student->id, 'course' => 'ct10', 'title' => 'Certificate of Excellence in Creative Technology 10']);
        $this->assertDatabaseHas('course_post_test_attempts', ['id' => $attempt->id, 'questions' => null, 'answers' => null]);
        $this->get(route('student.assessments.certificate', $query))->assertOk()->assertSee('Creative Technology 10');
        $this->get(route('student.assessments.certificate', ['course' => 'ct7']))->assertNotFound();
    }

    public function test_same_grade_peer_challenge_can_use_design_subject_and_requires_teacher_approval(): void
    {
        $room = $this->room('Grade 7');
        $first = $this->learner($room);
        $second = $this->learner($room);
        foreach ([$first, $second] as $student) {
            $this->complete($student, CourseAssessments::REASONING);
            $this->complete($student, 'ct7-pre-test');
        }
        $this->studentSession($first)->post(route('student.peer-challenges.store'), ['opponent' => $second->id, 'course' => 'ct7', 'lesson' => 'ct7-applications'])->assertRedirect();
        $match = CodingMatch::sole();
        $this->assertSame('awaiting_approval', $match->status);
        $this->assertStringContainsString('Design steps', $match->participants()->first()->draft_code);
        $this->teacherSession($room)->get(route('teacher.peer-challenges.review', $match))->assertOk()->assertSee('Creative Technology 7')->assertSee('Approve participation');
    }
}
