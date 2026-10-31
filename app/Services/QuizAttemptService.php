<?php

namespace App\Services;

use App\Models\QuizAttempt;
use App\Models\Student;
use App\Models\TeacherQuiz;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class QuizAttemptService
{
    private function reject(string $message): never
    {
        throw ValidationException::withMessages(['quiz' => $message]);
    }

    public function start(Student $student, TeacherQuiz $quiz): QuizAttempt
    {
        return DB::transaction(function () use ($student, $quiz) {
            Student::whereKey($student->id)->lockForUpdate()->firstOrFail();
            $grant=app(SecondChanceService::class)->requestFor($student->id,'quiz',$quiz->id);
            $number=$grant?->status === 'approved' ? 2 : 1;
            if (DB::table('quiz_submissions')->where('student_id',$student->id)->where('teacher_quiz_id',$quiz->id)->where('attempt_number',$number)->exists()) {
                $this->reject('You have already completed this quiz. Only one attempt is allowed.');
            }
            if (!app(SecondChanceService::class)->quizOpen($quiz,$student->id)) $this->reject('This quiz is outside its scheduled start and closing times.');
            $existing = QuizAttempt::where('student_id',$student->id)->where('teacher_quiz_id',$quiz->id)->where('attempt_number',$number)->first();
            if ($existing) return $existing;
            $deadline = $quiz->untimed ? now()->addYears(20) : now()->addSeconds(count($quiz->questions) * ($quiz->question_seconds ?: 60));
            $closing=$number === 2 ? $grant->expires_at : $quiz->closes_at;
            if ($closing && $closing->lt($deadline)) $deadline=$closing;
            if ($number === 2) $grant->update(['used_at'=>now()]);
            $questions=collect($quiz->questions)->shuffle()->map(function($question){if(($question['type'] ?? '') === 'ordering')$question['options']=collect($question['options'])->shuffle()->values()->all();return $question;})->values()->all();
            return QuizAttempt::create(['student_id'=>$student->id,'teacher_quiz_id'=>$quiz->id,'attempt_number'=>$number,'second_chance_request_id'=>$number === 2 ? $grant->id : null,'questions'=>$questions,'answers'=>[], 'started_at'=>now(),'expires_at'=>$deadline,'sequential'=>true,'question_started_at'=>now(),'question_timings'=>[],'untimed'=>(bool)$quiz->untimed,'question_seconds'=>$quiz->question_seconds ?: 60]);
        });
    }

    private function validateAnswers(QuizAttempt $attempt, array $input): array
    {
        $rules = ['answers'=>['present','array','max:'.count($attempt->questions)]];
        foreach ($attempt->questions as $index => $question) {
            $type = $question['type'] ?? 'identification';
            $rules['answers.'.$index] = $type === 'multiple_answer' ? ['nullable','array','max:8'] : ['nullable','string','max:5000'];
            if ($type === 'multiple_answer') {
                $rules['answers.'.$index.'.*'] = ['string','distinct',Rule::in($question['options'])];
            } elseif ($type !== 'identification') {
                $rules['answers.'.$index][] = Rule::in($type === 'true_false' ? ['True','False'] : $question['options']);
            }
        }
        $data = Validator::make(['answers'=>$input],$rules)->validate();
        $answers = [];
        foreach ($attempt->questions as $index => $question) $answers[$index] = $data['answers'][$index] ?? null;
        return $answers;
    }

    public function save(Student $student, TeacherQuiz $quiz, array $answers, bool $submit): bool
    {
        return DB::transaction(function () use ($student,$quiz,$answers,$submit) {
            Student::whereKey($student->id)->lockForUpdate()->firstOrFail();
            $number=QuizAttempt::where('student_id',$student->id)->where('teacher_quiz_id',$quiz->id)->max('attempt_number') ?? 1;
            if (DB::table('quiz_submissions')->where('student_id',$student->id)->where('teacher_quiz_id',$quiz->id)->where('attempt_number',$number)->exists()) {
                $this->reject('This quiz has already been submitted. Your original score is unchanged.');
            }
            $attempt = QuizAttempt::where('student_id',$student->id)->where('teacher_quiz_id',$quiz->id)->orderByDesc('attempt_number')->lockForUpdate()->first();
            if (!$attempt) $this->reject('Start the quiz before answering.');
            if ($attempt->sequential) $this->reject('Answer the current question using Save & Next.');
            $closing=$attempt->scheduleDeadline($quiz);
            $expired = now()->gte($attempt->expires_at) || ($closing && now()->gte($closing));
            if (!$expired && !$attempt->scheduleOpen($quiz)) $this->reject('This quiz is not available at this time.');
            if (!$expired) $attempt->answers = $this->validateAnswers($attempt,$answers);
            if ($submit || $expired) {
                $this->finish($attempt);
                return true;
            }
            $attempt->save();
            return false;
        });
    }

    public function finish(QuizAttempt $attempt): void
    {
        $grading = app(QuizGrader::class)->grade($attempt->questions,$attempt->answers);
        $speedXp = 0;
        $results = json_decode($grading['grading_results'],true);
        foreach ($results as $index => &$result) {
            $timing = $attempt->question_timings[$index] ?? null;
            $result['seconds_spent'] = $timing['seconds'] ?? null;
            $result['speed_bonus_xp'] = $result['correct'] === true && ($timing['speed_eligible'] ?? false) ? 5 : 0;
            $speedXp += $result['speed_bonus_xp'];
        }
        unset($result);
        $grading['grading_results'] = json_encode($results);
        DB::table('quiz_submissions')->insertOrIgnore([
            'student_id'=>$attempt->student_id,'teacher_quiz_id'=>$attempt->teacher_quiz_id,'attempt_number'=>$attempt->attempt_number,
            ...$grading,'speed_bonus_xp'=>$speedXp,'answers'=>json_encode($attempt->answers),'created_at'=>now(),'updated_at'=>now(),
        ]);
        $attempt->submitted_at = now();
        $attempt->save();
        app(LearningNotifications::class)->quizSubmitted($attempt->teacher_quiz_id,$attempt->student_id,$attempt->attempt_number);
        app(QuizRewards::class)->awardPending(Student::findOrFail($attempt->student_id));
        app(AchievementService::class)->sync(Student::findOrFail($attempt->student_id));
    }

    public function finalizeExpired(Student $student): void
    {
        DB::transaction(function () use ($student) {
            Student::whereKey($student->id)->lockForUpdate()->firstOrFail();
            foreach (QuizAttempt::where('student_id',$student->id)->whereNull('submitted_at')->lockForUpdate()->get() as $attempt) {
                $quiz = TeacherQuiz::withTrashed()->find($attempt->teacher_quiz_id);
                if ($attempt->sequential) {
                    app(SequentialQuizService::class)->synchronize($attempt,$quiz);
                    continue;
                }
                $closing=$attempt->scheduleDeadline($quiz);
                if (now()->gte($attempt->expires_at) || ($closing && now()->gte($closing))) $this->finish($attempt);
            }
        });
    }
}
