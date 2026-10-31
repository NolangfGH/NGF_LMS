<?php

namespace App\Services;

use App\Models\QuizAttempt;
use App\Models\Student;
use App\Models\TeacherQuiz;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SequentialQuizService
{
    // Called under a student lock, including when a student returns after being offline.
    public function synchronize(QuizAttempt $attempt, ?TeacherQuiz $quiz): void
    {
        if ($attempt->submitted_at) return;
        $closing=$attempt->scheduleDeadline($quiz);
        if ($closing && $closing->lt($attempt->expires_at)) $attempt->expires_at=$closing;
        while ($attempt->current_question < count($attempt->questions) && now()->gte($attempt->questionDeadline())) {
            $deadline = $attempt->questionDeadline();
            $seconds = max(0,$deadline->timestamp - $attempt->question_started_at->timestamp);
            $this->record($attempt,$seconds,false);
            $attempt->question_started_at = $deadline;
        }
        if ($attempt->current_question >= count($attempt->questions)) app(QuizAttemptService::class)->finish($attempt);
        else $attempt->save();
    }

    private function record(QuizAttempt $attempt, int $seconds, bool $speedEligible): void
    {
        $timings = $attempt->question_timings ?? [];
        $timings[$attempt->current_question] = ['seconds'=>$seconds,'speed_eligible'=>$speedEligible];
        $attempt->question_timings = $timings;
        $attempt->current_question++;
    }

    public function answer(Student $student, TeacherQuiz $quiz, int $index, mixed $answer, bool $next, ?int $attemptId=null): array
    {
        return DB::transaction(function () use ($student,$quiz,$index,$answer,$next,$attemptId) {
            Student::whereKey($student->id)->lockForUpdate()->firstOrFail();
            $attempt = QuizAttempt::where('student_id',$student->id)->where('teacher_quiz_id',$quiz->id)->orderByDesc('attempt_number')->lockForUpdate()->first();
            if (!$attempt || !$attempt->sequential || $attempt->submitted_at) throw ValidationException::withMessages(['quiz'=>'This attempt is unavailable or has already been submitted.']);
            if (($attempt->attempt_number > 1 || $attemptId !== null) && $attemptId !== $attempt->id) {
                throw ValidationException::withMessages(['quiz'=>'This page belongs to an older attempt. Refresh to open your current final opportunity.']);
            }
            $previousIndex = $attempt->current_question;
            $this->synchronize($attempt,$quiz);
            if ($attempt->submitted_at || $attempt->current_question !== $previousIndex) return ['finished'=>(bool)$attempt->submitted_at,'advanced'=>true];
            if ($index !== $attempt->current_question) throw ValidationException::withMessages(['quiz'=>'This question is no longer active. Refresh to continue.']);
            if (!$attempt->scheduleOpen($quiz)) throw ValidationException::withMessages(['quiz'=>'The quiz is not available at this time.']);
            $question = $attempt->questions[$index];
            $type = $question['type'] ?? 'identification';
            $rules = ['answer'=>in_array($type,['multiple_answer','ordering']) ? ['nullable','array','max:8'] : ['nullable','string','max:5000']];
            if (in_array($type,['multiple_answer','ordering'])) $rules['answer.*'] = ['string','distinct',Rule::in($question['options'])];
            elseif (!in_array($type,['identification','code_output','fill_code','debugging'])) $rules['answer'][] = Rule::in($type === 'true_false' ? ['True','False'] : $question['options']);
            $valid = Validator::make(['answer'=>$answer],$rules)->validate();
            $answers = $attempt->answers;
            $answers[$index] = $valid['answer'] ?? null;
            $attempt->answers = $answers;
            if ($next) {
                $seconds = max(0,(int) ceil((now()->getTimestampMs() - $attempt->question_started_at->getTimestampMs()) / 1000));
                $this->record($attempt,$seconds,!$attempt->untimed && $seconds<=30);
                $attempt->question_started_at = now();
                if ($attempt->current_question >= count($attempt->questions)) app(QuizAttemptService::class)->finish($attempt);
                else $attempt->save();
            } else $attempt->save();
            return ['finished'=>(bool)$attempt->submitted_at,'advanced'=>$next];
        });
    }
}
