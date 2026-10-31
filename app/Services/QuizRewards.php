<?php

namespace App\Services;

use App\Models\Student;
use Illuminate\Support\Facades\DB;

class QuizRewards
{
    public const XP_PER_ANSWER = 10;
    public const PERFECT_BONUS = 100;

    public function awardPending(Student $student): void
    {
        DB::transaction(function () use ($student) {
            $account = Student::whereKey($student->id)->lockForUpdate()->firstOrFail();
            $total = 0;
            $results = DB::table('quiz_submissions')->where('student_id', $student->id)
                ->whereNotNull('score')->whereNull('xp_awarded_at')->orderBy('id')->lockForUpdate()->get();
            foreach ($results as $result) {
                $answerXp = max(0, min($result->score, $result->graded_questions)) * self::XP_PER_ANSWER;
                $bonus = $result->total_questions > 0 && $result->graded_questions === $result->total_questions
                    && $result->score === $result->total_questions ? self::PERFECT_BONUS : 0;
                DB::table('quiz_submissions')->where('id', $result->id)->update([
                    'answer_xp'=>$answerXp, 'perfect_bonus_xp'=>$bonus, 'xp_awarded_at'=>now(),
                ]);
                $previous=DB::table('quiz_submissions')->where('student_id',$student->id)->where('teacher_quiz_id',$result->teacher_quiz_id)->where('id','!=',$result->id)->sum('xp_credited');
                $additional=max(0,$answerXp+$bonus+$result->speed_bonus_xp-$previous);
                DB::table('quiz_submissions')->where('id',$result->id)->update(['xp_credited'=>$additional]);
                $total += $additional;
            }
            if ($total > 0) {
                $account->xp += $total;
                $account->level = intdiv($account->xp, 600) + 1;
                $account->save();
            }
        });
    }
}
