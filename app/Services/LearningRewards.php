<?php

namespace App\Services;

use App\Models\Student;
use Illuminate\Support\Facades\DB;

class LearningRewards
{
    public function award(int $studentId, string $key, int $amount, array $context = []): int
    {
        return DB::transaction(function () use ($studentId, $key, $amount, $context) {
            Student::whereKey($studentId)->lockForUpdate()->firstOrFail();
            $inserted = DB::table('learning_rewards')->insertOrIgnore(['student_id' => $studentId, 'event_key' => $key, 'amount' => $amount, 'created_at' => now()]);
            if (! $inserted) {
                return 0;
            }

            return app(XpLedger::class)->change($studentId, 'reward:'.$key, $amount, $context ?: ['type' => 'learning', 'title' => 'Learning reward', 'reason' => 'Learning activity reward', 'details' => ['Reward reference: '.$key]]);
        });
    }
}
