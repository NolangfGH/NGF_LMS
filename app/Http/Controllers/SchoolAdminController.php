<?php

namespace App\Http\Controllers;

use App\Models\Classroom;
use App\Models\Student;
use App\Models\TeacherAccount;
use App\Services\Judge0Client;
use App\Services\SchoolOverviewService;
use App\Services\StaffAccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class SchoolAdminController extends Controller
{
    private function admin(): TeacherAccount
    {
        $teacher = TeacherAccount::find(session('teacher_id'));
        abort_unless($teacher?->hasStaffAccess() && $teacher->isSchoolAdmin(), 403);

        return $teacher;
    }

    public function index()
    {
        $teacher = $this->admin();

        return response()->view('school-admin', ['teacher' => $teacher,
            'accounts' => TeacherAccount::withCount('classrooms')->orderByRaw("staff_access_status = 'pending' DESC")->orderBy('last_name')->get(),
            'roleHistory' => DB::table('staff_role_reviews')->join('teacher_accounts', 'teacher_accounts.id', '=', 'staff_role_reviews.account_id')->latest('staff_role_reviews.id')->limit(20)->get(['staff_role_reviews.*', 'teacher_accounts.first_name', 'teacher_accounts.last_name']),
            'studentCount' => Student::count(), 'classCount' => Classroom::whereNull('archived_at')->count(),
            'archivedCount' => Classroom::whereNotNull('archived_at')->count(), 'compilerConfigured' => app(Judge0Client::class)->configured(),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function role(Request $request, TeacherAccount $account)
    {
        $admin = $this->admin();
        $data = $request->validate(['role' => ['required', Rule::in(array_keys(TeacherAccount::ROLES))], 'current_password' => ['required', 'string'],
            'revision' => ['required', 'integer', 'min:1'], 'decision' => ['sometimes', Rule::in(['approved', 'rejected'])],
            'note' => ['required_if:decision,rejected', 'nullable', 'string', 'max:1000']]);
        app(StaffAccessService::class)->review($admin, $account, $data);

        return back()->with('lesson_notice', ($data['decision'] ?? 'approved') === 'rejected' ? 'Access request declined. The reason is available privately to the applicant.' : 'Role and access saved. The account must sign in again.');
    }

    public function overview(Request $request, SchoolOverviewService $service)
    {
        $teacher = TeacherAccount::find(session('teacher_id'));
        abort_unless($teacher?->hasStaffAccess() && ($teacher->isPrincipal() || $teacher->isSchoolAdmin()), 403);

        return response()->view('school-overview', $service->build($teacher, $request))->header('Cache-Control', 'private, no-store');
    }

    public function store(Request $request)
    {
        $this->admin();
        $data = $request->validate(['first_name' => ['required', 'string', 'max:80'], 'last_name' => ['required', 'string', 'max:80'],
            'email' => ['required', 'email', 'max:255', 'unique:teacher_accounts,email'], 'school' => ['required', 'string', 'max:255'],
            'password' => ['required', 'confirmed', Password::min(10)->letters()->numbers()]]);
        TeacherAccount::create($data);

        return back()->with('lesson_notice', 'Teacher account created. Share its credentials privately.');
    }

    public function compiler()
    {
        $this->admin();
        try {
            $result = app(Judge0Client::class)->health();
        } catch (\Throwable $error) {
            return back()->withErrors(['compiler' => 'Compiler connection failed. Check the service URL, token, C++ language ID, and that the separate Judge0 service is running.']);
        }

        return back()->with('lesson_notice', 'School compiler connected. C++ language ID '.$result['language_id'].' is available.');
    }

    public function archive(Request $request, Classroom $classroom)
    {
        $teacher = TeacherAccount::find(session('teacher_id'));
        abort_unless($teacher && ((int) $classroom->teacher_account_id === $teacher->id || $teacher->isSchoolAdmin()), 403);
        $data = $request->validate(['archive' => ['required', 'boolean']]);
        abort_if(! $data['archive'] && (! $classroom->teacher?->hasStaffAccess() || $classroom->teacher->isPrincipal()), 422, 'An active Teacher or System Admin must own the classroom before it can be restored.');
        DB::transaction(function () use ($classroom, $data) {
            $classroom = Classroom::whereKey($classroom->id)->lockForUpdate()->firstOrFail();
            $classroom->update(['archived_at' => $data['archive'] ? now() : null, 'enrollment_open' => false]);
            Student::where('classroom_id', $classroom->id)->increment('session_version');
        });

        return back()->with('settings_notice', $data['archive'] ? 'Class archived. Saved work is retained; student access is paused.' : 'Class restored. Review its enrollment setting before reopening registration.');
    }
}
