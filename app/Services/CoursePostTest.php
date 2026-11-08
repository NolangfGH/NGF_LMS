<?php

namespace App\Services;

use App\Models\Student;
use App\Models\TeacherAccount;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use stdClass;

class CoursePostTest
{
    public const QUESTIONS = 50;

    public const PASS_SCORE = 45;

    public const FIRST_SECONDS = 90;

    public const FINAL_SECONDS = 60;

    public function __construct(private CourseAssessments $course, private QuizGrader $grader) {}

    public function state(Student $student): array
    {
        return DB::transaction(function () use ($student): array {
            $current = $this->lockStudent($student->id);
            $attempt = $this->latest($current);
            if ($attempt?->started_at && ! $attempt->finished_at) {
                $this->synchronize($current, $attempt, now());
            }
            $active = $attempt?->started_at && ! $attempt->finished_at;
            $result = $active ? null : $this->attempts($current)->whereNotNull('finished_at')->orderByDesc('attempt_number')->first(['score']);

            return [
                'ready' => $this->course->canTakePostTest($current),
                'next_attempt' => $this->nextAttempt($attempt),
                'requested' => $attempt && ! $attempt->started_at,
                'code_ready' => $attempt && ! $attempt->started_at && $this->validGrant($current, $attempt),
                'result' => $result,
                'current' => $active ? $this->currentQuestion($attempt) : null,
            ];
        }, 3);
    }

    public function requestCode(Student $student, LearningNotifications $notifications): void
    {
        DB::transaction(function () use ($student, $notifications): void {
            $current = $this->lockStudent($student->id);
            $attempt = $this->latest($current);
            $number = $this->availableAttempt($current, $attempt);
            $id = $attempt && ! $attempt->started_at ? $attempt->id : DB::table('course_post_test_attempts')->insertGetId([
                'student_id' => $current->id, 'attempt_number' => $number, 'created_at' => now(), 'updated_at' => now(),
            ]);
            $notifications->postTestCodeRequested($current, $id);
        }, 3);
    }

    public function issueCode(TeacherAccount $teacher, int $requestId): string
    {
        return DB::transaction(function () use ($teacher, $requestId): string {
            $studentId = DB::table('course_post_test_attempts')->where('id', $requestId)->value('student_id');
            abort_unless($studentId, 404);
            $student = $this->lockStudent($studentId);
            abort_unless($student->classroom->teacher_account_id === $teacher->id, 404);
            $attempt = $this->latest($student);
            abort_unless($attempt && (int) $attempt->id === $requestId && ! $attempt->started_at, 409, 'This request has already been used.');
            $this->availableAttempt($student, $attempt);
            $code = strtoupper(bin2hex(random_bytes(5)));
            DB::table('course_post_test_attempts')->where('id', $requestId)->update([
                'access_code_hash' => Hash::make($code), 'code_issued_by' => $teacher->id,
                'code_expires_at' => now()->addDay(), 'updated_at' => now(),
            ]);

            return $code;
        }, 3);
    }

    public function start(Student $student, string $code): void
    {
        DB::transaction(function () use ($student, $code): void {
            $current = $this->lockStudent($student->id);
            $attempt = $this->latest($current);
            if ($attempt?->started_at && ! $attempt->finished_at) {
                return;
            }
            $number = $this->availableAttempt($current, $attempt);
            if (! $attempt || $attempt->started_at || ! $this->validGrant($current, $attempt) || ! Hash::check(strtoupper($code), $attempt->access_code_hash)) {
                throw ValidationException::withMessages(['code' => 'Enter the current code issued by your teacher for this attempt.']);
            }
            $questions = collect($this->course->questions(CourseAssessments::POST))->shuffle()->map(function (array $question): array {
                shuffle($question['options']);

                return $question;
            })->values()->all();
            abort_unless(count($questions) === self::QUESTIONS, 503, 'The exam question bank is unavailable.');
            $startedAt = now()->format('Y-m-d H:i:s.v');
            DB::table('course_post_test_attempts')->where('id', $attempt->id)->update([
                'started_at' => $startedAt, 'question_started_at' => $startedAt,
                'question_seconds' => $number === 1 ? self::FIRST_SECONDS : self::FINAL_SECONDS,
                'wrong_answer_penalty' => XpLedger::ASSESSMENT_PENALTY,
                'questions' => json_encode($questions, JSON_THROW_ON_ERROR), 'answers' => '[]',
                'access_code_hash' => null, 'code_expires_at' => null, 'updated_at' => now(),
            ]);
        }, 3);
    }

