<?php

namespace App\Http\Controllers;

use App\Models\Classroom;
use App\Models\TeacherAccount;
use App\Services\StudentRegistrationApproval;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class StudentRegistrationApprovalController extends Controller
{
    private function teacher(): TeacherAccount
    {
        return TeacherAccount::find(session('teacher_id')) ?? abort(403, 'Sign in with your teacher account.');
    }

    private function data(Request $request, StudentRegistrationApproval $service, TeacherAccount $teacher): array
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(['pending', 'approved', 'rejected', 'all'])],
            'classroom' => ['nullable', 'integer', Rule::exists('classrooms', 'id')->where('teacher_account_id', $teacher->id)],
            'q' => ['nullable', 'string', 'max:100'], 'focus' => ['nullable', 'integer', 'min:1'], 'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $filters = array_replace(['status' => 'pending', 'classroom' => null, 'q' => '', 'focus' => null], array_filter($filters, fn ($value) => $value !== null));
        $query = $service->owned($teacher);
        $counts = (clone $query)->selectRaw('registration_status, COUNT(*) as total')->groupBy('registration_status')->pluck('total', 'registration_status');
        $records = $query->with('classroom')->when($filters['status'] !== 'all', fn ($q) => $q->where('registration_status', $filters['status']))
            ->when($filters['classroom'], fn ($q, $id) => $q->where('classroom_id', $id))
            ->when($filters['focus'], fn ($q, $id) => $q->whereKey($id))
            ->when($filters['q'] !== '', function ($query) use ($filters) {
                $search = '%'.$filters['q'].'%';
                $query->where(fn ($q) => $q->where('first_name', 'like', $search)->orWhere('middle_name', 'like', $search)->orWhere('surname', 'like', $search)->orWhere('nickname', 'like', $search)->orWhere('student_id', 'like', $search));
            })->oldest()->paginate(25)->withQueryString();
        $history = DB::table('student_registration_reviews')->join('teacher_accounts', 'teacher_accounts.id', '=', 'student_registration_reviews.teacher_account_id')
            ->whereIn('student_registration_reviews.student_id', $records->pluck('id'))->where('student_registration_reviews.teacher_account_id', $teacher->id)
            ->orderByDesc('student_registration_reviews.id')->get(['student_registration_reviews.*', 'teacher_accounts.first_name', 'teacher_accounts.last_name'])->groupBy('student_id');

        return ['teacher' => $teacher, 'filters' => $filters, 'counts' => $counts, 'records' => $records, 'history' => $history,
            'classrooms' => Classroom::where('teacher_account_id', $teacher->id)->orderBy('grade')->orderBy('section')->get(),
            'updatedAt' => now('Asia/Singapore')->format('M j, Y · g:i:s A')];
    }

    public function index(Request $request, StudentRegistrationApproval $service)
    {
        if (! session('teacher_id')) {
            return redirect()->route('teacher.login');
        }

        return response()->view('teacher-registrations', $this->data($request, $service, $this->teacher()))->header('Cache-Control', 'private, no-store');
    }

    public function status(Request $request, StudentRegistrationApproval $service)
    {
        $data = $this->data($request, $service, $this->teacher());

        return response()->json(['html' => view('registration-queue', $data)->render(), 'updated_at' => $data['updatedAt']])->header('Cache-Control', 'private, no-store');
    }

    public function review(Request $request, int $student, StudentRegistrationApproval $service)
    {
        $teacher = $this->teacher();
        $data = $request->validate(['decision' => ['required', Rule::in(['approved', 'rejected', 'pending'])],
            'revision' => ['required', 'integer', 'min:1'], 'note' => ['required_if:decision,rejected', 'nullable', 'string', 'max:1000']]);
        $record = $service->review($teacher, $student, $data['decision'], (int) $data['revision'], $data['note'] ?? null);
        $message = match ($record->registration_status) {
            'approved' => 'Registration approved. The student can now sign in and has received 100 welcome XP.',
            'rejected' => 'Registration declined. The student can see your reason on their private status page.',
            default => 'Registration reopened for review. Access remains blocked until approved.',
        };
        if ($request->expectsJson()) {
            return response()->json(['message' => $message, 'status' => $record->registration_status])->header('Cache-Control', 'private, no-store');
        }

        return redirect()->route('teacher.registrations')->with('approval_notice', $message);
    }
}
