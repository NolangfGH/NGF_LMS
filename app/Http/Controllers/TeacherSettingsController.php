<?php

namespace App\Http\Controllers;

use App\Models\Classroom;
use App\Models\LabSubmission;
use App\Models\LessonProgress;
use App\Models\Student;
use App\Models\TeacherAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class TeacherSettingsController extends Controller
{
    private function teacher(): TeacherAccount
    {
        $teacher = TeacherAccount::find(session('teacher_id'));
        abort_unless($teacher, 403);

        return $teacher;
    }

    private function confirmPassword(Request $request, TeacherAccount $teacher): void
    {
        $request->validate(['current_password' => ['required', 'string']]);
        if (! Hash::check($request->input('current_password'), $teacher->password)) {
            throw ValidationException::withMessages(['current_password' => 'Your current password is incorrect.']);
        }
    }

    private function notice(string $section, string $message)
    {
        return redirect()->to(route('teacher.settings').'#'.$section)->with('settings_notice', $message);
    }

    public function index()
    {
        if (! TeacherAccount::find(session('teacher_id'))) {
            return redirect()->route('teacher.login');
        }
        $teacher = $this->teacher();
        $classrooms = Classroom::where('teacher_account_id', $teacher->id)->withCount('students')->orderBy('grade')->orderBy('section')->get();
        $studentIds = Student::whereIn('classroom_id', $classrooms->pluck('id'))->pluck('id');
        $latest = LabSubmission::where('teacher_account_id', $teacher->id)->whereHas('workspace', fn ($query) => $query->whereIn('student_id', $studentIds))
            ->orderByDesc('id')->get(['id', 'code_workspace_id', 'reviewed_at'])->unique('code_workspace_id');
        $activity = [
            'enrollment' => Student::whereIn('id', $studentIds)->where('created_at', '>=', now(config('learning.timezone'))->startOfWeek()->utc())->count(),
            'submissions' => $latest->whereNull('reviewed_at')->count(),
            'support' => LessonProgress::whereIn('student_id', $studentIds)->where(fn ($query) => $query->where('confidence', 'need_help')->orWhere(fn ($help) => $help->whereNotNull('help_request')->where('help_request', '!=', '')))->distinct()->count('student_id'),
        ];

        return response()->view('teacher-settings', compact('teacher', 'classrooms', 'activity') + [
            'preferences' => $teacher->effectivePreferences(), 'grades' => config('teacher_settings.grades'),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function account(Request $request)
    {
        $teacher = $this->teacher();
        $request->merge(['email' => Str::lower(trim($request->input('email', '')))]);
        $data = $request->validate(['first_name' => ['required', 'string', 'max:80'], 'last_name' => ['required', 'string', 'max:80'],
            'middle_initial' => ['nullable', 'string', 'max:2', 'regex:/^\p{L}\.?$/u'],
            'email' => ['required', 'email', 'max:255', Rule::unique('teacher_accounts', 'email')->ignore($teacher->id)],
            'school' => ['required', 'string', 'max:255']]);
        abort_if($teacher->isPrincipal() && $data['school'] !== $teacher->school, 422, 'School affiliation for Principal access must be confirmed by the System Admin.');
        if ($data['email'] !== $teacher->email) {
            $this->confirmPassword($request, $teacher);
        }
        DB::transaction(function () use ($request, $teacher, $data) {
            $current = TeacherAccount::whereKey($teacher->id)->lockForUpdate()->firstOrFail();
            abort_if($current->isPrincipal() && $data['school'] !== $current->school, 422, 'School affiliation for Principal access must be confirmed by the System Admin.');
            if ($data['email'] !== $current->email) {
                $this->confirmPassword($request, $current);
                $current->forceFill(['email_verified_at' => null, 'session_version' => $current->session_version + 1]);
            }
            $current->fill($data)->save();
            $request->session()->put('teacher_session_version', $current->session_version);
        });

        return $this->notice('account', 'Account details saved. Use the saved email for your next teacher sign-in.');
    }

    public function profile(Request $request)
    {
        $teacher = $this->teacher();
        $data = $request->validate(['display_name' => ['nullable', 'string', 'max:80'], 'bio' => ['nullable', 'string', 'max:300'], 'profile_visible' => ['required', 'boolean']]);
        $teacher->update($data);

        return $this->notice('profile', 'Classroom profile saved.');
    }

    public function preferences(Request $request)
    {
        $teacher = $this->teacher();
        $data = $request->validate(['text_size' => ['required', Rule::in(['standard', 'comfortable', 'large'])],
            'high_contrast' => ['required', 'boolean'], 'reduce_motion' => ['required', 'boolean'],
            'notify_enrollment' => ['required', 'boolean'], 'notify_submissions' => ['required', 'boolean'], 'notify_support' => ['required', 'boolean']]);
        foreach (array_keys($data) as $key) {
            if ($key !== 'text_size') {
                $data[$key] = (bool) $data[$key];
            }
        }
        $teacher->update(['preferences' => $data]);

        return $this->notice('preferences', 'Activity and accessibility preferences saved.');
    }

    public function password(Request $request)
    {
        $teacher = $this->teacher();
        $data = $request->validate(['password' => ['required', 'confirmed', Password::min(10)->letters()->numbers()]]);
        DB::transaction(function () use ($request, $teacher, $data) {
            $current = TeacherAccount::whereKey($teacher->id)->lockForUpdate()->firstOrFail();
            $this->confirmPassword($request, $current);
            if (Hash::check($data['password'], $current->password)) {
                throw ValidationException::withMessages(['password' => 'Choose a new password different from your current password.']);
            }
            $current->forceFill(['password' => $data['password'], 'password_changed_at' => now(), 'session_version' => $current->session_version + 1])->save();
            $request->session()->regenerate();
            $request->session()->regenerateToken();
            $request->session()->put('teacher_session_version', $current->session_version);
        });

        return $this->notice('security', 'Password updated. Other teacher sessions must sign in again.');
    }

    public function revokeSessions(Request $request)
    {
        $teacher = $this->teacher();
        DB::transaction(function () use ($request, $teacher) {
            $current = TeacherAccount::whereKey($teacher->id)->lockForUpdate()->firstOrFail();
            $this->confirmPassword($request, $current);
            $current->forceFill(['session_version' => $current->session_version + 1])->save();
            $request->session()->regenerate();
            $request->session()->regenerateToken();
            $request->session()->put('teacher_session_version', $current->session_version);
        });

        return $this->notice('security', 'Other teacher sessions will be signed out on their next request. This session remains active.');
    }

    private function classData(Request $request, TeacherAccount $teacher, ?Classroom $classroom = null): array
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'grade' => ['required', Rule::in(config('teacher_settings.grades'))],
            'section' => ['required', 'string', 'max:80', Rule::unique('classrooms', 'section')->where('teacher_account_id', $teacher->id)->where('grade', $request->input('grade'))->ignore($classroom?->id)],
            'period' => ['nullable', 'string', 'max:80'], 'enrollment_open' => ['required', 'boolean'],
            'sequential_learning' => ['sometimes', 'boolean'], 'weekly_xp_goal' => ['sometimes', 'integer', 'between:100,100000']]);

        $data['period'] = $data['period'] ?? '';

        return $data;
    }

    private function joinCode(): string
    {
        do {
            $code = Str::upper(Str::random(8));
        } while (Classroom::where('join_code', $code)->exists());

        return $code;
    }

    public function storeClass(Request $request)
    {
        $teacher = $this->teacher();
        $data = $this->classData($request, $teacher);
        DB::transaction(function () use ($teacher, $data) {
            TeacherAccount::whereKey($teacher->id)->lockForUpdate()->firstOrFail();
            if ($teacher->classrooms()->count() >= 100) {
                throw ValidationException::withMessages(['section' => 'This account already has 100 sections. Edit an existing section.']);
            }
            Classroom::create([...$data, 'teacher_account_id' => $teacher->id, 'join_code' => $this->joinCode()]);
        });

        return $this->notice('classes', 'Classroom created. Share its join code with students in the selected grade and section.');
    }

    public function updateClass(Request $request, Classroom $classroom)
    {
        $teacher = $this->teacher();
        abort_unless($classroom->teacher_account_id === $teacher->id, 403);
        $data = $this->classData($request, $teacher, $classroom);
        DB::transaction(function () use ($classroom, $teacher, $data) {
            TeacherAccount::whereKey($teacher->id)->lockForUpdate()->firstOrFail();
            $current = Classroom::whereKey($classroom->id)->where('teacher_account_id', $teacher->id)->lockForUpdate()->firstOrFail();
            $current->update($data);
            // Keep enrollment labels consistent without treating a teacher edit as student activity.
            DB::table('students')->where('classroom_id', $current->id)->update(['grade_level' => $current->grade, 'section' => $current->section]);
        });

        return $this->notice('classes', 'Classroom settings saved. Existing students keep their accounts, work and XP.');
    }

    public function rotateCode(Request $request, Classroom $classroom)
    {
        $teacher = $this->teacher();
        abort_unless($classroom->teacher_account_id === $teacher->id, 403);
        $request->validate(['confirm_rotation' => ['accepted']]);
        $this->confirmPassword($request, $teacher);
        $classroom->update(['join_code' => $this->joinCode()]);

        return $this->notice('classes', 'A new join code has been generated. The previous code can no longer be used for registration.');
    }

    public function uploadAvatar(Request $request)
    {
        $teacher = $this->teacher();
        $request->validate(['avatar' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:max_width=4000,max_height=4000']]);
        $file = $request->file('avatar');
        $path = $file->store('teacher-avatars/'.$teacher->id, 'local');
        try {
            $old = DB::transaction(function () use ($teacher, $path, $file) {
                $current = TeacherAccount::whereKey($teacher->id)->lockForUpdate()->firstOrFail();
                $old = $current->avatar_path;
                $current->forceFill(['avatar_path' => $path, 'avatar_mime' => $file->getMimeType()])->save();

                return $old;
            });
        } catch (\Throwable $error) {
            Storage::disk('local')->delete($path);
            throw $error;
        }
        if ($old && str_starts_with($old, 'teacher-avatars/'.$teacher->id.'/')) {
            Storage::disk('local')->delete($old);
        }

        return $this->notice('profile', 'Profile photo saved.');
    }

    public function removeAvatar()
    {
        $teacher = $this->teacher();
        $old = $teacher->avatar_path;
        $teacher->forceFill(['avatar_path' => null, 'avatar_mime' => null])->save();
        if ($old && str_starts_with($old, 'teacher-avatars/'.$teacher->id.'/')) {
            Storage::disk('local')->delete($old);
        }

        return $this->notice('profile', 'Profile photo removed.');
    }

    public function avatar(TeacherAccount $teacher)
    {
        $student = Student::find(session('student_id'));
        $allowed = (int) session('teacher_id') === $teacher->id || ($teacher->profile_visible && $student?->classroom?->teacher_account_id === $teacher->id);
        abort_unless($allowed, 403);
        abort_unless($teacher->avatar_path && str_starts_with($teacher->avatar_path, 'teacher-avatars/'.$teacher->id.'/') && Storage::disk('local')->exists($teacher->avatar_path), 404);

        return Storage::disk('local')->response($teacher->avatar_path, null, ['Content-Type' => $teacher->avatar_mime, 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}
