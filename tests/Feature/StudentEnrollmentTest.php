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
        $teacher = TeacherAccount::create(['first_name'=>'Teacher','last_name'=>'Test','school'=>'School','email'=>'enroll@example.test','password'=>'password123']);
        $rooms = collect(['AM','BP','DB'])->map(fn ($section) => Classroom::create(['teacher_account_id'=>$teacher->id,'name'=>'C++','grade'=>'Grade 8','section'=>$section,'join_code'=>'ENROLL'.$section]));
        $page = $this->get(route('student.register'))->assertOk();
        $page->assertSee('First name')->assertSee('Middle name (optional)')->assertSee('Surname')->assertSee('Grade 8')
            ->assertViewHas('gradeLevels', fn ($grades) => $grades->all() === ['Grade 8']);
        $page->assertDontSee('ENROLLAM')->assertDontSee('ENROLLBP');
        $this->postJson(route('student.register.verify'), ['classroom_id'=>$rooms[1]->id,'grade_level'=>'Grade 8','class_code'=>'ENROLLAM'])->assertUnprocessable();
        $this->postJson(route('student.register.verify'), ['classroom_id'=>$rooms[0]->id,'grade_level'=>'Grade 9','class_code'=>'ENROLLAM'])->assertUnprocessable();
        $this->postJson(route('student.register.verify'), ['classroom_id'=>$rooms[1]->id,'grade_level'=>'Grade 8','class_code'=>' enrollbp '])->assertOk()->assertJsonPath('classroom.section','BP');
        $payload = ['first_name'=>'Maria','middle_name'=>'Luisa','surname'=>'Santos','grade_level'=>'Grade 8','classroom_id'=>$rooms[1]->id,'nickname'=>'CodeMaria','student_id'=>' maria123 ','password'=>'password123','password_confirmation'=>'password123'];
        $this->postJson(route('student.register.store'),$payload)->assertOk()->assertJsonPath('welcome_xp',100)->assertJsonPath('xp',100);
        $this->assertDatabaseHas('students',['student_id'=>'MARIA123','first_name'=>'Maria','middle_name'=>'Luisa','surname'=>'Santos','grade_level'=>'Grade 8','section'=>'BP','classroom_id'=>$rooms[1]->id]);
        $this->assertTrue(Hash::check('password123', Student::first()->password));
        $student = Student::first();
        $this->assertSame(100,$student->xp);
        $this->assertSame(1,$student->level);
        $this->get(route('student.leaderboard'))->assertOk()->assertSee('CodeMaria')->assertSee('100 XP');
        $this->postJson(route('student.register.store'),$payload)->assertUnprocessable()->assertJsonValidationErrors('student_id');
        $this->assertDatabaseCount('students',1);
        $this->get(route('student.leaderboard'))->assertOk();
        $this->postJson(route('student.login.store'),['student_id'=>'MARIA123','password'=>'password123'])->assertOk();
        $this->assertSame(100,$student->fresh()->xp);
    }

    public function test_registration_rejects_changed_section_and_invalid_verification_clears_access(): void
    {
        $teacher = TeacherAccount::create(['first_name'=>'Teacher','last_name'=>'Test','school'=>'School','email'=>'enroll2@example.test','password'=>'password123']);
        $room = Classroom::create(['teacher_account_id'=>$teacher->id,'name'=>'C++','grade'=>'Grade 8','section'=>'AM','join_code'=>'CODE123']);
        $payload = ['first_name'=>'Alex','surname'=>'Santos','grade_level'=>'Grade 8','classroom_id'=>$room->id,'nickname'=>'CadetAlex','student_id'=>'A123','password'=>'password123','password_confirmation'=>'password123'];
        $this->postJson(route('student.register.store'),$payload)->assertUnprocessable();
        $verification = ['classroom_id'=>$room->id,'grade_level'=>'Grade 8','class_code'=>'CODE123'];
        $this->postJson(route('student.register.verify'),$verification)->assertOk();
        $payload['classroom_id'] = 999;
        $this->postJson(route('student.register.store'),$payload)->assertUnprocessable();
        $payload['classroom_id'] = $room->id;
        $verification['class_code'] = 'WRONG';
        $this->postJson(route('student.register.verify'),$verification)->assertUnprocessable();
        $this->postJson(route('student.register.store'),$payload)->assertUnprocessable();
        $this->assertDatabaseCount('students',0);
        $verification['class_code'] = 'CODE123';
        $this->postJson(route('student.register.verify'),$verification)->assertOk();
        $this->postJson(route('student.register.store'),$payload)->assertOk();
        $this->assertDatabaseHas('students',['student_id'=>'A123','middle_name'=>null,'surname'=>'Santos']);
    }

    public function test_registration_only_lists_and_verifies_open_grade_eight_am_bp_db_sections(): void
    {
        $teacher = TeacherAccount::create(['first_name'=>'Teacher','last_name'=>'Test','school'=>'School','email'=>'sections@example.test','password'=>'password123']);
        $defaults = ['teacher_account_id'=>$teacher->id,'name'=>'C++','grade'=>'Grade 8'];
        $allowed = collect(['AM','BP','DB'])->map(fn ($section) => Classroom::create([...$defaults,'section'=>$section,'join_code'=>'ALLOW'.$section]));
        $excluded = collect([
            ['section'=>null,'period'=>'Period 3','join_code'=>'LEGACY3'],
            ['section'=>'OTHER','join_code'=>'OTHER8'],
            ['grade'=>'Grade 9','section'=>'AM','join_code'=>'NINTHAM'],
            ['section'=>'DB','enrollment_open'=>false,'join_code'=>'PAUSEDDB'],
        ])->map(fn ($attributes) => Classroom::create([...$defaults,...$attributes]));

        $this->get(route('student.register'))->assertOk()
            ->assertViewHas('enrollmentOptions', fn ($options) => $options->pluck('id')->all() === $allowed->pluck('id')->all()
                && $options->pluck('section')->all() === ['AM','BP','DB']
                && $options->every(fn ($room) => array_keys($room->getAttributes()) === ['id','grade','section']))
            ->assertDontSee('Period 3')->assertDontSee('OTHER')->assertDontSee('Grade 9');

        foreach ($excluded as $room) {
            $this->postJson(route('student.register.verify'), ['classroom_id'=>$room->id,'grade_level'=>$room->grade,'class_code'=>$room->join_code])
                ->assertUnprocessable()->assertSessionMissing('student_class_verified');
        }
        foreach ($allowed as $room) {
            $this->postJson(route('student.register.verify'), ['classroom_id'=>$room->id,'grade_level'=>'Grade 8','class_code'=>$room->join_code])
                ->assertOk()->assertJsonPath('classroom.section',$room->section);
        }
        $this->assertDatabaseCount('classrooms',7);
        $this->assertDatabaseCount('students',0);
    }

    public function test_registration_rechecks_grade_and_section_after_class_code_verification(): void
    {
        $teacher = TeacherAccount::create(['first_name'=>'Teacher','last_name'=>'Test','school'=>'School','email'=>'changed-section@example.test','password'=>'password123']);
        $room = Classroom::create(['teacher_account_id'=>$teacher->id,'name'=>'C++','grade'=>'Grade 8','section'=>'AM','join_code'=>'CODEAM']);
        $verification = ['classroom_id'=>$room->id,'grade_level'=>'Grade 8','class_code'=>$room->join_code];
        $registration = ['first_name'=>'New','surname'=>'Student','grade_level'=>'Grade 8','classroom_id'=>$room->id,'nickname'=>'NewCoder','student_id'=>'NEWAM','password'=>'password123','password_confirmation'=>'password123'];

        foreach ([['section'=>null,'period'=>'Period 3'], ['section'=>'OTHER'], ['grade'=>'Grade 9']] as $change) {
            $room->update(['grade'=>'Grade 8','section'=>'AM']);
            $this->postJson(route('student.register.verify'),$verification)->assertOk();
            $room->update($change);
            $this->postJson(route('student.register.store'),$registration)->assertUnprocessable()->assertJsonValidationErrors('class_code');
            $this->assertDatabaseCount('students',0);
        }
    }
}
