<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Models\TeacherAccount;
use App\Services\AttendanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class AttendancePhotoController extends Controller
{
    private function teacher(): TeacherAccount
    {
        $teacher = TeacherAccount::find(session('teacher_id'));
        abort_unless($teacher?->hasStaffAccess() && ! $teacher->isPrincipal(), 403);

        return $teacher;
    }

    public function mode(Request $request, int $session, AttendanceService $service): Response
    {
        $teacher = $this->teacher();
        $data = $request->validate(['photo_required' => ['required', 'boolean']]);
        DB::transaction(function () use ($teacher, $session, $data, $service): void {
            $current = DB::table('attendance_sessions')->where('teacher_account_id', $teacher->id)->lockForUpdate()->find($session) ?? abort(404);
            $service->writable($current);
            abort_if(DB::table('attendance_photo_requests')->where('attendance_session_id', $session)->where('status', 'pending')->exists(), 409, 'Review pending photo requests before changing check-in mode.');
            DB::table('attendance_sessions')->where('id', $session)->update(['photo_required' => $data['photo_required']]);
            $service->record($current, 'teacher', $teacher->id, $data['photo_required'] ? 'photo_checkin_enabled' : 'photo_checkin_disabled');
            $service->touch($current);
        });

        return back()->with('attendance_notice', 'Attendance check-in mode updated.');
    }

    public function store(Request $request, int $session, AttendanceService $service): Response
    {
        $student = Student::with('classroom')->find(session('student_id')) ?? abort(401);
        $data = $request->validate(['code' => ['required', 'string', 'max:12'], 'consent' => ['accepted'], 'photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:max_width=4096,max_height=4096']]);
        $path = null;
        try {
            DB::transaction(function () use ($request, $student, $session, $data, $service, &$path): void {
                $current = DB::table('attendance_sessions')->where('classroom_id', $student->classroom_id)->lockForUpdate()->find($session) ?? abort(404);
                $service->writable($current);
                abort_unless($current->teacher_account_id === $student->classroom->teacher_account_id && $current->photo_required && $current->checkin_enabled && now()->gte(Carbon::parse($current->checkin_opens_at)) && now()->lt(Carbon::parse($current->checkin_closes_at)), 422, 'Photo check-in is not open for your class.');
                abort_unless(hash_equals($current->checkin_code, Str::upper(trim($data['code']))), 422, 'The class passcode is incorrect.');
                $entry = DB::table('attendance_entries')->where('attendance_session_id', $session)->where('student_id', $student->id)->lockForUpdate()->first() ?? abort(404);
                abort_unless($entry->status === 'unmarked' && ! $entry->checked_in_at, 409, 'Your attendance is already recorded. Use a correction request if needed.');
                $requests = DB::table('attendance_photo_requests')->where('attendance_session_id', $session)->where('student_id', $student->id)->get();
                abort_if($requests->contains('status', 'pending'), 409, 'Your selfie is awaiting teacher review.');
                abort_if($requests->count() >= 3, 422, 'Contact your teacher for a manual attendance check.');
                $path ??= $request->file('photo')->store('attendance-photos', 'local');
                if (! $path) {
                    throw new \RuntimeException('Photo storage unavailable.');
                }
                DB::table('attendance_photo_requests')->insert(['attendance_session_id' => $session, 'attendance_entry_id' => $entry->id, 'student_id' => $student->id, 'photo_path' => $path, 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()]);
                $service->record($current, 'student', $student->id, 'photo_checkin_requested', $entry);
                $service->touch($current);
            }, 3);
        } catch (Throwable $error) {
            if ($path) {
                Storage::disk('local')->delete($path);
            }
            throw $error;
        }
        $message = 'Selfie sent privately. Your attendance is pending until the teacher approves it.';

        return $request->expectsJson() ? response()->json(['message' => $message]) : redirect()->route('student.attendance')->with('attendance_notice', $message);
    }

    public function index(int $session): Response
    {
        $teacher = $this->teacher();
        $attendance = DB::table('attendance_sessions')->where('teacher_account_id', $teacher->id)->find($session) ?? abort(404);
        $photos = DB::table('attendance_photo_requests as photos')->join('attendance_entries as entries', 'entries.id', '=', 'photos.attendance_entry_id')->where('photos.attendance_session_id', $session)->select('photos.*', 'entries.student_name', 'entries.school_id')->orderByRaw("photos.status = 'pending' DESC")->orderByDesc('photos.id')->paginate(24);

        return response()->view('attendance.photos', compact('teacher', 'attendance', 'photos'))->header('Cache-Control', 'private, no-store');
    }

    public function image(int $photo): Response
    {
        $item = DB::table('attendance_photo_requests')->find($photo) ?? abort(404);
        $attendance = DB::table('attendance_sessions')->find($item->attendance_session_id) ?? abort(404);
        $teacher = TeacherAccount::find(session('teacher_id'));
        $student = Student::find(session('student_id'));
        abort_unless(($teacher?->hasStaffAccess() && ! $teacher->isPrincipal() && $teacher->id === $attendance->teacher_account_id) || ($student && $student->id === $item->student_id), 403);
        abort_unless(Storage::disk('local')->exists($item->photo_path), 404);

        return Storage::disk('local')->response($item->photo_path, null, ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function review(Request $request, int $photo, AttendanceService $service): Response
    {
        $teacher = $this->teacher();
        $data = $request->validate(['decision' => ['required', Rule::in(['present', 'late', 'rejected'])], 'note' => ['nullable', 'string', 'max:500']]);
        DB::transaction(function () use ($teacher, $photo, $data, $service): void {
            $reference = DB::table('attendance_photo_requests')->find($photo) ?? abort(404);
            $current = DB::table('attendance_sessions')->where('teacher_account_id', $teacher->id)->lockForUpdate()->find($reference->attendance_session_id) ?? abort(403);
            $item = DB::table('attendance_photo_requests')->lockForUpdate()->find($photo) ?? abort(404);
            abort_unless($item->status === 'pending', 409, 'This request was already reviewed.');
            $entry = DB::table('attendance_entries')->lockForUpdate()->find($item->attendance_entry_id) ?? abort(404);
            if ($data['decision'] !== 'rejected') {
                $service->writable($current);
                abort_unless(Student::whereKey($item->student_id)->where('classroom_id', $current->classroom_id)->exists() && $entry->status === 'unmarked', 409, 'Enrollment or attendance changed. Check the roster before marking attendance.');
                $service->mark($current, $entry, $data['decision'], 'teacher', $teacher->id, $data['note'] ?? 'Selfie checked by class teacher.');
                DB::table('attendance_entries')->where('id', $entry->id)->update(['checked_in_at' => $item->created_at, 'method' => 'Selfie reviewed', 'late_minutes' => $data['decision'] === 'late' ? max(0, (int) ceil(Carbon::parse($current->starts_at)->diffInMinutes(Carbon::parse($item->created_at), false))) : 0]);
            }
            DB::table('attendance_photo_requests')->where('id', $photo)->update(['status' => $data['decision'] === 'rejected' ? 'rejected' : 'approved', 'reviewed_by' => $teacher->id, 'reviewed_at' => now(), 'review_note' => $data['note'] ?? null, 'updated_at' => now()]);
            $service->record($current, 'teacher', $teacher->id, 'photo_'.$data['decision'], $entry);
            $service->touch($current);
        }, 3);

        return back()->with('portal_notice', 'Selfie check-in reviewed.');
    }
}
