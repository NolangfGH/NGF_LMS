<?php

namespace App\Observers;

use App\Models\Student;
use Illuminate\Support\Facades\DB;

class XpEventObserver
{
    public function created(Student $student): void
    {
        $this->record($student, (int) $student->xp, 'Initial learning XP', true);
    }

    public function updated(Student $student): void
    {
        if (! $student->xpLedgerManaged && $student->wasChanged('xp')) {
            $this->record($student, (int) $student->xp - (int) $student->getOriginal('xp'), 'XP balance adjustment');
        }
    }

    private function record(Student $student, int $amount, string $reason, bool $opening = false): void
    {
        if ($amount !== 0) {
            DB::table('xp_events')->insert(['student_id' => $student->id, 'amount' => $amount, 'requested_amount' => $amount, 'balance_after' => $student->xp,
                'reason' => $reason, 'source_type' => $opening ? 'opening' : 'adjustment', 'activity_title' => $reason, 'counts_for_period' => true, 'created_at' => now()]);
        }
    }
}
