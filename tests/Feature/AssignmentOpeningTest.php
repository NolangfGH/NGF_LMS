<?php

namespace Tests\Feature;

use App\Models\{Classroom, Student, TeacherAccount, TeacherAssignment, CodeWorkspace, LabSubmission};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssignmentOpeningTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $teacher=TeacherAccount::create(['first_name'=>'Teacher','last_name'=>'Test','school'=>'School','email'=>'opening@example.test','password'=>'password123']);
        $room=Classroom::create(['teacher_account_id'=>$teacher->id,'name'=>'C++','grade'=>'Grade 8','section'=>'A','join_code'=>'OPEN123']);
        $student=Student::create(['classroom_id'=>$room->id,'first_name'=>'Alex','nickname'=>'CoderAlex','student_id'=>'OPEN123','password'=>'password123','xp'=>500]);
        $assignment=TeacherAssignment::create(['teacher_account_id'=>$teacher->id,'classroom_id'=>$room->id,'title'=>'Opening reward lab','school_term'=>'Term1','xp'=>150,'status'=>'published','due_at'=>now()->addHour()]);
        $this->withSession(['student_id'=>$student->id]);
        return [$student,$teacher,$assignment];
    }

    public function test_opening_credits_once_updates_leaderboard_and_does_not_duplicate_on_review(): void
    {
        [$student,$teacher,$assignment]=$this->fixture();
        $this->get(route('student.assignments'))->assertOk()->assertSee('+150 XP on first open');
        $this->get(route('student.code-lab',['assignment'=>$assignment->id]))->assertOk();
        $this->assertSame(500,$student->fresh()->xp);
        $this->post(route('student.assignments.open',$assignment))->assertRedirect(route('student.code-lab',['assignment'=>$assignment->id]))->assertSessionHas('assignment_reward','+150 XP added to your account and leaderboard.');
        $workspace=CodeWorkspace::firstOrFail();
        $this->assertSame(650,$student->fresh()->xp);
        $this->assertSame(2,$student->fresh()->level);
        $this->assertSame(150,$workspace->opening_xp_awarded);
        $this->assertNotNull($workspace->assignment_opened_at);
        $this->get(route('student.code-lab',['assignment'=>$assignment->id]))->assertOk()->assertSee('150 assignment XP already credited');
        $this->post(route('student.assignments.open',$assignment))->assertRedirect();
        $this->get(route('student.assignments'))->assertSee('150 XP credited')->assertSee('Continue Code Lab');
        $this->get(route('student.leaderboard'))->assertSee('650 XP');
        $assignment->update(['xp'=>300]);
        $this->post(route('student.assignments.open',$assignment))->assertRedirect();
        $this->assertSame(650,$student->fresh()->xp);
        $this->assertDatabaseCount('code_workspaces',1);
        $this->putJson(route('student.code-lab.save',$workspace),['revision'=>1,'code'=>'int main(){return 0;}','reflection'=>'I tested the return value and explained my program.'])->assertOk();
        $this->postJson(route('student.code-lab.submit',$workspace),['revision'=>2])->assertOk();
        $submission=LabSubmission::firstOrFail();
        $this->withSession(['teacher_id'=>$teacher->id])->post(route('teacher.code-lab.review',$submission),['rubric'=>['correctness'=>4,'logic'=>4,'readability'=>4,'explanation'=>4],'feedback'=>'Your solution meets all the learning criteria.'])->assertRedirect();
        $this->assertSame(0,$submission->fresh()->xp_awarded);
        $this->assertSame(650,$student->fresh()->xp);
    }

    public function test_deadline_is_enforced_on_the_exact_second_and_expired_links_cannot_award_xp(): void
    {
        [$student,,$assignment]=$this->fixture();
        $this->travelTo(now()->startOfSecond());
        $assignment->update(['due_at'=>now()->addSecond()]);
        $this->get(route('student.assignments'))->assertOk()->assertSee('data-deadline=',false)->assertDontSee('disabled class=',false);
        $this->travel(1)->seconds();
        $this->get(route('student.assignments'))->assertOk()->assertSee('Code Lab closed')->assertSee('data-open-button disabled',false);
        $this->postJson(route('student.assignments.open',$assignment))->assertUnprocessable()->assertJsonValidationErrors('assignment');
        $this->get(route('student.code-lab',['assignment'=>$assignment->id]))->assertForbidden();
        $this->assertSame(500,$student->fresh()->xp);
        $this->assertDatabaseCount('code_workspaces',0);
    }

    public function test_reward_remains_credited_after_deadline_and_no_due_date_stays_available(): void
    {
        [$student,,$assignment]=$this->fixture();
        $this->post(route('student.assignments.open',$assignment))->assertRedirect();
        $assignment->update(['due_at'=>now()->subSecond()]);
        $this->get(route('student.assignments'))->assertSee('150 XP credited')->assertSee('Code Lab closed');
        $this->postJson(route('student.assignments.open',$assignment))->assertUnprocessable();
        $this->assertSame(650,$student->fresh()->xp);
        $assignment->update(['due_at'=>null]);
        $this->post(route('student.assignments.open',$assignment))->assertRedirect();
        $this->assertSame(650,$student->fresh()->xp);
        $another=$assignment->replicate();
        $another->title='No deadline lab';
        $another->save();
        $this->post(route('student.assignments.open',$another))->assertRedirect();
        $this->assertSame(800,$student->fresh()->xp);
    }

    public function test_older_rubric_rewards_are_only_topped_up_and_each_student_has_a_separate_reward(): void
    {
        [$student,,$assignment]=$this->fixture();
        CodeWorkspace::create(['student_id'=>$student->id,'teacher_assignment_id'=>$assignment->id,'activity_key'=>'assignment:'.$assignment->id,'title'=>$assignment->title,'code'=>'int main(){}','xp_earned'=>50]);
        $this->post(route('student.assignments.open',$assignment))->assertRedirect()->assertSessionHas('assignment_reward','+100 XP added to your account and leaderboard.');
        $this->assertSame(600,$student->fresh()->xp);
        $this->assertSame(100,CodeWorkspace::first()->opening_xp_awarded);
        $other=Student::create(['classroom_id'=>$student->classroom_id,'first_name'=>'Other','nickname'=>'OtherCoder','student_id'=>'OTHEROPEN','password'=>'password123','xp'=>100]);
        $this->withSession(['student_id'=>$other->id])->post(route('student.assignments.open',$assignment))->assertRedirect();
        $this->assertSame(250,$other->fresh()->xp);
        $this->assertSame(600,$student->fresh()->xp);
    }

    public function test_only_published_assignments_for_the_students_sections_can_be_opened(): void
    {
        [$student,$teacher,$assignment]=$this->fixture();
        $this->withSession(['student_id'=>null])->postJson(route('student.assignments.open',$assignment))->assertUnauthorized();
        $this->withSession(['student_id'=>$student->id]);
        $assignment->update(['status'=>'draft']);
        $this->postJson(route('student.assignments.open',$assignment))->assertNotFound();
        $assignment->update(['status'=>'published']);
        $room=Classroom::create(['teacher_account_id'=>$teacher->id,'name'=>'C++','grade'=>'Grade 8','section'=>'B','join_code'=>'OPENB']);
        $assignment->update(['classroom_id'=>$room->id]);
        $this->postJson(route('student.assignments.open',$assignment))->assertNotFound();
        $assignment->classrooms()->attach($student->classroom_id);
        $this->post(route('student.assignments.open',$assignment))->assertRedirect();
        $this->assertSame(650,$student->fresh()->xp);
    }
}
