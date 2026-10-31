<?php

namespace Tests\Feature;

use App\Models\{Classroom, CodeWorkspace, Student, TeacherAccount, TeacherAssignment};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssignmentSampleOutputTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $teacher=TeacherAccount::create(['first_name'=>'Teacher','last_name'=>'Test','school'=>'School','email'=>'output@example.test','password'=>'password123']);
        $room=Classroom::create(['teacher_account_id'=>$teacher->id,'name'=>'C++','grade'=>'Grade 8','section'=>'A','join_code'=>'OUTPUT1']);
        $student=Student::create(['classroom_id'=>$room->id,'first_name'=>'Alex','nickname'=>'CoderAlex','student_id'=>'OUTPUT1','password'=>'password123','xp'=>100]);
        $this->withSession(['teacher_id'=>$teacher->id]);
        $payload=['title'=>'Countdown output assignment','school_term'=>'Term1','classroom_ids'=>[$room->id],'objective'=>'Print a countdown.','difficulty'=>'Easy','xp'=>150,'status'=>'published'];
        return [$student,$payload];
    }

    public function test_teacher_output_is_saved_exactly_and_shown_separately_from_student_code(): void
    {
        [$student,$payload]=$this->fixture();
        $output="  Countdown:\n5\n4\n3\n2\n1\nLaunch!\n  ";
        $this->get(route('teacher.assignments'))->assertOk()->assertSee('Assignment Sample Output')->assertSee('name="sample_output"',false)->assertSee('Optional C++ starter code')->assertSee('name="starter_code"',false);
        $this->postJson(route('teacher.assignments.store'),[...$payload,'sample_output'=>$output])->assertOk();
        $assignment=TeacherAssignment::firstOrFail();
        $this->assertSame($output,$assignment->sample_output);
        $this->assertNull($assignment->starter_code);
        $this->get(route('teacher.assignments'))->assertSee($output);
        $this->withSession(['student_id'=>$student->id])->get(route('student.assignments'))->assertOk()->assertSee($output);
        $this->post(route('student.assignments.open',$assignment))->assertRedirect();
        $this->get(route('student.code-lab',['assignment'=>$assignment->id]))->assertOk()->assertSee('Expected program output')->assertSee($output)->assertViewHas('workspace',fn ($workspace)=>$workspace->code===config('code_lab.assignment_starter'));
        $workspace=CodeWorkspace::firstOrFail();
        $this->assertStringNotContainsString('Countdown:', $workspace->code);
        $code='#include <iostream>\nint main(){std::cout << "Launch!";}';
        $this->putJson(route('student.code-lab.save',$workspace),['revision'=>1,'code'=>$code,'reflection'=>'I used output statements to show the countdown.'])->assertOk();
        $this->assertSame($output,$assignment->fresh()->sample_output);
        $this->assertSame($code,$workspace->fresh()->code);
    }

    public function test_output_is_plain_text_and_validation_rejects_invalid_values(): void
    {
        [$student,$payload]=$this->fixture();
        $this->postJson(route('teacher.assignments.store'),[...$payload,'sample_output'=>['not a string']])->assertUnprocessable()->assertJsonValidationErrors('sample_output');
        $this->postJson(route('teacher.assignments.store'),[...$payload,'sample_output'=>str_repeat('x',20001)])->assertUnprocessable()->assertJsonValidationErrors('sample_output');
        $this->assertDatabaseCount('teacher_assignments',0);
        $output='<script>alert("sample")</script>';
        $this->postJson(route('teacher.assignments.store'),[...$payload,'sample_output'=>$output])->assertOk();
        $assignment=TeacherAssignment::firstOrFail();
        $this->get(route('teacher.assignments'))->assertSee(e($output),false)->assertDontSee($output,false);
        $this->withSession(['student_id'=>$student->id])->get(route('student.code-lab',['assignment'=>$assignment->id]))->assertOk()->assertSee(e($output),false)->assertDontSee($output,false);
    }

    public function test_drafts_keep_output_private_and_old_starter_code_and_saved_drafts_are_preserved(): void
    {
        [$student,$payload]=$this->fixture();
        $this->postJson(route('teacher.assignments.store'),[...$payload,'status'=>'draft','sample_output'=>'Private draft output'])->assertOk();
        $draft=TeacherAssignment::firstOrFail();
        $this->get(route('teacher.assignments'))->assertSee('Private draft output');
        $this->withSession(['student_id'=>$student->id])->get(route('student.assignments'))->assertDontSee('Private draft output');
        $this->get(route('student.code-lab',['assignment'=>$draft->id]))->assertNotFound();
        $legacy=$draft->replicate();
        $legacy->fill(['status'=>'published','sample_output'=>null,'starter_code'=>'int main(){return 7;}']);
        $legacy->save();
        $this->get(route('student.code-lab',['assignment'=>$legacy->id]))->assertOk()->assertDontSee('Expected program output');
        $workspace=CodeWorkspace::firstOrFail();
        $this->assertSame('int main(){return 7;}',$workspace->code);
        $workspace->update(['code'=>'int main(){return 8;}']);
        $legacy->update(['sample_output'=>'New expected output']);
        $this->get(route('student.code-lab',['assignment'=>$legacy->id]))->assertOk()->assertSee('New expected output')->assertViewHas('workspace',fn ($workspace)=>$workspace->code==='int main(){return 8;}');
    }
}
