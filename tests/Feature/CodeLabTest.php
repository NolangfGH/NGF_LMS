<?php

namespace Tests\Feature;

use App\Models\{Classroom,Student,TeacherAccount,TeacherAssignment,CodeWorkspace,LabSubmission};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CodeLabTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $teacher=TeacherAccount::create(['first_name'=>'Teacher','last_name'=>'Test','school'=>'School','email'=>'lab@example.test','password'=>'password123']);
        $room=Classroom::create(['teacher_account_id'=>$teacher->id,'name'=>'C++','section'=>'A','join_code'=>'LAB123']);
        $student=Student::create(['classroom_id'=>$room->id,'first_name'=>'Alex','nickname'=>'CoderAlex','student_id'=>'LAB123','password'=>'password123','xp'=>100]);
        $assignment=TeacherAssignment::create(['teacher_account_id'=>$teacher->id,'classroom_id'=>$room->id,'title'=>'Output lab','objective'=>'Print a greeting','xp'=>100,'status'=>'published','starter_code'=>"#include <iostream>\nint main() { return 0; }"]);
        $this->withSession(['student_id'=>$student->id]);
        return [$student,$teacher,$assignment];
    }

    public function test_drafts_persist_and_other_students_cannot_change_them(): void
    {
        [$student,$teacher,$assignment]=$this->fixture();
        $this->get(route('student.code-lab',['assignment'=>$assignment->id]))->assertOk()->assertSee('Output lab')->assertSee('Review code')->assertSee('Download .cpp');
        $workspace=CodeWorkspace::first();
        $payload=['revision'=>1,'code'=>'int main() { return 1; }','prediction'=>'No output','reflection'=>'My program returns a status code.'];
        $this->putJson(route('student.code-lab.save',$workspace),$payload)->assertOk()->assertJsonPath('revision',2);
        $this->get(route('student.code-lab',['assignment'=>$assignment->id]))->assertSee('return 1;');
        $this->putJson(route('student.code-lab.save',$workspace),$payload)->assertConflict();
        $other=Student::create(['classroom_id'=>$student->classroom_id,'first_name'=>'Other','nickname'=>'OtherCadet','student_id'=>'OTHER','password'=>'password123']);
        $this->withSession(['student_id'=>$other->id])->putJson(route('student.code-lab.save',$workspace),[...$payload,'revision'=>2])->assertForbidden();
        $this->postJson(route('student.code-lab.submit',$workspace),['revision'=>2])->assertForbidden();
        $assignment->update(['status'=>'draft']);
        $this->get(route('student.code-lab',['assignment'=>$assignment->id]))->assertNotFound();
    }

    public function test_submissions_are_snapshots_and_review_awards_only_improvement_xp_once(): void
    {
        [$student,$teacher,$assignment]=$this->fixture();
        $this->get(route('student.code-lab',['assignment'=>$assignment->id]));
        $workspace=CodeWorkspace::first();
        $this->postJson(route('student.code-lab.submit',$workspace),['revision'=>1])->assertUnprocessable();
        $payload=['revision'=>1,'code'=>'int main(){return 0;}','reflection'=>'My solution has a main function and returns zero.'];
        $this->putJson(route('student.code-lab.save',$workspace),$payload)->assertOk();
        $this->postJson(route('student.code-lab.submit',$workspace),['revision'=>2])->assertOk();
        $submission=LabSubmission::first();
        $this->postJson(route('student.code-lab.submit',$workspace),['revision'=>2])->assertUnprocessable();
        $this->putJson(route('student.code-lab.save',$workspace),[...$payload,'revision'=>2,'code'=>'int main(){return 1;}'])->assertOk();
        $this->assertSame('int main(){return 0;}',$submission->fresh()->code);
        $other=TeacherAccount::create(['first_name'=>'Other','last_name'=>'Teacher','school'=>'School','email'=>'otherlab@example.test','password'=>'password123']);
        $rubric=['correctness'=>2,'logic'=>2,'readability'=>2,'explanation'=>2];
        $review=['rubric'=>$rubric,'feedback'=>'Improve your output and test it with a boundary case.'];
        $this->withSession(['teacher_id'=>$other->id])->postJson(route('teacher.code-lab.review',$submission),$review)->assertForbidden();
        $this->get(route('teacher.code-lab'))->assertOk()->assertDontSee('Output lab');
        $this->withSession(['teacher_id'=>$teacher->id])->post(route('teacher.code-lab.review',$submission),$review)->assertRedirect();
        $this->assertSame(150,$student->fresh()->xp);
        $this->postJson(route('teacher.code-lab.review',$submission),$review)->assertUnprocessable();
        $this->assertSame(150,$student->fresh()->xp);
        $this->get(route('student.code-lab',['assignment'=>$assignment->id]))->assertSee('50 XP credited')->assertSee('Improve your output');
        $this->postJson(route('student.code-lab.submit',$workspace),['revision'=>3])->assertOk();
        $improved=LabSubmission::latest('id')->first();
        $this->post(route('teacher.code-lab.review',$improved),['rubric'=>array_fill_keys(array_keys($rubric),4),'feedback'=>'Your revised solution demonstrates all learning criteria.'])->assertRedirect();
        $this->assertSame(200,$student->fresh()->xp);
        $this->assertSame(50,$improved->fresh()->xp_awarded);
        $this->get(route('student.leaderboard'))->assertSee('200 XP');
    }

    public function test_practice_has_no_xp_reward_and_due_dates_block_assignment_submissions(): void
    {
        [$student,$teacher,$assignment]=$this->fixture();
        $this->get(route('student.code-lab',['activity'=>'variables']))->assertOk()->assertSee('Supplies calculator');
        $practice=CodeWorkspace::first();
        $this->putJson(route('student.code-lab.save',$practice),['revision'=>1,'code'=>'int main(){return 0;}','reflection'=>'I used a main function to organize the program.'])->assertOk();
        $this->postJson(route('student.code-lab.submit',$practice),['revision'=>2])->assertOk();
        $submission=LabSubmission::first();
        $this->withSession(['teacher_id'=>$teacher->id])->post(route('teacher.code-lab.review',$submission),['rubric'=>['correctness'=>4,'logic'=>4,'readability'=>4,'explanation'=>4],'feedback'=>'Good explanation; now complete the assigned coding task.'])->assertRedirect();
        $this->assertSame(100,$student->fresh()->xp);
        $this->get(route('student.code-lab',['assignment'=>$assignment->id]));
        $assigned=CodeWorkspace::where('teacher_assignment_id',$assignment->id)->first();
        $assignment->update(['due_at'=>now()->subMinute()]);
        $this->get(route('student.code-lab',['assignment'=>$assignment->id]))->assertForbidden();
        $this->putJson(route('student.code-lab.save',$assigned),['revision'=>1,'code'=>'int main(){return 0;}','reflection'=>'I explained my program structure and output.'])->assertOk();
        $this->postJson(route('student.code-lab.submit',$assigned),['revision'=>2])->assertUnprocessable();
    }
}
