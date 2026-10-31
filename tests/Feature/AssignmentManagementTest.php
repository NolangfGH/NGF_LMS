<?php

namespace Tests\Feature;

use App\Models\{Classroom, CodeWorkspace, LabSubmission, Student, TeacherAccount, TeacherAssignment};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssignmentManagementTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $teacher=TeacherAccount::create(['first_name'=>'Owner','last_name'=>'Teacher','school'=>'School','email'=>'manage@example.test','password'=>'password123']);
        $other=TeacherAccount::create(['first_name'=>'Other','last_name'=>'Teacher','school'=>'School','email'=>'othermanage@example.test','password'=>'password123']);
        $rooms=collect(['A','B','C'])->map(fn ($section)=>Classroom::create(['teacher_account_id'=>$teacher->id,'name'=>'C++','grade'=>'Grade 8','section'=>$section,'join_code'=>'MANAGE'.$section]));
        $foreign=Classroom::create(['teacher_account_id'=>$other->id,'name'=>'Private class','grade'=>'Grade 8','section'=>'Private','join_code'=>'MANAGEOTHER']);
        $students=$rooms->map(fn ($room)=>Student::create(['classroom_id'=>$room->id,'first_name'=>'Student','nickname'=>'Coder'.$room->section,'student_id'=>'MANAGE'.$room->id,'password'=>'password123','xp'=>100]));
        $assignment=TeacherAssignment::create(['teacher_account_id'=>$teacher->id,'classroom_id'=>$rooms[0]->id,'title'=>'Original task','school_term'=>'Term1','objective'=>'Print a message.','xp'=>150,'difficulty'=>'Easy','status'=>'published','due_at'=>now()->addDay(),'starter_code'=>'int main(){return 0;}','sample_output'=>"First line\nSecond line",'csta_standard'=>'2-AP-12']);
        $assignment->classrooms()->sync($rooms->take(2)->pluck('id'));
        $payload=['title'=>'Updated task','school_term'=>'Term2','classroom_ids'=>[$rooms[1]->id,$rooms[2]->id],'objective'=>'Print the new message.','difficulty'=>'Hard','xp'=>175,'status'=>'published','due_at'=>now()->addDays(2)->timezone(config('learning.timezone'))->format('Y-m-d\\TH:i'),'sample_output'=>"  Updated output\nNext line\n  ",'csta_standard'=>'2-AP-13'];
        $this->withSession(['teacher_id'=>$teacher->id]);
        return compact('teacher','other','rooms','foreign','students','assignment','payload');
    }

    public function test_edit_loads_the_correct_record_and_updates_all_details_without_creating_a_duplicate(): void
    {
        $f=$this->fixture();extract($f);
        $this->get(route('teacher.assignments',['edit'=>$assignment->id]))->assertOk()->assertSee('Edit assignment')->assertSee('Update Published Assignment')->assertSee('Cancel Edit / New Assignment')->assertViewHas('assignmentEditor',fn ($editor)=>
            $editor['id']===$assignment->id && $editor['sample_output']===$assignment->sample_output &&
            $editor['due_at']===$assignment->due_at->timezone(config('learning.timezone'))->format('Y-m-d\\TH:i') &&
            $editor['classroom_ids']->all()===$rooms->take(2)->pluck('id')->all());
        $this->post(route('teacher.assignments.update',$assignment),[...$payload,'_method'=>'PUT'],['Accept'=>'application/json'])->assertOk()->assertJsonPath('id',$assignment->id)->assertSessionHas('assignment_notice');
        $this->assertDatabaseCount('teacher_assignments',1);
        $current=$assignment->fresh();
        foreach (['title','school_term','objective','difficulty','xp','status','sample_output','csta_standard'] as $field) $this->assertSame($payload[$field],$current->$field);
        $this->assertSame($payload['due_at'],$current->due_at->timezone(config('learning.timezone'))->format('Y-m-d\\TH:i'));
        $this->assertSame($rooms[1]->id,$current->classroom_id);
        $this->assertEqualsCanonicalizing($payload['classroom_ids'],$current->classrooms()->pluck('classrooms.id')->all());
        $this->assertSame('int main(){return 0;}',$current->starter_code);
        $this->withSession(['student_id'=>$students[0]->id])->get(route('student.assignments'))->assertDontSee('Updated task');
        $this->get(route('student.code-lab',['assignment'=>$assignment->id]))->assertNotFound();
        foreach ($students->slice(1) as $student) {
            $this->withSession(['student_id'=>$student->id])->get(route('student.assignments'))->assertSee('Updated task')->assertSee($payload['sample_output']);
            $this->get(route('student.code-lab',['assignment'=>$assignment->id]))->assertOk()->assertSee('Updated task')->assertSee($payload['sample_output']);
        }
    }

    public function test_updates_preserve_student_code_submission_snapshots_and_already_credited_xp(): void
    {
        $f=$this->fixture();extract($f);
        $student=$students[1];
        $this->withSession(['student_id'=>$student->id])->post(route('student.assignments.open',$assignment))->assertRedirect();
        $workspace=CodeWorkspace::firstOrFail();
        $code='int main(){return 9;}';
        $this->putJson(route('student.code-lab.save',$workspace),['revision'=>1,'code'=>$code,'reflection'=>'I tested a different return value in the program.'])->assertOk();
        $this->postJson(route('student.code-lab.submit',$workspace),['revision'=>2])->assertOk();
        $submission=LabSubmission::firstOrFail();
        $this->putJson(route('teacher.assignments.update',$assignment),$payload)->assertOk();
        $saved=$workspace->fresh();
        $this->assertSame($code,$saved->code);
        $this->assertSame(2,$saved->revision);
        $this->assertSame('Updated task',$saved->title);
        $this->assertSame('Original task',$submission->fresh()->title);
        $this->assertSame($code,$submission->fresh()->code);
        $this->assertSame(150,$submission->fresh()->reward_budget);
        $this->post(route('student.assignments.open',$assignment))->assertRedirect();
        $this->assertSame(250,$student->fresh()->xp);
        $this->assertSame(150,$workspace->fresh()->opening_xp_awarded);
        $this->withSession(['student_id'=>$students[2]->id])->post(route('student.assignments.open',$assignment))->assertRedirect();
        $this->assertSame(275,$students[2]->fresh()->xp);
        $this->get(route('student.leaderboard'))->assertSee('250 XP')->assertSee('275 XP');
    }

    public function test_draft_and_deadline_updates_immediately_control_student_access(): void
    {
        $f=$this->fixture();extract($f);
        $payload['classroom_ids']=[$rooms[0]->id];
        $this->putJson(route('teacher.assignments.update',$assignment),[...$payload,'status'=>'draft'])->assertOk();
        $this->get(route('teacher.assignments',['edit'=>$assignment->id]))->assertSee('Update Draft');
        $this->withSession(['student_id'=>$students[0]->id])->get(route('student.assignments'))->assertDontSee('Updated task');
        $this->postJson(route('student.assignments.open',$assignment))->assertNotFound();
        $payload['due_at']=now()->subMinutes(2)->timezone(config('learning.timezone'))->format('Y-m-d\\TH:i');
        $this->putJson(route('teacher.assignments.update',$assignment),$payload)->assertOk();
        $this->get(route('student.assignments'))->assertSee('Updated task')->assertSee('Code Lab closed');
        $this->postJson(route('student.assignments.open',$assignment))->assertUnprocessable();
        $payload['due_at']=null;
        $this->putJson(route('teacher.assignments.update',$assignment),$payload)->assertOk();
        $this->get(route('student.assignments'))->assertSee('No due date');
        $this->post(route('student.assignments.open',$assignment))->assertRedirect();
        $this->assertSame(275,$students[0]->fresh()->xp);
    }

    public function test_invalid_updates_do_not_change_the_assignment_or_its_audience(): void
    {
        $f=$this->fixture();extract($f);
        foreach ([
            ['title'=>''],['school_term'=>'Term4'],['xp'=>-1],['status'=>'invalid'],['due_at'=>'not a date'],
            ['classroom_ids'=>[]],['classroom_ids'=>[$rooms[0]->id,$foreign->id]],['classroom_ids'=>[$rooms[0]->id,$rooms[0]->id]],
            ['sample_output'=>str_repeat('x',20001)],
        ] as $invalid) {
            $this->putJson(route('teacher.assignments.update',$assignment),[...$payload,...$invalid])->assertUnprocessable();
        }
        $this->assertSame('Original task',$assignment->fresh()->title);
        $this->assertSame(150,$assignment->fresh()->xp);
        $this->assertEqualsCanonicalizing($rooms->take(2)->pluck('id')->all(),$assignment->classrooms()->pluck('classrooms.id')->all());
        $this->assertDatabaseCount('teacher_assignments',1);
    }

    public function test_only_the_owner_can_edit_update_or_delete_an_assignment(): void
    {
        $f=$this->fixture();extract($f);
        $this->withSession(['teacher_id'=>null,'student_id'=>$students[0]->id])->get(route('teacher.assignments',['edit'=>$assignment->id]))->assertRedirect(route('teacher.login'));
        $this->putJson(route('teacher.assignments.update',$assignment),$payload)->assertForbidden();
        $this->deleteJson(route('teacher.assignments.destroy',$assignment))->assertForbidden();
        $this->withSession(['teacher_id'=>$other->id])->get(route('teacher.assignments',['edit'=>$assignment->id]))->assertNotFound();
        $this->putJson(route('teacher.assignments.update',$assignment),$payload)->assertForbidden();
        $this->deleteJson(route('teacher.assignments.destroy',$assignment))->assertForbidden();
        $this->assertSame('Original task',$assignment->fresh()->title);
        $this->assertNotSoftDeleted($assignment);
    }

    public function test_deletion_removes_access_and_keeps_saved_work_submissions_and_xp(): void
    {
        $f=$this->fixture();extract($f);
        $student=$students[0];
        $this->withSession(['student_id'=>$student->id])->post(route('student.assignments.open',$assignment))->assertRedirect();
        $workspace=CodeWorkspace::firstOrFail();
        $this->putJson(route('student.code-lab.save',$workspace),['revision'=>1,'code'=>'int main(){return 5;}','reflection'=>'I changed the return value and checked my program.'])->assertOk();
        $this->postJson(route('student.code-lab.submit',$workspace),['revision'=>2])->assertOk();
        $this->post(route('teacher.assignments.destroy',$assignment),['_method'=>'DELETE'],['Accept'=>'application/json'])->assertOk()->assertSessionHas('assignment_notice');
        $this->assertSoftDeleted($assignment);
        $this->assertSame(250,$student->fresh()->xp);
        $this->assertSame('int main(){return 5;}',$workspace->fresh()->code);
        $this->assertDatabaseCount('lab_submissions',1);
        $this->get(route('teacher.assignments'))->assertDontSee('Original task');
        $this->get(route('student.assignments'))->assertDontSee('Original task');
        $this->get(route('student.code-lab',['assignment'=>$assignment->id]))->assertNotFound();
        $this->postJson(route('student.assignments.open',$assignment))->assertNotFound();
        $this->putJson(route('student.code-lab.save',$workspace),['revision'=>2,'code'=>'int main(){}'])->assertForbidden();
        $this->get(route('teacher.assignments',['edit'=>$assignment->id]))->assertNotFound();
        $this->putJson(route('teacher.assignments.update',$assignment),$payload)->assertNotFound();
        $this->deleteJson(route('teacher.assignments.destroy',$assignment))->assertNotFound();
        $this->get(route('teacher.code-lab'))->assertOk()->assertSee('Original task');
        $this->get(route('student.code-lab',['activity'=>'hello']))->assertOk();
    }
}
