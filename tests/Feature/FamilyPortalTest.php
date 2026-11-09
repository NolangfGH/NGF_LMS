<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\ParentAccount;
use App\Models\ParentStudentLink;
use App\Models\Student;
use App\Models\TeacherAccount;
use App\Models\TeacherQuiz;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class FamilyPortalTest extends TestCase
{
    use RefreshDatabase;

    private function family(): array
    {
        $teacher = TeacherAccount::create(['first_name' => 'Family', 'last_name' => 'Teacher', 'email' => 'family@example.test', 'school' => 'Regional Science High School', 'password' => 'password123']);
        $room = Classroom::create(['teacher_account_id' => $teacher->id, 'name' => 'C++', 'grade' => 'Grade 8', 'section' => 'AM', 'join_code' => 'FAM001']);
        $student = Student::create(['classroom_id' => $room->id, 'first_name' => 'Learner', 'surname' => 'Child', 'nickname' => 'PrivateCadet', 'student_id' => 'FAM-STUDENT', 'password' => 'password123', 'xp' => 100]);
        $parent = ParentAccount::factory()->create();

        return [$teacher, $room, $student, $parent];
    }

    public function test_registration_hashes_password_and_parent_login_is_separate(): void
    {
        $this->post(route('parent.register'), ['name' => 'Sample Parent', 'email' => 'PARENT@example.test', 'password' => 'ParentPassword123!', 'password_confirmation' => 'ParentPassword123!'])->assertRedirect(route('parent.dashboard'));
        $parent = ParentAccount::firstOrFail();
        $this->assertTrue(Hash::check('ParentPassword123!', $parent->password));
        $this->get(route('parent.dashboard'))->assertOk()->assertSee('No approved child access yet');
        $this->post(route('parent.logout'))->assertRedirect(route('parent.login'));
        $this->get(route('parent.dashboard'))->assertRedirect(route('parent.login'));
        $this->post(route('parent.login.store'), ['email' => $parent->email, 'password' => 'wrong-password'])->assertSessionHasErrors('email');
        $this->post(route('parent.login.store'), ['email' => $parent->email, 'password' => 'ParentPassword123!'])->assertRedirect(route('parent.dashboard'));
        if ($preview = getenv('COMMUNITY_PREVIEW')) {
            file_put_contents($preview.'/parent-login.html', $this->get(route('parent.login'))->getContent());
        }
    }

    public function test_only_own_class_teacher_can_approve_and_parent_sees_only_approved_child(): void
    {
        [$teacher, $room, $student, $parent] = $this->family();
        $this->withSession(['parent_id' => $parent->id, 'parent_session_version' => 1])->post(route('parent.links.store'), ['student_reference' => $student->student_id, 'relationship' => 'Parent'])->assertRedirect();
        $link = ParentStudentLink::firstOrFail();
        $this->get(route('parent.dashboard'))->assertOk()->assertDontSee('PrivateCadet')->assertDontSee('Learner Child');
        $other = TeacherAccount::create(['first_name' => 'Other', 'last_name' => 'Teacher', 'email' => 'other-family@example.test', 'school' => 'Other school', 'password' => 'password123']);
        $this->withSession(['teacher_id' => $other->id, 'parent_id' => null])->post(route('teacher.parents.review', $link), ['decision' => 'approved'])->assertForbidden();
        $this->withSession(['teacher_id' => $teacher->id])->get(route('teacher.parents'))->assertOk()->assertSee($parent->email);
        $this->post(route('teacher.parents.review', $link), ['decision' => 'approved', 'note' => 'Verified through school records.'])->assertRedirect();
        TeacherQuiz::create(['teacher_account_id' => $teacher->id, 'classroom_id' => $room->id, 'title' => 'Safe quiz title', 'status' => 'published', 'questions' => [['prompt' => 'PRIVATE ANSWER KEY', 'correct' => 'SECRET']], 'opens_at' => now()->addDay()]);
        $this->withSession(['teacher_id' => null, 'parent_id' => $parent->id, 'parent_session_version' => 1]);
        $page = $this->get(route('parent.dashboard'))->assertOk()->assertSee('Learner Child')->assertSee('Safe quiz title')->assertDontSee('PRIVATE ANSWER KEY')->assertDontSee('SECRET')->assertSee('100');
        $this->get(route('parent.dashboard', ['child' => $link->id + 100]))->assertNotFound();
        $stranger = ParentAccount::factory()->create();
        $this->withSession(['parent_id' => $stranger->id])->get(route('parent.dashboard', ['child' => $link->id]))->assertNotFound();
        $this->withSession(['parent_id' => $parent->id]);
        if ($preview = getenv('COMMUNITY_PREVIEW')) {
            file_put_contents($preview.'/parent-dashboard.html', $page->getContent());
        }
        $link->update(['status' => 'revoked']);
        $this->get(route('parent.dashboard'))->assertDontSee('Learner Child');
        $link->update(['status' => 'approved']);
        $room->update(['archived_at' => now()]);
        $this->get(route('parent.dashboard'))->assertDontSee('Learner Child');
    }

    public function test_class_transfer_and_password_change_invalidate_access(): void
    {
        [$teacher, $room, $student, $parent] = $this->family();
        ParentStudentLink::create(['parent_account_id' => $parent->id, 'student_reference' => $student->student_id, 'student_id' => $student->id, 'approved_classroom_id' => $room->id, 'relationship' => 'Guardian', 'status' => 'approved']);
        $newRoom = Classroom::create(['teacher_account_id' => $teacher->id, 'name' => 'C++ new', 'grade' => 'Grade 9', 'section' => 'DB', 'join_code' => 'FAM009']);
        $student->update(['classroom_id' => $newRoom->id]);
        $this->withSession(['parent_id' => $parent->id, 'parent_session_version' => 1])->get(route('parent.dashboard'))->assertOk()->assertDontSee('Learner Child');
        $this->put(route('parent.password'), ['current_password' => 'incorrect', 'password' => 'ChangedPassword123!', 'password_confirmation' => 'ChangedPassword123!'])->assertSessionHasErrors('current_password');
        $this->put(route('parent.password'), ['current_password' => 'ParentPassword123!', 'password' => 'ChangedPassword123!', 'password_confirmation' => 'ChangedPassword123!'])->assertRedirect();
        $this->withSession(['parent_session_version' => 1])->get(route('parent.dashboard'))->assertForbidden();
    }

    public function test_other_school_details_are_saved_without_bypassing_class_approval(): void
    {
        [$teacher, $room] = $this->family();
        $room->update(['section' => config('student_registration.external_section')]);
        $this->postJson(route('student.register.verify'), ['school_origin' => 'other', 'class_code' => $room->join_code, 'grade_level' => 'Grade 8', 'section' => $room->section])->assertOk();
        $this->postJson(route('student.register.store'), ['school_origin' => 'other', 'classroom_id' => $room->id, 'first_name' => 'Guest', 'surname' => 'Learner', 'grade_level' => 'Grade 8', 'school_name' => 'Another High School', 'ict_teacher_name' => 'Sample ICT Teacher', 'ict_teacher_contact' => 'teacher@example.test', 'nickname' => 'OtherSchoolCoder', 'student_id' => 'EXTERNAL1', 'password' => 'password123', 'password_confirmation' => 'password123'])->assertSuccessful();
        $this->assertDatabaseHas('students', ['student_id' => 'EXTERNAL1', 'school_name' => 'Another High School', 'ict_teacher_name' => 'Sample ICT Teacher', 'registration_status' => 'pending', 'xp' => 0]);
        $this->withSession(['student_id' => null, 'parent_id' => null, 'teacher_id' => $teacher->id])->get(route('teacher.registrations'))->assertOk()->assertSee('Another High School')->assertSee('Sample ICT Teacher');
    }
}
