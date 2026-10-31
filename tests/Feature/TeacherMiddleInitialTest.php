<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Student;
use App\Models\TeacherAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class TeacherMiddleInitialTest extends TestCase
{
    use RefreshDatabase;

    private function registration(array $changes = []): array
    {
        return array_replace(['first_name' => 'Nolan', 'last_name' => 'Fadriquilan', 'email' => 'middle@example.test', 'school' => 'Our School',
            'password' => 'Password123', 'password_confirmation' => 'Password123', 'class_name' => 'Grade 8 C++', 'grade' => 'Grade 8', 'section' => 'AM', 'period' => 'Period 3'], $changes);
    }

    public function test_registration_saves_a_normalized_initial_and_displays_it_in_account_names(): void
    {
        $this->get(route('teacher.register'))->assertOk()->assertSee('name="middle_initial"', false)->assertSee('Optional. One letter');
        $this->postJson(route('teacher.register.store'), $this->registration(['middle_initial' => 'a.']))->assertOk();
        $teacher = TeacherAccount::firstOrFail();
        $this->assertSame('A', $teacher->middle_initial);
        $this->assertSame('Nolan A. Fadriquilan', $teacher->fullName());
        $this->assertTrue(Hash::check('Password123', $teacher->password));
        $this->get(route('teacher.dashboard'))->assertOk()->assertSee('Nolan A. Fadriquilan');
        $this->get(route('teacher.settings'))->assertOk()->assertSee('Middle initial')->assertSee('value="A"', false)->assertSee('Nolan A. Fadriquilan');
        $this->getJson(route('home.status'))->assertOk()->assertSee('Nolan A. Fadriquilan');
    }

    public function test_settings_save_preserve_and_clear_the_initial_without_changing_passwords_or_display_aliases(): void
    {
        $teacher = TeacherAccount::create(['first_name' => 'Nolan', 'last_name' => 'Fadriquilan', 'email' => 'settings-middle@example.test', 'school' => 'Our School', 'password' => 'Password123']);
        $password = $teacher->password;
        $room = Classroom::create(['teacher_account_id' => $teacher->id, 'name' => 'C++', 'grade' => 'Grade 8', 'section' => 'AM', 'join_code' => 'MIDDLE1']);
        $student = Student::create(['classroom_id' => $room->id, 'first_name' => 'Alex', 'nickname' => 'CodeAlex', 'student_id' => 'MIDDLE001', 'password' => 'Password123']);
        $this->withSession(['teacher_id' => $teacher->id, 'teacher_session_version' => 1]);
        $account = ['first_name' => $teacher->first_name, 'last_name' => $teacher->last_name, 'email' => $teacher->email, 'school' => $teacher->school];
        $this->put(route('teacher.settings.account'), [...$account, 'middle_initial' => ' ñ. '])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('Ñ', $teacher->fresh()->middle_initial);
        $this->get(route('teacher.students'))->assertOk()->assertSee('Nolan Ñ. Fadriquilan');
        $this->withSession(['student_id' => $student->id])->get(route('student.learn'))->assertOk()->assertSee('Nolan Ñ. Fadriquilan');
        $this->put(route('teacher.settings.account'), $account)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('Ñ', $teacher->fresh()->middle_initial);
        $teacher->update(['display_name' => 'Sir Nolan']);
        $this->assertSame('Sir Nolan', $teacher->fresh()->classroomProfile()['name']);
        $this->put(route('teacher.settings.account'), [...$account, 'middle_initial' => ''])->assertRedirect()->assertSessionHasNoErrors();
        $teacher->refresh();
        $this->assertNull($teacher->middle_initial);
        $this->assertSame('Nolan Fadriquilan', $teacher->fullName());
        $this->assertSame($password, $teacher->password);
        $this->assertSame(1, $teacher->session_version);
        $this->assertSame('Sir Nolan', $teacher->classroomProfile()['name']);
    }

    public function test_invalid_initials_are_rejected_by_registration_and_settings(): void
    {
        foreach (['AB', 'A..', '1', '.', ['A']] as $initial) {
            $this->postJson(route('teacher.register.store'), $this->registration(['middle_initial' => $initial]))->assertUnprocessable()->assertJsonValidationErrors('middle_initial');
        }
        $this->assertDatabaseCount('teacher_accounts', 0);
        $teacher = TeacherAccount::create(['first_name' => 'Nolan', 'last_name' => 'Fadriquilan', 'email' => 'invalid-middle@example.test', 'school' => 'Our School', 'password' => 'Password123', 'middle_initial' => 'A']);
        $this->withSession(['teacher_id' => $teacher->id, 'teacher_session_version' => 1]);
        $account = ['first_name' => 'Nolan', 'last_name' => 'Fadriquilan', 'email' => $teacher->email, 'school' => 'Our School'];
        foreach (['AB', 'A..', '1', '.', ['A']] as $initial) {
            $this->putJson(route('teacher.settings.account'), [...$account, 'middle_initial' => $initial])->assertUnprocessable()->assertJsonValidationErrors('middle_initial');
        }
        $this->assertSame('A', $teacher->fresh()->middle_initial);
    }

    public function test_registration_and_existing_names_work_without_a_middle_initial(): void
    {
        $this->postJson(route('teacher.register.store'), $this->registration())->assertOk();
        $teacher = TeacherAccount::firstOrFail();
        $this->assertNull($teacher->middle_initial);
        $this->assertSame('Nolan Fadriquilan', $teacher->fullName());
        $this->get(route('teacher.dashboard'))->assertOk()->assertSee('Nolan Fadriquilan');
    }
}
