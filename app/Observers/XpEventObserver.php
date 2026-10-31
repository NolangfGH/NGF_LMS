<?php

namespace App\Observers;

use App\Models\Student;
use Illuminate\Support\Facades\DB;

class XpEventObserver
{
    public function created(Student $student): void
    {
        $this->record($student, (int) $student->xp, 'Initial learning XP');
    }

    public function updated(Student $student): void
    {
        if ($student->wasChanged('xp')) {
            $this->record($student, (int) $student->xp - (int) $student->getOriginal('xp'), 'Learning activity reward');
        }
    }

    private function record(Student $student, int $amount, string $reason): void
    {
        if ($amount !== 0) {
            DB::table('xp_events')->insert(['student_id' => $student->id, 'amount' => $amount, 'reason' => $reason, 'counts_for_period' => true, 'created_at' => now()]);
        }
    }
}
