<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Student;
use App\Models\TeacherAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class StudentEnrollmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_enrollment_saves_names_and_verified_grade_and_section(): void
    {
        $teacher = TeacherAccount::create(['first_name' => 'Teacher', 'last_name' => 'Test', 'school' => 'School', 'email' => 'enroll@example.test', 'password' => 'password123']);
        $rooms = collect(['AM', 'BP', 'DB'])->map(fn ($section) => Classroom::create(['teacher_account_id' => $teacher->id, 'name' => 'C++', 'grade' => 'Grade 8', 'section' => $section, 'join_code' => 'ENROLL'.$section]));
        $page = $this->get(route('student.register'))->assertOk();
        $page->assertSee('First name')->assertSee('Middle name (optional)')->assertSee('Surname')->assertSee('Grade 8')
            ->assertViewHas('gradeLevels', fn ($grades) => $grades->all() === ['Grade 8']);
        $page->assertDontSee('ENROLLAM')->assertDontSee('ENROLLBP');
        $this->postJson(route('student.register.verify'), ['classroom_id' => $rooms[1]->id, 'grade_level' => 'Grade 8', 'class_code' => 'ENROLLAM'])->assertUnprocessable();
        $this->postJson(route('student.register.verify'), ['classroom_id' => $rooms[0]->id, 'grade_level' => 'Grade 9', 'class_code' => 'ENROLLAM'])->assertUnprocessable();
        $this->postJson(route('student.register.verify'), ['classroom_id' => $rooms[1]->id, 'grade_level' => 'Grade 8', 'class_code' => ' enrollbp '])->assertOk()->assertJsonPath('classroom.section', 'BP');
        $payload = ['first_name' => 'Maria', 'middle_name' => 'Luisa', 'surname' => 'Santos', 'grade_level' => 'Grade 8', 'classroom_id' => $rooms[1]->id, 'nickname' => 'CodeMaria', 'student_id' => ' maria123 ', 'password' => 'password123', 'password_confirmation' => 'password123'];
        $this->postJson(route('student.register.store'), $payload)->assertOk()->assertJsonPath('welcome_xp', 100)->assertJsonPath('xp', 0)->assertJsonPath('status', 'pending');
        $this->assertDatabaseHas('students', ['student_id' => 'MARIA123', 'first_name' => 'Maria', 'middle_name' => 'Luisa', 'surname' => 'Santos', 'grade_level' => 'Grade 8', 'section' => 'BP', 'classroom_id' => $rooms[1]->id]);
        $student = Student::withUnapproved()->firstOrFail();
        $this->assertTrue(Hash::check('password123', $student->password));
        $this->assertSame(0, $student->xp);
        $this->assertSame(1, $student->level);
        $this->get(route('student.leaderboard'))->assertRedirect();
        $this->postJson(route('student.register.verify'), ['classroom_id' => $rooms[1]->id, 'grade_level' => 'Grade 8', 'class_code' => 'ENROLLBP'])->assertOk();
        $this->postJson(route('student.register.store'), $payload)->assertUnprocessable()->assertJsonValidationErrors('student_id');
        $this->assertDatabaseCount('students', 1);
        $this->withSession(['teacher_id' => $teacher->id])->postJson(route('teacher.registrations.review', $student->id), ['decision' => 'approved', 'revision' => 1])->assertOk();
        $this->postJson(route('student.login.store'), ['student_id' => 'MARIA123', 'password' => 'password123'])->assertOk();
        $this->get(route('student.leaderboard'))->assertOk()->assertSee('CodeMaria')->assertSee('100 XP');
        $this->assertSame(100, $student->fresh()->xp);
    }

    public function test_registration_rejects_changed_section_and_invalid_verification_clears_access(): void
    {
        $teacher = TeacherAccount::create(['first_name' => 'Teacher', 'last_name' => 'Test', 'school' => 'School', 'email' => 'enroll2@example.test', 'password' => 'password123']);
        $room = Classroom::create(['teacher_account_id' => $teacher->id, 'name' => 'C++', 'grade' => 'Grade 8', 'section' => 'AM', 'join_code' => 'CODE123']);
        $payload = ['first_name' => 'Alex', 'surname' => 'Santos', 'grade_level' => 'Grade 8', 'classroom_id' => $room->id, 'nickname' => 'CadetAlex', 'student_id' => 'A123', 'password' => 'password123', 'password_confirmation' => 'password123'];
        $this->postJson(route('student.register.store'), $payload)->assertUnprocessable();
        $verification = ['classroom_id' => $room->id, 'grade_level' => 'Grade 8', 'class_code' => 'CODE123'];
        $this->postJson(route('student.register.verify'), $verification)->assertOk();
        $payload['classroom_id'] = 999;
        $this->postJson(route('student.register.store'), $payload)->assertUnprocessable();
        $payload['classroom_id'] = $room->id;
        $verification['class_code'] = 'WRONG';
        $this->postJson(route('student.register.verify'), $verification)->assertUnprocessable();
        $this->postJson(route('student.register.store'), $payload)->assertUnprocessable();
        $this->assertDatabaseCount('students', 0);
        $verification['class_code'] = 'CODE123';
        $this->postJson(route('student.register.verify'), $verification)->assertOk();
        $this->postJson(route('student.register.store'), $payload)->assertOk();
        $this->assertDatabaseHas('students', ['student_id' => 'A123', 'middle_name' => null, 'surname' => 'Santos']);
    }

    public function test_registration_keeps_grade_eight_sections_and_includes_other_open_grades(): void
    {
        $teacher = TeacherAccount::create(['first_name' => 'Teacher', 'last_name' => 'Test', 'school' => 'School', 'email' => 'sections@example.test', 'password' => 'password123']);
        $defaults = ['teacher_account_id' => $teacher->id, 'name' => 'C++', 'grade' => 'Grade 8'];
        $allowed = collect(['AM', 'BP', 'DB'])->map(fn ($section) => Classroom::create([...$defaults, 'section' => $section, 'join_code' => 'ALLOW'.$section]));
        $ninth = Classroom::create([...$defaults, 'grade' => 'Grade 9', 'section' => 'AHB', 'join_code' => 'NINTHAHB']);
        $excluded = collect([
            ['section' => null, 'period' => 'Period 3', 'join_code' => 'LEGACY3'],
            ['section' => 'OTHER', 'join_code' => 'OTHER8'],
            ['section' => 'DB', 'enrollment_open' => false, 'join_code' => 'PAUSEDDB'],
        ])->map(fn ($attributes) => Classroom::create([...$defaults, ...$attributes]));

        $this->get(route('student.register'))->assertOk()
            ->assertViewHas('enrollmentOptions', fn ($options) => $options->all() === [
                ['grade' => 'Grade 8', 'section' => 'AM'], ['grade' => 'Grade 8', 'section' => 'BP'], ['grade' => 'Grade 8', 'section' => 'DB'], ['grade' => 'Grade 9', 'section' => 'AHB'],
            ])
            ->assertDontSee('Period 3')->assertDontSee('OTHER')->assertSee('Grade 9');

        foreach ($excluded as $room) {
            $this->postJson(route('student.register.verify'), ['classroom_id' => $room->id, 'grade_level' => $room->grade, 'class_code' => $room->join_code])
                ->assertUnprocessable()->assertSessionMissing('student_class_verified');
        }
        foreach ($allowed as $room) {
            $this->postJson(route('student.register.verify'), ['classroom_id' => $room->id, 'grade_level' => 'Grade 8', 'class_code' => $room->join_code])
                ->assertOk()->assertJsonPath('classroom.section', $room->section);
        }
        $this->postJson(route('student.register.verify'), ['section' => 'AHB', 'grade_level' => 'Grade 9', 'class_code' => $ninth->join_code])->assertOk()->assertJsonPath('classroom.id', $ninth->id);
        $this->assertDatabaseCount('classrooms', 7);
        $this->assertDatabaseCount('students', 0);
    }

    public function test_registration_rechecks_grade_and_section_after_class_code_verification(): void
    {
        $teacher = TeacherAccount::create(['first_name' => 'Teacher', 'last_name' => 'Test', 'school' => 'School', 'email' => 'changed-section@example.test', 'password' => 'password123']);
        $room = Classroom::create(['teacher_account_id' => $teacher->id, 'name' => 'C++', 'grade' => 'Grade 8', 'section' => 'AM', 'join_code' => 'CODEAM']);
        $verification = ['classroom_id' => $room->id, 'grade_level' => 'Grade 8', 'class_code' => $room->join_code];
        $registration = ['first_name' => 'New', 'surname' => 'Student', 'grade_level' => 'Grade 8', 'classroom_id' => $room->id, 'nickname' => 'NewCoder', 'student_id' => 'NEWAM', 'password' => 'password123', 'password_confirmation' => 'password123'];

        foreach ([['section' => null, 'period' => 'Period 3'], ['section' => 'OTHER'], ['grade' => 'Grade 9']] as $change) {
            $room->update(['grade' => 'Grade 8', 'section' => 'AM']);
            $this->postJson(route('student.register.verify'), $verification)->assertOk();
            $room->update($change);
            $this->postJson(route('student.register.store'), $registration)->assertUnprocessable()->assertJsonValidationErrors('class_code');
            $this->assertDatabaseCount('students', 0);
        }
    }

    public function test_registration_catalog_deduplicates_sections_and_refreshes_from_active_classes(): void
    {
        $teacher = TeacherAccount::create(['first_name' => 'One', 'last_name' => 'Teacher', 'email' => 'catalog-one@example.test', 'school' => 'School', 'password' => 'password123']);
        $other = TeacherAccount::create(['first_name' => 'Two', 'last_name' => 'Teacher', 'email' => 'catalog-two@example.test', 'school' => 'School', 'password' => 'password123']);
        $expected = [];
        foreach (['Grade 8' => ['AM', 'BP', 'DB'], 'Grade 9' => ['AHB', 'LM']] as $grade => $sections) {
            foreach ($sections as $section) {
                foreach ([$teacher, $other] as $owner) {
                    Classroom::create(['teacher_account_id' => $owner->id, 'name' => 'C++ '.$owner->id, 'grade' => $grade, 'section' => $section, 'join_code' => 'SECRET'.$owner->id.str_replace(' ', '', $grade).$section]);
                }
                $expected[] = compact('grade', 'section');
            }
        }
        foreach ([['grade' => 'Grade 7', 'section' => 'ARCHIVED', 'archived_at' => now()], ['grade' => 'Grade 10', 'section' => 'CLOSED', 'enrollment_open' => false], ['grade' => 'Grade 9', 'section' => ''], ['grade' => 'Grade 8', 'section' => 'Period 3']] as $index => $attributes) {
            Classroom::create(['teacher_account_id' => $teacher->id, 'name' => 'Unavailable', 'join_code' => 'UNAVAILABLE'.$index, ...$attributes]);
        }
        $this->get(route('student.register'))->assertOk()->assertViewHas('gradeLevels', fn ($grades) => $grades->all() === ['Grade 8', 'Grade 9'])
            ->assertViewHas('enrollmentOptions', fn ($options) => $options->all() === $expected)->assertDontSee('SECRET')->assertDontSee('UNAVAILABLE')->assertDontSee('catalog-one@example.test');
        $this->getJson(route('student.register.options'))->assertOk()->assertExactJson(['enrollment_options' => $expected])->assertHeader('Cache-Control', 'no-store, private');
        $newRoom = Classroom::create(['teacher_account_id' => $teacher->id, 'name' => 'New Grade 9 class', 'grade' => 'Grade 9', 'section' => 'MC', 'join_code' => 'FRESHMC']);
        $expected[] = ['grade' => 'Grade 9', 'section' => 'MC'];
        $this->getJson(route('student.register.options'))->assertOk()->assertExactJson(['enrollment_options' => $expected]);
        $page = $this->get(route('student.register'))->assertOk()->assertViewHas('enrollmentOptions', fn ($options) => $options->all() === $expected);
        if (getenv('ENROLLMENT_PREVIEW')) {
            file_put_contents(base_path('.codex/student-enrollment-preview.html'), $page->getContent());
        }
        $newRoom->update(['enrollment_open' => false]);
        array_pop($expected);
        $this->getJson(route('student.register.options'))->assertExactJson(['enrollment_options' => $expected]);
    }

    public function test_section_selection_and_code_identify_the_correct_teacher_for_grade_nine_registration(): void
    {
        $teachers = collect(['first', 'second'])->map(fn ($name) => TeacherAccount::create(['first_name' => $name, 'last_name' => 'Teacher', 'email' => $name.'-ninth@example.test', 'school' => 'School', 'password' => 'password123']));
        $rooms = $teachers->map(fn ($teacher) => Classroom::create(['teacher_account_id' => $teacher->id, 'name' => 'C++', 'grade' => 'Grade 9', 'section' => 'AHB', 'join_code' => 'NINTH'.$teacher->id]));
        foreach ([['grade_level' => 'Grade 8', 'section' => 'AHB'], ['grade_level' => 'Grade 9', 'section' => 'LM'], ['grade_level' => 'Grade 9', 'section' => 'AHB', 'classroom_id' => $rooms[0]->id]] as $mismatch) {
            $this->postJson(route('student.register.verify'), [...$mismatch, 'class_code' => $rooms[1]->join_code])->assertUnprocessable()->assertSessionMissing('student_class_verified');
        }
        $this->postJson(route('student.register.verify'), ['grade_level' => 'Grade 9', 'section' => 'AHB', 'class_code' => $rooms[1]->join_code])->assertOk()->assertJsonPath('classroom.id', $rooms[1]->id);
        $this->postJson(route('student.register.store'), ['first_name' => 'Ninth', 'surname' => 'Learner', 'grade_level' => 'Grade 9', 'classroom_id' => $rooms[1]->id, 'nickname' => 'NinthCoder', 'student_id' => 'NINTH001', 'password' => 'password123', 'password_confirmation' => 'password123'])->assertOk()->assertJsonPath('status', 'pending')->assertJsonPath('xp', 0);
        $student = Student::withUnapproved()->sole();
        $this->assertSame($rooms[1]->id, $student->classroom_id);
        $this->assertSame('Grade 9', $student->grade_level);
        $this->assertSame('AHB', $student->section);
        $this->assertDatabaseHas('challenge_notices', ['teacher_account_id' => $teachers[1]->id, 'target_type' => 'student_registration', 'target_id' => $student->id]);
        $this->assertDatabaseMissing('challenge_notices', ['teacher_account_id' => $teachers[0]->id, 'target_type' => 'student_registration', 'target_id' => $student->id]);
        $this->postJson(route('student.login.store'), ['student_id' => $student->student_id, 'password' => 'password123'])->assertOk()->assertJsonPath('status', 'pending')->assertSessionMissing('student_id');
    }

    public function test_registration_rejects_a_new_valid_section_until_its_code_is_verified_again(): void
    {
        $teacher = TeacherAccount::create(['first_name' => 'Class', 'last_name' => 'Teacher', 'email' => 'class-change@example.test', 'school' => 'School', 'password' => 'password123']);
        $room = Classroom::create(['teacher_account_id' => $teacher->id, 'name' => 'C++', 'grade' => 'Grade 9', 'section' => 'AHB', 'join_code' => 'BINDNINTH']);
        $this->postJson(route('student.register.verify'), ['grade_level' => 'Grade 9', 'section' => 'AHB', 'class_code' => $room->join_code])->assertOk();
        $room->update(['section' => 'LM']);
        $registration = ['first_name' => 'New', 'surname' => 'Coder', 'grade_level' => 'Grade 9', 'classroom_id' => $room->id, 'nickname' => 'NewNinthCoder', 'student_id' => 'NEWNINTH', 'password' => 'password123', 'password_confirmation' => 'password123'];
        $this->postJson(route('student.register.store'), $registration)->assertUnprocessable()->assertJsonValidationErrors('class_code');
        $this->assertDatabaseCount('students', 0);
        $this->postJson(route('student.register.verify'), ['grade_level' => 'Grade 9', 'section' => 'LM', 'class_code' => $room->join_code])->assertOk();
        $this->postJson(route('student.register.store'), $registration)->assertOk();
        $this->assertDatabaseHas('students', ['grade_level' => 'Grade 9', 'section' => 'LM']);
    }
}
