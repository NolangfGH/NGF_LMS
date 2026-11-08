<?php

namespace App\Services;

use App\Models\Student;
use App\Models\TeacherQuiz;
use Illuminate\Support\Facades\DB;

class QuizRewards
{
    public const XP_PER_ANSWER = 10;

    public const PERFECT_BONUS = 100;

    public function awardPending(Student $student): void
    {
        DB::transaction(function () use ($student) {
            Student::whereKey($student->id)->lockForUpdate()->firstOrFail();
            $results = DB::table('quiz_submissions')->where('student_id', $student->id)
                ->whereNotNull('score')->whereNull('xp_awarded_at')->orderBy('id')->lockForUpdate()->get();
            foreach ($results as $result) {
                $answerXp = max(0, min($result->score, $result->graded_questions)) * self::XP_PER_ANSWER;
                $bonus = $result->total_questions > 0 && $result->graded_questions === $result->total_questions
                    && $result->score === $result->total_questions ? self::PERFECT_BONUS : 0;
                DB::table('quiz_submissions')->where('id', $result->id)->update([
                    'answer_xp' => $answerXp, 'perfect_bonus_xp' => $bonus, 'xp_awarded_at' => now(),
                ]);
                $previous = DB::table('quiz_submissions')->where('student_id', $student->id)->where('teacher_quiz_id', $result->teacher_quiz_id)->where('id', '!=', $result->id)->sum('xp_credited');
                $additional = max(0, $answerXp + $bonus + $result->speed_bonus_xp - $previous);
                DB::table('quiz_submissions')->where('id', $result->id)->update(['xp_credited' => $additional]);
                $quiz = TeacherQuiz::withTrashed()->find($result->teacher_quiz_id);
                $context = ['type' => 'quiz', 'id' => $result->teacher_quiz_id, 'title' => $quiz?->title ?? 'Saved quiz or term assessment', 'reason' => 'Quiz / term assessment reward',
                    'details' => ['Attempt '.$result->attempt_number.'. Correct answers: '.$result->score.' × 10 XP = '.$answerXp.' XP.', 'Speed bonus: '.$result->speed_bonus_xp.' XP. Perfect bonus: '.$bonus.' XP.', 'Previously credited for this quiz: '.$previous.' XP. Additional reward this attempt: '.$additional.' XP.']];
                $ledger = app(XpLedger::class);
                $ledger->change($student->id, 'quiz-reward:'.$result->id, $additional, $context);
                $wrong = max(0, $result->graded_questions - $result->score);
                $deducted = 0;
                if ($result->wrong_answer_penalty > 0) {
                    $context['reason'] = 'Incorrect quiz / term assessment answers';
                    $context['details'] = ['Attempt '.$result->attempt_number.'. '.$wrong.' incorrect or unanswered graded questions × '.$result->wrong_answer_penalty.' XP = '.($wrong * $result->wrong_answer_penalty).' XP deduction requested.', 'Ungraded questions do not incur a deduction. Applied after the attempt is graded; balances cannot go below zero.'];
                    $incorrect = collect(json_decode($result->grading_results ?? '[]', true, flags: JSON_THROW_ON_ERROR))->filter(fn ($item): bool => ($item['correct'] ?? null) === false)->keys()->map(fn ($index): int => $index + 1);
                    if ($incorrect->isNotEmpty()) {
                        $context['details'][] = 'Incorrect/unanswered graded question numbers in this attempt: '.$incorrect->implode(', ').'.';
                    }
                    $deducted = -$ledger->change($student->id, 'quiz-penalty:'.$result->id, -$wrong * $result->wrong_answer_penalty, $context);
                }
                DB::table('quiz_submissions')->where('id', $result->id)->update(['xp_deducted' => $deducted, 'wrong_answers' => $wrong]);
            }
        });
    }
}
