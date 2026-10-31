<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Services\StudentSettingsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class StudentSettingsController extends Controller
{
    private function student(): Student
    {
        $student = Student::with('classroom.teacher')->find(session('student_id'));
        abort_unless($student, 401);

        return $student;
    }

    private function notice(string $section, string $message)
    {
        return redirect()->to(route('student.settings').'#'.$section)->with('student_settings_notice', $message);
    }

    public function index(StudentSettingsService $service)
    {
        if (! Student::find(session('student_id'))) {
            return redirect()->route('student.login');
        }
        $student = $this->student();

        return response()->view('student-settings', [
            'student' => $student, 'classroom' => $student->classroom, 'teacherProfile' => $student->classroom?->teacher?->classroomProfile(),
            'preferences' => $student->effectivePreferences(), 'avatars' => $service->avatars($student), 'studentAlerts' => $service->alerts($student),
            'lastSyncedAt' => now(config('learning.timezone'))->format('M j, Y · g:i A'),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function profile(Request $request)
    {
        $student = $this->student();
        $data = $request->validate(['nickname' => ['required', 'string', 'min:3', 'max:15', 'regex:/\A(?=.*[A-Za-z0-9])[A-Za-z0-9-]+\z/']]);
        DB::table('students')->where('id', $student->id)->update(['nickname' => $data['nickname']]);
        $request->session()->put('student_name', $data['nickname']);

        return $this->notice('profile', 'Your classroom callsign is saved.');
    }

    public function avatar(Request $request, StudentSettingsService $service)
    {
        $student = $this->student();
        $data = $request->validate(['avatar' => ['required', Rule::in(array_keys(config('student_settings.avatars')))]]);
        DB::transaction(function () use ($student, $data, $service) {
            $current = Student::whereKey($student->id)->lockForUpdate()->firstOrFail();
            $card = collect($service->avatars($current))->firstWhere('key', $data['avatar']);
            if (! $card['unlocked']) {
                throw ValidationException::withMessages(['avatar' => 'This companion is still locked. Complete its learning requirement first.']);
            }
            DB::table('students')->where('id', $current->id)->update(['avatar' => $data['avatar']]);
        });

        return $this->notice('avatars', 'Your coding companion is equipped. Your XP is unchanged.');
    }

    public function notifications(Request $request)
    {
        $student = $this->student();
        $data = $request->validate(['quiet_mode' => ['required', 'boolean'], 'notify_deadlines' => ['required', 'boolean'], 'notify_feedback' => ['required', 'boolean'], 'notify_achievements' => ['required', 'boolean']]);

        return $this->savePreferences($student, $data, 'notifications', 'In-app notification preferences saved.');
    }

    public function display(Request $request)
    {
        $student = $this->student();
        $data = $request->validate(['text_size' => ['required', Rule::in(['small', 'medium', 'large'])], 'high_contrast' => ['required', 'boolean'], 'reduce_motion' => ['required', 'boolean']]);

        return $this->savePreferences($student, $data, 'display', 'Your reading and editor comfort settings are saved.');
    }

    private function savePreferences(Student $student, array $data, string $section, string $message)
    {
        foreach ($data as $key => $value) {
            if ($key !== 'text_size') {
                $data[$key] = (bool) $value;
            }
        }
        DB::transaction(function () use ($student, $data) {
            $current = Student::whereKey($student->id)->lockForUpdate()->firstOrFail();
            DB::table('students')->where('id', $current->id)->update(['preferences' => json_encode(array_replace($current->effectivePreferences(), $data), JSON_THROW_ON_ERROR)]);
        });

        return $this->notice($section, $message);
    }

    public function privacy(Request $request)
    {
        $student = $this->student();
        $data = $request->validate(['leaderboard_visible' => ['required', 'boolean']]);
        DB::table('students')->where('id', $student->id)->update(['leaderboard_visible' => (bool) $data['leaderboard_visible']]);

        return $this->notice('privacy', 'Leaderboard privacy saved. Ranking and XP are unchanged.');
    }

    public function password(Request $request)
    {
        $student = $this->student();
        $data = $request->validate(['current_password' => ['required', 'string'], 'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()]]);
        DB::transaction(function () use ($request, $student, $data) {
            $current = Student::whereKey($student->id)->lockForUpdate()->firstOrFail();
            if (! Hash::check($data['current_password'], $current->password)) {
                throw ValidationException::withMessages(['current_password' => 'Your current password is incorrect.']);
            }
            if (Hash::check($data['password'], $current->password)) {
                throw ValidationException::withMessages(['password' => 'Choose a password different from your current one.']);
            }
            $current->forceFill(['password' => $data['password'], 'password_changed_at' => now(), 'session_version' => $current->session_version + 1])->save();
            $request->session()->regenerate(true);
            $request->session()->regenerateToken();
            $request->session()->put('student_session_version', $current->session_version);
        });

        return $this->notice('account', 'Password changed. Other student sessions must sign in again. Your work and XP are kept.');
    }

    public function logout(Request $request)
    {
        $request->session()->forget(['student_id', 'student_session_version', 'student_name', 'student_class_verified', 'classroom_id', 'classroom_name', 'verified_join_code']);
        $request->session()->regenerate(true);
        $request->session()->regenerateToken();

        return redirect()->route('student.login')->with('auth_notice','You have signed out safely.');
    }
}
