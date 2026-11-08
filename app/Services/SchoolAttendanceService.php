<?php

namespace App\Services;

use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SchoolAttendanceService
{
    public function build(Collection $classrooms, Collection $teachers, Request $request, ?int $selected): array
    {
        $filter = $request->validate(['from' => ['nullable', 'date_format:Y-m-d'], 'to' => ['nullable', 'date_format:Y-m-d'], 'session' => ['nullable', 'integer', 'min:1'], 'register_page' => ['nullable', 'integer', 'min:1']]);
        $today = now(config('learning.timezone'));
        $from = $filter['from'] ?? $today->copy()->startOfMonth()->toDateString();
        $to = $filter['to'] ?? $today->toDateString();
        if ($from > $to) {
            throw ValidationException::withMessages(['to' => 'The end date must be on or after the start date.']);
        }
        $query = DB::table('attendance_sessions as sessions')->join('classrooms as rooms', 'rooms.id', '=', 'sessions.classroom_id')
            ->whereIn('sessions.classroom_id', $classrooms->pluck('id'))->whereColumn('sessions.teacher_account_id', 'rooms.teacher_account_id')
            ->whereBetween('sessions.session_date', [$from, $to]);
        $statuses = ['unmarked' => 'Unmarked', ...AttendanceService::STATUSES];
        $totals = DB::table('attendance_entries as entries')->joinSub((clone $query)->select('sessions.id'), 'visible_sessions', 'visible_sessions.id', '=', 'entries.attendance_session_id')
            ->groupBy('entries.status')->selectRaw('entries.status, COUNT(*) AS total')->pluck('total', 'status');
        $counts = collect($statuses)->map(fn ($label, $status): int => (int) $totals->get($status, 0));
        $summaries = DB::table('attendance_entries')->select('attendance_session_id')->groupBy('attendance_session_id');
        foreach (array_keys($statuses) as $status) {
            $summaries->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS '.$status.'_count', [$status]);
        }
        $columns = ['sessions.id', 'sessions.classroom_id', 'sessions.teacher_account_id', 'sessions.title', 'sessions.session_date', 'sessions.starts_at', 'sessions.ends_at', 'sessions.finalized_at', 'sessions.updated_at'];
        $sessions = (clone $query)->leftJoinSub($summaries, 'totals', 'totals.attendance_session_id', '=', 'sessions.id')->select($columns);
        foreach (array_keys($statuses) as $status) {
            $sessions->selectRaw('COALESCE(totals.'.$status.'_count, 0) AS '.$status.'_count');
        }
        $attendanceSessions = $sessions->orderByDesc('sessions.session_date')->orderByDesc('sessions.id')->paginate(15)->withQueryString()
            ->through(function ($session) use ($classrooms, $teachers): object {
                $room = $classrooms->firstWhere('id', $session->classroom_id);
                $session->section = $room->grade.' · '.($room->section ?: $room->name);
                $session->teacher = $teachers->get($session->teacher_account_id)?->fullName();

                return $session;
            });
        $attendanceSession = isset($filter['session']) ? (clone $query)->select($columns)->where('sessions.id', $filter['session'])->first() : null;
        abort_if(isset($filter['session']) && ! $attendanceSession, 404);
        $attendanceRegister = null;
        if ($attendanceSession) {
            $attendanceRegister = DB::table('attendance_entries')->where('attendance_session_id', $attendanceSession->id)->orderBy('id')
                ->paginate(25, ['id', 'student_id', 'status', 'checked_in_at', 'late_minutes'], 'register_page')->withQueryString();
            $names = Student::whereIn('classroom_id', $classrooms->pluck('id'))->whereIn('id', $attendanceRegister->getCollection()->pluck('student_id'))
                ->get(['id', 'nickname', 'leaderboard_visible'])->keyBy('id');
            $attendanceRegister->through(fn ($entry): array => ['name' => $names->get($entry->student_id)?->rankingName(0) ?? 'Former learner', 'status' => $statuses[$entry->status] ?? 'Unmarked', 'checked_in_at' => $entry->checked_in_at, 'late_minutes' => $entry->late_minutes]);
        }
        $denominator = $counts['present'] + $counts['late'] + $counts['absent'];

        return ['attendanceFrom' => $from, 'attendanceTo' => $to, 'attendanceSessions' => $attendanceSessions, 'attendanceCounts' => $counts,
            'attendanceSession' => $attendanceSession, 'attendanceRegister' => $attendanceRegister,
            'attendanceRate' => $denominator > 0 ? round(100 * ($counts['present'] + $counts['late']) / $denominator, 1) : null,
            'attendanceQuery' => array_filter(['tab' => 'attendance', 'classroom' => $selected, 'from' => $from, 'to' => $to])];
    }
}
