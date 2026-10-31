<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\QuizAttempt;
use App\Models\Student;
use App\Models\TeacherAccount;
use App\Models\TeacherQuiz;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SequentialQuizTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(int $count = 4): array
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-10-28 01:00:00.000000','UTC'));
        $teacher = TeacherAccount::create(['first_name'=>'Teacher','last_name'=>'Test','school'=>'School','email'=>'sequence@example.test','password'=>'password123']);
        $room = Classroom::create(['teacher_account_id'=>$teacher->id,'name'=>'C++','join_code'=>'SEQ123']);
        $student = Student::create(['classroom_id'=>$room->id,'first_name'=>'Alex','nickname'=>'Cadet','student_id'=>'SEQ123','password'=>'password123']);
        $questions = [
            ['type'=>'identification','prompt'=>'Identify the loop keyword','options'=>[],'correct'=>['while']],
            ['type'=>'true_false','prompt'=>'Is C++ compiled','options'=>['True','False'],'correct'=>['True']],
            ['type'=>'multiple_choice','prompt'=>'Select the output keyword','options'=>['cout','cin'],'correct'=>['cout']],
            ['type'=>'multiple_answer','prompt'=>'Select every loop keyword','options'=>['for','while','if'],'correct'=>['for','while']],
        ];
        $quiz = TeacherQuiz::create(['teacher_account_id'=>$teacher->id,'classroom_id'=>$room->id,'title'=>'Sequence Quiz','status'=>'published','time_limit'=>1,'questions'=>array_slice($questions,0,$count)]);
        $this->withSession(['student_id'=>$student->id]);
        return [$student,$quiz];
    }

    private function correct(QuizAttempt $attempt, int $index): mixed
    {
        $question=$attempt->questions[$index];
        return $question['type'] === 'multiple_answer' ? $question['correct'] : $question['correct'][0];
    }

    public function test_random_order_is_persistent_and_only_current_question_is_sent_to_student(): void
    {
        [$student,$quiz]=$this->fixture();
        $this->post(route('student.quiz.start',$quiz))->assertRedirect();
        $attempt=QuizAttempt::first();
        $this->assertTrue($attempt->sequential);
        $this->assertEqualsCanonicalizing($quiz->questions,$attempt->questions);
        $this->assertSame(240,$attempt->expires_at->timestamp-$attempt->started_at->timestamp);
        $page=$this->get(route('student.quiz'))->assertOk()->assertSee('Question 1 of 4')->assertSee($attempt->questions[0]['prompt']);
        foreach (array_slice($attempt->questions,1) as $question) $page->assertDontSee($question['prompt']);
        $this->post(route('student.quiz.start',$quiz))->assertRedirect();
        $this->assertSame($attempt->questions,QuizAttempt::first()->questions);
        $this->postJson(route('student.quiz.question',$quiz),['question_index'=>1,'answer'=>'for'])->assertUnprocessable();
        $this->postJson(route('student.quiz.submit',$quiz),['answers'=>['while']])->assertUnprocessable();
        foreach ([10,30,31,60] as $index=>$seconds) {
            $attempt->refresh();
            $answer=$this->correct($attempt,$index);
            $this->postJson(route('student.quiz.draft',$quiz),['question_index'=>$index,'answer'=>$answer])->assertOk();
            $this->travel($seconds)->seconds();
            $this->postJson(route('student.quiz.question',$quiz),['question_index'=>$index,'answer'=>$answer])->assertOk()->assertJsonPath('finished',$index===3);
        }
        $result=DB::table('quiz_submissions')->first();
        $this->assertSame(4,$result->score);
        $this->assertSame(10,$result->speed_bonus_xp);
        $this->assertSame(150,$student->fresh()->xp);
        $timings=QuizAttempt::first()->question_timings;
        $this->assertSame([10,30,31,60],array_column($timings,'seconds'));
        $this->get(route('student.quiz'))->assertSee('150 XP earned')->assertSee('10 seconds')->assertSee('includes +5 fast bonus');
        $this->withSession(['teacher_id'=>$quiz->teacher_account_id])->get(route('teacher.quiz.reviews'))->assertSee('Time spent: 30 seconds');
        $this->postJson(route('student.quiz.question',$quiz),['question_index'=>3,'answer'=>$answer])->assertUnprocessable();
        $this->assertSame(150,$student->fresh()->xp);
        $this->travelBack();
    }

    public function test_speed_threshold_uses_server_time_and_previous_answers_are_locked(): void
    {
        [$student,$quiz]=$this->fixture(2);
        $this->post(route('student.quiz.start',$quiz));
        $attempt=QuizAttempt::first();
        $this->travel(30001)->milliseconds();
        $this->postJson(route('student.quiz.question',$quiz),['question_index'=>0,'answer'=>$this->correct($attempt,0),'seconds'=>1,'speed_eligible'=>true])->assertOk();
        $this->postJson(route('student.quiz.question',$quiz),['question_index'=>0,'answer'=>'changed'])->assertUnprocessable();
        $this->postJson(route('student.quiz.question',$quiz),['question_index'=>1,'answer'=>$this->correct($attempt,1)])->assertOk()->assertJsonPath('finished',true);
        $this->assertSame(125,$student->fresh()->xp);
        $this->assertSame(31,QuizAttempt::first()->question_timings[0]['seconds']);
        $this->assertSame(5,DB::table('quiz_submissions')->first()->speed_bonus_xp);
        $this->travelBack();
    }

    public function test_offline_time_does_not_pause_questions_and_teacher_closing_time_is_enforced(): void
    {
        [$student,$quiz]=$this->fixture(3);
        $quiz->update(['closes_at'=>now()->addSeconds(70)]);
        $this->post(route('student.quiz.start',$quiz));
        $this->travel(61)->seconds();
        $this->get(route('student.quiz'))->assertOk()->assertSee('Question 2 of 3');
        $attempt=QuizAttempt::first();
        $this->assertSame(60,$attempt->question_timings[0]['seconds']);
        $this->postJson(route('student.quiz.draft',$quiz),['question_index'=>0,'answer'=>'while'])->assertUnprocessable();
        $this->travel(10)->seconds();
        $this->get(route('student.quiz'))->assertSee('attempt used');
        $this->assertSame([60,10,0],array_column(QuizAttempt::first()->question_timings,'seconds'));
        $this->assertSame(0,$student->fresh()->xp);
        $this->assertDatabaseCount('quiz_submissions',1);
        $this->travelBack();
    }
}
