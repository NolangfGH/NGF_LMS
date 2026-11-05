<?php

namespace App\Http\Controllers;

use App\Models\TeacherAccount;
use App\Notifications\StaffPasswordReset;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules\Password as PasswordRules;
use Throwable;

use function Illuminate\Support\defer;

class StaffPasswordController extends Controller
{
    public function request(): Response
    {
        return $this->page(['mailAvailable' => $this->smtpAvailable()]);
    }

    public function email(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'string', 'email', 'max:255']]);
        if (! $this->smtpAvailable()) {
            return redirect()->route('staff.password.request')
                ->with('recovery_notice', 'Email recovery is currently unavailable. Contact your school’s authorized LMS support to verify your account.');
        }

        $email = mb_strtolower($data['email']);
        $delivery = DB::transaction(function () use ($email): ?array {
            $account = TeacherAccount::where('email', $email)->lockForUpdate()->first();
            $delivery = null;
            Password::broker('staff')->sendResetLink(
                ['email' => $email, 'staff_access_status' => 'active', 'role' => array_keys(TeacherAccount::ROLES)],
                function (TeacherAccount $recipient, string $token) use (&$delivery): void {
                    $delivery = [$recipient, new StaffPasswordReset($token)];
                },
            );

            return $account?->hasStaffAccess() ? $delivery : null;
        }, 3);
        if ($delivery) {
            defer(static function () use ($delivery): void {
                try {
                    [$account, $notification] = $delivery;
                    $account->notify($notification);
                } catch (Throwable $error) {
                    Log::warning('Staff password reset email could not be delivered.', ['exception_type' => $error::class]);
                }
            })->always();
        }

        return redirect()->route('staff.password.request')
            ->with('recovery_notice', 'If the email belongs to an active staff account, a reset link will be sent. Check your school inbox and spam folder.');
    }

    public function form(Request $request, string $token): Response
    {
        $email = $request->query('email');
        abort_unless(is_string($email) && strlen($email) <= 255 && filter_var($email, FILTER_VALIDATE_EMAIL), 404);

        return $this->page(compact('email', 'token'));
    }

    public function reset(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'token' => ['required', 'string', 'size:64'],
            'password' => ['bail', 'required', 'string', 'max:72', 'confirmed', PasswordRules::min(10)->letters()->numbers(),
                function (string $attribute, string $password, \Closure $fail): void {
                    if (strlen($password) > 72 || str_contains($password, "\0")) {
                        $fail('Choose a password within the supported 72-byte limit.');
                    }
                }],
        ]);
        $email = mb_strtolower($data['email']);
        $status = DB::transaction(function () use ($email, $data): string {
            $account = TeacherAccount::where('email', $email)->lockForUpdate()->first();
            if (! $account?->hasStaffAccess()) {
                return Password::INVALID_USER;
            }

            return Password::broker('staff')->reset(
                ['email' => $email, 'token' => $data['token'], 'password' => $data['password']],
                function (TeacherAccount $staff, string $password): void {
                    $staff->forceFill(['password' => Hash::make($password), 'password_changed_at' => now(), 'session_version' => $staff->session_version + 1])->save();
                    DB::afterCommit(fn () => event(new PasswordReset($staff)));
                },
            );
        }, 3);
        if ($status !== Password::PASSWORD_RESET) {
            return redirect()->route('staff.password.request')->withErrors(['email' => 'This link is invalid or expired. Request a new link.']);
        }
        if ($request->session()->get('teacher_id')) {
            $request->session()->forget(['teacher_id', 'teacher_session_version', 'teacher_authenticated_at']);
            $request->session()->regenerate();
        }

        return redirect()->route('staff.password.request')
            ->with('recovery_notice', 'Password updated. Use your school email and new password to sign in.');
    }

    private function smtpAvailable(): bool
    {
        $mailer = config('mail.default');

        return config("mail.mailers.{$mailer}.transport") === 'smtp' && filled(config("mail.mailers.{$mailer}.host"));
    }

    private function page(array $data): Response
    {
        return response()->view('staff-password', $data, 200, [
            'Cache-Control' => 'private, no-store', 'Referrer-Policy' => 'no-referrer',
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }
}
