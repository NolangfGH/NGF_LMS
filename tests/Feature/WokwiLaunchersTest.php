<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\CodeWorkspace;
use App\Models\Student;
use App\Models\TeacherAccount;
use App\Services\CourseAssessments;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class WokwiLaunchersTest extends TestCase
{
    use RefreshDatabase;

    public function test_arduino_drafts_reviews_and_lesson_editors_offer_wokwi_while_cpp_keeps_onecompiler(): void
    {
        $teacher = TeacherAccount::create(['first_name' => 'Robotics', 'last_name' => 'Teacher', 'school' => 'School', 'email' => 'wokwi@example.test', 'password' => 'Password123']);
        $room = Classroom::create(['teacher_account_id' => $teacher->id, 'name' => 'Robotics', 'grade' => 'Grade 9', 'section' => 'AM', 'join_code' => 'WOKWI9']);
        $student = Student::create(['classroom_id' => $room->id, 'first_name' => 'Builder', 'nickname' => 'Builder', 'student_id' => 'WOKWI001', 'password' => 'Password123']);
        $this->withSession(['student_id' => $student->id, 'student_class_verified' => true]);
        $this->get(route('student.code-lab', ['activity' => 'arduino-blink']))->assertOk()->assertSee('Open in Wokwi')->assertSee('data-wokwi-editor="codeEditor"', false)->assertSee('data-wokwi-return', false)->assertDontSee('data-onecompiler-editor="codeEditor"', false);
        foreach ([CourseAssessments::REASONING, CourseAssessments::ARDUINO_PRE] as $assessment) {
            DB::table('course_assessment_results')->insert(['student_id' => $student->id, 'assessment' => $assessment, 'score' => 0, 'total_questions' => 50, 'skill_scores' => '{}', 'completed_at' => now()]);
        }
        $this->get(route('student.lesson.show', 'arduino-blink'))->assertOk()->assertSee('data-wokwi-editor="lesson-main-example"', false)->assertDontSee('data-wokwi-return', false);
        $workspace = CodeWorkspace::sole();
        $this->putJson(route('student.code-lab.save', $workspace), ['revision' => $workspace->revision, 'code' => 'void setup() {} void loop() {}', 'reflection' => 'I tested my sketch and checked the circuit.'])->assertOk();
        $this->postJson(route('student.code-lab.submit', $workspace), ['revision' => $workspace->fresh()->revision])->assertOk();
        $submission = $workspace->submissions()->sole();
        $this->withSession(['student_id' => null, 'teacher_id' => $teacher->id])->get(route('teacher.code-lab'))->assertOk()->assertSee('data-wokwi-editor="review-code-'.$submission->id.'"', false)->assertDontSee('data-wokwi-return', false);
        $this->withSession(['teacher_id' => null, 'student_id' => $student->id])->get(route('student.code-lab', ['activity' => 'hello']))->assertOk()->assertSee('data-onecompiler-editor="codeEditor"', false)->assertDontSee('data-wokwi-editor="codeEditor"', false);
        $this->withSession(['student_id' => null, 'teacher_id' => $teacher->id])->get(route('teacher.content.edit', 'arduino-blink'))->assertOk()->assertSee('data-wokwi-editor="lesson-example-editor"', false)->assertSee('data-wokwi-editor="explanation-0-code"', false);
    }

    public function test_readonly_examples_have_no_import_controls_and_escape_identifiers(): void
    {
        $html = view('wokwi-button', ['wokwiEditor' => 'example" unsafe', 'wokwiReview' => true])->render();
        $this->assertStringContainsString('data-wokwi-editor="example&quot; unsafe"', $html);
        $this->assertStringContainsString('https://wokwi.com/projects/new/arduino-uno', $html);
        $this->assertStringNotContainsString('data-wokwi-return', $html);
        $this->assertStringNotContainsString('data-wokwi-apply', $html);
    }
}
