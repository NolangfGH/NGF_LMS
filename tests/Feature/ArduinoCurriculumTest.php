<?php

namespace Tests\Feature;

use App\Models\BlogPost;
use App\Models\Classroom;
use App\Models\CodeWorkspace;
use App\Models\LessonContent;
use App\Models\LessonProgress;
use App\Models\Student;
use App\Models\TeacherAccount;
use App\Models\TeacherQuiz;
use App\Services\CourseAssessments;
use App\Services\CoursePostTest;
use App\Services\LearningNotifications;
use App\Services\LessonService;
use App\Services\ReadyMadeActivities;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ArduinoCurriculumTest extends TestCase
{
    use RefreshDatabase;

    private function classroom(): array
    {
        $teacher = TeacherAccount::create(['first_name' => 'Robotics', 'last_name' => 'Teacher', 'school' => 'School', 'email' => 'robotics@example.test', 'password' => 'Password123']);
        $room = Classroom::create(['teacher_account_id' => $teacher->id, 'name' => 'Grade 9 Robotics', 'grade' => 'Grade 9', 'section' => 'AM', 'join_code' => 'ROBOTICS9']);
        $student = Student::create(['classroom_id' => $room->id, 'first_name' => 'Robot', 'nickname' => 'Builder', 'student_id' => 'ROBOT001', 'password' => 'Password123', 'xp' => 100]);

        return [$teacher, $room, $student];
    }

    private function finishPretest(Student $student): void
    {
        DB::table('course_assessment_results')->insert(['student_id' => $student->id, 'assessment' => CourseAssessments::ARDUINO_PRE, 'score' => 0, 'total_questions' => 50, 'skill_scores' => '{}', 'completed_at' => now()]);
    }

    public function test_all_topics_have_complete_checkpoints_quizzes_tasks_articles_and_ilaw_plans(): void
    {
        $this->assertCount(25, config('arduino_curriculum.lessons'));
        $catalog = app(ReadyMadeActivities::class)->catalog('arduino-uno');
        $this->assertCount(25, $catalog);
        foreach (config('arduino_curriculum.lessons') as $key => $lesson) {
            $this->assertSame('Grade 9', $lesson['grade_level']);
            $this->assertCount(5, $lesson['checks']);
            $this->assertCount(5, array_unique(array_column($lesson['checks'], 'prompt')));
            foreach ($lesson['checks'] as $question) {
                $this->assertCount(3, array_unique($question['options']));
                $this->assertArrayHasKey($question['correct'], $question['options']);
            }
            $this->assertCount(10, $catalog[$key]['quiz']['questions']);
            foreach ($catalog[$key]['quiz']['questions'] as $question) {
                $this->assertCount(4, array_unique($question['options']), $question['prompt']);
                $this->assertCount(1, $question['correct']);
                $this->assertContains($question['correct'][0], $question['options']);
            }
            foreach (['assignment', 'challenge'] as $kind) {
                $this->assertSame('Arduino UNO R3', $catalog[$key][$kind]['unit']);
                $this->assertNotEmpty($catalog[$key][$kind]['sample_output']);
                $this->assertSame([], $catalog[$key][$kind]['test_cases']);
            }
            foreach (['intentions', 'learning_experience', 'assessing_learning', 'ways_forward', 'resources'] as $field) {
                $this->assertNotEmpty($lesson['ilaw'][$field]);
                $this->assertLessThanOrEqual(6000, mb_strlen($lesson['ilaw'][$field]));
            }
            $this->assertStringContainsString($lesson['code'], $catalog[$key]['article']['body']);
            $this->assertNotEmpty($catalog[$key]['article']['teacher_notes']);
            $this->assertStringContainsString('void setup()', $lesson['code']);
            $this->assertStringContainsString('void loop()', $lesson['code']);
        }
    }

    public function test_each_term_has_three_nonrepeating_twenty_five_question_sets_and_course_tests_have_fifty_questions(): void
    {
        $exams = app(ReadyMadeActivities::class)->termExams('arduino-uno');
        $this->assertCount(9, $exams);
        foreach (['Term1', 'Term2', 'Term3'] as $term) {
            $sets = array_filter($exams, fn ($exam) => $exam['term'] === $term);
            $this->assertCount(3, $sets);
            $prompts = [];
            foreach ($sets as $exam) {
                $this->assertCount(25, $exam['quiz']['questions']);
                $this->assertSame(60, $exam['quiz']['question_seconds']);
                $prompts = [...$prompts, ...array_column($exam['quiz']['questions'], 'prompt')];
            }
            $this->assertCount(75, array_unique($prompts));
        }
        foreach ([CourseAssessments::ARDUINO_PRE, CourseAssessments::ARDUINO_POST] as $assessment) {
            $questions = app(CourseAssessments::class)->questions($assessment);
            $this->assertCount(50, $questions);
            $this->assertCount(25, array_unique(array_column($questions, 'skill')));
            $this->assertSame(['multiple_choice'], array_values(array_unique(array_column($questions, 'type'))));
        }
    }

    public function test_teacher_packs_copy_as_private_editable_drafts_and_ilaw_downloads_are_filled(): void
    {
        [$teacher,$room] = $this->classroom();
        $this->withSession(['teacher_id' => $teacher->id]);
        $this->get(route('teacher.library', ['course' => 'arduino-uno']))->assertOk()->assertSee('Grade 9 Arduino lessons')->assertSee('Arduino · Term 3 · Term Exam');
        if ($preview = getenv('ARDUINO_PREVIEW')) {
            file_put_contents($preview.'/library.html', $this->get(route('teacher.library', ['course' => 'arduino-uno']))->getContent());
            file_put_contents($preview.'/editor.html', $this->get(route('teacher.content.edit', 'output'))->getContent());
            file_put_contents($preview.'/arduino-editor.html', $this->get(route('teacher.content.edit', 'arduino-ultrasonic'))->getContent());
        }
        $key = 'arduino-ultrasonic';
        foreach (['quiz', 'assignment', 'challenge', 'article'] as $kind) {
            $this->get(route('teacher.library.show', [$key, $kind]))->assertOk();
            $this->travel(4)->seconds();
            $this->post(route('teacher.library.store', [$key, $kind]), ['classroom_ids' => [$room->id], 'school_term' => 'Term2'])->assertRedirect()->assertSessionHasNoErrors();
        }
        $this->assertDatabaseHas('teacher_assignments', ['unit' => 'Arduino UNO R3', 'status' => 'draft']);
        $this->assertCount(10, TeacherQuiz::firstOrFail()->questions);
        $this->assertSame('draft', BlogPost::firstOrFail()->status);
        foreach (app(ReadyMadeActivities::class)->termExams('arduino-uno') as $exam) {
            $this->travel(4)->seconds();
            $this->post(route('teacher.library.store', [$exam['key'], 'quiz']), ['classroom_ids' => [$room->id]])->assertRedirect()->assertSessionHasNoErrors();
            $this->assertCount(25, TeacherQuiz::latest('id')->firstOrFail()->questions);
        }
        $this->get(route('teacher.ilaw.edit', $key))->assertOk()->assertSee('Grade 9 Robotics')->assertSee('Three comparison rows');
        $this->get(route('teacher.ilaw.download', $key))->assertOk()->assertDownload('ilaw-'.$key.'.html')->assertSee('HC-SR04');
        $this->assertDatabaseCount('challenge_notices', 0);
    }

    public function test_course_pretests_and_progress_remain_separate(): void
    {
        [,, $student] = $this->classroom();
        $this->withSession(['student_id' => $student->id, 'student_class_verified' => true]);
        $this->get(route('student.lesson.show', 'arduino-robotics'))->assertRedirect(route('student.assessments.show', CourseAssessments::ARDUINO_PRE));
        $questions = app(CourseAssessments::class)->questions(CourseAssessments::ARDUINO_PRE);
        $this->get(route('student.assessments.show', CourseAssessments::ARDUINO_PRE))->assertOk()->assertSee('50 questions');
        $this->post(route('student.assessments.submit', CourseAssessments::ARDUINO_PRE), ['answers' => array_map(fn ($q) => $q['correct'][0], $questions)])->assertRedirect();
        $this->get(route('student.lesson.show', 'arduino-robotics'))->assertOk()->assertSee('Introduction to Robotics');
        $this->get(route('student.lesson.show', 'introduction'))->assertRedirect(route('student.assessments.show', CourseAssessments::PRE));
        $this->get(route('student.learn', ['course' => 'arduino-uno']))->assertOk()->assertViewHas('lessonPath', fn ($p) => $p['total'] === 25 && $p['course'] === 'arduino-uno');
        if ($preview = getenv('ARDUINO_PREVIEW')) {
            file_put_contents($preview.'/learn.html', $this->get(route('student.learn', ['course' => 'arduino-uno']))->getContent());
            file_put_contents($preview.'/lesson.html', $this->get(route('student.lesson.show', 'arduino-robotics'))->getContent());
        }
        $this->assertCount(25, app(LessonService::class)->catalogForTeacher(null));
        $this->assertCount(50, app(LessonService::class)->catalogForTeacher(null, null));
        $this->assertSame(['completed' => 0, 'total' => 25], app(CourseAssessments::class)->progress($student));
    }

    public function test_arduino_workspaces_save_sketches_and_reject_console_execution(): void
    {
        [$teacher,,$student] = $this->classroom();
        $this->withSession(['student_id' => $student->id, 'student_class_verified' => true]);
        $this->get(route('student.code-lab', ['activity' => 'arduino-blink']))->assertOk()->assertSee('sketch.ino')->assertSee('Arduino IDE')->assertDontSee('data-onecompiler-editor="codeEditor"', false)->assertDontSee('Run C++');
        $workspace = CodeWorkspace::firstOrFail();
        $this->assertTrue($workspace->isArduino());
        $code = "void setup() { pinMode(LED_BUILTIN, OUTPUT); }\nvoid loop() { digitalWrite(LED_BUILTIN, HIGH); }";
        $this->putJson(route('student.code-lab.save', $workspace), ['revision' => $workspace->revision, 'code' => $code, 'reflection' => 'I checked the board and predicted a steady built-in LED.'])->assertOk();
        $workspace->refresh();
        $this->postJson(route('student.code-runs.store', $workspace), ['revision' => $workspace->revision, 'mode' => 'run', 'request_key' => (string) Str::uuid()])->assertUnprocessable()->assertJsonValidationErrors('compiler');
        $this->postJson(route('student.code-lab.submit', $workspace), ['revision' => $workspace->revision])->assertOk();
        $this->assertSame($code, $workspace->submissions()->sole()->code);
        $this->assertSame($teacher->id, $workspace->submissions()->sole()->teacher_account_id);
    }

    public function test_arduino_exam_requests_and_codes_stay_in_the_selected_course_and_owned_classroom(): void
    {
        [$teacher, , $student] = $this->classroom();
        $this->finishPretest($student);
        foreach (config('arduino_curriculum.lessons') as $key => $lesson) {
            LessonProgress::create(['student_id' => $student->id, 'lesson_key' => $key, 'completed_at' => now(), 'started_at' => now(), 'last_opened_at' => now()]);
        }
        $query = ['course' => 'arduino-uno'];
        $this->withSession(['student_id' => $student->id, 'student_class_verified' => true]);
        $this->post(route('student.post-test.request', $query))->assertRedirect(route('student.post-test.show', $query));
        $attempt = DB::table('course_post_test_attempts')->sole();
        $this->assertSame('arduino-uno', $attempt->course);
        $this->withSession(['student_id' => null, 'teacher_id' => $teacher->id]);
        $this->get(route('teacher.post-test.index'))->assertOk()->assertDontSee($student->nickname);
        $this->get(route('teacher.post-test.index', $query))->assertOk()->assertSee($student->nickname);
        $this->post(route('teacher.post-test.issue', [$attempt->id] + $query))->assertRedirect();
        $code = session('post_test_code.code');
        $this->withSession(['teacher_id' => null, 'student_id' => $student->id]);
        $this->postJson(route('student.post-test.start'), ['code' => $code])->assertForbidden();
        $this->post(route('student.post-test.start', $query), ['code' => $code])->assertRedirect(route('student.post-test.show', $query));
        $this->get(route('student.post-test.show', $query))->assertOk()->assertSee('course=arduino-uno', false)->assertSee('Question 1 of 50');
        if ($preview = getenv('ARDUINO_PREVIEW')) {
            file_put_contents($preview.'/post-test.html', $this->get(route('student.post-test.show', $query))->getContent());
        }
        $this->postJson(route('student.post-test.question'), ['attempt_id' => $attempt->id, 'question_index' => 0])->assertConflict();
    }

    public function test_explanations_save_and_display_their_own_examples_outputs_and_onecompiler_buttons(): void
    {
        [$teacher,,$student] = $this->classroom();
        $this->withSession(['teacher_id' => $teacher->id]);
        $definition = config('lessons.output');
        $definition['sections'][0]['code'] = "#include <iostream>\nint main() { std::cout << \"One\"; }";
        $definition['sections'][0]['output'] = 'One';
        $definition['sections'][1]['code'] = "#include <iostream>\nint main() { std::cout << \"Two\"; }";
        $definition['sections'][1]['output'] = 'Two';
        $this->post(route('teacher.content.save', 'output'), $definition + ['revision' => 0, 'status' => 'published'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('One', LessonContent::sole()->definition['sections'][0]['output']);
        $this->get(route('teacher.content.edit', 'output'))->assertOk()->assertSee('sections[0][code]', false)->assertSee('explanation-1-code')->assertSee('Add explanation');
        $this->recordCompletedPretest($student);
        $this->withSession(['teacher_id' => null, 'student_id' => $student->id, 'student_class_verified' => true])->get(route('student.lesson.show', 'output'))->assertOk()->assertSee('lesson-section-0-code')->assertSee('lesson-section-1-code')->assertSee('One')->assertSee('Two');
    }

    public function test_explanation_validation_rejects_oversized_examples_and_non_list_or_excess_sections(): void
    {
        [$teacher] = $this->classroom();
        $this->withSession(['teacher_id' => $teacher->id]);
        $payload = config('lessons.output') + ['revision' => 0, 'status' => 'published'];
        $invalid = $payload;
        $invalid['sections'][0]['code'] = str_repeat('x', 20001);
        $this->postJson(route('teacher.content.save', 'output'), $invalid)->assertUnprocessable()->assertJsonValidationErrors('sections.0.code');
        $invalid = $payload;
        $invalid['sections'] = ['untrusted' => $payload['sections'][0]];
        $this->postJson(route('teacher.content.save', 'output'), $invalid)->assertUnprocessable()->assertJsonValidationErrors('sections');
        $invalid['sections'] = array_fill(0, 13, $payload['sections'][0]);
        $this->postJson(route('teacher.content.save', 'output'), $invalid)->assertUnprocessable()->assertJsonValidationErrors('sections');
        $this->assertDatabaseCount('lesson_contents', 0);
        $invalid['sections'] = 'invalid';
        $this->from(route('teacher.content.edit', 'output'))->post(route('teacher.content.save', 'output'), $invalid)->assertRedirect()->assertSessionHasErrors('sections');
        $this->get(route('teacher.content.edit', 'output'))->assertOk()->assertViewHas('explanationSections', fn ($sections) => count($sections) === 1);
    }

    public function test_editing_an_arduino_lesson_preserves_its_course_and_sketch_editor_after_saving(): void
    {
        [$teacher] = $this->classroom();
        $this->withSession(['teacher_id' => $teacher->id]);
        $this->post(route('teacher.content.save', 'arduino-blink'), config('arduino_curriculum.lessons.arduino-blink') + ['revision' => 0, 'status' => 'draft'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('arduino-uno', LessonContent::sole()->definition['course']);
        $this->get(route('teacher.content.edit', 'arduino-blink'))->assertOk()->assertSee('Worked Arduino sketch (.ino)')->assertDontSee('data-onecompiler-editor=', false);
    }

    public function test_arduino_certification_uses_separate_timed_attempts_and_awards_only_after_forty_five_correct(): void
    {
        [$teacher,,$student] = $this->classroom();
        $this->finishPretest($student);
        foreach (config('arduino_curriculum.lessons') as $key => $lesson) {
            LessonProgress::create(['student_id' => $student->id, 'lesson_key' => $key, 'completed_at' => now(), 'started_at' => now(), 'last_opened_at' => now()]);
        }
        $exam = app(CoursePostTest::class)->forCourse('arduino-uno');
        $notifications = app(LearningNotifications::class);
        $this->freezeSecond();
        foreach ([1 => 44, 2 => 45] as $number => $target) {
            $exam->requestCode($student, $notifications);
            $attempt = DB::table('course_post_test_attempts')->where('course', 'arduino-uno')->latest('id')->first();
            $exam->start($student, $exam->issueCode($teacher, $attempt->id));
            $attempt = DB::table('course_post_test_attempts')->find($attempt->id);
            $this->assertSame($number === 1 ? 90 : 60, $attempt->question_seconds);
            $items = json_decode($attempt->questions, true);
            $this->assertCount(50, $items);
            $this->withSession(['student_id' => $student->id, 'student_class_verified' => true])->get(route('student.post-test.show', ['course' => 'arduino-uno']))->assertOk()->assertSee('Arduino Uno Certification')->assertSee($items[0]['prompt'])->assertDontSee($items[1]['prompt'])->assertViewHas('state', fn ($s) => ! isset($s['current']['question']['correct']));
            for ($i = 0; $i < 50; $i++) {
                $exam->answer($student, $attempt->id, $i, $i < $target ? $items[$i]['correct'][0] : null, true);
            }
            $this->get(route('student.post-test.show', ['course' => 'arduino-uno']))->assertOk()->assertSee($target.' / 50')->assertSee($target === 44 ? 'Failed' : 'Passed')->assertDontSee($items[0]['prompt']);
            $this->assertNull(DB::table('course_post_test_attempts')->find($attempt->id)->questions);
            $this->assertSame(0, DB::table('course_post_test_attempts')->where('course', 'cpp')->count());
        }
        $this->get(route('student.assessments.certificate', ['course' => 'arduino-uno']))->assertOk()->assertSee('Certificate of Excellence in Arduino Uno Robotics');
        $this->get(route('student.assessments.certificate'))->assertNotFound();
        $this->assertDatabaseCount('course_certificates', 1);
        $this->postJson(route('student.post-test.request', ['course' => 'arduino-uno']))->assertConflict();
    }
}
