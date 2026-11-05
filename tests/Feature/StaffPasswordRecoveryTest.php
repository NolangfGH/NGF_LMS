<?php

namespace Tests\Feature;

use App\Models\TeacherAccount;
use App\Notifications\StaffPasswordReset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class StaffPasswordRecoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_active_roles_can_recover_using_their_school_email_and_a_single_use_link(): void
    {
        $this->fakeDelivery();
        $this->get(route('teacher.login'))->assertOk()->assertSee(route('staff.password.request'));
        $this->get(route('staff.password.request'))->assertOk()->assertSee('registered school email as their username')->assertSee('Send reset link');

        foreach (array_keys(TeacherAccount::ROLES) as $role) {
            $staff = $this->staff($role);
            $notification = $this->sendLink($staff);
            $this->assertNotSame($notification->token, DB::table('staff_password_reset_tokens')->where('email', $staff->email)->value('token'));
            $this->assertStringStartsWith('https://lms.example.test/staff-password/reset/', $notification->toMail($staff)->actionUrl);
            $this->get(route('staff.password.reset', ['token' => $notification->token, 'email' => $staff->email]))
                ->assertOk()->assertHeader('Referrer-Policy', 'no-referrer')->assertHeader('Cache-Control', 'no-store, private');
            $data = $this->resetData($staff, $notification);
            $this->post(route('staff.password.update'), $data)->assertRedirect(route('staff.password.request'))->assertSessionHas('recovery_notice');
            $staff->refresh();
            $this->assertSame(2, $staff->session_version);
            $this->assertSame($role, $staff->role);
            $this->assertTrue(Hash::check($data['password'], $staff->password));
            $this->assertDatabaseMissing('staff_password_reset_tokens', ['email' => $staff->email]);
            $this->withSession(['teacher_id' => $staff->id, 'teacher_session_version' => 1])->get(route('teacher.dashboard'))->assertSessionMissing('teacher_id');
            $this->post(route('staff.password.update'), $data)->assertRedirect(route('staff.password.request'))->assertSessionHasErrors('email');
            $this->assertSame(2, $staff->fresh()->session_version);
            $this->postJson(route('teacher.login.store'), ['identifier' => $staff->email, 'password' => $data['password']])->assertOk()
                ->assertJsonPath('redirect', route($staff->dashboardRoute()));
            $this->withSession(['teacher_id' => null]);
        }
    }

    public function test_unknown_pending_expired_and_revoked_accounts_cannot_use_recovery(): void
    {
        $this->fakeDelivery();
        $pending = $this->staff('principal', 'pending');
        $teacher = $this->staff('teacher');
        $notice = null;
        foreach (['missing@example.test', $pending->email, $teacher->email] as $email) {
            $response = $this->post(route('staff.password.email'), ['email' => $email])->assertRedirect(route('staff.password.request'))->assertSessionHas('recovery_notice');
            $notice ??= session('recovery_notice');
            $this->assertSame($notice, session('recovery_notice'));
        }
        Notification::assertNotSentTo($pending, StaffPasswordReset::class);
        $this->assertDatabaseCount('staff_password_reset_tokens', 1);
        $notification = Notification::sent($teacher, StaffPasswordReset::class)->sole();
        $this->travel(31)->minutes();
        $this->post(route('staff.password.update'), $this->resetData($teacher, $notification))->assertSessionHasErrors('email');
        $this->assertTrue(Hash::check('Original123', $teacher->fresh()->password));

        $newLink = $this->sendLink($teacher, latest: true);
        $this->withSession(['teacher_id' => $teacher->id])->post(route('teacher.settings.sessions'), ['current_password' => 'Original123'])->assertRedirect();
        $this->assertDatabaseMissing('staff_password_reset_tokens', ['email' => $teacher->email]);
        $this->withSession(['teacher_id' => null])->post(route('staff.password.update'), $this->resetData($teacher, $newLink))->assertSessionHasErrors('email');
    }

    public function test_log_mailer_does_not_create_or_log_reset_tokens(): void
    {
        Notification::fake();
        config(['mail.default' => 'log']);
        $staff = $this->staff('school_admin');
        $this->get(route('staff.password.request'))->assertOk()->assertSee('Email recovery is currently unavailable');
        $this->post(route('staff.password.email'), ['email' => $staff->email])->assertRedirect(route('staff.password.request'));
        $this->assertDatabaseEmpty('staff_password_reset_tokens');
        Notification::assertNothingSent();
    }

    private function fakeDelivery(): void
    {
        Notification::fake();
        config(['app.url' => 'https://lms.example.test', 'mail.default' => 'smtp', 'mail.mailers.smtp.host' => 'smtp.example.test']);
    }

    private function sendLink(TeacherAccount $staff, bool $latest = false): StaffPasswordReset
    {
        $this->post(route('staff.password.email'), ['email' => strtoupper($staff->email)])->assertRedirect(route('staff.password.request'));
        $sent = Notification::sent($staff, StaffPasswordReset::class);

        return $latest ? $sent->last() : $sent->sole();
    }

    private function resetData(TeacherAccount $staff, StaffPasswordReset $notification): array
    {
        return ['email' => $staff->email, 'token' => $notification->token, 'password' => 'NewPassword54321', 'password_confirmation' => 'NewPassword54321'];
    }

    private function staff(string $role, string $status = 'active'): TeacherAccount
    {
        $staff = new TeacherAccount(['first_name' => 'Staff', 'last_name' => 'Member', 'school' => 'School', 'email' => $role.'@example.test', 'password' => 'Original123']);
        $staff->forceFill(['role' => $role, 'staff_access_status' => $status])->save();

        return $staff;
    }
}
