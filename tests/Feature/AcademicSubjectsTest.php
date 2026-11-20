<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\CodeWorkspace;
use App\Models\LessonProgress;
use App\Models\ParentAccount;
use App\Models\ParentStudentLink;
use App\Models\QuizBeeMatch;
use App\Models\RecitationRound;
use App\Models\Student;
use App\Models\TeacherAccount;
use App\Models\TeacherAssignment;
use App\Models\TeacherQuiz;
use App\Services\AcademicCurriculum;
use App\Services\CourseAssessments;
use App\Services\LearningCourses;
use App\Services\ReadyMadeActivities;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class AcademicSubjectsTest extends TestCase
{
    use RefreshDatabase;

    private function room(string $grade = 'Grade 7'): Classroom
    {
        $teacher = TeacherAccount::create(['first_name' => 'Academic', 'last_name' => 'Teacher', 'school' => 'Sample RSHS', 'email' => fake()->unique()->safeEmail(), 'password' => 'Password123']);

        return Classroom::create(['teacher_account_id' => $teacher->id, 'name' => 'Academic classroom', 'grade' => $grade, 'section' => 'Sample', 'join_code' => fake()->unique()->bothify('AC#######')]);
    }

    private function learner(Classroom $room): Student
    {
        return Student::create(['classroom_id' => $room->id, 'first_name' => 'Sample', 'nickname' => fake()->unique()->bothify('Learner####'), 'student_id' => fake()->unique()->bothify('AC######'), 'password' => 'Password123', 'xp' => 100]);
    }

    private function complete(Student $student, string $assessment): void
    {
        DB::table('course_assessment_results')->insert(['student_id' => $student->id, 'assessment' => $assessment, 'score' => 0, 'total_questions' => 50, 'skill_scores' => '{}', 'completed_at' => now()]);
    }

    public function test_all_grade_subjects_have_balanced_packs_and_distinct_assessments(): void
    {
        $courses = array_filter(LearningCourses::keys(), LearningCourses::isAcademic(...));
        $this->assertCount(40, $courses);
        foreach (LearningCourses::groups() as $group) {
            $this->assertCount(11, $group);
        }
        $library = app(ReadyMadeActivities::class);
        $assessments = app(CourseAssessments::class);
        foreach ($courses as $course) {
            $lessons = LearningCourses::lessons($course);
            $catalog = $library->catalog($course);
            $this->assertCount(9, $lessons, $course);
            $this->assertCount(9, $catalog, $course);
            foreach ($lessons as $key => $lesson) {
                $this->assertSame($course, LearningCourses::lessonCourse($key));
                $this->assertSame(LearningCourses::grade($course), $lesson['grade_level']);
                $this->assertLessThanOrEqual(40, strlen($key));
                $this->assertCount(5, $lesson['checks']);
                foreach ($lesson['checks'] as $question) {
                    $this->assertCount(3, array_unique($question['options']));
                    $this->assertArrayHasKey($question['correct'], $question['options']);
                }
                $this->assertCount(5, $catalog[$key]['recitation']);
                $this->assertCount(10, $catalog[$key]['quiz']['questions']);
                foreach ($catalog[$key]['quiz']['questions'] as $question) {
                    $this->assertCount(4, array_unique($question['options']));
                    $this->assertContains($question['correct'][0], $question['options']);
                }
                $this->assertSame('academic', config('code_lab.activities.'.$key.'.environment'));
                $this->assertSame(LearningCourses::title($course), $lesson['ilaw']['learning_area']);
                $this->assertNotEmpty($catalog[$key]['article']['body']);
                $this->assertSame(AcademicCurriculum::UNIT, $catalog[$key]['assignment']['unit']);
            }
            $exams = $library->termExams($course);
            $this->assertCount(9, $exams);
            foreach (['Term1', 'Term2', 'Term3'] as $term) {
                $questions = collect($exams)->where('term', $term)->flatMap(fn ($exam) => $exam['quiz']['questions']);
                $this->assertCount(75, $questions->pluck('prompt')->unique(), $course.' '.$term);
                foreach ($questions as $question) {
                    $this->assertCount(4, array_unique($question['options']));
                    $this->assertContains($question['correct'][0], $question['options']);
                }
            }
            $pre = $assessments->questions(LearningCourses::pretest($course));
            $post = $assessments->questions(LearningCourses::posttest($course));
            $this->assertCount(50, $pre);
            $this->assertCount(50, $post);
            $this->assertCount(50, array_unique(array_column($pre, 'prompt')));
            $this->assertCount(50, array_unique(array_column($post, 'prompt')));
            $this->assertSame([], array_intersect(array_column($pre, 'prompt'), array_column($post, 'prompt')));
            foreach (QuizBeeMatch::questionBank($course) as $level => $bank) {
                $this->assertCount(15, $bank['regular']);
                $this->assertCount(5, $bank['tiebreak']);
                if ($level === 'difficult') {
                    $this->assertContains('fill_blank', array_column($bank['regular'], 'type'));
                }
            }
        }
    }

    public function test_subject_prerequisites_and_xp_are_separate_and_teacher_sees_item_analysis(): void
    {
        $room = $this->room();
        $student = $this->learner($room);
        $this->withSession(['student_id' => $student->id, 'student_class_verified' => true]);
        $url = route('student.lesson.show', 'math7-polygons');
        $this->get($url)->assertRedirect(route('student.assessments.show', ['reasoning', 'course' => 'math7']));
        $this->complete($student, CourseAssessments::REASONING);
        $this->get($url)->assertRedirect(route('student.assessments.show', 'math7-pre-test'));
        $this->get(route('student.assessments.show', 'invalid-pre-test'))->assertNotFound();
        $this->get(route('student.assessments.show', 'math7-pre-test'))->assertOk()->assertSee('Mathematics 7 Pre-Test');
        $questions = app(CourseAssessments::class)->questions('math7-pre-test');
        $answers = array_map(fn ($question) => $question['correct'][0], $questions);
        $this->post(route('student.assessments.submit', 'math7-pre-test'), ['answers' => $answers])->assertRedirect();
        $this->assertSame(350, $student->fresh()->xp);
        $this->post(route('student.assessments.submit', 'math7-pre-test'), ['answers' => $answers])->assertRedirect();
        $this->assertSame(350, $student->fresh()->xp);
        $this->get($url)->assertOk()->assertSee('Study Notebook')->assertDontSee('data-onecompiler-editor="lesson-main-example"', false);
        $this->get(route('student.lesson.show', 'science7-models'))->assertRedirect(route('student.assessments.show', 'science7-pre-test'));
        $this->get(route('student.learn', ['course' => 'math7']))->assertOk()->assertViewHas('lessonPath', fn ($path) => $path['total'] === 9 && $path['course'] === 'math7');
        $this->withSession(['teacher_id' => $room->teacher_account_id, 'student_id' => null])->get(route('teacher.analytics', ['kind' => 'pre', 'course' => 'math7']))->assertOk()->assertViewHas('insight', fn ($insight) => $insight['metrics']['mps'] === 100.0 && count($insight['items']) === 50);
    }

    public function test_teacher_can_review_and_edit_academic_packs(): void
    {
        $room = $this->room('Grade 10');
        $this->withoutMiddleware(ThrottleRequests::class);
        $this->withSession(['teacher_id' => $room->teacher_account_id]);
        $key = 'research10-framework';
        $page = $this->get(route('teacher.library', ['course' => 'research10']))->assertOk()->assertSee('Research 10 · Term 3 · Term Exam')->assertSee('RSHS elective');
        if ($preview = getenv('ACADEMIC_SUBJECT_PREVIEW')) {
            file_put_contents($preview.'/library.html', $page->getContent());
        }
        $this->get(route('teacher.content.edit', $key))->assertOk()->assertSee('Worked classroom example')->assertSee('Embed a lesson video')->assertDontSee('data-onecompiler-editor="lesson-example-editor"', false);
        $definition = LearningCourses::lessons('research10')[$key];
        $this->post(route('teacher.content.save', $key), [...$definition, 'revision' => 0, 'status' => 'published', 'title' => 'Edited academic lesson'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('lesson_contents', ['teacher_account_id' => $room->teacher_account_id, 'lesson_key' => $key, 'status' => 'published']);
        $this->get(route('teacher.ilaw.download', $key))->assertOk()->assertDownload('ilaw-'.$key.'.html');
        foreach (['quiz', 'assignment', 'challenge', 'article'] as $kind) {
            $this->get(route('teacher.library.show', [$key, $kind]))->assertOk();
            $this->post(route('teacher.library.store', [$key, $kind]), ['classroom_ids' => [$room->id], 'school_term' => 'Term1'])->assertRedirect()->assertSessionHasNoErrors();
        }
        $this->post(route('teacher.library.store', ['research10-term3-term-exam', 'quiz']), ['classroom_ids' => [$room->id]])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertCount(25, TeacherQuiz::where('course', 'research10')->latest('id')->firstOrFail()->questions);
        $assignment = TeacherAssignment::where('course', 'research10')->where('kind', 'assignment')->sole();
        $this->get(route('teacher.assignments', ['edit' => $assignment->id]))->assertOk()->assertSee('Subject: Research 10')->assertSee('Study notebook');
        $this->get(route('teacher.assignments', ['course' => 'math10']))->assertOk()->assertSee('Subject: Mathematics 10')->assertSee('Study notebook');
        $this->postJson(route('teacher.assignments.store'), ['course' => 'math10', 'classroom_ids' => [$room->id], 'title' => 'Number patterns', 'school_term' => 'Term1', 'difficulty' => 'Easy', 'xp' => 150, 'status' => 'draft', 'objective' => 'Explain the nth-term formula.'])->assertOk();
        $this->assertDatabaseHas('teacher_assignments', ['course' => 'math10', 'unit' => AcademicCurriculum::UNIT, 'title' => 'Number patterns']);
        $this->get(route('teacher.quiz-bee.create', ['course' => 'research10']))->assertOk()->assertSee('Research 10 Quiz Bee')->assertSee('Fill in the blank');
        $rounds = app(ReadyMadeActivities::class)->lesson($key)['recitation'];
        $this->post(route('teacher.library.store', [$key, 'recitation']), ['classroom_ids' => [$room->id], 'school_term' => 'Term1', 'school_year' => '2026-2027', 'questions' => $rounds])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(5, RecitationRound::where('course', 'research10')->count());
    }

    public function test_study_notebook_and_subject_recitation(): void
    {
        $room = $this->room();
        $student = $this->learner($room);
        $this->withSession(['student_id' => $student->id, 'student_class_verified' => true]);
        $page = $this->get(route('student.code-lab', ['activity' => 'math7-polygons']))->assertOk()->assertSee('study-notebook.txt')->assertSee('Review reasoning')->assertDontSee('data-onecompiler-editor="codeEditor"', false)->assertDontSee('Run C++');
        if ($preview = getenv('ACADEMIC_SUBJECT_PREVIEW')) {
            file_put_contents($preview.'/notebook.html', $page->getContent());
        }
        $workspace = CodeWorkspace::sole();
        $this->assertSame('academic', $workspace->environment());
        $this->putJson(route('student.code-lab.save', $workspace), ['revision' => $workspace->revision, 'code' => 'Hexagon: (6−2)×180° = 720°. Regular angle = 120°.', 'reflection' => 'I checked by dividing total by six.'])->assertOk();
        $workspace->refresh();
        $this->postJson(route('student.code-runs.store', $workspace), ['revision' => $workspace->revision, 'mode' => 'run', 'request_key' => (string) Str::uuid()])->assertUnprocessable()->assertJsonValidationErrors('compiler');
        $this->postJson(route('student.code-lab.submit', $workspace), ['revision' => $workspace->revision])->assertOk();
        $this->assertDatabaseHas('lab_submissions', ['code_workspace_id' => $workspace->id]);
        foreach (['math7', 'math7', 'math7', 'science7'] as $index => $course) {
            $round = RecitationRound::factory()->forClassroom($room)->create(['course' => $course, 'status' => 'open', 'term' => 'Term1', 'school_year' => '2026-2027']);
            $response = $this->postJson(route('student.recitations.submit', $round), ['answer' => 'cout', 'request_key' => (string) Str::uuid()]);
            if ($index === 2) {
                $response->assertConflict();
            } else {
                $response->assertOk()->assertJsonPath('term_progress.correct_answers', $index === 1 ? 2 : 1);
            }
        }
    }

    public function test_academic_posttest_creates_only_its_subject_certificate(): void
    {
        $room = $this->room('Grade 8');
        $student = $this->learner($room);
        $this->complete($student, CourseAssessments::REASONING);
        $this->complete($student, 'english8-pre-test');
        foreach (LearningCourses::lessons('english8') as $key => $lesson) {
            LessonProgress::create(['student_id' => $student->id, 'lesson_key' => $key, 'started_at' => now(), 'last_opened_at' => now(), 'completed_at' => now()]);
        }
        $query = ['course' => 'english8'];
        $this->withSession(['student_id' => $student->id, 'student_class_verified' => true])->post(route('student.post-test.request', $query))->assertRedirect();
        $attempt = DB::table('course_post_test_attempts')->sole();
        $this->withSession(['teacher_id' => $room->teacher_account_id, 'student_id' => null])->post(route('teacher.post-test.issue', [$attempt->id] + $query))->assertRedirect();
        $code = session('post_test_code.code');
        $this->assertNotEmpty($code);
        $this->withSession(['student_id' => $student->id, 'student_class_verified' => true, 'teacher_id' => null])->post(route('student.post-test.start', $query), ['code' => $code])->assertRedirect();
        $attempt = DB::table('course_post_test_attempts')->sole();
        $this->assertSame('english8', $attempt->course);
        $this->assertSame(90, $attempt->question_seconds);
        $this->withoutMiddleware(ThrottleRequests::class);
        $questions = json_decode($attempt->questions, true);
        $this->get(route('student.post-test.show', $query))->assertOk()->assertSee('Question 1 of 50')->assertDontSee($questions[1]['prompt']);
        foreach ($questions as $index => $question) {
            $this->postJson(route('student.post-test.question', $query), ['attempt_id' => $attempt->id, 'question_index' => $index, 'answer' => $question['correct'][0]])->assertOk();
        }
        $this->get(route('student.post-test.show', $query))->assertOk()->assertSee('50 / 50')->assertSee('Passed')->assertDontSee($questions[0]['prompt']);
        $this->assertDatabaseHas('course_certificates', ['student_id' => $student->id, 'course' => 'english8', 'title' => 'Certificate of Excellence in English 8']);
        $this->get(route('student.assessments.certificate', ['course' => 'math8']))->assertNotFound();
    }

    public function test_student_parent_and_principal_views_show_grade_subject_progress_without_expanding_access(): void
    {
        $room = $this->room();
        $student = $this->learner($room);
        LessonProgress::create(['student_id' => $student->id, 'lesson_key' => 'math7-polygons', 'started_at' => now(), 'last_opened_at' => now(), 'completed_at' => now()]);
        $this->withSession(['student_id' => $student->id, 'student_class_verified' => true])->get(route('student.dashboard'))->assertOk()->assertSee('Your grade-level curriculum')->assertSee('Mathematics 7')->assertSee('Research 7')->assertViewHas('overview', fn ($overview) => collect($overview['subjectProgress'])->firstWhere('course', 'math7')['completed'] === 1);
        $parent = ParentAccount::factory()->create();
        $link = ParentStudentLink::create(['parent_account_id' => $parent->id, 'student_reference' => $student->student_id, 'student_id' => $student->id, 'relationship' => 'Parent', 'status' => 'approved', 'approved_classroom_id' => $room->id]);
        $this->withSession(['student_id' => null, 'parent_id' => $parent->id, 'parent_session_version' => 1])->get(route('parent.dashboard', ['child' => $link->id]))->assertOk()->assertSee('Grade-level subject progress')->assertViewHas('subjectProgress', fn ($progress) => collect($progress)->firstWhere('course', 'math7')['completed'] === 1);
        $this->get(route('parent.dashboard', ['child' => $link->id + 100]))->assertNotFound();
        $principal = new TeacherAccount(['first_name' => 'Sample', 'last_name' => 'Principal', 'school' => 'Sample RSHS', 'email' => fake()->unique()->safeEmail(), 'password' => 'Password123']);
        $principal->forceFill(['role' => 'principal'])->save();
        $this->withSession(['parent_id' => null, 'teacher_id' => $principal->id, 'teacher_session_version' => 1])->get(route('school.overview', ['tab' => 'students', 'course' => 'math7']))->assertOk()->assertViewHas('rows', fn ($rows) => $rows->first()['completed'] === 1 && $rows->first()['total'] === 9);
        $this->get(route('school.overview', ['tab' => 'lessons', 'course' => 'math7']))->assertOk();
        $this->get(route('school.overview', ['tab' => 'quizzes', 'course' => 'math7']))->assertOk();
        $this->get(route('school.overview', ['tab' => 'assignments', 'course' => 'math7']))->assertOk();
    }
}
