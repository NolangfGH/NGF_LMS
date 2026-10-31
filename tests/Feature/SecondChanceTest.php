<?php

namespace Tests\Feature;

use App\Models\{Classroom,Student,TeacherAccount,TeacherAssignment,TeacherQuiz,SecondChanceRequest,CodeWorkspace,LabSubmission,QuizAttempt};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB,Storage};
use Tests\TestCase;

class SecondChanceTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $this->travelTo(now()->startOfSecond());
        Storage::fake('local');
        $teacher=TeacherAccount::create(['first_name'=>'Owner','last_name'=>'Teacher','school'=>'School','email'=>'excuses@example.test','password'=>'password123']);
        $other=TeacherAccount::create(['first_name'=>'Other','last_name'=>'Teacher','school'=>'School','email'=>'otherexcuses@example.test','password'=>'password123']);
        $room=Classroom::create(['teacher_account_id'=>$teacher->id,'name'=>'C++','grade'=>'Grade 8','section'=>'A','join_code'=>'EXCUSE1']);
        $student=Student::create(['classroom_id'=>$room->id,'first_name'=>'Alex','nickname'=>'ExcusedCoder','student_id'=>'EXCUSE1','password'=>'password123','xp'=>100]);
        $peer=Student::create(['classroom_id'=>$room->id,'first_name'=>'Peer','nickname'=>'PeerCoder','student_id'=>'EXCUSE2','password'=>'password123','xp'=>100]);
        $assignment=TeacherAssignment::create(['teacher_account_id'=>$teacher->id,'classroom_id'=>$room->id,'title'=>'Missed assignment','status'=>'published','xp'=>150,'due_at'=>now()->subHour(),'time_limit_minutes'=>1]);
        $quiz=TeacherQuiz::create(['teacher_account_id'=>$teacher->id,'classroom_id'=>$room->id,'title'=>'Missed quiz','status'=>'published','opens_at'=>now()->subHours(2),'closes_at'=>now()->subHour(),'questions'=>[
            ['type'=>'identification','prompt'=>'Loop keyword?','options'=>[],'correct'=>['while']],
            ['type'=>'identification','prompt'=>'Integer keyword?','options'=>[],'correct'=>['int']],
        ]]);
        $this->withSession(['student_id'=>$student->id,'teacher_id'=>null]);
        return compact('teacher','other','student','peer','assignment','quiz');
    }
    private function proof(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('excuse.png',base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jf1sAAAAASUVORK5CYII='));
    }
    private function requestExcuse(string $type, $activity): SecondChanceRequest
    {
        $this->post(route('student.excuses.store',['type'=>$type,'activity'=>$activity->id]),['reason'=>'I was absent due to illness and could not complete the activity.','proof'=>$this->proof()])->assertRedirect()->assertSessionHas('excuse_notice');
        return SecondChanceRequest::latest('id')->firstOrFail();
    }
    private function approve(SecondChanceRequest $excuse, TeacherAccount $teacher, int $minutes=1): void
    {
        $this->withSession(['teacher_id'=>$teacher->id])->post(route('teacher.excuses.review',$excuse),['decision'=>'approved','teacher_note'=>'Your valid excuse has been accepted. Please use this final opportunity.','expires_at'=>now()->addMinutes(20)->timezone(config('learning.timezone'))->format('Y-m-d\\TH:i'),'duration_minutes'=>$minutes])->assertRedirect()->assertSessionHas('excuse_notice');
    }
    private function finishQuiz(TeacherQuiz $quiz, bool $perfect): void
    {
        $attempt=QuizAttempt::where('teacher_quiz_id',$quiz->id)->orderByDesc('attempt_number')->firstOrFail();
        foreach ($attempt->questions as $index=>$question) {
            $this->postJson(route('student.quiz.question',$quiz),['question_index'=>$index,'answer'=>$perfect || $index===0 ? $question['correct'][0] : 'wrong','attempt_id'=>$attempt->id])->assertOk();
        }
    }

    public function test_request_is_pending_and_proof_is_private_to_its_student_and_owning_teacher(): void
    {
        extract($this->fixture());
        $excuse=$this->requestExcuse('assignment',$assignment);
        Storage::disk('local')->assertExists($excuse->proof_path);
        $this->assertSame('pending',$excuse->status);
        $this->assertSame($teacher->id,$excuse->teacher_account_id);
        $this->get(route('student.assignments'))->assertOk()->assertSee('Pending')->assertSee('Code Lab closed');
        $this->get(route('excuses.proof',$excuse))->assertOk()->assertHeader('X-Content-Type-Options','nosniff');
        $this->get(route('student.code-lab',['assignment'=>$assignment->id]))->assertForbidden();
        $this->withSession(['student_id'=>$peer->id,'teacher_id'=>null])->get(route('excuses.proof',$excuse))->assertForbidden();
        $this->withSession(['student_id'=>null,'teacher_id'=>$other->id])->get(route('excuses.proof',$excuse))->assertForbidden();
        $this->get(route('teacher.excuses'))->assertOk()->assertDontSee($excuse->reason);
        $this->withSession(['teacher_id'=>$teacher->id])->get(route('excuses.proof',$excuse))->assertOk();
        $this->get(route('teacher.excuses'))->assertOk()->assertSee($excuse->reason)->assertSee('Missed assignment');
        $this->withSession(['teacher_id'=>null])->get(route('excuses.proof',$excuse))->assertForbidden();
    }
    public function test_upload_validation_and_request_eligibility_prevent_unverified_or_repeated_requests(): void
    {
        extract($this->fixture());
        $url=route('student.excuses.store',['type'=>'assignment','activity'=>$assignment->id]);
        $this->postJson($url,['reason'=>'Too short'])->assertUnprocessable()->assertJsonValidationErrors(['reason','proof']);
        $this->postJson($url,['reason'=>str_repeat('Reason ',5),'proof'=>UploadedFile::fake()->createWithContent('fake.png','not an image')])->assertUnprocessable();
        $this->postJson($url,['reason'=>str_repeat('Reason ',5),'proof'=>$this->proof()->size(3000)])->assertUnprocessable();
        $assignment->update(['due_at'=>now()->addHour()]);
        $this->postJson($url,['reason'=>str_repeat('Reason ',5),'proof'=>$this->proof()])->assertUnprocessable();
        $assignment->update(['due_at'=>now()->subHour()]);
        $excuse=$this->requestExcuse('assignment',$assignment);
        $this->postJson($url,['reason'=>str_repeat('Reason ',5),'proof'=>$this->proof()])->assertUnprocessable();
        $this->assertDatabaseCount('second_chance_requests',1);
        $this->assertCount(1,Storage::disk('local')->allFiles('excuse-proofs'));
        $foreign=Classroom::create(['teacher_account_id'=>$other->id,'name'=>'Other class','join_code'=>'FOREIGNEXCUSE']);
        $assignment->update(['classroom_id'=>$foreign->id]);
        $this->postJson($url,['reason'=>str_repeat('Reason ',5),'proof'=>$this->proof()])->assertForbidden();
        $this->withSession(['student_id'=>null])->postJson($url,[])->assertUnauthorized();
        $this->get(route('student.assignments'))->assertRedirect(route('student.login'));
    }
    public function test_assignment_approval_grants_one_new_timer_preserves_work_and_does_not_repeat_xp(): void
    {
        extract($this->fixture());
        $assignment->update(['due_at'=>now()->addHour()]);
        $this->post(route('student.assignments.open',$assignment))->assertRedirect();
        $workspace=CodeWorkspace::firstOrFail();
        $this->putJson(route('student.code-lab.save',$workspace),['revision'=>1,'code'=>'int main(){return 3;}','reflection'=>'My first solution needs improvement and testing.'])->assertOk();
        $this->postJson(route('student.code-lab.submit',$workspace),['revision'=>2])->assertOk();
        $assignment->update(['due_at'=>now()->subMinute()]);
        $excuse=$this->requestExcuse('assignment',$assignment);
        $this->approve($excuse,$teacher);
        $this->withSession(['teacher_id'=>null])->get(route('student.assignments'))->assertOk()->assertSee('Approved by your teacher')->assertSee('Continue Code Lab')->assertDontSee('data-open-button disabled',false);
        $this->post(route('student.assignments.open',$assignment))->assertRedirect();
        $workspace->refresh();
        $this->assertSame($excuse->id,$workspace->second_chance_request_id);
        $this->assertSame(60,$workspace->time_limit_seconds);
        $this->assertSame('int main(){return 3;}',$workspace->code);
        $this->assertSame(250,$student->fresh()->xp);
        $started=$workspace->timer_started_at->toIso8601String();
        $this->get(route('student.code-lab',['assignment'=>$assignment->id]))->assertOk();
        $this->putJson(route('student.code-lab.save',$workspace),['revision'=>$workspace->revision,'code'=>'int main(){return 4;}','reflection'=>'I improved my solution for the final opportunity.'])->assertOk();
        $this->postJson(route('student.code-lab.submit',$workspace),['revision'=>$workspace->fresh()->revision])->assertOk();
        $this->assertDatabaseCount('lab_submissions',2);
        $this->assertSame('int main(){return 3;}',LabSubmission::oldest('id')->first()->code);
        $this->assertSame($excuse->id,LabSubmission::latest('id')->first()->second_chance_request_id);
        $this->post(route('student.assignments.open',$assignment))->assertRedirect();
        $this->assertSame($started,$workspace->fresh()->timer_started_at->toIso8601String());
        $this->travel(60)->seconds();
        $this->postJson(route('student.assignments.open',$assignment))->assertUnprocessable();
        $this->putJson(route('student.code-lab.save',$workspace),['revision'=>$workspace->fresh()->revision,'code'=>'int main(){}'])->assertUnprocessable();
        $this->assertSame(250,$student->fresh()->xp);
        $this->postJson(route('student.excuses.store',['type'=>'assignment','activity'=>$assignment->id]),['reason'=>str_repeat('Reason ',5),'proof'=>$this->proof()])->assertUnprocessable();
    }
    public function test_final_quiz_retains_original_result_caps_xp_and_blocks_a_third_attempt(): void
    {
        extract($this->fixture());
        $quiz->update(['closes_at'=>now()->addHour()]);
        $this->post(route('student.quiz.start',$quiz))->assertRedirect();
        $this->finishQuiz($quiz,false);
        $original=DB::table('quiz_submissions')->first();
        $this->assertSame(1,$original->score);
        $this->assertSame(115,$student->fresh()->xp);
        $quiz->update(['closes_at'=>now()->subMinute()]);
        $excuse=$this->requestExcuse('quiz',$quiz);
        $this->postJson(route('student.quiz.start',$quiz))->assertUnprocessable();
        $this->approve($excuse,$teacher);
        $this->withSession(['teacher_id'=>null])->get(route('student.quiz'))->assertOk()->assertSee('Start Final Quiz')->assertSee('Approved by your teacher');
        $this->post(route('student.quiz.start',$quiz))->assertRedirect();
        $final=QuizAttempt::latest('id')->firstOrFail();
        $this->assertSame(2,$final->attempt_number);
        $this->assertSame($excuse->id,$final->second_chance_request_id);
        $this->assertTrue($final->expires_at->gt(now()));
        $this->postJson(route('student.quiz.question',$quiz),['question_index'=>0,'answer'=>'wrong','attempt_id'=>$final->id-1])->assertUnprocessable();
        $this->get(route('student.quiz'))->assertOk()->assertSee('Save &');
        $this->finishQuiz($quiz,true);
        $this->assertDatabaseCount('quiz_submissions',2);
        $this->assertDatabaseCount('quiz_attempts',2);
        $this->assertSame($original->answers,DB::table('quiz_submissions')->where('id',$original->id)->value('answers'));
        $this->assertSame(230,$student->fresh()->xp);
        $this->assertDatabaseHas('quiz_submissions',['attempt_number'=>2,'score'=>2,'xp_credited'=>115]);
        $this->get(route('student.quiz'))->assertOk()->assertSee('+115 XP earned')->assertSee('Saved score: 2 / 2');
        $this->postJson(route('student.quiz.start',$quiz))->assertUnprocessable();
        $this->postJson(route('student.quiz.question',$quiz),['question_index'=>0,'answer'=>'while'])->assertUnprocessable();
        $this->withSession(['teacher_id'=>$teacher->id])->get(route('teacher.quiz.reviews'))->assertOk()->assertSee('Original attempt')->assertSee('Second and final opportunity');
        $this->postJson(route('teacher.excuses.review',$excuse),['decision'=>'approved','teacher_note'=>'Trying to approve twice','expires_at'=>now()->addDay()->format('Y-m-d\\TH:i')])->assertUnprocessable();
    }
    public function test_missed_quiz_without_first_attempt_can_use_final_approval_and_expiry_is_enforced(): void
    {
        extract($this->fixture());
        $excuse=$this->requestExcuse('quiz',$quiz);
        $this->approve($excuse,$teacher);
        $this->post(route('student.quiz.start',$quiz))->assertRedirect();
        $attempt=QuizAttempt::firstOrFail();
        $this->assertSame(2,$attempt->attempt_number);
        $this->travel(121)->seconds();
        $this->get(route('student.quiz'))->assertOk()->assertSee('Quiz completed');
        $this->assertDatabaseHas('quiz_submissions',['attempt_number'=>2,'score'=>0]);
        $this->postJson(route('student.quiz.start',$quiz))->assertUnprocessable();
        $this->assertSame(100,$student->fresh()->xp);
        $this->assertDatabaseCount('quiz_attempts',1);
    }
    public function test_only_own_teacher_may_review_and_declined_or_expired_approvals_do_not_reopen_work(): void
    {
        extract($this->fixture());
        $excuse=$this->requestExcuse('assignment',$assignment);
        $data=['decision'=>'approved','teacher_note'=>'A valid reason was provided.','expires_at'=>now()->addHour()->timezone(config('learning.timezone'))->format('Y-m-d\\TH:i')];
        $this->postJson(route('teacher.excuses.review',$excuse),$data)->assertForbidden();
        $this->withSession(['teacher_id'=>$other->id])->postJson(route('teacher.excuses.review',$excuse),$data)->assertForbidden();
        $this->withSession(['teacher_id'=>$teacher->id])->postJson(route('teacher.excuses.review',$excuse),[...$data,'expires_at'=>now()->subHour()->timezone(config('learning.timezone'))->format('Y-m-d\\TH:i')])->assertUnprocessable();
        $this->post(route('teacher.excuses.review',$excuse),['decision'=>'declined','teacher_note'=>'Please discuss the missing evidence with me.'])->assertRedirect();
        $this->get(route('student.assignments'))->assertSee('Declined')->assertSee('Code Lab closed');
        $this->postJson(route('student.assignments.open',$assignment))->assertUnprocessable();
        $quizExcuse=$this->requestExcuse('quiz',$quiz);
        $this->approve($quizExcuse,$teacher);
        $this->travel(21)->minutes();
        $this->postJson(route('student.quiz.start',$quiz))->assertUnprocessable();
        $this->get(route('student.quiz'))->assertOk()->assertSee('final opportunity has expired');
        $this->assertSame(100,$student->fresh()->xp);
    }
    public function test_teachers_final_deadline_caps_the_quiz_even_when_its_original_schedule_is_closed(): void
    {
        extract($this->fixture());
        $this->travelTo(now()->startOfMinute());
        $excuse=$this->requestExcuse('quiz',$quiz);
        $end=now()->addMinute();
        $this->withSession(['teacher_id'=>$teacher->id])->post(route('teacher.excuses.review',$excuse),[
            'decision'=>'approved','teacher_note'=>'Complete the final quiz within this short approval window.',
            'expires_at'=>$end->copy()->timezone(config('learning.timezone'))->format('Y-m-d\\TH:i'),
        ])->assertRedirect();
        $this->post(route('student.quiz.start',$quiz))->assertRedirect();
        $attempt=QuizAttempt::firstOrFail();
        $this->assertSame($end->getTimestampMs(),$attempt->expires_at->getTimestampMs());
        $this->travel(60)->seconds();
        $this->get(route('student.quiz'))->assertOk()->assertSee('Quiz completed')->assertSee('final opportunity has expired');
        $this->postJson(route('student.quiz.question',$quiz),['attempt_id'=>$attempt->id,'question_index'=>0,'answer'=>'while'])->assertUnprocessable();
        $this->assertSame(100,$student->fresh()->xp);
        $this->assertDatabaseHas('quiz_submissions',['attempt_number'=>2,'score'=>0]);
    }
}