    public function answer(Student $student, int $attemptId, int $index, ?string $answer, bool $advance): array
    {
        return DB::transaction(function () use ($student, $attemptId, $index, $answer, $advance): array {
            $current = $this->lockStudent($student->id);
            $attempt = $this->latest($current);
            abort_unless($attempt && (int) $attempt->id === $attemptId && $attempt->started_at, 409, 'Open your current exam attempt.');
            $previous = (int) $attempt->question_index;
            $time = now();
            $this->synchronize($current, $attempt, $time);
            if ($attempt->finished_at || (int) $attempt->question_index !== $previous) {
                return ['finished' => (bool) $attempt->finished_at, 'advanced' => true];
            }
            abort_unless($index === $previous, 409, 'This question is closed. Refresh to continue.');
            $questions = $this->decode($attempt->questions);
            if ($answer !== null && ! in_array($answer, $questions[$index]['options'], true)) {
                throw ValidationException::withMessages(['answer' => 'Choose one of the displayed options.']);
            }
            $answers = $this->decode($attempt->answers);
            $answers[$index] = $answer;
            $attempt->answers = json_encode($answers, JSON_THROW_ON_ERROR);
            if ($advance) {
                $attempt->question_index++;
                $attempt->question_started_at = $time->format('Y-m-d H:i:s.v');
            }
            $this->persist($current, $attempt, $time);

            return ['finished' => (bool) $attempt->finished_at, 'advanced' => $advance];
        }, 3);
    }

    private function synchronize(Student $student, stdClass $attempt, Carbon $now): void
    {
        if ($attempt->finished_at) {
            return;
        }
        $started = Carbon::parse($attempt->question_started_at);
        $elapsed = max(0, $now->getTimestampMs() - $started->getTimestampMs());
        $steps = min(self::QUESTIONS - $attempt->question_index, intdiv($elapsed, $attempt->question_seconds * 1000));
        if ($steps > 0) {
            $attempt->question_index += $steps;
            $attempt->question_started_at = $started->addSeconds($steps * $attempt->question_seconds)->format('Y-m-d H:i:s.v');
            $this->persist($student, $attempt, $now);
        }
    }

    private function persist(Student $student, stdClass $attempt, Carbon $now): void
    {
        $update = [
            'question_index' => $attempt->question_index, 'question_started_at' => $attempt->question_started_at,
            'answers' => $attempt->answers, 'updated_at' => $now,
        ];
        if ($attempt->question_index >= self::QUESTIONS) {
            $score = $this->grader->grade($this->decode($attempt->questions), $this->decode($attempt->answers))['score'];
            $attempt->finished_at = $now;
            $attempt->score = $score;
            if ($attempt->wrong_answer_penalty > 0) {
                $wrong = self::QUESTIONS - $score;
                app(XpLedger::class)->change($student->id, 'post-test-penalty:'.$attempt->id, -$wrong * $attempt->wrong_answer_penalty,
                    ['type' => 'post-test', 'id' => $attempt->id, 'title' => 'C++ Post-Test · Attempt '.$attempt->attempt_number, 'reason' => 'Post-test incorrect answers',
                        'details' => [$wrong.' incorrect or unanswered questions × '.$attempt->wrong_answer_penalty.' XP = '.($wrong * $attempt->wrong_answer_penalty).' XP deduction requested.', 'Only the final total is recorded here. Question content, answer keys and per-question correctness are not disclosed.']]);
            }
            $update = array_replace($update, ['finished_at' => $now, 'score' => $score, 'questions' => null, 'answers' => null]);
            if ($score >= self::PASS_SCORE) {
                $this->course->awardExcellence($student);
            }
        }
        DB::table('course_post_test_attempts')->where('id', $attempt->id)->update($update);
    }

    private function currentQuestion(stdClass $attempt): array
    {
        $index = (int) $attempt->question_index;
        $question = $this->decode($attempt->questions)[$index];
        $deadline = Carbon::parse($attempt->question_started_at)->addSeconds($attempt->question_seconds);

        return [
            'id' => $attempt->id, 'index' => $index, 'seconds' => $attempt->question_seconds,
            'deadline' => $deadline->getTimestampMs(), 'question' => Arr::only($question, ['prompt', 'options', 'type']),
            'answer' => $this->decode($attempt->answers)[$index] ?? null,
        ];
    }

    private function validGrant(Student $student, stdClass $attempt): bool
    {
        return $attempt->access_code_hash && (int) $attempt->code_issued_by === $student->classroom->teacher_account_id
            && $attempt->code_expires_at && now()->lt(Carbon::parse($attempt->code_expires_at));
    }

    private function availableAttempt(Student $student, ?stdClass $attempt): int
    {
        abort_unless($this->course->canTakePostTest($student), 403, 'Complete the pre-test and all core checkpoints first.');
        $number = $this->nextAttempt($attempt);
        abort_unless($number, 409, 'Your exam attempt is active or you have finished your opportunities.');

        return $number;
    }

    private function nextAttempt(?stdClass $attempt): ?int
    {
        if (! $attempt?->started_at) {
            return (int) ($attempt?->attempt_number ?? 1);
        }

        return $attempt->finished_at && (int) $attempt->attempt_number === 1 && $attempt->score < self::PASS_SCORE ? 2 : null;
    }

    private function lockStudent(int $id): Student
    {
        $student = Student::with('classroom.teacher')->whereKey($id)->lockForUpdate()->firstOrFail();
        abort_unless($student->classroom && ! $student->classroom->archived_at, 403);

        return $student;
    }

    private function attempts(Student $student): Builder
    {
        return DB::table('course_post_test_attempts')->where('student_id', $student->id);
    }

    private function latest(Student $student): ?stdClass
    {
        return $this->attempts($student)->orderByDesc('attempt_number')->first();
    }

    private function decode(string $json): array
    {
        return json_decode($json, true, flags: JSON_THROW_ON_ERROR);
    }
}
