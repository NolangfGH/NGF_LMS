<?php

namespace App\Services;

use App\Models\Student;
use Illuminate\Support\Facades\DB;

class LearningRewards
{
    public function award(int $studentId, string $key, int $amount): int
    {
        return DB::transaction(function () use ($studentId, $key, $amount) {
            $student = Student::whereKey($studentId)->lockForUpdate()->firstOrFail();
            $inserted = DB::table('learning_rewards')->insertOrIgnore(['student_id' => $studentId, 'event_key' => $key, 'amount' => $amount, 'created_at' => now()]);
            if (! $inserted) {
                return 0;
            }
            $student->xp += $amount;
            $student->level = intdiv($student->xp, 600) + 1;
            $student->save();

            return $amount;
        });
    }
}
