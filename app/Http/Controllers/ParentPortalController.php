<?php

namespace App\Http\Controllers;

use App\Models\LabSubmission;
use App\Models\ParentAccount;
use App\Models\ParentStudentLink;
use App\Models\Student;
use App\Models\TeacherAccount;
use App\Services\DashboardService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class ParentPortalController extends Controller
{
    private function parentAccount(): ParentAccount
    {
        $parent = ParentAccount::find(session('parent_id'));
        abort_unless($parent && (int) session('parent_session_version') === $parent->session_version, 403, 'Please sign in as a parent again.');

        return $parent;
    }

    private function teacher(): TeacherAccount
    {
        $teacher = TeacherAccount::find(session('teacher_id'));
        abort_unless($teacher?->hasStaffAccess() && ! $teacher->isPrincipal(), 403);

        return $teacher;
    }

    public function login(): Response
    {
        return response()->view('parent-login')->header('Cache-Control', 'private, no-store');
    }

    public function register(Request $request): Response
    {
        $request->merge(['email' => strtolower(trim((string) $request->input('email')))]);
        $data = $request->validate(['name' => ['required', 'string', 'max:160'], 'email' => ['required', 'email', 'max:255', 'unique:parent_accounts,email'], 'password' => ['required', 'string', 'min:10', 'max:100', 'confirmed']]);
        $parent = ParentAccount::create($data);
        $request->session()->forget(['teacher_id', 'student_id', 'student_class_verified']);
        $request->session()->regenerate();
        session(['parent_id' => $parent->id, 'parent_session_version' => $parent->session_version]);

        return redirect()->route('parent.dashboard')->with('portal_notice', 'Account created. Request access to your child below; their teacher must verify and approve your request.');
    }

    public function authenticate(Request $request): Response
    {
        $data = $request->validate(['email' => ['required', 'email', 'max:255'], 'password' => ['required', 'string', 'max:100']]);
        $parent = ParentAccount::where('email', strtolower(trim($data['email'])))->first();
        if (! $parent || ! Hash::check($data['password'], $parent->password)) {
            throw ValidationException::withMessages(['email' => 'The email or password is incorrect.']);
        }
        $request->session()->forget(['teacher_id', 'student_id', 'student_class_verified']);
        $request->session()->regenerate();
        session(['parent_id' => $parent->id, 'parent_session_version' => $parent->session_version]);

        return redirect()->route('parent.dashboard');
    }

    public function logout(Request $request): Response
    {
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('parent.login');
    }

    public function index(Request $request, DashboardService $dashboard): Response
    {
        if (! session('parent_id')) {
            return redirect()->route('parent.login');
        }
        $parent = $this->parentAccount();
        $links = ParentStudentLink::where('parent_account_id', $parent->id)->with('student.classroom.teacher')->latest()->get();
        $approved = $links->filter(fn (ParentStudentLink $link): bool => $link->status === 'approved' && $link->student && ! $link->student->classroom?->archived_at && (int) $link->approved_classroom_id === (int) $link->student->classroom_id);
        $selected = $request->filled('child') ? $approved->firstWhere('id', (int) $request->query('child')) : $approved->first();
        abort_if($request->filled('child') && ! $selected, 404);
        $overview = $selected ? $dashboard->student($selected->student) : null;
        $scores = $selected ? DB::table('quiz_submissions')->join('teacher_quizzes', 'teacher_quizzes.id', '=', 'quiz_submissions.teacher_quiz_id')->where('quiz_submissions.student_id', $selected->student_id)->whereNotNull('quiz_submissions.graded_at')->orderByDesc('quiz_submissions.graded_at')->limit(15)->get(['teacher_quizzes.title', 'quiz_submissions.score', 'quiz_submissions.total_questions']) : collect();
        $feedback = $selected ? LabSubmission::whereHas('workspace', fn ($q) => $q->where('student_id', $selected->student_id))->whereNotNull('reviewed_at')->latest('reviewed_at')->limit(10)->get(['score', 'feedback', 'reviewed_at']) : collect();
        $attendance = $selected ? DB::table('attendance_entries')->where('student_id', $selected->student_id)->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status') : collect();

        return response()->view('parent-dashboard', compact('parent', 'links', 'approved', 'selected', 'overview', 'scores', 'feedback', 'attendance'))->header('Cache-Control', 'private, no-store');
    }

    public function link(Request $request): Response
    {
        $parent = $this->parentAccount();
        $data = $request->validate(['student_reference' => ['required', 'string', 'max:40'], 'relationship' => ['required', Rule::in(['Parent', 'Guardian'])]]);
        $reference = strtoupper(trim($data['student_reference']));
        ParentStudentLink::firstOrCreate(['parent_account_id' => $parent->id, 'student_reference' => $reference], ['relationship' => $data['relationship']]);

        return back()->with('portal_notice', 'Access request recorded. Contact the class teacher to verify your identity. No student records are shown until approval.');
    }

    public function password(Request $request): Response
    {
        $parent = $this->parentAccount();
        $data = $request->validate(['current_password' => ['required', 'string'], 'password' => ['required', 'string', 'min:10', 'max:100', 'confirmed']]);
        if (! Hash::check($data['current_password'], $parent->password)) {
            throw ValidationException::withMessages(['current_password' => 'Your current password is incorrect.']);
        }
        $parent->forceFill(['password' => $data['password'], 'session_version' => $parent->session_version + 1])->save();
        session(['parent_session_version' => $parent->session_version]);
        $request->session()->regenerate();

        return back()->with('portal_notice', 'Password changed. Other parent sessions have been signed out.');
    }

    public function requests(): Response
    {
        $teacher = $this->teacher();
        $students = Student::with('classroom')->whereHas('classroom', fn ($q) => $q->whereNull('archived_at')->when(! $teacher->isSchoolAdmin(), fn ($q) => $q->where('teacher_account_id', $teacher->id)))->get()->keyBy('student_id');
        $requests = ParentStudentLink::with('parentAccount')->whereIn('student_reference', $students->keys())->orderByRaw("status = 'pending' DESC")->latest()->paginate(30);

        return response()->view('parent-requests', compact('teacher', 'students', 'requests'))->header('Cache-Control', 'private, no-store');
    }

    public function review(Request $request, ParentStudentLink $link): Response
    {
        $teacher = $this->teacher();
        $data = $request->validate(['decision' => ['required', Rule::in(['approved', 'rejected', 'revoked'])], 'note' => ['nullable', 'string', 'max:500']]);
        DB::transaction(function () use ($teacher, $link, $data): void {
            $current = ParentStudentLink::whereKey($link->id)->lockForUpdate()->firstOrFail();
            $student = Student::where('student_id', $current->student_reference)->whereHas('classroom', fn ($q) => $q->whereNull('archived_at')->when(! $teacher->isSchoolAdmin(), fn ($q) => $q->where('teacher_account_id', $teacher->id)))->lockForUpdate()->first();
            abort_unless($student, 403);
            $current->update(['status' => $data['decision'], 'student_id' => $student->id, 'approved_classroom_id' => $student->classroom_id, 'reviewed_by' => $teacher->id, 'reviewed_at' => now(), 'review_note' => $data['note'] ?? null]);
        });

        return back()->with('portal_notice', 'Parent access updated.');
    }
}
