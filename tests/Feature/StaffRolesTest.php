<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Student;
use App\Models\TeacherAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class StaffRolesTest extends TestCase
{
    use RefreshDatabase;

    private function account(string $email, string $role = 'teacher'): TeacherAccount
    {
        $account = new TeacherAccount(['first_name' => 'Staff', 'last_name' => 'Member', 'email' => $email, 'school' => 'School', 'password' => 'Password123']);
        $account->forceFill(['role' => $role])->save();

        return $account;
    }

    private function registration(string $role = 'teacher'): array
    {
        return ['first_name' => 'New', 'last_name' => 'Staff', 'email' => 'new-staff@example.test', 'school' => 'School', 'password' => 'Password123', 'password_confirmation' => 'Password123',
            'requested_role' => $role, 'class_name' => 'C++ Programming', 'grade' => 'Grade 8', 'section' => 'AM', 'period' => ''];
    }

    private function asStaff(TeacherAccount $account): self
    {
        return $this->withSession(['teacher_id' => $account->id, 'teacher_session_version' => $account->session_version]);
    }

    public function test_teacher_registration_saves_only_the_account_and_opens_class_setup(): void
    {
        $this->get(route('teacher.register'))->assertOk()->assertSee('Admin / staff role')->assertSee('Principal')->assertSee('System Admin')->assertSee('© 2026–2027 NGF_C++CodeQuest')
            ->assertDontSee('FERPA compliant')->assertDontSee('COPPA certified')->assertDontSee('District enclave')->assertDontSee('2024–2025');
        $this->postJson(route('teacher.register.store'), [...$this->registration(), 'role' => 'school_admin', 'staff_access_status' => 'active', 'staff_reviewed_by' => 99])->assertOk()->assertJsonPath('status', 'active')->assertJsonPath('redirect', route('teacher.classes'));
        $teacher = TeacherAccount::sole();
        $this->assertSame('teacher', $teacher->role);
        $this->assertNull($teacher->staff_reviewed_by);
        $this->assertTrue(Hash::check('Password123', $teacher->password));
        $this->assertDatabaseCount('classrooms', 0);
        $this->get(route('teacher.classes'))->assertOk()->assertSee('Create your first class');
        $this->get(route('teacher.dashboard'))->assertOk()->assertSee('Teacher Portal');
        $this->get(route('school.admin'))->assertForbidden();
    }

    public function test_elevated_public_requests_cannot_grant_privileges_or_create_a_classroom(): void
    {
        foreach (['principal', 'school_admin'] as $index => $role) {
            $payload = $this->registration($role);
            unset($payload['class_name'], $payload['grade'], $payload['section'], $payload['period']);
            $payload['email'] = 'requested'.$index.'@example.test';
            $this->postJson(route('teacher.register.store'), [...$payload, 'role' => $role, 'staff_access_status' => 'active'])
                ->assertOk()->assertJsonPath('status', 'pending')->assertJsonPath('redirect', route('staff.registration.status'))->assertSessionMissing('teacher_id');
            $account = TeacherAccount::where('email', $payload['email'])->firstOrFail();
            $this->assertSame('teacher', $account->role);
            $this->assertSame('pending', $account->staff_access_status);
            $this->assertSame($role, $account->requested_role);
            $this->assertFalse($account->hasStaffAccess());
            $this->get(route('staff.registration.status'))->assertOk()->assertSee('Awaiting System Admin approval')->assertDontSee($account->password, false);
            $this->postJson(route('teacher.login.store'), ['identifier' => $account->email, 'password' => 'Password123'])->assertOk()->assertJsonPath('status', 'pending')->assertSessionMissing('teacher_id');
            $this->asStaff($account)->get(route('school.admin'))->assertForbidden()->assertSessionMissing('teacher_id');
        }
        $this->assertDatabaseCount('classrooms', 0);
        $this->assertDatabaseCount('staff_role_reviews', 0);
    }

    public function test_only_system_admin_can_approve_a_role_after_password_confirmation_and_audit_it(): void
    {
        $admin = $this->account('admin@example.test', 'school_admin');
        $teacher = $this->account('teacher@example.test');
        $target = $this->account('request@example.test');
        $target->forceFill(['requested_role' => 'principal', 'staff_access_status' => 'pending'])->save();
        $payload = ['role' => 'principal', 'revision' => 1, 'decision' => 'approved', 'current_password' => 'Password123'];
        $this->putJson(route('school.roles', $target), $payload)->assertForbidden();
        $this->asStaff($teacher)->putJson(route('school.roles', $target), $payload)->assertForbidden();
        $this->asStaff($admin)->putJson(route('school.roles', $target), [...$payload, 'current_password' => 'incorrect'])->assertUnprocessable()->assertJsonValidationErrors('current_password');
        $this->putJson(route('school.roles', $target), [...$payload, 'role' => 'superuser'])->assertUnprocessable()->assertJsonValidationErrors('role');
        $this->put(route('school.roles', $target), $payload)->assertRedirect();
        $this->assertDatabaseHas('teacher_accounts', ['id' => $target->id, 'role' => 'principal', 'staff_access_status' => 'active', 'staff_reviewed_by' => $admin->id, 'session_version' => 2]);
        $this->assertDatabaseHas('staff_role_reviews', ['account_id' => $target->id, 'reviewer_id' => $admin->id, 'assigned_role' => 'principal', 'decision' => 'approved']);
        $this->putJson(route('school.roles', $target), $payload)->assertConflict();
        $this->putJson(route('school.roles', $admin), ['role' => 'teacher', 'revision' => 1, 'current_password' => 'Password123'])->assertUnprocessable();
        $this->assertTrue($admin->fresh()->isSchoolAdmin());
        $this->assertDatabaseCount('staff_role_reviews', 1);
        $this->withSession(['teacher_id' => null])->postJson(route('teacher.login.store'), ['identifier' => $target->email, 'password' => 'Password123'])->assertOk()->assertJsonPath('redirect', route('school.overview'));
    }

    public function test_principal_sees_aggregate_summaries_but_cannot_read_private_learning_pages_or_mutate_classes(): void
    {
        $principal = $this->account('principal@example.test', 'principal');
        $teacher = $this->account('owner@example.test');
        $room = Classroom::create(['teacher_account_id' => $teacher->id, 'name' => 'C++', 'grade' => 'Grade 8', 'section' => 'AM', 'join_code' => 'PRIVATECODE']);
        Student::create(['classroom_id' => $room->id, 'first_name' => 'PrivateName', 'nickname' => 'PrivateCoder', 'student_id' => 'PRIVATEID', 'password' => 'Password123']);
        $this->asStaff($principal)->get(route('school.overview'))->assertOk()->assertViewHas('studentCount', 1)->assertSee('Grade 8')->assertSee('Principal Portal')
            ->assertDontSee('PrivateName')->assertDontSee('PrivateCoder')->assertDontSee('PRIVATECODE')->assertDontSee('PRIVATEID');
        $this->get(route('teacher.dashboard'))->assertRedirect(route('school.overview'));
        foreach (['teacher.students', 'teacher.blog', 'teacher.assignments', 'teacher.grading', 'teacher.code-lab', 'teacher.registrations', 'school.admin'] as $route) {
            $this->get(route($route))->assertForbidden();
        }
        $this->postJson(route('teacher.settings.classes.store'), ['name' => 'Unauthorized'])->assertForbidden();
        $this->postJson(route('teacher.assignments.store'), [])->assertForbidden();
        $this->asStaff($teacher)->get(route('school.overview'))->assertForbidden();
        $this->assertDatabaseCount('classrooms', 1);
    }

    public function test_principal_can_manage_their_own_settings_without_changing_their_role(): void
    {
        $principal = $this->account('principal@example.test', 'principal');
        $this->asStaff($principal)->get(route('teacher.settings'))->assertOk()->assertDontSee('Create classroom &amp; join code');
        $this->put(route('teacher.settings.account'), ['first_name' => 'Updated', 'last_name' => 'Principal', 'email' => $principal->email, 'school' => 'School', 'role' => 'school_admin'])->assertRedirect();
        $this->assertSame('principal', $principal->fresh()->role);
        $this->assertSame('Updated', $principal->fresh()->first_name);
    }

    public function test_declined_role_request_is_private_escaped_and_stays_blocked(): void
    {
        $admin = $this->account('admin@example.test', 'school_admin');
        $this->postJson(route('teacher.register.store'), $this->registration('school_admin'))->assertOk();
        $target = TeacherAccount::where('email', 'new-staff@example.test')->firstOrFail();
        $this->asStaff($admin)->putJson(route('school.roles', $target), ['role' => 'teacher', 'revision' => 1, 'decision' => 'rejected', 'current_password' => 'Password123'])->assertUnprocessable()->assertJsonValidationErrors('note');
        $note = '<script>alert("unsafe")</script> Confirm your school appointment.';
        $this->put(route('school.roles', $target), ['role' => 'teacher', 'revision' => 1, 'decision' => 'rejected', 'current_password' => 'Password123', 'note' => $note])->assertRedirect();
        $this->withSession(['teacher_id' => null])->get(route('staff.registration.status'))->assertOk()->assertSee($note)->assertDontSee($note, false);
        $this->getJson(route('staff.registration.check'))->assertOk()->assertJsonPath('status', 'rejected');
        $this->get(route('teacher.logout'))->assertRedirect(route('teacher.login'))->assertSessionMissing('pending_staff_registration_id');
        $this->get(route('staff.registration.status', ['email' => $target->email]))->assertRedirect(route('teacher.login'));
        $this->getJson(route('staff.registration.check'))->assertUnauthorized();
        $this->postJson(route('teacher.login.store'), ['identifier' => $target->email, 'password' => 'incorrect'])->assertUnprocessable()->assertSessionMissing('pending_staff_registration_id');
        $this->postJson(route('teacher.login.store'), ['identifier' => $target->email, 'password' => 'Password123'])->assertOk()->assertJsonPath('status', 'rejected')->assertSessionMissing('teacher_id');
    }

    public function test_role_changes_revoke_old_sessions_and_do_not_orphan_teaching_classes(): void
    {
        $admin = $this->account('admin@example.test', 'school_admin');
        $target = $this->account('teacher@example.test');
        $room = Classroom::create(['teacher_account_id' => $target->id, 'name' => 'C++', 'grade' => 'Grade 8', 'section' => 'AM', 'join_code' => 'OWNCLASS']);
        $this->asStaff($admin)->putJson(route('school.roles', $target), ['role' => 'principal', 'revision' => 1, 'current_password' => 'Password123'])->assertUnprocessable();
        $this->put(route('school.roles', $target), ['role' => 'school_admin', 'revision' => 1, 'current_password' => 'Password123'])->assertRedirect();
        $this->withSession(['teacher_id' => $target->id, 'teacher_session_version' => 1])->get(route('teacher.dashboard'))->assertRedirect(route('teacher.login'))->assertSessionMissing('teacher_id');
        $this->asStaff($admin)->post(route('teacher.classes.archive', $room), ['archive' => 1])->assertRedirect();
        $this->put(route('school.roles', $target), ['role' => 'principal', 'revision' => 2, 'current_password' => 'Password123'])->assertRedirect();
        $this->postJson(route('teacher.classes.archive', $room), ['archive' => 0])->assertUnprocessable();
        $this->assertNotNull($room->fresh()->archived_at);
        $this->assertDatabaseCount('classrooms', 1);
    }

    public function test_server_bootstrap_assigns_system_admin_to_the_explicit_existing_account_with_an_audit_record(): void
    {
        $owner = $this->account('owner@example.test');
        $other = $this->account('other@example.test');
        $this->artisan('school:promote-admin', ['email' => $owner->email])->assertExitCode(0);
        $this->assertTrue($owner->fresh()->isSchoolAdmin());
        $this->assertSame('teacher', $other->fresh()->role);
        $this->assertDatabaseHas('staff_role_reviews', ['account_id' => $owner->id, 'reviewer_id' => null, 'assigned_role' => 'school_admin']);
        $this->artisan('school:promote-admin', ['email' => 'missing@example.test'])->assertExitCode(1);
        $this->assertDatabaseCount('teacher_accounts', 2);
    }

    public function test_invalid_registration_does_not_leave_an_orphan_staff_account(): void
    {
        $teacher = $this->account('existing@example.test');
        Classroom::create(['teacher_account_id' => $teacher->id, 'name' => 'C++', 'grade' => 'Grade 8', 'section' => 'AM', 'join_code' => 'EXISTING']);
        $this->postJson(route('teacher.register.store'), [...$this->registration(), 'requested_role' => 'superuser'])->assertUnprocessable()->assertJsonValidationErrors('requested_role');
        $this->postJson(route('teacher.register.store'), [...$this->registration(), 'email' => ['invalid'], 'class_code' => ['invalid']])->assertUnprocessable()->assertJsonValidationErrors('email');
        $payload = $this->registration();
        unset($payload['school']);
        $this->postJson(route('teacher.register.store'), $payload)->assertUnprocessable()->assertJsonValidationErrors('school');
        $this->assertDatabaseCount('teacher_accounts', 1);
        $this->assertDatabaseCount('classrooms', 1);
    }
}
