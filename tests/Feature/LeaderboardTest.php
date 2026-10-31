<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Student;
use App\Models\TeacherAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeaderboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_overall_and_section_ranks_include_three_sections_with_fair_ties(): void
    {
        $teacher=TeacherAccount::create(['first_name'=>'Test','last_name'=>'Teacher','email'=>'rank@example.test','school'=>'School','password'=>'password123']);
        $otherTeacher=TeacherAccount::create(['first_name'=>'Other','last_name'=>'Teacher','email'=>'rankother@example.test','school'=>'School','password'=>'password123']);
        $rooms=collect(['A','B','C'])->map(fn ($section)=>Classroom::create(['teacher_account_id'=>$teacher->id,'name'=>'C++','grade'=>'Grade 8','section'=>$section,'join_code'=>'RANK'.$section]));
        $otherRoom=Classroom::create(['teacher_account_id'=>$otherTeacher->id,'name'=>'C++','grade'=>'Grade 8','section'=>'PRIVATE','join_code'=>'PRIVATE']);
        $otherGrade=Classroom::create(['teacher_account_id'=>$teacher->id,'name'=>'C++','grade'=>'Grade 9','section'=>'OLDER','join_code'=>'OLDER']);
        $make=fn ($room,$name,$xp)=>Student::create(['classroom_id'=>$room->id,'first_name'=>'Student','nickname'=>$name,'student_id'=>$name,'password'=>'password123','xp'=>$xp]);
        $me=$make($rooms[0],'Alex',100);
        $top=$make($rooms[1],'Bea',250);
        $tied=$make($rooms[2],'Cara',250);
        $peer=$make($rooms[0],'Drew',50);
        $make($otherRoom,'PrivateCadet',9999);
        $make($otherGrade,'OlderCadet',9999);
        $page=$this->withSession(['student_id'=>$me->id])->get(route('student.leaderboard'));
        $page->assertOk()->assertViewHas('myOverallRank',3)->assertViewHas('mySectionRank',1);
        $page->assertViewHas('overallRanking',fn ($rows)=>$rows->pluck('rank')->all()===[1,1,3,4]);
        $page->assertViewHas('sections',fn ($sections)=>$sections->count()===3);
        $page->assertSee('Bea')->assertSee('Cara')->assertSee('Drew')->assertDontSee('PrivateCadet')->assertDontSee('OlderCadet');
        $this->get(route('student.leaderboard',['section'=>$rooms[0]->id]))->assertOk()
            ->assertViewHas('rankingRows',fn ($rows)=>$rows->pluck('id')->all()===[$me->id,$peer->id] && $rows->pluck('rank')->all()===[1,2]);
        $this->get(route('student.leaderboard',['section'=>$rooms[1]->id]))->assertOk()
            ->assertViewHas('rankingRows',fn ($rows)=>$rows->count()===1 && $rows->first()['id']===$top->id);
        $this->get(route('student.leaderboard',['section'=>$otherRoom->id]))->assertForbidden();
        $this->get(route('student.leaderboard',['section'=>$otherGrade->id]))->assertForbidden();
        $me->update(['xp'=>300]);
        $this->get(route('student.leaderboard'))->assertOk()->assertViewHas('myOverallRank',1)
            ->assertViewHas('overallRanking',fn ($rows)=>$rows->pluck('rank')->all()===[1,2,2,4]);
    }
}
