<?php

namespace Tests\Feature;

use App\Models\{Classroom,Student,TeacherAccount,TeacherAssignment};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssignmentAudienceTest extends TestCase
{
    use RefreshDatabase;

    public function test_school_terms_and_multiple_sections_are_saved_and_enforced_in_student_pages_and_lab(): void
    {
        $teacher=TeacherAccount::create(['first_name'=>'Teacher','last_name'=>'Test','school'=>'School','email'=>'assignment@example.test','password'=>'password123']);
        $rooms=collect(['A','B','C','D'])->map(fn ($section)=>Classroom::create(['teacher_account_id'=>$teacher->id,'name'=>'C++','grade'=>'Grade 8','section'=>$section,'join_code'=>'ASSIGN'.$section]));
        $students=$rooms->map(fn ($room)=>Student::create(['classroom_id'=>$room->id,'first_name'=>'Student','nickname'=>'Coder'.$room->section,'student_id'=>'ASSIGN'.$room->id,'password'=>'password123']));
        $this->withSession(['teacher_id'=>$teacher->id])->get(route('teacher.assignments'))->assertOk()->assertSee('School Term')->assertSee('Term1')->assertSee('Term2')->assertSee('Term3')->assertSee('Grade level & sections',false);
        $due=now()->addDay()->timezone(config('learning.timezone'))->format('Y-m-d\TH:i');
        $payload=['title'=>'Shared assignment','school_term'=>'Term2','classroom_ids'=>$rooms->take(3)->pluck('id')->all(),'objective'=>'Print a greeting','difficulty'=>'Easy','xp'=>100,'status'=>'published','starter_code'=>'int main(){return 0;}','due_at'=>$due];
        $this->postJson(route('teacher.assignments.store'),$payload)->assertOk();
        $assignment=TeacherAssignment::first();
        $this->assertSame('Term2',$assignment->school_term);
        $this->assertSame(3,$assignment->classrooms()->count());
        $this->assertSame($due,$assignment->due_at->timezone(config('learning.timezone'))->format('Y-m-d\TH:i'));
        $this->assertDatabaseCount('teacher_assignments',1);
        foreach ($students->take(3) as $student) {
            $this->withSession(['student_id'=>$student->id])->get(route('student.assignments'))->assertOk()->assertSee('Shared assignment')->assertSee('School Term: Term2');
            $this->get(route('student.code-lab',['assignment'=>$assignment->id]))->assertOk()->assertSee('Shared assignment');
        }
        $this->withSession(['student_id'=>$students[3]->id])->get(route('student.assignments'))->assertDontSee('Shared assignment');
        $this->get(route('student.code-lab',['assignment'=>$assignment->id]))->assertNotFound();
        $payload['title']='Private draft';$payload['school_term']='Term3';$payload['status']='draft';
        $this->postJson(route('teacher.assignments.store'),$payload)->assertOk();
        $this->withSession(['student_id'=>$students[0]->id])->get(route('student.assignments'))->assertDontSee('Private draft');
        $this->get(route('teacher.assignments'))->assertSee('Shared assignment')->assertSee('Term2')->assertSee('Term3');
    }

    public function test_invalid_term_missing_targets_and_foreign_sections_are_rejected(): void
    {
        $teacher=TeacherAccount::create(['first_name'=>'Teacher','last_name'=>'Test','school'=>'School','email'=>'ownassign@example.test','password'=>'password123']);
        $other=TeacherAccount::create(['first_name'=>'Other','last_name'=>'Teacher','school'=>'School','email'=>'foreignassign@example.test','password'=>'password123']);
        $room=Classroom::create(['teacher_account_id'=>$teacher->id,'name'=>'C++','join_code'=>'OWN123']);
        $foreign=Classroom::create(['teacher_account_id'=>$other->id,'name'=>'C++','join_code'=>'OTHER123']);
        $payload=['title'=>'Assignment','school_term'=>'Term1','classroom_ids'=>[$room->id],'difficulty'=>'Mid','xp'=>100,'status'=>'published'];
        $this->withSession(['teacher_id'=>$teacher->id]);
        $this->postJson(route('teacher.assignments.store'),[...$payload,'school_term'=>'Term4'])->assertUnprocessable()->assertJsonValidationErrors('school_term');
        $this->postJson(route('teacher.assignments.store'),[...$payload,'classroom_ids'=>[]])->assertUnprocessable();
        $this->postJson(route('teacher.assignments.store'),[...$payload,'classroom_ids'=>[$room->id,$foreign->id]])->assertUnprocessable()->assertJsonValidationErrors('classroom_ids.1');
        $this->postJson(route('teacher.assignments.store'),[...$payload,'classroom_ids'=>[$room->id,$room->id]])->assertUnprocessable();
        $this->assertDatabaseCount('teacher_assignments',0);
        foreach (['Term1','Term2','Term3'] as $term) $this->postJson(route('teacher.assignments.store'),[...$payload,'school_term'=>$term])->assertOk();
        $this->assertDatabaseCount('teacher_assignments',3);
        $this->withSession(['teacher_id'=>$other->id])->get(route('teacher.assignments'))->assertDontSee('assignment-record rounded-xl');
    }
}
