<?php

namespace App\Http\Controllers;

use App\Models\Classroom;
use App\Models\Student;
use App\Models\TeacherAccount;
use App\Services\AttendanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class AttendanceController extends Controller
{
    private function teacher(): TeacherAccount
    {
        return TeacherAccount::find(session('teacher_id')) ?? abort(403, 'Sign in with your teacher account.');
    }

    private function student(): Student
    {
        return Student::with('classroom')->find(session('student_id')) ?? abort(401, 'Sign in with your student account.');
    }

    private function owned(int $session, bool $lock = false): object
    {
        return DB::table('attendance_sessions')->where('teacher_account_id', $this->teacher()->id)->when($lock, fn ($q) => $q->lockForUpdate())->find($session) ?? abort(404);
    }

    private function data(Request $request, AttendanceService $service): array
    {
        $teacher = $this->teacher();
        $rooms = Classroom::where('teacher_account_id', $teacher->id)->withCount('students')->orderBy('grade')->orderBy('section')->get();
        $filters = $request->validate(['classroom' => ['nullable', 'integer'], 'date' => ['nullable', 'date_format:Y-m-d']]);
        $room = isset($filters['classroom']) ? $rooms->firstWhere('id', (int) $filters['classroom']) : $rooms->first(fn ($room) => ! $room->archived_at);
        abort_if(isset($filters['classroom']) && ! $room, 404);
        $date = $filters['date'] ?? now(config('learning.timezone'))->toDateString();
        $attendance = $room ? DB::table('attendance_sessions')->where('teacher_account_id', $teacher->id)->where('classroom_id', $room->id)->where('session_date', $date)->first() : null;
        $rows = $attendance ? $service->rows($attendance) : collect();
        $counts = collect(['unmarked' => 'Unmarked', ...AttendanceService::STATUSES])->map(fn ($label, $status) => $rows->where('status', $status)->count())->all();
        $pending = $rows->filter(fn ($row) => $row->pending)->count();
        $history = $attendance ? DB::table('attendance_events')->leftJoin('attendance_entries', 'attendance_entries.id', '=', 'attendance_events.attendance_entry_id')
            ->where('attendance_events.attendance_session_id', $attendance->id)->orderByDesc('attendance_events.id')->limit(50)->select('attendance_events.*', 'student_name')->get() : collect();
        $sessions = $room ? DB::table('attendance_sessions')->where('teacher_account_id', $teacher->id)->where('classroom_id', $room->id)->orderByDesc('session_date')->limit(30)->get() : collect();

        return compact('teacher', 'rooms', 'room', 'date', 'attendance', 'rows', 'counts', 'pending', 'history', 'sessions');
    }

    public function index(Request $request, AttendanceService $service)
    {
        if (! session('teacher_id')) {
            return redirect()->route('teacher.login');
        }

        return response()->view('attendance.index', $this->data($request, $service))->header('Cache-Control', 'private, no-store');
    }

    public function status(Request $request, AttendanceService $service)
    {
        $data = $this->data($request, $service);

        return response()->json(['html' => view('attendance.workspace', $data)->render(), 'revision' => $data['attendance']?->revision, 'server_now' => now()->toISOString()])->header('Cache-Control', 'private, no-store');
    }

    public function store(Request $request, AttendanceService $service)
    {
        $teacher = $this->teacher();
        $data = $request->validate(['classroom_id' => ['required', 'integer'], 'session_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.now(config('learning.timezone'))->addYear()->toDateString()],
            'title' => ['required', 'string', 'max:160'], 'start_time' => ['required', 'date_format:H:i'], 'end_time' => ['required', 'date_format:H:i'],
            'open_time' => ['required', 'date_format:H:i'], 'close_time' => ['required', 'date_format:H:i'], 'photo_required' => ['sometimes', 'boolean']]);
        $times = [];
        foreach (['start', 'end', 'open', 'close'] as $key) {
            $times[$key] = Carbon::parse($data['session_date'].' '.$data[$key.'_time'], config('learning.timezone'))->utc();
        }
        abort_unless($times['end']->gt($times['start']) && $times['close']->gt($times['open']) && $times['open']->lte($times['start']) && $times['close']->gte($times['start']) && $times['close']->lte($times['end']), 422, 'Use one same-day class period: check-in must open by class start and close between class start and end.');
        $session = DB::transaction(function () use ($teacher, $data, $times, $service) {
            $room = Classroom::where('teacher_account_id', $teacher->id)->whereNull('archived_at')->lockForUpdate()->findOrFail($data['classroom_id']);
            abort_if(DB::table('attendance_sessions')->where('classroom_id', $room->id)->where('session_date', $data['session_date'])->exists(), 409, 'A session already exists for this section and date. Open that session instead.');
            $id = DB::table('attendance_sessions')->insertGetId(['teacher_account_id' => $teacher->id, 'classroom_id' => $room->id, 'session_date' => $data['session_date'], 'title' => $data['title'],
                'starts_at' => $times['start'], 'ends_at' => $times['end'], 'photo_required' => $data['photo_required'] ?? false, 'checkin_opens_at' => $times['open'], 'checkin_closes_at' => $times['close'], 'checkin_code' => Str::upper(Str::random(8)), 'created_at' => now(), 'updated_at' => now()]);
            $session = DB::table('attendance_sessions')->find($id);
            $service->sync($session);
            $service->record($session, 'teacher', $teacher->id, 'session_created');

            return $session;
        }, 3);

        return $this->saved($request, $session, 'Attendance session created. Open check-in or start manual roll call.');
    }

    private function saved(Request $request, object $session, string $message)
    {
        return $request->expectsJson() ? response()->json(['message' => $message, 'redirect' => route('teacher.attendance', ['classroom' => $session->classroom_id, 'date' => $session->session_date])])
            : redirect()->route('teacher.attendance', ['classroom' => $session->classroom_id, 'date' => $session->session_date])->with('attendance_notice', $message);
    }

    public function action(Request $request, int $session, AttendanceService $service)
    {
        $teacher = $this->teacher();
        $data = $request->validate(['action' => ['required', Rule::in(['mark', 'open', 'close', 'sync', 'finalize', 'reopen', 'review'])], 'revision' => ['required', 'integer', 'min:1'],
            'entries' => ['required_if:action,mark', 'array', 'min:1', 'max:500'], 'entries.*' => ['integer', 'distinct'],
            'status' => ['required_if:action,mark', Rule::in(array_keys(AttendanceService::STATUSES))], 'note' => ['nullable', 'string', 'max:500', 'required_if:action,reopen'],
            'auto_absent' => ['sometimes', 'boolean'], 'request_id' => ['required_if:action,review', 'integer'], 'decision' => ['required_if:action,review', Rule::in(['approved', 'declined'])]]);
        $current = DB::transaction(function () use ($session, $teacher, $data, $service) {
            $current = $this->owned($session, true);
            abort_unless((int) $current->revision === (int) $data['revision'], 409, 'Attendance changed in another tab or a student checked in. Refresh saved records before trying again.');
            if ($data['action'] === 'reopen') {
                abort_unless($current->finalized_at && Classroom::whereKey($current->classroom_id)->whereNull('archived_at')->exists(), 409, 'Only a finalized session in an active class can be reopened.');
                DB::table('attendance_sessions')->where('id', $current->id)->update(['finalized_at' => null, 'checkin_enabled' => false]);
                $service->record($current, 'teacher', $teacher->id, 'reopened', note: $data['note']);
            } else {
                $service->writable($current);
                if ($data['action'] === 'mark') {
                    $entries = DB::table('attendance_entries')->where('attendance_session_id', $current->id)->whereIn('id', $data['entries'])->get();
                    abort_unless($entries->count() === count($data['entries']), 404);
                    foreach ($entries as $entry) {
                        $service->mark($current, $entry, $data['status'], 'teacher', $teacher->id, $data['note'] ?? null);
                    }
                } elseif (in_array($data['action'], ['open', 'close'])) {
                    abort_if($data['action'] === 'open' && now()->gte(Carbon::parse($current->checkin_closes_at)), 422, 'The check-in window has ended. Use manual roll call.');
                    DB::table('attendance_sessions')->where('id', $current->id)->update(['checkin_enabled' => $data['action'] === 'open']);
                    $service->record($current, 'teacher', $teacher->id, 'checkin_'.$data['action']);
                } elseif ($data['action'] === 'sync') {
                    $service->sync($current);
                    $service->record($current, 'teacher', $teacher->id, 'roster_synced');
                } elseif ($data['action'] === 'review') {
                    $entryIds = DB::table('attendance_entries')->where('attendance_session_id', $current->id)->pluck('id');
                    $correction = DB::table('attendance_requests')->whereIn('attendance_entry_id', $entryIds)->find($data['request_id']) ?? abort(404);
                    abort_unless($correction->decision === 'pending', 409, 'This request has already been reviewed.');
                    if ($data['decision'] === 'approved') {
                        $entry = DB::table('attendance_entries')->find($correction->attendance_entry_id);
                        $service->mark($current, $entry, $correction->requested_status, 'teacher', $teacher->id, $data['note'] ?? null);
                    }
                    DB::table('attendance_requests')->where('id', $correction->id)->update(['decision' => $data['decision'], 'review_note' => $data['note'] ?? null, 'reviewed_by' => $teacher->id, 'reviewed_at' => now(), 'updated_at' => now()]);
                    $service->record($current, 'teacher', $teacher->id, 'correction_'.$data['decision']);
                } elseif ($data['action'] === 'finalize') {
                    $rows = $service->rows($current);
                    abort_if($rows->contains(fn ($entry) => $entry->pending), 422, 'Review every pending correction request before finalizing.');
                    abort_if($rows->isEmpty(), 422, 'There are no students in this session.');
                    abort_if($rows->contains('status', 'unmarked') && ! ($data['auto_absent'] ?? false), 422, 'Mark every student, or explicitly choose to mark the remaining students absent.');
                    foreach ($rows->where('status', 'unmarked') as $entry) {
                        $service->mark($current, $entry, 'absent', 'teacher', $teacher->id);
                    }
                    DB::table('attendance_sessions')->where('id', $current->id)->update(['finalized_at' => now(), 'checkin_enabled' => false]);
                    $service->record($current, 'teacher', $teacher->id, 'finalized');
                }
            }
            $service->touch($current);

            return $current;
        }, 3);

        return $this->saved($request, $current, 'Attendance saved.');
    }

    public function studentIndex()
    {
        if (! session('student_id')) {
            return redirect()->route('student.login');
        }
        $student = $this->student();
        $entries = DB::table('attendance_entries')->join('attendance_sessions', 'attendance_sessions.id', '=', 'attendance_entries.attendance_session_id')
            ->where('attendance_entries.student_id', $student->id)->where('attendance_sessions.classroom_id', $student->classroom_id)
            ->orderByDesc('session_date')->select('attendance_entries.*', 'session_date', 'title', 'starts_at', 'checkin_opens_at', 'checkin_closes_at', 'checkin_enabled', 'finalized_at', 'photo_required')->paginate(20);
        $requests = DB::table('attendance_requests')->whereIn('attendance_entry_id', $entries->pluck('id'))->orderByDesc('id')->get()->groupBy('attendance_entry_id');
        $photos = DB::table('attendance_photo_requests')->where('student_id', $student->id)->whereIn('attendance_entry_id', $entries->pluck('id'))->latest('id')->get()->groupBy('attendance_entry_id');

        return response()->view('attendance.student', ['student' => $student, 'classroom' => $student->classroom, 'entries' => $entries, 'requests' => $requests, 'photos' => $photos,
            'lastSyncedAt' => now(config('learning.timezone'))->format('M j · g:i A')])->header('Cache-Control', 'private, no-store');
    }

    public function studentAction(Request $request, int $session, AttendanceService $service)
    {
        $student = $this->student();
        $data = $request->validate(['action' => ['required', Rule::in(['checkin', 'correction'])], 'code' => ['required_if:action,checkin', 'string', 'max:12'],
            'requested_status' => ['required_if:action,correction', Rule::in(array_keys(AttendanceService::STATUSES))], 'reason' => ['required_if:action,correction', 'string', 'min:10', 'max:1000']]);
        try {
            DB::transaction(function () use ($session, $student, $data, $service) {
                $current = DB::table('attendance_sessions')->where('classroom_id', $student->classroom_id)->lockForUpdate()->find($session) ?? abort(404);
                $service->writable($current);
                $entry = DB::table('attendance_entries')->where('attendance_session_id', $current->id)->where('student_id', $student->id)->first() ?? abort(404);
                if ($data['action'] === 'checkin') {
                    abort_if($current->photo_required, 422, 'This session requires a selfie for teacher review. Use the photo check-in form.');
                    abort_unless($current->checkin_enabled && now()->gte(Carbon::parse($current->checkin_opens_at)) && now()->lt(Carbon::parse($current->checkin_closes_at)), 422, 'Check-in is not open. Ask your teacher to record your attendance.');
                    abort_unless(hash_equals($current->checkin_code, Str::upper(trim($data['code']))), 422, 'The class passcode is incorrect. Ask your teacher for today’s code.');
                    if ($entry->checked_in_at) {
                        return;
                    }
                    abort_unless($entry->status === 'unmarked', 409, 'Your teacher already marked attendance. Request a correction if needed.');
                    $service->mark($current, $entry, now()->gt(Carbon::parse($current->starts_at)) ? 'late' : 'present', 'student', $student->id);
                } else {
                    abort_if($entry->status === $data['requested_status'], 422, 'Your saved status already matches this request.');
                    abort_if(DB::table('attendance_requests')->where('attendance_entry_id', $entry->id)->count() >= 3, 422, 'You reached the three-request limit for this session. Speak privately with your teacher.');
                    abort_if(DB::table('attendance_requests')->where('attendance_entry_id', $entry->id)->where('decision', 'pending')->exists(), 409, 'Your correction request is already awaiting review.');
                    DB::table('attendance_requests')->insert(['attendance_entry_id' => $entry->id, 'requested_status' => $data['requested_status'], 'reason' => $data['reason'], 'created_at' => now(), 'updated_at' => now()]);
                    $service->record($current, 'student', $student->id, 'correction_requested', $entry, $data['requested_status']);
                }
                $service->touch($current);
            }, 3);
        } catch (HttpExceptionInterface $exception) {
            if (! $request->expectsJson() && in_array($exception->getStatusCode(), [409, 422])) {
                return redirect()->route('student.attendance')->withErrors(['attendance' => $exception->getMessage()]);
            }
            throw $exception;
        }

        return redirect()->route('student.attendance')->with('attendance_notice', $data['action'] === 'checkin' ? 'Your attendance is saved.' : 'Your private correction request was sent to your teacher.');
    }

    public function export(Request $request, AttendanceService $service)
    {
        $data = $this->data($request, $service);
        abort_unless($data['attendance'], 404);

        return response()->streamDownload(function () use ($data) {
            $stream = fopen('php://output', 'w');
            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, ['Date', 'Grade', 'Section', 'Student', 'School ID', 'Attendance', 'Check-in (UTC+08:00)', 'Late minutes', 'Method', 'Session state'], ',', '"', '');
            foreach ($data['rows'] as $row) {
                $values = [$data['date'], $data['room']->grade, $data['room']->section, $row->student_name, $row->school_id, ucfirst($row->status),
                    $row->checked_in_at ? Carbon::parse($row->checked_in_at)->timezone(config('learning.timezone'))->format('Y-m-d H:i:s') : '', $row->late_minutes, $row->method, $data['attendance']->finalized_at ? 'Finalized' : 'Draft'];
                fputcsv($stream, array_map(fn ($value) => preg_match('/^[\s]*[=+@\-]/u', (string) $value) ? "'".$value : (string) $value, $values), ',', '"', '');
            }
            fclose($stream);
        }, 'attendance-'.$data['date'].'.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store']);
    }
}
