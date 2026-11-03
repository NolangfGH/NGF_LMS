<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Student;
use App\Models\TeacherAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeacherClassesTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $teacher = TeacherAccount::create(['first_name' => 'Class', 'last_name' => 'Teacher', 'email' => 'classes@example.test', 'school' => 'School', 'password' => 'Password123']);
        $other = TeacherAccount::create(['first_name' => 'Private', 'last_name' => 'Teacher', 'email' => 'other-classes@example.test', 'school' => 'School', 'password' => 'Password123']);
        $room = Classroom::create(['teacher_account_id' => $other->id, 'name' => 'Private subject', 'grade' => 'Grade 8', 'section' => 'DB', 'join_code' => 'PRIVATECODE']);
        $this->withSession(['teacher_id' => $teacher->id]);

        return compact('teacher', 'other', 'room');
    }

    private function payload(array $sections = ['AM', 'BP']): array
    {
        return ['name' => 'C++ Programming', 'grade' => 'Grade 8', 'sections' => $sections, 'period' => '', 'enrollment_open' => 1];
    }

    public function test_registration_does_not_need_or_create_a_classroom_even_with_old_form_fields(): void
    {
        $this->get(route('teacher.register'))->assertOk()->assertSee('Register once. Manage all your classes.')->assertDontSee('name="class_name"', false)->assertDontSee('name="section"', false);
        $data = ['first_name' => 'New', 'last_name' => 'Teacher', 'school' => 'School', 'email' => 'new-class-teacher@example.test', 'password' => 'Password123', 'password_confirmation' => 'Password123', 'requested_role' => 'teacher'];
        $this->postJson(route('teacher.register.store'), [...$data, 'class_name' => 'Must not create', 'grade' => 'Grade 8', 'section' => 'DB', 'class_code' => 'UNUSED'])->assertOk()->assertJsonPath('redirect', route('teacher.classes'));
        $this->assertDatabaseCount('teacher_accounts', 1);
        $this->assertDatabaseCount('classrooms', 0);
        $this->get(route('teacher.classes'))->assertOk()->assertSee('Create your first class');
        $this->get(route('teacher.dashboard'))->assertOk();
    }

    public function test_one_teacher_can_add_three_sections_and_they_immediately_appear_in_learning_and_enrollment(): void
    {
        extract($this->fixture());
        $this->post(route('teacher.classes.store'), [...$this->payload(['AM', 'BP', 'DB']), 'teacher_account_id' => $other->id])->assertRedirect(route('teacher.classes'))->assertSessionHasNoErrors();
        $rooms = $teacher->classrooms()->orderBy('section')->get();
        $this->assertCount(3, $rooms);
        $this->assertCount(3, $rooms->pluck('join_code')->unique());
        $this->assertDatabaseCount('teacher_accounts', 2);
        foreach ($rooms as $class) {
            $this->assertSame($teacher->id, $class->teacher_account_id);
            $this->assertMatchesRegularExpression('/^[A-Z0-9]{8}$/', $class->join_code);
            $this->postJson(route('student.register.verify'), ['classroom_id' => $class->id, 'grade_level' => 'Grade 8', 'class_code' => $class->join_code])->assertOk();
        }
        $this->get(route('teacher.lessons'))->assertOk()->assertViewHas('rooms', fn ($rooms) => $rooms->count() === 3);
        $page = $this->get(route('teacher.classes'))->assertOk()->assertSee('Already added')->assertDontSee('PRIVATECODE')->assertDontSee('Private subject');
        if (getenv('CLASSES_PREVIEW')) {
            file_put_contents(base_path('.codex/classes-preview.html'), $page->getContent());
        }
    }

    public function test_duplicates_and_unsupported_selections_are_rejected_without_partial_creation(): void
    {
        extract($this->fixture());
        $this->post(route('teacher.classes.store'), $this->payload(['AM']))->assertRedirect();
        if (getenv('CLASSES_PREVIEW')) {
            file_put_contents(base_path('.codex/classes-preview.html'), $this->get(route('teacher.classes'))->assertOk()->getContent());
        }
        $this->postJson(route('teacher.classes.store'), $this->payload(['AM', 'BP']))->assertUnprocessable()->assertJsonValidationErrors('sections');
        $this->assertSame(1, $teacher->classrooms()->count());
        foreach ([[], ['AM', 'AM'], ['NOT-A-SECTION']] as $sections) {
            $this->postJson(route('teacher.classes.store'), $this->payload($sections))->assertUnprocessable();
        }
        $this->postJson(route('teacher.classes.store'), [...$this->payload(), 'grade' => 'Grade 99'])->assertUnprocessable();
        $this->assertSame(1, $teacher->classrooms()->count());
        $class = $teacher->classrooms()->first();
        $class->update(['archived_at' => now()]);
        $this->postJson(route('teacher.classes.store'), $this->payload(['AM']))->assertUnprocessable()->assertJsonValidationErrors('sections');
        $this->get(route('teacher.classes'))->assertOk()->assertSee('Archived · restore below');
    }

    public function test_class_management_is_private_and_edits_preserve_existing_accounts_and_work(): void
    {
        $this->get(route('teacher.classes'))->assertRedirect(route('teacher.login'));
        $this->postJson(route('teacher.classes.store'), $this->payload())->assertForbidden();
        extract($this->fixture());
        $this->putJson(route('teacher.classes.update', $room), ['name' => 'Hijack', 'grade' => 'Grade 8', 'section' => 'DB', 'enrollment_open' => 1])->assertForbidden();
        $this->post(route('teacher.classes.store'), $this->payload(['AM']))->assertRedirect();
        $class = $teacher->classrooms()->first();
        $code = $class->join_code;
        $student = Student::create(['classroom_id' => $class->id, 'first_name' => 'Alex', 'nickname' => 'ClassCoder', 'student_id' => 'CLASS1', 'password' => 'Password123', 'xp' => 230]);
        $this->put(route('teacher.classes.update', $class), ['name' => 'Updated subject', 'grade' => 'Grade 8', 'section' => 'AM', 'period' => 'Wednesday 9 AM', 'enrollment_open' => 0])->assertRedirect(route('teacher.classes'));
        $this->assertSame($code, $class->fresh()->join_code);
        $this->assertFalse($class->fresh()->enrollment_open);
        $this->assertSame(230, $student->fresh()->xp);
        $teacher->forceFill(['role' => 'principal'])->save();
        $this->get(route('teacher.classes'))->assertForbidden();
        $this->postJson(route('teacher.classes.store'), $this->payload(['BP']))->assertForbidden();
        $this->assertSame('Private subject', $room->fresh()->name);
    }
}
