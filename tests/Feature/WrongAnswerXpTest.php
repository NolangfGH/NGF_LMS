<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\LessonProgress;
use App\Models\QuizAttempt;
use App\Models\RecitationRound;
use App\Models\Student;
use App\Models\TeacherAccount;
use App\Models\TeacherQuiz;
use App\Services\CoursePostTest;
use App\Services\LearningNotifications;
use App\Services\QuizAttemptService;
use App\Services\QuizRewards;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class WrongAnswerXpTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $teacher = TeacherAccount::create(['first_name' => 'XP', 'last_name' => 'Teacher', 'email' => 'penalties@example.test', 'school' => 'School', 'password' => 'Password123']);
        $room = Classroom::create(['teacher_account_id' => $teacher->id, 'name' => 'C++', 'grade' => 'Grade 8', 'section' => 'AM', 'join_code' => 'XPTEST']);
        $student = Student::create(['classroom_id' => $room->id, 'first_name' => 'Test', 'nickname' => 'PenaltyCoder', 'student_id' => 'PENALTY01', 'password' => 'Password123', 'xp' => 100]);
        $this->recordCompletedLessonPrerequisites($student);
        $this->withSession(['student_id' => $student->id, 'student_session_version' => 1, 'student_class_verified' => true]);

        return [$teacher, $room, $student];
    }

    public function test_checkpoint_deducts_two_per_wrong_answer_and_replay_cannot_charge_or_overwrite_progress_twice(): void
    {
        [, , $student] = $this->fixture();
        $this->get(route('student.lesson.show', 'introduction'))->assertOk();
        $answers = array_column(config('lessons.introduction.checks'), 'correct');
        $wrong = $answers;
        foreach ([0, 1] as $index) {
            $wrong[$index] = ($answers[$index] + 1) % count(config('lessons.introduction.checks')[$index]['options']);
        }
        $payload = ['request_key' => Str::uuid()->toString(), 'answers' => $wrong, 'reflection' => 'I tested my understanding of programming algorithms.'];
        $url = route('student.lesson.checkpoint', 'introduction');
        $this->post($url, $payload)->assertRedirect();
        $this->assertSame(96, $student->fresh()->xp);
        $this->post($url, $payload)->assertRedirect();
        $this->assertSame(1, LessonProgress::sole()->attempts);
        $this->assertSame(96, $student->fresh()->xp);
        $this->postJson($url, [...$payload, 'answers' => $answers])->assertConflict();
        $this->post($url, [...$payload, 'request_key' => Str::uuid()->toString(), 'answers' => $answers])->assertRedirect();
        $this->assertSame(121, $student->fresh()->xp);
        $this->post($url, $payload)->assertRedirect();
        $this->assertSame(2, LessonProgress::sole()->attempts);
        $this->assertSame(121, $student->fresh()->xp);
        $this->assertDatabaseHas('xp_events', ['source_type' => 'checkpoint', 'amount' => -4, 'balance_after' => 96]);
        $this->assertDatabaseHas('xp_events', ['source_type' => 'lesson', 'amount' => 25, 'balance_after' => 121]);
        $this->postJson($url, ['answers' => $answers, 'reflection' => $payload['reflection']])->assertUnprocessable()->assertJsonValidationErrors('request_key');
    }

    public function test_recitation_penalties_are_per_submitted_attempt_without_changing_the_term_score_allowance(): void
    {
        [, $room, $student] = $this->fixture();
        $round = RecitationRound::factory()->forClassroom($room)->create();
        $key = Str::uuid()->toString();
        $url = route('student.recitations.submit', $round);
        $this->postJson($url, ['answer' => 'wrong', 'request_key' => $key])->assertOk()->assertJsonPath('xp', 95)->assertJsonPath('term_progress.correct_answers', 0);
        $this->postJson($url, ['answer' => 'wrong', 'request_key' => $key])->assertOk()->assertJsonPath('xp', 95);
        $this->postJson($url, ['answer' => 'still wrong', 'request_key' => Str::uuid()->toString()])->assertOk()->assertJsonPath('xp', 90);
        $this->postJson($url, ['answer' => 'cout', 'request_key' => Str::uuid()->toString()])->assertOk()->assertJsonPath('xp', 100)->assertJsonPath('award.score', 10)->assertJsonPath('term_progress.correct_answers', 1);
        $this->assertSame(2, DB::table('xp_events')->where('source_type', 'recitation')->where('amount', -5)->count());
    }

    public function test_quizzes_and_term_assessments_deduct_only_at_final_grading_and_preserve_correct_and_speed_rewards(): void
    {
        [, $room, $student] = $this->fixture();
        $quiz = TeacherQuiz::create(['teacher_account_id' => $room->teacher_account_id, 'classroom_id' => $room->id, 'title' => 'Term2 Second Summative Test', 'status' => 'published', 'questions' => collect(range(1, 3))->map(fn ($index) => ['type' => 'multiple_choice', 'prompt' => 'Output check '.$index, 'options' => ['A', 'B'], 'correct' => ['A']])->all()]);
        $this->post(route('student.quiz.start', $quiz))->assertRedirect();
        foreach (['A', 'B', null] as $index => $answer) {
            $this->postJson(route('student.quiz.question', $quiz), ['question_index' => $index, 'answer' => $answer])->assertOk()->assertJsonPath('finished', $index === 2);
            $this->assertSame($index === 2 ? 105 : 100, $student->fresh()->xp);
        }
        $this->assertDatabaseHas('quiz_submissions', ['student_id' => $student->id, 'answer_xp' => 10, 'speed_bonus_xp' => 5, 'perfect_bonus_xp' => 0, 'xp_deducted' => 10, 'wrong_answers' => 2]);
        app(QuizRewards::class)->awardPending($student);
        $this->get(route('student.quiz'))->assertOk()->assertSee('Wrong-answer deduction: −10 XP');
        $this->assertSame(105, $student->fresh()->xp);
        $this->assertSame(1, DB::table('xp_events')->where('source_type', 'quiz')->where('amount', -10)->count());
    }

    public function test_legacy_attempts_are_not_charged_retroactively_and_ungraded_questions_have_no_penalty(): void
    {
        [, $room, $student] = $this->fixture();
        $quiz = TeacherQuiz::create(['teacher_account_id' => $room->teacher_account_id, 'classroom_id' => $room->id, 'title' => 'Legacy quiz', 'status' => 'published', 'questions' => [['type' => 'identification', 'prompt' => 'Stream?', 'correct' => ['cout']], ['type' => 'identification', 'prompt' => 'Teacher review', 'correct' => []]]]);
        foreach ([0, 5] as $policy) {
            $attempt = QuizAttempt::create(['student_id' => $student->id, 'teacher_quiz_id' => $quiz->id, 'attempt_number' => $policy === 0 ? 1 : 2, 'questions' => $quiz->questions, 'answers' => ['wrong', 'not graded'], 'started_at' => now(), 'expires_at' => now()->addMinutes(5), 'wrong_answer_penalty' => $policy]);
            DB::transaction(function () use ($student, $attempt): void {
                Student::whereKey($student->id)->lockForUpdate()->firstOrFail();
                app(QuizAttemptService::class)->finish($attempt);
            });
            $this->assertSame($policy === 0 ? 100 : 95, $student->fresh()->xp);
        }
        $this->assertDatabaseHas('quiz_submissions', ['attempt_number' => 2, 'wrong_answers' => 1, 'xp_deducted' => 5]);
    }

    #[TestWith([5, 90])]
    #[TestWith([0, 100])]
    public function test_post_test_penalties_are_final_total_only_idempotent_and_keep_the_certification_score(int $policy, int $balance): void
    {
        [$teacher, , $student] = $this->fixture();
        foreach (array_keys(config('lessons')) as $key) {
            LessonProgress::create(['student_id' => $student->id, 'lesson_key' => $key, 'started_at' => now(), 'last_opened_at' => now(), 'completed_at' => now()]);
        }
        $exam = app(CoursePostTest::class);
        $exam->requestCode($student, app(LearningNotifications::class));
        $id = DB::table('course_post_test_attempts')->sole()->id;
        $exam->start($student, $exam->issueCode($teacher, $id));
        DB::table('course_post_test_attempts')->where('id', $id)->update(['wrong_answer_penalty' => $policy]);
        $questions = json_decode(DB::table('course_post_test_attempts')->find($id)->questions, true);
        foreach ($questions as $index => $question) {
            $answer = $index < 48 ? $question['correct'][0] : collect($question['options'])->first(fn ($option) => $option !== $question['correct'][0]);
            $result = $exam->answer($student, $id, $index, $answer, true);
            if ($index < 49) {
                $this->assertSame(100, $student->fresh()->xp);
            }
            $this->assertArrayNotHasKey('correct', $result);
        }
        $this->assertSame($balance, $student->fresh()->xp);
        $exam->state($student);
        $exam->state($student);
        $this->assertSame($balance, $student->fresh()->xp);
        $this->assertDatabaseHas('course_post_test_attempts', ['id' => $id, 'score' => 48, 'questions' => null, 'answers' => null]);
        $this->assertDatabaseCount('course_certificates', 1);
        $receipt = DB::table('xp_events')->where('source_type', 'post-test')->first();
        if ($policy > 0) {
            $this->assertSame(-10, $receipt->amount);
            foreach ($questions as $question) {
                $this->assertStringNotContainsString($question['prompt'], $receipt->details);
            }
        } else {
            $this->assertNull($receipt);
        }
    }
}
