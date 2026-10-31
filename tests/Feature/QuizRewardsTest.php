<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Student;
use App\Models\TeacherAccount;
use App\Models\TeacherQuiz;
use App\Services\QuizRewards;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class QuizRewardsTest extends TestCase
{
    use RefreshDatabase, \Tests\Supports\ExistingLegacyQuizAttempt;

    private function fixture(): array
    {
        $teacher = TeacherAccount::create(['first_name'=>'Teacher','last_name'=>'Test','email'=>'rewards@example.test','school'=>'School','password'=>'password123']);
        $room = Classroom::create(['teacher_account_id'=>$teacher->id,'name'=>'C++','join_code'=>'REWARDS']);
        $student = Student::create(['classroom_id'=>$room->id,'first_name'=>'Alex','nickname'=>'CadetAlex','student_id'=>'XP123','password'=>'password123','xp'=>550]);
        Student::create(['classroom_id'=>$room->id,'first_name'=>'Peer','nickname'=>'CadetPeer','student_id'=>'XP456','password'=>'password123','xp'=>600]);
        $quiz = TeacherQuiz::create(['teacher_account_id'=>$teacher->id,'classroom_id'=>$room->id,'title'=>'XP Quiz','status'=>'published','time_limit'=>5,'questions'=>[
            ['type'=>'identification','prompt'=>'Loop?', 'options'=>[], 'correct'=>['while']],
            ['type'=>'true_false','prompt'=>'Compiled?', 'options'=>['True','False'], 'correct'=>['True']],
        ]]);
        $this->withSession(['student_id'=>$student->id]);
        return [$student,$quiz];
    }

    public function test_perfect_quiz_awards_answer_xp_bonus_and_level_once(): void
    {
        [$student,$quiz] = $this->fixture();
        $this->beginLegacyQuiz($quiz)->assertRedirect();
        $this->post(route('student.quiz.submit',$quiz),['answers'=>['while','True'],'xp'=>99999])->assertRedirect();
        $this->assertDatabaseHas('quiz_submissions',['student_id'=>$student->id,'answer_xp'=>20,'perfect_bonus_xp'=>100]);
        $this->assertSame(670,$student->fresh()->xp);
        $this->assertSame(2,$student->fresh()->level);
        $this->get(route('student.quiz'))->assertOk()->assertSee('+120 XP earned')->assertSee('Perfect score!');
        $this->get(route('student.code-lab'))->assertOk()->assertSee('670 XP');
        $this->get(route('student.leaderboard'))->assertOk()->assertSeeInOrder(['CadetAlex','670 XP','CadetPeer','600 XP']);
        $this->postJson(route('student.quiz.submit',$quiz),['answers'=>['while','True']])->assertUnprocessable();
        $this->get(route('student.quiz'))->assertOk();
        app(QuizRewards::class)->awardPending($student);
        $this->assertSame(670,$student->fresh()->xp);
    }

    public function test_partial_and_zero_scores_never_award_a_perfect_bonus(): void
    {
        [$student,$quiz] = $this->fixture();
        $this->beginLegacyQuiz($quiz);
        $this->post(route('student.quiz.submit',$quiz),['answers'=>['while','False']])->assertRedirect();
        $this->assertDatabaseHas('quiz_submissions',['teacher_quiz_id'=>$quiz->id,'answer_xp'=>10,'perfect_bonus_xp'=>0]);
        $this->assertSame(560,$student->fresh()->xp);
        $second = $quiz->replicate(); $second->title='Zero score'; $second->save();
        $this->beginLegacyQuiz($second);
        $this->post(route('student.quiz.submit',$second),['answers'=>[]])->assertRedirect();
        $this->assertDatabaseHas('quiz_submissions',['teacher_quiz_id'=>$second->id,'answer_xp'=>0,'perfect_bonus_xp'=>0]);
        $this->assertSame(560,$student->fresh()->xp);
    }

    public function test_expired_drafts_receive_rewards_and_legacy_scores_are_credited_once(): void
    {
        [$student,$quiz] = $this->fixture();
        $this->beginLegacyQuiz($quiz);
        $this->postJson(route('student.quiz.draft',$quiz),['answers'=>['while','True']])->assertOk();
        $this->travel(6)->minutes();
        $this->get(route('student.progress'))->assertOk()->assertSee('670');
        $this->assertSame(670,$student->fresh()->xp);
        $this->get(route('student.progress'));
        $this->assertSame(670,$student->fresh()->xp);
        $this->travelBack();
        $legacy = $quiz->replicate(); $legacy->title='Legacy partial grading'; $legacy->save();
        DB::table('quiz_submissions')->insert(['student_id'=>$student->id,'teacher_quiz_id'=>$legacy->id,'answers'=>'[]','score'=>1,'graded_questions'=>1,'total_questions'=>2,'created_at'=>now(),'updated_at'=>now()]);
        app(QuizRewards::class)->awardPending($student);
        $this->assertDatabaseHas('quiz_submissions',['teacher_quiz_id'=>$legacy->id,'answer_xp'=>10,'perfect_bonus_xp'=>0]);
        app(QuizRewards::class)->awardPending($student);
        $this->assertSame(680,$student->fresh()->xp);
    }
}
