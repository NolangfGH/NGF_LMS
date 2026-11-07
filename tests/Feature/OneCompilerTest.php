<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Student;
use App\Models\TeacherAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OneCompilerTest extends TestCase
{
    use RefreshDatabase;

    public function test_external_compiler_is_opt_in_and_available_in_student_and_teacher_editors(): void
    {
        Http::preventStrayRequests();
        config(['compiler.url' => null, 'compiler.token' => null]);
        $teacher = TeacherAccount::create(['first_name' => 'Compiler', 'last_name' => 'Teacher', 'email' => 'compiler@example.test', 'school' => 'School', 'password' => 'password123']);
        $room = Classroom::create(['teacher_account_id' => $teacher->id, 'name' => 'C++', 'grade' => 'Grade 8', 'section' => 'AM', 'join_code' => 'COMPAM']);
        $student = Student::create(['classroom_id' => $room->id, 'first_name' => 'Practice', 'nickname' => 'PracticeCoder', 'student_id' => 'COMP1', 'password' => 'password123', 'xp' => 100]);
        $this->withSession(['student_id' => $student->id, 'student_class_verified' => true]);
        $page = $this->get(route('student.code-lab', ['activity' => 'hello']))->assertOk()
            ->assertSee('data-onecompiler-editor="codeEditor"', false)
            ->assertSee('js/onecompiler.js', false)
            ->assertSee('The school compiler is not connected yet.')
            ->assertSee('Open in OneCompiler')
            ->assertDontSee('src="https://onecompiler.com', false);
        if ($preview = getenv('PORTAL_COMPILER_PREVIEW')) {
            file_put_contents($preview.'/code-lab.html', $page->getContent());
        }
        $this->withSession(['teacher_id' => $teacher->id, 'student_id' => null]);
        foreach ([route('teacher.assignments') => 'assignment-starter-editor', route('teacher.content.edit', 'introduction') => 'lesson-example-editor'] as $url => $editor) {
            $this->get($url)->assertOk()->assertSee('data-onecompiler-editor="'.$editor.'"', false)->assertSee('id="'.$editor.'"', false);
        }
        $this->assertSame(100, $student->fresh()->xp);
        $this->assertDatabaseCount('code_runs', 0);
        Http::assertNothingSent();
    }

    public function test_signed_out_visitors_still_cannot_open_the_student_editor(): void
    {
        $this->get(route('student.code-lab'))->assertRedirect(route('student.login'));
    }
}
