<?php

namespace Tests\Feature;

use App\Models\{Classroom,Student,TeacherAccount,TeacherQuiz,CodeWorkspace,LabSubmission,StudentAchievement};
use App\Services\AchievementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AchievementsTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $teacher=TeacherAccount::create(['first_name'=>'Owner','last_name'=>'Teacher','school'=>'School','email'=>'badges@example.test','password'=>'password123']);
        $other=TeacherAccount::create(['first_name'=>'Other','last_name'=>'Teacher','school'=>'School','email'=>'otherbadges@example.test','password'=>'password123']);
        $room=Classroom::create(['teacher_account_id'=>$teacher->id,'name'=>'C++','grade'=>'Grade 8','section'=>'A','join_code'=>'BADGEA']);
        $second=Classroom::create(['teacher_account_id'=>$teacher->id,'name'=>'C++','grade'=>'Grade 8','section'=>'B','join_code'=>'BADGEB']);
        $foreign=Classroom::create(['teacher_account_id'=>$other->id,'name'=>'Private class','join_code'=>'BADGEC']);
        $student=Student::create(['classroom_id'=>$room->id,'first_name'=>'Alex','nickname'=>'MyCoder','student_id'=>'BADGE1','password'=>'password123','xp'=>999]);
        $peer=Student::create(['classroom_id'=>$second->id,'first_name'=>'Peer','nickname'=>'PeerCoder','student_id'=>'BADGE2','password'=>'password123']);
        $private=Student::create(['classroom_id'=>$foreign->id,'first_name'=>'Private','nickname'=>'ForeignPrivateCoder','student_id'=>'PRIVATE-BADGE','password'=>'password123']);
        $this->withSession(['student_id'=>$student->id,'teacher_id'=>null,'student_class_verified'=>true]);
        return compact('teacher','other','room','second','foreign','student','peer','private');
    }
    private function quiz(Student $student, TeacherAccount $teacher, int $score=2, int $graded=2): TeacherQuiz
    {
        $quiz=TeacherQuiz::create(['teacher_account_id'=>$teacher->id,'classroom_id'=>$student->classroom_id,'title'=>'Knowledge checkpoint','status'=>'published','questions'=>[]]);
        DB::table('quiz_submissions')->insert(['student_id'=>$student->id,'teacher_quiz_id'=>$quiz->id,'answers'=>'[]','score'=>$score,'graded_questions'=>$graded,'total_questions'=>2,'created_at'=>now(),'updated_at'=>now(),'graded_at'=>now(),'xp_awarded_at'=>now(),'xp_credited'=>0]);
        return $quiz;
    }
    private function lab(Student $student, TeacherAccount $teacher, string $key='one'): LabSubmission
    {
        $workspace=CodeWorkspace::create(['student_id'=>$student->id,'activity_key'=>'practice:'.$key,'title'=>'C++ activity '.$key,'code'=>'int main(){return 0;}']);
        return $workspace->submissions()->create(['teacher_account_id'=>$teacher->id,'revision'=>1,'title'=>$workspace->title,'code'=>$workspace->code,'reflection'=>'I explained the code and a test case.']);
    }
    public function test_empty_state_shows_real_goals_and_xp_alone_cannot_award_a_badge(): void
    {
        extract($this->fixture());
        $page=$this->get(route('student.achievements'))->assertOk()->assertSee('First C++ Mission')->assertSee('Make this my focus')->assertDontSee('No achievements earned yet');
        $page->assertViewHas('achievementReport',fn ($report)=>$report['earned']===0 && $report['total']===count(config('achievements')) && $report['metrics']['active_days']===0);
        $this->assertSame(999,$student->fresh()->xp);
        $this->assertDatabaseCount('student_achievements',0);
        $this->withSession(['student_id'=>null])->get(route('student.achievements'))->assertRedirect(route('student.login'));
    }
    public function test_quiz_badges_deduplicate_final_opportunities_and_do_not_treat_partial_grading_as_perfect(): void
    {
        extract($this->fixture());
        $first=$this->quiz($student,$teacher);
        DB::table('quiz_submissions')->insert(['student_id'=>$student->id,'teacher_quiz_id'=>$first->id,'attempt_number'=>2,'answers'=>'[]','score'=>2,'graded_questions'=>2,'total_questions'=>2,'created_at'=>now(),'updated_at'=>now(),'graded_at'=>now(),'xp_awarded_at'=>now(),'xp_credited'=>0]);
        $this->quiz($student,$teacher,1,1);$this->quiz($student,$teacher,0,2);
        $before=$student->updated_at->toIso8601String();
        $service=app(AchievementService::class);$report=$service->sync($student);
        $this->assertSame(3,$report['metrics']['quizzes_completed']);
        $this->assertSame(1,$report['metrics']['perfect_quizzes']);
        $this->assertSame(3,$report['earned']);
        $service->sync($student);$service->sync($student);
        $this->assertDatabaseCount('student_achievements',3);
        $this->assertSame(3,$student->fresh()->badges_count);
        $this->assertSame($before,$student->fresh()->updated_at->toIso8601String());
        $this->assertSame(999,$student->fresh()->xp);
        $this->get(route('student.achievements'))->assertOk()->assertSee('A step forward!')->assertSee('View learning evidence');
        $this->get(route('student.achievements'))->assertOk()->assertDontSee('A step forward!');
        $this->get(route('student.progress'))->assertOk()->assertViewHas('student',fn ($student)=>$student->badges_count===3);
        $this->get(route('student.dashboard'))->assertOk()->assertSee('3 / '.count(config('achievements')).' learning badges');
    }
    public function test_skill_badges_require_final_teacher_reviews_and_distinct_activities(): void
    {
        extract($this->fixture());$service=app(AchievementService::class);
        $first=$this->lab($student,$teacher);
        $first->update(['ai_suggestion'=>['rubric'=>array_fill_keys(['correctness','logic','readability','explanation'],4)]]);
        $report=$service->sync($student);
        $this->assertSame(1,$report['earned']);
        $this->assertFalse($report['cards']->firstWhere('code','clear_coder')['earned']);
        $first->update(['rubric'=>['correctness'=>3,'logic'=>3,'readability'=>3,'explanation'=>3],'score'=>12,'reviewed_at'=>now()]);
        $first->workspace->submissions()->create(['teacher_account_id'=>$teacher->id,'revision'=>2,'title'=>'Repeated revision','code'=>'int main(){}','reflection'=>'I improved the explanation and testing.','rubric'=>['correctness'=>3,'logic'=>3,'readability'=>3,'explanation'=>3],'score'=>12,'reviewed_at'=>now()]);
        $report=$service->sync($student);
        $this->assertSame(1,$report['metrics']['strong_solutions']);
        $this->assertSame(1,$report['metrics']['submitted_activities']);
        $this->assertTrue($report['cards']->firstWhere('code','clear_coder')['earned']);
        $this->assertTrue($report['cards']->firstWhere('code','explain_it')['earned']);
        $this->assertFalse($report['cards']->firstWhere('code','problem_solver')['earned']);
        $second=$this->lab($student,$teacher,'two');
        $second->update(['rubric'=>['correctness'=>3,'logic'=>4,'readability'=>2,'explanation'=>2],'score'=>11,'reviewed_at'=>now()]);
        $report=$service->sync($student);
        $this->assertTrue($report['cards']->firstWhere('code','problem_solver')['earned']);
        $this->assertSame(999,$student->fresh()->xp);
    }
    public function test_improvement_and_local_learning_days_have_real_evidence_and_survive_history_changes(): void
    {
        extract($this->fixture());$service=app(AchievementService::class);
        $first=$this->lab($student,$teacher);
        $first->update(['created_at'=>'2026-10-25 15:59:00','reviewed_at'=>'2026-10-26 10:00:00','score'=>4,'rubric'=>['correctness'=>1,'logic'=>1,'readability'=>1,'explanation'=>1]]);
        $later=$first->workspace->submissions()->create(['teacher_account_id'=>$teacher->id,'revision'=>2,'title'=>'Improved C++ work','code'=>'int main(){}','reflection'=>'I used the teacher feedback to improve my solution.','created_at'=>'2026-10-26 16:01:00','reviewed_at'=>'2026-10-27 10:00:00','score'=>12,'rubric'=>['correctness'=>3,'logic'=>3,'readability'=>3,'explanation'=>3]]);
        $extra=$this->lab($student,$teacher,'two');$extra->update(['created_at'=>'2026-10-25 16:01:00']);
        $report=$service->sync($student);
        $this->assertSame(3,$report['metrics']['active_days']);
        $this->assertTrue($report['cards']->firstWhere('code','feedback_builder')['earned']);
        $this->assertTrue($report['cards']->firstWhere('code','learning_rhythm')['earned']);
        $evidence=$report['cards']->firstWhere('code','feedback_builder')['evidence'];
        $this->assertStringContainsString('4 → 12',$evidence[0]['detail']);
        $count=$report['earned'];$later->delete();
        $this->assertSame($count,$service->sync($student)['earned']);
        $this->assertSame(999,$student->fresh()->xp);
    }
    public function test_goal_is_private_validated_and_persisted_for_the_signed_in_student_only(): void
    {
        extract($this->fixture());
        $this->post(route('student.achievements.goal'),['badge_code'=>'problem_solver','student_id'=>$peer->id])->assertRedirect(route('student.achievements'));
        $this->assertSame('problem_solver',$student->fresh()->achievement_goal);
        $this->assertNull($peer->fresh()->achievement_goal);
        $this->get(route('student.achievements'))->assertViewHas('achievementReport',fn ($report)=>$report['focus']['code']==='problem_solver');
        $this->postJson(route('student.achievements.goal'),['badge_code'=>'imaginary'])->assertUnprocessable();
        $this->quiz($student,$teacher);
        $this->postJson(route('student.achievements.goal'),['badge_code'=>'first_quiz'])->assertUnprocessable();
        $this->post(route('student.achievements.goal'),[])->assertRedirect();
        $this->assertNull($student->fresh()->achievement_goal);
        $this->withSession(['student_id'=>null])->postJson(route('student.achievements.goal'),['badge_code'=>'first_code'])->assertUnauthorized();
    }
    public function test_teacher_report_and_csv_are_limited_to_the_teachers_students_and_export_safe_cells(): void
    {
        extract($this->fixture());$this->quiz($student,$teacher);
        $this->withSession(['teacher_id'=>$teacher->id])->get(route('teacher.achievements'))->assertOk()->assertSee('MyCoder')->assertSee('PeerCoder')->assertDontSee('ForeignPrivateCoder');
        $this->get(route('teacher.achievements',['classroom'=>$room->id]))->assertSee('MyCoder')->assertDontSee('PeerCoder');
        $this->get(route('teacher.achievements',['classroom'=>$foreign->id]))->assertForbidden();
        $this->get(route('teacher.achievements.export',['classroom'=>$foreign->id]))->assertForbidden();
        $student->update(['student_id'=>'=2+3']);
        $csv=$this->get(route('teacher.achievements.export',['classroom'=>$room->id]))->assertOk()->streamedContent();
        $this->assertStringContainsString("'=2+3",$csv);
        $this->assertStringContainsString('MyCoder',$csv);
        $this->assertStringNotContainsString('PeerCoder',$csv);
        $this->assertStringNotContainsString('ForeignPrivateCoder',$csv);
        $this->withSession(['teacher_id'=>null])->get(route('teacher.achievements'))->assertRedirect(route('teacher.login'));
        $this->get(route('teacher.achievements.export'))->assertForbidden();
    }
}
