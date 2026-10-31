<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\QuizAttempt;
use App\Models\Student;
use App\Models\TeacherAccount;
use App\Models\TeacherQuiz;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TimedQuizTest extends TestCase
{
    use RefreshDatabase, \Tests\Supports\ExistingLegacyQuizAttempt;

    private function fixture(): array
    {
        $teacher = TeacherAccount::create(['first_name'=>'Teacher','last_name'=>'Test','school'=>'School','email'=>'timer@example.test','password'=>'password123']);
        $room = Classroom::create(['teacher_account_id'=>$teacher->id,'name'=>'C++','grade'=>'Grade 8','section'=>'A','join_code'=>'TIMER123']);
        $student = Student::create(['classroom_id'=>$room->id,'first_name'=>'Alex','nickname'=>'Cadet','student_id'=>'TIMER1','password'=>'password123']);
        $quiz = TeacherQuiz::create(['teacher_account_id'=>$teacher->id,'classroom_id'=>$room->id,'title'=>'Timed quiz','status'=>'published','time_limit'=>15,'opens_at'=>'2026-10-28 01:00:00','closes_at'=>'2026-10-28 01:02:00','questions'=>[
            ['type'=>'identification','prompt'=>'Loop keyword?', 'options'=>[], 'correct'=>['while']],
            ['type'=>'multiple_answer','prompt'=>'Select loops', 'options'=>['for','while','if'], 'correct'=>['for','while']],
        ]]);
        $this->withSession(['student_id'=>$student->id]);
        return [$student,$quiz];
    }

    public function test_timer_survives_refresh_and_expiry_submits_only_saved_answers_once(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-10-28 00:59:00','UTC'));
        [$student,$quiz] = $this->fixture();
        $this->postJson(route('student.quiz.start',$quiz))->assertUnprocessable();
        $this->postJson(route('student.quiz.submit',$quiz),['answers'=>['while']])->assertUnprocessable();
        $this->assertDatabaseCount('quiz_attempts',0);
        $this->travelTo(\Carbon\Carbon::parse('2026-10-28 01:00:00','UTC'));
        $this->get(route('student.quiz'))->assertSee('Start Quiz')->assertDontSee('Loop keyword?');
        $this->beginLegacyQuiz($quiz)->assertRedirect();
        $attempt = QuizAttempt::first();
        $this->assertSame('2026-10-28 01:02:00',$attempt->expires_at->format('Y-m-d H:i:s'));
        $this->get(route('student.quiz'))->assertSee('Time remaining')->assertSee('Loop keyword?')->assertSee('timer-bar');
        $this->postJson(route('student.quiz.draft',$quiz),['answers'=>['while',['while','for']]])->assertOk()->assertJsonPath('finished',false);
        $this->get(route('student.quiz'))->assertSee('value="while"',false);
        $this->travelTo(\Carbon\Carbon::parse('2026-10-28 01:01:00','UTC'));
        $this->beginLegacyQuiz($quiz)->assertRedirect();
        $this->assertSame($attempt->expires_at->timestamp,QuizAttempt::first()->expires_at->timestamp);
        $this->assertDatabaseCount('quiz_attempts',1);
        // Editing the live answer key does not rewrite an attempt already in progress.
        $quiz->update(['questions'=>[['type'=>'identification','prompt'=>'Edited question','options'=>[],'correct'=>['new key']]]]);
        $this->travelTo(\Carbon\Carbon::parse('2026-10-28 01:02:00','UTC'));
        $this->post(route('student.quiz.submit',$quiz),['answers'=>['late answer']])->assertRedirect();
        $this->assertDatabaseHas('quiz_submissions',['student_id'=>$student->id,'score'=>2,'graded_questions'=>2]);
        $this->postJson(route('student.quiz.submit',$quiz),['answers'=>['changed']])->assertUnprocessable();
        $this->postJson(route('student.quiz.start',$quiz))->assertUnprocessable();
        $this->get(route('student.quiz'))->assertSee('attempt used')->assertDontSee('timed-quiz space-y-5');
        $this->assertDatabaseCount('quiz_submissions',1);
        $this->travelBack();
    }

    public function test_empty_expired_attempt_is_completed_on_return_and_manual_submission_is_final(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-10-28 01:00:00','UTC'));
        [$student,$quiz] = $this->fixture();
        $quiz->update(['closes_at'=>null,'time_limit'=>1]);
        $this->beginLegacyQuiz($quiz)->assertRedirect();
        $this->postJson(route('student.quiz.draft',$quiz),['answers'=>['while',['invented']]])->assertUnprocessable();
        $this->travel(61)->seconds();
        $this->get(route('student.quiz'))->assertOk()->assertSee('Saved score: 0 / 2');
        $this->assertNotNull(QuizAttempt::first()->submitted_at);
        $this->postJson(route('student.quiz.draft',$quiz),['answers'=>['while']])->assertUnprocessable();
        $newQuiz = TeacherQuiz::create(['teacher_account_id'=>$quiz->teacher_account_id,'classroom_id'=>$quiz->classroom_id,'title'=>'Second quiz','status'=>'published','time_limit'=>2,'questions'=>$quiz->questions]);
        $this->beginLegacyQuiz($newQuiz)->assertRedirect();
        $this->post(route('student.quiz.submit',$newQuiz),['answers'=>['while']])->assertRedirect();
        $this->assertDatabaseHas('quiz_submissions',['teacher_quiz_id'=>$newQuiz->id,'score'=>1]);
        $this->postJson(route('student.quiz.submit',$newQuiz),['answers'=>['while',['while','for']]])->assertUnprocessable();
        $this->travelBack();
    }
}
