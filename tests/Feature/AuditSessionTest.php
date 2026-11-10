<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\Classroom;
use App\Models\ParentAccount;
use App\Models\Student;
use App\Models\TeacherAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditSessionTest extends TestCase
{
    use RefreshDatabase;

    private function learner(): Student
    {
        $teacher = TeacherAccount::create(['first_name' => 'Audit', 'last_name' => 'Teacher', 'school' => 'School', 'email' => fake()->unique()->safeEmail(), 'password' => 'Password123']);
        $room = Classroom::create(['teacher_account_id' => $teacher->id, 'name' => 'C++', 'grade' => 'Grade 8', 'section' => 'AM', 'join_code' => fake()->unique()->bothify('AUD####')]);

        return Student::create(['classroom_id' => $room->id, 'first_name' => 'Learner', 'nickname' => 'AuditCadet', 'student_id' => fake()->unique()->bothify('AUD#######'), 'password' => 'Password123', 'xp' => 100]);
    }

    private function location(): array
    {
        return ['audit_location_opt_in' => 1, 'audit_latitude' => 12.345678, 'audit_longitude' => 121.987654, 'audit_accuracy_km' => 1];
    }

    public function test_student_signin_signout_and_linked_actions_show_real_times_and_only_rounded_shared_area(): void
    {
        $this->travelTo(now()->startOfSecond());
        $student = $this->learner();
        $this->postJson(route('student.login.store'), ['student_id' => $student->student_id, 'password' => 'Password123'] + $this->location())->assertOk()->assertSessionHas('audit_contexts.student.login_event_id');
        $login = AuditEvent::where('action', 'student.login.store')->firstOrFail();
        $this->assertSame('student', $login->actor_type);
        $this->assertSame($student->id, $login->actor_id);
        $this->assertSame('12.3', $login->location_latitude);
        $this->assertSame('122.0', $login->location_longitude);
        $this->assertSame(10, $login->location_accuracy_km);
        $this->assertTrue($login->login_at->equalTo(now()));
        $this->postJson(route('student.assessment-activity.store'), ['kind' => 'invalid'])->assertUnprocessable();
        $action = AuditEvent::latest('id')->firstOrFail();
        $this->assertSame($login->id, $action->login_event_id);
        $this->travel(15)->minutes();
        $this->post(route('student.logout'))->assertRedirect();
        $logout = AuditEvent::where('action', 'student.logout')->firstOrFail();
        $this->assertTrue($logout->logout_at->equalTo(now()));
        $this->assertTrue($login->fresh()->logout_at->equalTo($logout->logout_at));
        $this->assertTrue($logout->login_at->equalTo($login->login_at));
        $serialized = AuditEvent::all()->toJson();
        $this->assertStringNotContainsString('12.345678', $serialized);
        $this->assertStringNotContainsString('121.987654', $serialized);
        $this->assertStringNotContainsString('Password123', $serialized);
        $admin = $student->classroom->teacher;
        $admin->forceFill(['role' => 'school_admin'])->save();
        $page = $this->withSession(['teacher_id' => $admin->id])->get(route('school.audit'))->assertOk()->assertSee('Login time')->assertSee('Logout time')->assertSee('12.3')->assertSee('122.0')->assertSee('Optional approximate location');
        $csv = $this->get(route('school.audit.export'))->assertOk()->streamedContent();
        $this->assertStringContainsString('Login time (UTC+08)', $csv);
        $this->assertStringContainsString($logout->logout_at->copy()->timezone('Asia/Singapore')->format('Y-m-d H:i:s'), $csv);
        $this->assertStringNotContainsString('12.345678', $csv);
        if ($preview = getenv('ASSESSMENT_AUDIT_PREVIEW')) {
            file_put_contents($preview.'/audit-sessions.html', $page->getContent());
        }
    }

    public function test_location_is_optional_invalid_coordinates_do_not_block_login_and_failed_logins_do_not_capture_location(): void
    {
        $student = $this->learner();
        $credentials = ['student_id' => $student->student_id, 'password' => 'Password123'];
        $this->postJson(route('student.login.store'), $credentials + array_replace($this->location(), ['audit_location_opt_in' => 0]))->assertOk();
        $this->assertNull(AuditEvent::latest('id')->firstOrFail()->location_latitude);
        $this->post(route('student.logout'));
        $this->postJson(route('student.login.store'), $credentials + array_replace($this->location(), ['audit_latitude' => 1000]))->assertOk();
        $this->assertNull(AuditEvent::latest('id')->firstOrFail()->location_latitude);
        $this->post(route('student.logout'));
        $this->postJson(route('student.login.store'), array_replace($credentials, ['password' => 'WrongPassword']) + $this->location())->assertUnprocessable();
        $failure = AuditEvent::latest('id')->firstOrFail();
        $this->assertSame('rejected', $failure->outcome);
        $this->assertNull($failure->login_at);
        $this->assertNull($failure->location_latitude);
    }

    public function test_staff_and_parent_signins_are_recorded_and_legacy_missing_logout_is_not_invented(): void
    {
        $student = $this->learner();
        $teacher = $student->classroom->teacher;
        $this->postJson(route('teacher.login.store'), ['identifier' => $teacher->email, 'password' => 'Password123'])->assertOk();
        $this->assertDatabaseHas('audit_events', ['action' => 'teacher.login.store', 'actor_type' => 'teacher', 'actor_id' => $teacher->id]);
        $this->get(route('teacher.logout'))->assertRedirect();
        $staff = AuditEvent::where('action', 'teacher.login.store')->firstOrFail();
        $this->assertNotNull($staff->logout_at);
        $parent = ParentAccount::factory()->create();
        $this->post(route('parent.login.store'), ['email' => $parent->email, 'password' => 'ParentPassword123!'] + $this->location())->assertRedirect();
        $this->post(route('parent.logout'))->assertRedirect();
        $this->assertNotNull(AuditEvent::where('action', 'parent.login.store')->firstOrFail()->logout_at);
        $legacy = AuditEvent::create(['actor_type' => 'student', 'actor_id' => $student->id, 'action' => 'student.login.store', 'method' => 'POST', 'status' => 200, 'outcome' => 'success']);
        $this->assertTrue($legacy->recordedLoginAt()->equalTo($legacy->created_at));
        $this->assertNull($legacy->recordedLogoutAt());
        $this->assertSame('Not shared', $legacy->approximateArea());
    }

    public function test_student_login_is_not_misattributed_when_a_staff_session_is_also_present(): void
    {
        $student = $this->learner();
        $teacher = $student->classroom->teacher;
        $this->withSession(['teacher_id' => $teacher->id])->postJson(route('student.login.store'), ['student_id' => $student->student_id, 'password' => 'Password123'])->assertOk();
        $event = AuditEvent::where('action', 'student.login.store')->firstOrFail();
        $this->assertSame('student',$event->actor_type);
        $this->assertSame($student->id,$event->actor_id);
    }
}
