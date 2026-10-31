<?php

namespace App\Http\Controllers;

use App\Models\Classroom;
use App\Models\Student;
use App\Models\TeacherAccount;
use App\Services\Judge0Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class SchoolAdminController extends Controller
{
    private function admin(): TeacherAccount
    {
        $teacher = TeacherAccount::find(session('teacher_id'));
        abort_unless($teacher?->isSchoolAdmin(), 403);

        return $teacher;
    }

    public function index()
    {
        $teacher = $this->admin();

        return response()->view('school-admin', ['teacher' => $teacher,
            'accounts' => TeacherAccount::withCount('classrooms')->orderBy('last_name')->get(),
            'studentCount' => Student::count(), 'classCount' => Classroom::whereNull('archived_at')->count(),
            'archivedCount' => Classroom::whereNotNull('archived_at')->count(), 'compilerConfigured' => app(Judge0Client::class)->configured(),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function role(Request $request, TeacherAccount $account)
    {
        $admin = $this->admin();
        $data = $request->validate(['role' => ['required', 'in:teacher,school_admin'], 'current_password' => ['required', 'string']]);
        if (! Hash::check($data['current_password'], $admin->password)) {
            throw ValidationException::withMessages(['current_password' => 'Confirm your own current password.']);
        }
        abort_if($account->id === $admin->id, 422, 'Ask another school administrator to change your own role.');
        DB::transaction(function () use ($account, $data) {
            $current = TeacherAccount::whereKey($account->id)->lockForUpdate()->firstOrFail();
            $current->forceFill(['role' => $data['role'], 'session_version' => $current->session_version + 1])->save();
        });

        return back()->with('lesson_notice', 'Role updated. The account must sign in again.');
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
        DB::transaction(function () use ($classroom, $data) {
            $classroom = Classroom::whereKey($classroom->id)->lockForUpdate()->firstOrFail();
            $classroom->update(['archived_at' => $data['archive'] ? now() : null, 'enrollment_open' => false]);
            Student::where('classroom_id', $classroom->id)->increment('session_version');
        });

        return back()->with('settings_notice', $data['archive'] ? 'Class archived. Saved work is retained; student access is paused.' : 'Class restored. Review its enrollment setting before reopening registration.');
    }
}
