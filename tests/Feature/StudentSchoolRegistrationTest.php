<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Student;
use App\Models\TeacherAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentSchoolRegistrationTest extends TestCase
{
    use RefreshDatabase;

    private function classroom(string $grade = 'Grade 8', bool $external = true): Classroom
    {
        $teacher = TeacherAccount::create(['first_name' => 'Class', 'last_name' => 'Teacher', 'school' => 'RSHS MIMAROPA', 'email' => fake()->unique()->safeEmail(), 'password' => 'Password123']);

        return Classroom::create(['teacher_account_id' => $teacher->id, 'name' => 'C++ Programming', 'grade' => $grade, 'section' => $external ? config('student_registration.external_section') : 'AM', 'join_code' => 'SCHOOL'.$teacher->id]);
    }

    private function verification(Classroom $room): array
    {
        return ['school_origin' => $room->section === config('student_registration.external_section') ? 'other' : 'rshs', 'grade_level' => $room->grade, 'section' => $room->section, 'class_code' => $room->join_code];
    }

    private function registration(Classroom $room): array
    {
        return [...$this->verification($room), 'classroom_id' => $room->id, 'first_name' => 'New', 'surname' => 'Learner', 'school_name' => 'Another High School', 'ict_teacher_name' => 'Guest ICT Teacher', 'ict_teacher_contact' => 'teacher@another-school.edu.ph', 'nickname' => 'GuestCoder'.$room->id, 'student_id' => 'GUEST'.$room->id, 'password' => 'Password123', 'password_confirmation' => 'Password123'];
    }

    public function test_school_choices_and_external_grades_do_not_disclose_join_codes_or_contacts(): void
    {
        $room = $this->classroom('Grade 12');
        $this->classroom('Grade 8', false);
        $page = $this->get(route('student.register'))->assertOk()->assertSee('From what school')->assertSee('RSHS MIMAROPA')->assertSee('Other School')->assertSee('Contact Number or Email of ICT Teacher')->assertSee('Grade 7')->assertSee('Grade 12')->assertViewHas('gradeLevels', fn ($grades) => $grades->all() === ['Grade 8'])->assertDontSee($room->join_code)->assertDontSee($room->teacher->email);
        $this->getJson(route('student.register.options'))->assertOk()->assertJsonFragment(['grade' => 'Grade 12', 'section' => config('student_registration.external_section')])->assertDontSee($room->join_code)->assertDontSee($room->teacher->email);
        if (getenv('REGISTRATION_SCHOOL_PREVIEW')) {
            file_put_contents(storage_path('framework/testing/school-registration-preview.html'), $page->getContent());
        }
    }

    public function test_other_school_students_in_all_six_grades_are_pending_until_the_class_owner_approves(): void
    {
        foreach (config('student_registration.external_grades') as $grade) {
            $room = $this->classroom($grade);
            $this->postJson(route('student.register.verify'), $this->verification($room))->assertOk();
            $this->postJson(route('student.register.store'), [...$this->registration($room), 'xp' => 9999, 'registration_status' => 'approved'])->assertOk()->assertJsonPath('status', 'pending')->assertSessionMissing('student_id');
            $student = Student::withUnapproved()->where('classroom_id', $room->id)->sole();
            $this->assertSame('other', $student->school_origin);
            $this->assertSame($grade, $student->grade_level);
            $this->assertSame(config('student_registration.external_section'), $student->section);
            $this->assertSame('Another High School', $student->school_name);
            $this->assertSame('teacher@another-school.edu.ph', $student->ict_teacher_contact);
            $this->assertSame(0, $student->xp);
        }
        $this->assertSame(0, Student::count());
        $student = Student::withUnapproved()->firstOrFail();
        $this->postJson(route('student.login.store'), ['student_id' => $student->student_id, 'password' => 'Password123'])->assertOk()->assertJsonPath('status', 'pending')->assertSessionMissing('student_id');
        $this->withSession(['teacher_id' => $student->classroom->teacher_account_id])->postJson(route('teacher.registrations.review', $student->id), ['decision' => 'approved', 'revision' => 1])->assertOk();
        $this->postJson(route('student.login.store'), ['student_id' => $student->student_id, 'password' => 'Password123'])->assertOk()->assertJsonPath('redirect', route('student.dashboard'));
        $this->assertSame(100, $student->fresh()->xp);
    }

    public function test_rshs_disabled_fields_are_derived_on_the_server_and_ignore_forged_school_details(): void
    {
        $room = $this->classroom('Grade 8', false);
        $this->postJson(route('student.register.verify'), $this->verification($room))->assertOk();
        $this->postJson(route('student.register.store'), $this->registration($room))->assertOk();
        $this->assertDatabaseHas('students', ['classroom_id' => $room->id, 'school_origin' => 'rshs', 'school_name' => 'RSHS MIMAROPA', 'ict_teacher_name' => 'Class Teacher', 'ict_teacher_contact' => null, 'section' => 'AM']);
    }

    public function test_required_external_school_details_and_contact_format_are_validated(): void
    {
        $room = $this->classroom();
        $this->postJson(route('student.register.verify'), $this->verification($room))->assertOk();
        foreach (['school_name', 'ict_teacher_name', 'ict_teacher_contact'] as $field) {
            $this->postJson(route('student.register.store'), [...$this->registration($room), $field => ''])->assertUnprocessable()->assertJsonValidationErrors($field);
        }
        foreach (['not a phone', 'a@', '1234', str_repeat('1', 16), ['bad']] as $contact) {
            $this->postJson(route('student.register.store'), [...$this->registration($room), 'ict_teacher_contact' => $contact])->assertUnprocessable()->assertJsonValidationErrors('ict_teacher_contact');
        }
        $this->assertDatabaseCount('students', 0);
        $this->postJson(route('student.register.store'), [...$this->registration($room), 'ict_teacher_contact' => '+63 (917) 123-4567'])->assertOk();
        $this->assertDatabaseHas('students', ['ict_teacher_contact' => '+63 (917) 123-4567']);
    }

    public function test_school_type_grade_and_section_must_match_the_verified_code(): void
    {
        $external = $this->classroom();
        $internal = $this->classroom('Grade 8', false);
        foreach ([['school_origin' => 'rshs'], ['grade_level' => 'Grade 9'], ['section' => 'AM'], ['class_code' => $internal->join_code], ['school_origin' => 'invalid']] as $mismatch) {
            $this->postJson(route('student.register.verify'), [...$this->verification($external), ...$mismatch])->assertUnprocessable()->assertSessionMissing('student_class_verified');
        }
        $this->postJson(route('student.register.verify'), $this->verification($external))->assertOk();
        $this->postJson(route('student.register.store'), [...$this->registration($external), 'school_origin' => 'rshs'])->assertUnprocessable()->assertJsonValidationErrors('class_code');
        $this->postJson(route('student.register.store'), [...$this->registration($external), 'classroom_id' => $internal->id])->assertUnprocessable()->assertJsonValidationErrors('class_code');
        $this->assertDatabaseCount('students', 0);
    }

    public function test_external_registration_cannot_use_a_closed_archived_or_rotated_class(): void
    {
        $room = $this->classroom('Grade 11');
        foreach ([['enrollment_open' => false], ['archived_at' => now()]] as $closed) {
            $room->update(['enrollment_open' => true, 'archived_at' => null]);
            $this->postJson(route('student.register.verify'), $this->verification($room))->assertOk();
            $room->update($closed);
            $this->postJson(route('student.register.store'), $this->registration($room))->assertUnprocessable();
            $this->postJson(route('student.register.verify'), $this->verification($room))->assertUnprocessable();
        }
        $room->update(['enrollment_open' => true, 'archived_at' => null]);
        $this->postJson(route('student.register.verify'), $this->verification($room))->assertOk();
        $room->update(['join_code' => 'CHANGED']);
        $this->postJson(route('student.register.store'), $this->registration($room))->assertUnprocessable();
        $this->assertDatabaseCount('students', 0);
    }

    public function test_contact_details_are_visible_only_to_the_responsible_teacher_in_review_and_roster(): void
    {
        $room = $this->classroom();
        $other = $this->classroom();
        $this->postJson(route('student.register.verify'), $this->verification($room))->assertOk();
        $this->postJson(route('student.register.store'), $this->registration($room))->assertOk();
        $student = Student::withUnapproved()->sole();
        $this->assertArrayNotHasKey('ict_teacher_contact', $student->toArray());
        $this->withSession(['teacher_id' => $other->teacher_account_id])->get(route('teacher.registrations'))->assertOk()->assertDontSee('teacher@another-school.edu.ph');
        $this->withSession(['teacher_id' => $room->teacher_account_id])->get(route('teacher.registrations'))->assertOk()->assertSee('teacher@another-school.edu.ph');
        $this->postJson(route('teacher.registrations.review', $student->id), ['decision' => 'approved', 'revision' => 1])->assertOk();
        $this->get(route('teacher.students'))->assertOk()->assertSee('teacher@another-school.edu.ph');
        $this->get(route('teacher.students.show', $student->id))->assertOk()->assertSee('teacher@another-school.edu.ph');
        $this->withSession(['teacher_id' => $other->teacher_account_id])->get(route('teacher.students.show', $student->id))->assertNotFound();
    }

    public function test_teacher_can_create_an_sfos_class_per_grade_without_registering_again(): void
    {
        $teacher = $this->classroom()->teacher;
        $this->withSession(['teacher_id' => $teacher->id])->get(route('teacher.classes'))->assertOk()->assertSee('Welcome students from other schools')->assertSee('Grade 12');
        foreach (['Grade 7', 'Grade 11', 'Grade 12'] as $grade) {
            $payload = ['name' => 'Other-school C++', 'grade' => $grade, 'sections' => [config('student_registration.external_section')], 'enrollment_open' => 1];
            $this->post(route('teacher.classes.store'), $payload)->assertRedirect(route('teacher.classes'))->assertSessionHasNoErrors();
            $room = $teacher->classrooms()->where('grade', $grade)->sole();
            $this->assertTrue($room->acceptsStudentRegistration());
            $this->postJson(route('teacher.classes.store'), $payload)->assertUnprocessable()->assertJsonValidationErrors('sections');
        }
        $this->postJson(route('teacher.classes.store'), ['name' => 'Mixed', 'grade' => 'Grade 8', 'sections' => ['AM', config('student_registration.external_section')], 'enrollment_open' => 1])->assertUnprocessable();
        $teacher->forceFill(['role' => 'principal'])->save();
        $this->postJson(route('teacher.classes.store'), ['name' => 'Not allowed', 'grade' => 'Grade 9', 'sections' => [config('student_registration.external_section')], 'enrollment_open' => 1])->assertForbidden();
    }
}
