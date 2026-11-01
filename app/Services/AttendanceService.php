<?php

namespace App\Services;

use App\Models\Classroom;
use App\Models\Student;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AttendanceService
{
    public const STATUSES = ['present' => 'Present', 'late' => 'Late', 'absent' => 'Absent', 'excused' => 'Excused'];

    public function record(object $session, string $role, int $actor, string $action, ?object $entry = null, ?string $to = null, ?string $note = null): void
    {
        DB::table('attendance_events')->insert(['attendance_session_id' => $session->id, 'attendance_entry_id' => $entry?->id,
            'actor_role' => $role, 'actor_id' => $actor, 'action' => $action, 'from_status' => $entry?->status, 'to_status' => $to, 'note' => $note, 'created_at' => now()]);
    }

    public function sync(object $session): int
    {
        $count = 0;
        foreach (Student::where('classroom_id', $session->classroom_id)->orderBy('id')->get() as $student) {
            $count += DB::table('attendance_entries')->insertOrIgnore(['attendance_session_id' => $session->id, 'student_id' => $student->id,
                'student_name' => trim(implode(' ', array_filter([$student->first_name, $student->middle_name, $student->surname]))) ?: $student->nickname,
                'school_id' => $student->student_id, 'status' => 'unmarked', 'created_at' => now(), 'updated_at' => now()]);
        }

        return $count;
    }

    public function rows(object $session): Collection
    {
        $rows = DB::table('attendance_entries')->where('attendance_session_id', $session->id)->orderBy('student_name')->orderBy('id')->get();
        $requests = DB::table('attendance_requests')->whereIn('attendance_entry_id', $rows->pluck('id'))->orderByDesc('id')->get()->groupBy('attendance_entry_id');

        return $rows->map(function ($entry) use ($requests) {
            $entry->requests = $requests->get($entry->id, collect());
            $entry->pending = $entry->requests->firstWhere('decision', 'pending');

            return $entry;
        });
    }

    public function writable(object $session): void
    {
        abort_if($session->finalized_at, 409, 'This session is finalized. Reopen it with a reason before making changes.');
        abort_unless(Classroom::whereKey($session->classroom_id)->where('teacher_account_id', $session->teacher_account_id)->whereNull('archived_at')->exists(), 409, 'This class is archived or no longer belongs to this teacher.');
    }

    public function mark(object $session, object $entry, string $status, string $role, int $actor, ?string $note = null): void
    {
        $this->record($session, $role, $actor, 'marked', $entry, $status, $note);
        DB::table('attendance_entries')->where('id', $entry->id)->update(['status' => $status, 'method' => $role === 'student' ? 'Class passcode' : 'Teacher roll call',
            'checked_in_at' => $role === 'student' ? now() : $entry->checked_in_at,
            'late_minutes' => $role === 'student' && $status === 'late' ? (int) ceil(Carbon::parse($session->starts_at)->diffInMinutes(now(), false)) : ($status === 'late' ? $entry->late_minutes : 0),
            'note' => $note, 'updated_at' => now()]);
    }

    public function touch(object $session): void
    {
        DB::table('attendance_sessions')->where('id', $session->id)->update(['revision' => $session->revision + 1, 'updated_at' => now()]);
    }
}
