<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\CodeWorkspace;
use App\Models\LearningNotification;
use App\Models\Student;
use App\Models\TeacherAccount;
use App\Models\TeacherAssignment;
use App\Services\LearningNotifications;
use App\Services\LearningNotificationTargets;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class StudentRegistrationApprovalTest extends TestCase
{
    use RefreshDatabase;

    private function classroom(string $email = 'approval@example.test', string $section = 'AM'): Classroom
    {
        $teacher = TeacherAccount::create(['first_name' => 'Class', 'last_name' => 'Teacher', 'email' => $email, 'school' => 'School', 'password' => 'password123']);

        return Classroom::create(['teacher_account_id' => $teacher->id, 'name' => 'C++', 'grade' => 'Grade 8', 'section' => $section, 'join_code' => 'JOIN'.$teacher->id.$section]);
    }

    private function pending(Classroom $room, string $id = 'PENDING01'): Student
    {
        $student = new Student(['classroom_id' => $room->id, 'first_name' => 'New', 'surname' => 'Student', 'nickname' => 'NewCoder'.$id, 'student_id' => $id,
            'password' => 'password123', 'grade_level' => $room->grade, 'section' => $room->section, 'xp' => 0]);
        $student->forceFill(['registration_status' => 'pending'])->save();

        return $student;
    }

    private function asTeacher(Classroom $room): self
    {
        return $this->withSession(['teacher_id' => $room->teacher_account_id, 'student_id' => null]);
    }

    public function test_public_registration_is_pending_without_access_or_xp_and_notifies_only_its_owner(): void
    {
        $room = $this->classroom();
        $other = $this->classroom('other@example.test', 'BP');
        $this->postJson(route('student.register.verify'), ['classroom_id' => $room->id, 'grade_level' => 'Grade 8', 'class_code' => $room->join_code])->assertOk();
        $this->postJson(route('student.register.store'), ['first_name' => 'Ada', 'surname' => 'Learner', 'nickname' => 'AdaCoder', 'student_id' => 'ada123', 'password' => 'password123', 'password_confirmation' => 'password123',
            'classroom_id' => $room->id, 'grade_level' => 'Grade 8', 'registration_status' => 'approved', 'registration_reviewed_by' => $other->teacher_account_id, 'xp' => 99999])
            ->assertOk()->assertJsonPath('status', 'pending')->assertJsonPath('xp', 0)->assertJsonPath('redirect', route('student.registration.status'))->assertSessionMissing('student_id')->assertSessionMissing('student_class_verified');
        $student = Student::withUnapproved()->where('student_id', 'ADA123')->firstOrFail();
        $this->assertTrue(Hash::check('password123', $student->password));
        $this->assertSame(0, $student->xp);
        $this->assertNull($student->registration_reviewed_by);
        $this->assertDatabaseCount('learning_rewards', 0);
        $this->assertSame(0, Student::count());
        $this->assertSame(0, $room->students()->count());
        $notice = LearningNotification::where('target_type', 'student_registration')->sole();
        $this->assertSame($room->teacher_account_id, $notice->teacher_account_id);
        $this->assertNull(app(LearningNotificationTargets::class)->url($notice, TeacherAccount::find($other->teacher_account_id)));
        $this->get(route('student.registration.status'))->assertOk()->assertSee('Awaiting teacher approval')->assertSee('ADA123')->assertDontSee($student->password, false)->assertHeader('Cache-Control', 'no-store, private');
        $this->asTeacher($room)->get(route('teacher.dashboard'))->assertOk()->assertSee('Ada')->assertSee('1 pending');
        $this->get(route('teacher.students'))->assertOk()->assertDontSee('AdaCoder');
    }

    public function test_only_the_class_owner_can_review_even_when_another_teacher_is_a_school_admin(): void
    {
        $room = $this->classroom();
        $other = $this->classroom('foreign@example.test');
        TeacherAccount::find($other->teacher_account_id)->forceFill(['role' => 'school_admin'])->save();
        $student = $this->pending($room);
        $foreign = $this->pending($other, 'SECRET01');
        $this->get(route('teacher.registrations'))->assertRedirect(route('teacher.login'));
        $this->postJson(route('teacher.registrations.review', $student->id), ['decision' => 'approved', 'revision' => 1])->assertForbidden();
        $this->asTeacher($other)->postJson(route('teacher.registrations.review', $student->id), ['decision' => 'approved', 'revision' => 1])->assertNotFound();
        $this->asTeacher($room)->get(route('teacher.registrations'))->assertOk()->assertSee('PENDING01')->assertDontSee('SECRET01');
        $this->getJson(route('teacher.registrations.status', ['focus' => $foreign->id, 'status' => 'all']))->assertOk()->assertJsonMissingPath('student_id');
        $this->getJson(route('teacher.registrations.status', ['classroom' => $other->id]))->assertUnprocessable();
        $this->assertDatabaseHas('students', ['id' => $student->id, 'registration_status' => 'pending', 'xp' => 0]);
        $this->assertDatabaseCount('student_registration_reviews', 0);
    }

    public function test_approval_enables_login_and_awards_welcome_xp_once_with_an_audit_trail(): void
    {
        $room = $this->classroom();
        $student = $this->pending($room);
        $this->asTeacher($room)->postJson(route('teacher.registrations.review', $student->id), ['decision' => 'approved', 'revision' => 1])->assertOk()->assertJsonPath('status', 'approved');
        $this->assertDatabaseHas('students', ['id' => $student->id, 'registration_status' => 'approved', 'xp' => 100, 'registration_reviewed_by' => $room->teacher_account_id]);
        $this->assertDatabaseHas('student_registration_reviews', ['student_id' => $student->id, 'from_status' => 'pending', 'decision' => 'approved', 'teacher_account_id' => $room->teacher_account_id]);
        $this->postJson(route('teacher.registrations.review', $student->id), ['decision' => 'approved', 'revision' => 1])->assertConflict();
        $this->postJson(route('teacher.registrations.review', $student->id), ['decision' => 'approved', 'revision' => 2])->assertConflict();
        $this->assertDatabaseCount('student_registration_reviews', 1);
        $this->assertDatabaseCount('learning_rewards', 1);
        $this->withSession(['teacher_id' => null, 'pending_registration_id' => $student->id])->getJson(route('student.registration.check'))->assertOk()->assertJsonPath('status', 'approved')->assertSessionMissing('student_id');
        $this->postJson(route('student.login.store'), ['student_id' => 'PENDING01', 'password' => 'password123'])->assertOk()->assertJsonPath('redirect', route('student.dashboard'))->assertSessionMissing('pending_registration_id');
        $this->get(route('student.dashboard'))->assertOk();
        $this->get(route('student.leaderboard'))->assertOk()->assertSee('100 XP');
        $this->assertSame(100, $student->fresh()->xp);
    }

    public function test_decline_requires_a_reason_and_can_be_reopened_without_granting_access(): void
    {
        $room = $this->classroom();
        $student = $this->pending($room);
        $this->asTeacher($room)->postJson(route('teacher.registrations.review', $student->id), ['decision' => 'rejected', 'revision' => 1])->assertUnprocessable()->assertJsonValidationErrors('note');
        $reason = '<script>alert("unsafe")</script> Confirm your section with me.';
        $this->postJson(route('teacher.registrations.review', $student->id), ['decision' => 'rejected', 'revision' => 1, 'note' => $reason])->assertOk();
        $this->withSession(['teacher_id' => null])->postJson(route('student.login.store'), ['student_id' => 'PENDING01', 'password' => 'password123'])->assertOk()->assertJsonPath('status', 'rejected')->assertSessionMissing('student_id');
        $this->get(route('student.registration.status'))->assertOk()->assertSee($reason)->assertDontSee($reason, false);
        $this->asTeacher($room)->get(route('teacher.registrations', ['status' => 'rejected']))->assertOk()->assertSee($reason)->assertDontSee($reason, false);
        $this->postJson(route('teacher.registrations.review', $student->id), ['decision' => 'pending', 'revision' => 2])->assertOk();
        $this->assertDatabaseHas('students', ['id' => $student->id, 'registration_status' => 'pending', 'xp' => 0, 'registration_revision' => 3]);
        $this->postJson(route('teacher.registrations.review', $student->id), ['decision' => 'approved', 'revision' => 3])->assertOk();
        $this->assertDatabaseCount('student_registration_reviews', 3);
        $this->assertDatabaseHas('students', ['id' => $student->id, 'registration_status' => 'approved', 'xp' => 100]);
    }

    public function test_pending_login_verifies_password_and_status_is_private_to_the_verified_session(): void
    {
        $room = $this->classroom();
        $student = $this->pending($room);
        $other = $this->pending($room, 'PRIVATE02');
        $this->get(route('student.registration.status', ['student_id' => $student->id]))->assertRedirect(route('student.login'));
        $this->getJson(route('student.registration.check', ['pending_registration_id' => $student->id]))->assertUnauthorized();
        $this->postJson(route('student.login.store'), ['student_id' => 'PENDING01', 'password' => 'wrong'])->assertUnprocessable()->assertJsonPath('message', 'The Student ID or password is incorrect.')->assertSessionMissing('pending_registration_id');
        $this->postJson(route('student.login.store'), ['student_id' => 'PENDING01', 'password' => 'password123'])->assertOk()->assertJsonPath('status', 'pending')->assertSessionHas('pending_registration_id', $student->id)->assertSessionMissing('student_id');
        $this->get(route('student.registration.status', ['student_id' => $other->id]))->assertOk()->assertSee('PENDING01')->assertDontSee('PRIVATE02');
        $this->post(route('student.logout'))->assertRedirect(route('student.login'))->assertSessionMissing('pending_registration_id');
        $this->getJson(route('student.registration.check'))->assertUnauthorized();
    }

    public function test_forged_pending_student_sessions_cannot_enter_learning_pages_or_submit_work(): void
    {
        $room = $this->classroom();
        $student = $this->pending($room);
        $workspace = CodeWorkspace::create(['student_id' => $student->id, 'activity_key' => 'practice:hello', 'title' => 'Greeting', 'code' => 'Original code']);
        foreach (['student.dashboard', 'student.learn', 'student.assignments', 'student.code-lab', 'student.quiz', 'student.leaderboard', 'student.blog', 'student.settings'] as $route) {
            $this->withSession(['student_id' => $student->id, 'student_class_verified' => true, 'student_session_version' => 1])->get(route($route))->assertRedirect(route('student.registration.status'))->assertSessionMissing('student_id');
        }
        $this->withSession(['student_id' => $student->id, 'student_class_verified' => true])->putJson(route('student.code-lab.save', $workspace->id), ['code' => 'int main(){}'])->assertForbidden();
        $this->assertSame('Original code', $workspace->fresh()->code);
        $this->assertDatabaseCount('learning_rewards', 0);
    }

    public function test_pending_students_receive_no_class_content_notifications_and_cannot_be_approved_in_archived_classes(): void
    {
        $room = $this->classroom();
        $student = $this->pending($room);
        $assignment = TeacherAssignment::create(['teacher_account_id' => $room->teacher_account_id, 'classroom_id' => $room->id, 'title' => 'Private task', 'status' => 'published', 'kind' => 'assignment', 'xp' => 10]);
        app(LearningNotifications::class)->publishedActivity($assignment);
        $this->assertSame(0, LearningNotification::where('student_id', $student->id)->count());
        $room->forceFill(['archived_at' => now()])->save();
        $this->asTeacher($room)->postJson(route('teacher.registrations.review', $student->id), ['decision' => 'approved', 'revision' => 1])->assertUnprocessable();
        $this->assertDatabaseHas('students', ['id' => $student->id, 'registration_status' => 'pending', 'xp' => 0]);
        $this->assertDatabaseCount('student_registration_reviews', 0);
    }

    public function test_existing_accounts_and_teacher_created_students_remain_approved(): void
    {
        $room = $this->classroom();
        $existing = Student::create(['classroom_id' => $room->id, 'first_name' => 'Existing', 'nickname' => 'OldCoder', 'student_id' => 'OLD01', 'password' => 'password123', 'xp' => 430]);
        $this->postJson(route('student.login.store'), ['student_id' => 'OLD01', 'password' => 'password123'])->assertOk()->assertJsonPath('redirect', route('student.dashboard'));
        $this->assertSame(430, $existing->fresh()->xp);
        $this->asTeacher($room)->post(route('teacher.students.store'), ['classroom_id' => $room->id, 'first_name' => 'Verified', 'surname' => 'Student', 'nickname' => 'VerifiedCoder', 'student_id' => 'MANUAL01', 'password' => 'password123', 'password_confirmation' => 'password123'])->assertRedirect();
        $created = Student::where('student_id', 'MANUAL01')->firstOrFail();
        $this->assertSame('approved', $created->registration_status);
        $this->assertSame(100, $created->xp);
        $this->assertSame($room->teacher_account_id, $created->registration_reviewed_by);
        $this->assertDatabaseHas('student_registration_reviews', ['student_id' => $created->id, 'decision' => 'approved', 'from_status' => null]);
    }
}
