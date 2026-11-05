<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\CodeWorkspace;
use App\Models\LessonContent;
use App\Models\LessonProgress;
use App\Models\Student;
use App\Models\TeacherAccount;
use App\Models\TeacherAssignment;
use App\Models\TeacherQuiz;
use App\Services\CodeExecutionService;
use App\Services\LessonService;
use App\Services\ReadyMadeActivities;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CppCurriculumTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{teacher: TeacherAccount, room: Classroom, student: Student} */
    private function classroom(): array
    {
        $teacher = TeacherAccount::create(['first_name' => 'Topic', 'last_name' => 'Teacher', 'school' => 'School', 'email' => 'topic@example.test', 'password' => 'password123']);
        $room = Classroom::create(['teacher_account_id' => $teacher->id, 'name' => 'C++', 'grade' => 'Grade 9', 'section' => 'AHB', 'join_code' => 'TOPICAHB']);
        $student = Student::create(['classroom_id' => $room->id, 'first_name' => 'Topic', 'nickname' => 'TopicCoder', 'student_id' => 'TOPIC001', 'password' => 'password123', 'xp' => 125]);

        return compact('teacher', 'room', 'student');
    }

    public function test_requested_topic_order_and_editable_lesson_contracts_are_complete(): void
    {
        $expected = ['introduction', 'get-started', 'syntax', 'output', 'comments', 'variables', 'input', 'data-types', 'operators', 'strings', 'math', 'booleans', 'conditions', 'switch', 'while-loops', 'for-loops', 'break-continue', 'arrays', 'structures', 'enums', 'references', 'pointers', 'memory-management', 'functions', 'function-parameters'];
        $this->assertSame($expected, array_keys(config('lessons')));
        foreach (config('lessons') as $key => $lesson) {
            $this->assertNotEmpty($lesson['subtopics'], $key);
            $this->assertContains($lesson['term'], ['Term1', 'Term2', 'Term3']);
            $this->assertLessThanOrEqual(6, count($lesson['sections']), $key);
            $this->assertNotEmpty(config('code_lab.activities.'.$lesson['practice']), $key);
            $this->assertNotEmpty(config('compiler_tests.'.$lesson['practice']), $key);
            $this->assertSame('https://www.w3schools.com/cpp/default.asp', $lesson['reference_url']);
            if (isset(config('legacy_lessons')[$key])) {
                $this->assertSame(config('legacy_lessons.'.$key.'.checks'), $lesson['checks'], 'Existing checkpoint answers must not be reinterpreted: '.$key);
                $this->assertSame(config('legacy_lessons.'.$key.'.practice'), $lesson['practice']);
            }
        }
        $this->assertContains('Multidimensional arrays', config('lessons.arrays.subtopics'));
        $this->assertContains('Pass structures', config('lessons.function-parameters.subtopics'));
        $this->assertContains('C-style strings', config('lessons.strings.subtopics'));
    }

    public function test_every_topic_quiz_assignment_and_challenge_copies_to_its_owned_editable_builder(): void
    {
        extract($this->classroom());
        $this->withSession(['teacher_id' => $teacher->id]);
        foreach (app(ReadyMadeActivities::class)->catalog() as $key => $lesson) {
            foreach (['quiz', 'assignment', 'challenge'] as $kind) {
                $this->travel(4)->seconds();
                $this->post(route('teacher.library.store', [$key, $kind]), ['classroom_ids' => [$room->id], 'school_term' => $lesson['term']])->assertRedirect()->assertSessionHasNoErrors();
                $model = $kind === 'quiz' ? TeacherQuiz::class : TeacherAssignment::class;
                $copy = $model::latest('id')->firstOrFail();
                $this->assertSame($teacher->id, $copy->teacher_account_id);
                $this->assertSame('draft', $copy->status);
                $this->assertSame($lesson[$kind]['title'], $copy->title);
                $this->assertSame([$room->id], $copy->classrooms()->pluck('classrooms.id')->all());
                if ($kind === 'quiz') {
                    $this->assertCount(10, $copy->questions);
                } else {
                    $this->assertSame($lesson[$kind]['test_cases'], $copy->test_cases);
                }
            }
        }
        $this->assertDatabaseCount('teacher_quizzes', 25);
        $this->assertDatabaseCount('teacher_assignments', 50);
        $this->assertDatabaseCount('challenge_notices', 0);
        $this->assertSame(125, $student->fresh()->xp);
    }

    public function test_retired_topics_remain_accessible_for_existing_work_without_reappearing_as_new_defaults(): void
    {
        extract($this->classroom());
        $this->recordCompletedPretest($student);
        $evidence = ['version' => 2, 'score' => 5, 'total' => 5, 'answers' => array_column(config('legacy_lessons.debugging.checks'), 'correct')];
        $record = LessonProgress::create(['student_id' => $student->id, 'lesson_key' => 'debugging', 'started_at' => now(), 'last_opened_at' => now(), 'completed_at' => now(), 'attempts' => 1, 'best_score' => 5, 'latest_score' => 5, 'answers' => $evidence['answers'], 'completion_evidence' => $evidence]);
        $service = app(LessonService::class);
        $this->assertArrayNotHasKey('debugging', config('lessons'));
        $this->assertArrayNotHasKey('mini-project', $service->catalogForTeacher($teacher->id));
        $this->assertTrue($service->catalogForTeacher($teacher->id)['debugging']['legacy']);
        $this->withSession(['student_id' => $student->id])->get(route('student.lesson.show', 'debugging'))->assertOk()->assertSee('Find and fix a small bug');
        $this->assertSame($evidence, $record->fresh()->completion_evidence);
        $this->assertSame(125, $student->fresh()->xp);
        $this->withSession(['student_id' => null, 'teacher_id' => $teacher->id])->get(route('teacher.content.edit', 'debugging'))->assertOk();
        $this->assertDatabaseCount('learning_rewards', 0);
    }

    public function test_teacher_overrides_are_preserved_and_new_practice_tests_remain_private(): void
    {
        extract($this->classroom());
        $this->recordCompletedPretest($student);
        $definition = config('lessons.pointers');
        $definition['title'] = 'My teacher pointer lesson';
        unset($definition['term'], $definition['subtopics'], $definition['reference_url']);
        LessonContent::create(['teacher_account_id' => $teacher->id, 'lesson_key' => 'pointers', 'definition' => $definition, 'status' => 'published', 'revision' => 1]);
        $this->withSession(['student_id' => $student->id])->get(route('student.lesson.show', 'pointers'))->assertOk()->assertSee('My teacher pointer lesson')->assertSee('Dereferencing');
        $catalog = app(LessonService::class)->catalogForTeacher($teacher->id);
        $this->assertSame('My teacher pointer lesson', $catalog['pointers']['title']);
        $this->assertSame('C++ Pointers', config('lessons.pointers.title'));
        $this->get(route('student.code-lab', ['activity' => 'cpp-data-types']))->assertOk()->assertDontSee('7.00')->assertDontSee('4 1.75');
        $workspace = CodeWorkspace::where('student_id', $student->id)->where('activity_key', 'practice:cpp-data-types')->firstOrFail();
        $cases = app(CodeExecutionService::class)->cases($workspace);
        $this->assertCount(3, $cases);
        $this->assertFalse($cases[0]['hidden']);
        $this->assertTrue($cases[1]['hidden']);
        $this->assertSame('7.50', $cases[0]['output']);
        $this->assertSame(125, $student->fresh()->xp);
        if (getenv('LIBRARY_PREVIEW')) {
            file_put_contents(base_path('.codex/cpp-learn-preview.html'), $this->get(route('student.learn'))->getContent());
            file_put_contents(base_path('.codex/cpp-lesson-preview.html'), $this->get(route('student.lesson.show', 'function-parameters'))->getContent());
            $this->withSession(['student_id' => null, 'teacher_id' => $teacher->id]);
            file_put_contents(base_path('.codex/cpp-studio-preview.html'), $this->get(route('teacher.lessons'))->getContent());
        }
    }
}
