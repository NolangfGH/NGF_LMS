<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\LessonContent;
use App\Models\LessonProgress;
use App\Models\Student;
use App\Models\TeacherAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LessonCheckpointQuestionsTest extends TestCase
{
    use RefreshDatabase;

    private function teacher(): TeacherAccount
    {
        $teacher = TeacherAccount::create(['first_name' => 'Checkpoint', 'last_name' => 'Teacher', 'school' => 'School', 'email' => 'checkpoint@example.test', 'password' => 'password123']);
        $this->withSession(['teacher_id' => $teacher->id]);

        return $teacher;
    }

    private function payload(int $count = 5): array
    {
        $definition = config('lessons.output');
        $definition['checks'] = array_map(fn (int $index) => ['prompt' => 'Trace output question '.($index + 1), 'options' => ['First line', 'Second line', 'No output'], 'correct' => $index % 3, 'why' => 'Trace the statements in order to find the output.'], range(0, $count - 1));

        return $definition + ['revision' => 0, 'status' => 'published'];
    }

    public function test_every_builtin_lesson_contains_five_complete_distinct_checkpoint_questions(): void
    {
        $this->assertCount(25, config('lessons'));
        foreach (config('lessons') as $key => $lesson) {
            $this->assertCount(5, $lesson['checks'], $key);
            $this->assertSame(2, $lesson['version']);
            $this->assertCount(5, array_unique(array_column($lesson['checks'], 'prompt')), $key);
            foreach ($lesson['checks'] as $check) {
                $this->assertNotEmpty($check['prompt']);
                $this->assertCount(3, $check['options']);
                $this->assertArrayHasKey($check['correct'], $check['options']);
                $this->assertNotEmpty($check['why']);
            }
        }
    }

    public function test_new_builtin_and_legacy_editors_offer_at_least_five_questions_without_rewriting_saved_content(): void
    {
        $teacher = $this->teacher();
        $this->get(route('teacher.content.edit', 'new'))->assertOk()->assertViewHas('checkpointChecks', fn ($checks) => count($checks) === 5)->assertSee('Add checkpoint question');
        $this->get(route('teacher.content.edit', 'output'))->assertOk()->assertViewHas('checkpointChecks', fn ($checks) => count($checks) === 5 && filled($checks[4]['why']));
        $legacy = config('lessons.output');
        $legacy['checks'] = array_slice($legacy['checks'], 0, 3);
        $content = LessonContent::create(['teacher_account_id' => $teacher->id, 'lesson_key' => 'output', 'definition' => $legacy, 'status' => 'published', 'revision' => 1]);
        $this->get(route('teacher.content.edit', 'output'))->assertOk()->assertViewHas('checkpointChecks', fn ($checks) => count($checks) === 5 && $checks[0] === $legacy['checks'][0] && $checks[3]['prompt'] === '')->assertSee('Complete the extra fields below before saving.');
        $this->assertSame($legacy, $content->fresh()->definition);
    }

    public static function questionCounts(): array
    {
        return ['three rejected' => [3, false], 'four rejected' => [4, false], 'five accepted' => [5, true], 'six accepted' => [6, true], 'twenty accepted' => [20, true], 'twenty-one rejected' => [21, false]];
    }

    #[DataProvider('questionCounts')]
    public function test_saving_enforces_minimum_and_maximum_question_counts(int $count, bool $accepted): void
    {
        $this->teacher();
        $payload = $this->payload($count);
        $response = $this->postJson(route('teacher.content.save', 'new'), $payload);
        if (! $accepted) {
            $response->assertUnprocessable()->assertJsonValidationErrors('checks');
            $this->assertDatabaseCount('lesson_contents', 0);

            return;
        }
        $response->assertRedirect(route('teacher.content'));
        $content = LessonContent::firstOrFail();
        $this->assertSame($payload['checks'], $content->definition['checks']);
        $this->get(route('teacher.content.edit', $content->lesson_key))->assertOk()->assertViewHas('checkpointChecks', fn ($checks) => count($checks) === $count);
    }

    public function test_each_question_requires_a_prompt_three_choices_valid_answer_and_explanation(): void
    {
        $this->teacher();
        $payload = $this->payload();
        $payload['checks'][0]['prompt'] = '';
        $payload['checks'][1]['options'] = ['One', 'Two'];
        $payload['checks'][2]['correct'] = 3;
        $payload['checks'][3]['why'] = '';
        $payload['checks'][4]['options'][0] = '';
        $this->postJson(route('teacher.content.save', 'new'), $payload)->assertUnprocessable()->assertJsonValidationErrors(['checks.0.prompt', 'checks.1.options', 'checks.2.correct', 'checks.3.why', 'checks.4.options.0']);
        $this->assertDatabaseCount('lesson_contents', 0);
        $sparse = $this->payload();
        $sparse['checks'][7] = $sparse['checks'][4];
        unset($sparse['checks'][4]);
        $this->postJson(route('teacher.content.save', 'new'), $sparse)->assertUnprocessable()->assertJsonValidationErrors('checks');
    }

    public function test_validation_redisplays_all_questions_and_recovers_from_invalid_question_input(): void
    {
        $this->teacher();
        $url = route('teacher.content.edit', 'new');
        $payload = $this->payload(6);
        $payload['title'] = '';
        $this->from($url)->post(route('teacher.content.save', 'new'), $payload)->assertRedirect($url)->assertSessionHasErrors('title');
        $this->get($url)->assertOk()->assertViewHas('checkpointChecks', fn ($checks) => count($checks) === 6 && $checks[5]['prompt'] === $payload['checks'][5]['prompt']);
        $this->from($url)->post(route('teacher.content.save', 'new'), [...$this->payload(), 'checks' => 'invalid'])->assertRedirect($url)->assertSessionHasErrors('checks');
        $this->get($url)->assertOk()->assertViewHas('checkpointChecks', fn ($checks) => count($checks) === 5);
    }

    public function test_five_question_checkpoint_keeps_historical_completion_and_does_not_duplicate_xp(): void
    {
        $teacher = $this->teacher();
        $room = Classroom::create(['teacher_account_id' => $teacher->id, 'name' => 'C++', 'grade' => 'Grade 8', 'section' => 'AM', 'join_code' => 'CHECKAM']);
        $student = Student::create(['classroom_id' => $room->id, 'first_name' => 'Alex', 'nickname' => 'Coder', 'student_id' => 'CHECK1', 'password' => 'password123', 'xp' => 125]);
        $answers = array_column(config('lessons.output.checks'), 'correct');
        $evidence = ['version' => 1, 'score' => 3, 'total' => 3, 'answers' => array_slice($answers, 0, 3), 'reflection' => 'I traced each line in order.'];
        $record = LessonProgress::create(['student_id' => $student->id, 'lesson_key' => 'output', 'answers' => $evidence['answers'], 'attempts' => 1, 'latest_score' => 3, 'best_score' => 3, 'completed_at' => now(), 'started_at' => now(), 'last_opened_at' => now(), 'completion_evidence' => $evidence]);
        $this->recordCompletedPretest($student);
        $this->withSession(['student_id' => $student->id]);
        $this->get(route('student.lesson.show', 'output'))->assertOk()->assertSee('Latest 3 / 3')->assertSee('Get all 5 questions correct');
        $this->get(route('teacher.lessons.progress'))->assertOk()->assertSee('Latest: 3 / 3');
        $payload = ['answers' => array_slice($answers, 0, 3), 'reflection' => 'I traced each line in order.'];
        $this->postJson(route('student.lesson.checkpoint', 'output'), $payload)->assertUnprocessable()->assertJsonValidationErrors(['answers', 'answers.3', 'answers.4']);
        $this->post(route('student.lesson.checkpoint', 'output'), [...$payload, 'answers' => $answers])->assertRedirect();
        $this->assertSame(5, $record->fresh()->latest_score);
        $this->assertSame($evidence, $record->fresh()->completion_evidence);
        $this->assertSame(125, $student->fresh()->xp);
        $this->assertDatabaseCount('learning_rewards', 0);
    }
}
