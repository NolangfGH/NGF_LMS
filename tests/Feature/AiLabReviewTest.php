<?php

namespace Tests\Feature;

use App\Models\{Classroom,Student,TeacherAccount,TeacherAssignment,CodeWorkspace,LabSubmission};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\{Cache,Http};
use Tests\TestCase;

class AiLabReviewTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        config(['services.openai.api_key'=>'test-server-key-not-real','services.openai.model'=>'gpt-4.1-mini']);
        Http::preventStrayRequests();
        $teacher=TeacherAccount::create(['first_name'=>'Owner','last_name'=>'Teacher','school'=>'Private school name','email'=>'ai@example.test','password'=>'password123']);
        $other=TeacherAccount::create(['first_name'=>'Other','last_name'=>'Teacher','school'=>'School','email'=>'aiforeign@example.test','password'=>'password123']);
        $room=Classroom::create(['teacher_account_id'=>$teacher->id,'name'=>'C++','join_code'=>'AILAB1']);
        $student=Student::create(['classroom_id'=>$room->id,'first_name'=>'PrivateStudentName','nickname'=>'PrivateNickname','student_id'=>'PRIVATE-STUDENT-ID','password'=>'password123','xp'=>200]);
        $assignment=TeacherAssignment::create(['teacher_account_id'=>$teacher->id,'classroom_id'=>$room->id,'title'=>'Print 42','objective'=>'Print 42 on one line.','sample_output'=>'42','status'=>'published','xp'=>100]);
        $workspace=CodeWorkspace::create(['student_id'=>$student->id,'teacher_assignment_id'=>$assignment->id,'activity_key'=>'assignment:'.$assignment->id,'title'=>$assignment->title,'code'=>"#include <iostream>\nint main(){std::cout >> 42;}",'reflection'=>'My program displays the answer with cout.','xp_earned'=>100,'assignment_opened_at'=>now()]);
        $submission=$workspace->submissions()->create(['teacher_account_id'=>$teacher->id,'revision'=>1,'title'=>$workspace->title,'code'=>$workspace->code,'reflection'=>$workspace->reflection,'reward_budget'=>100,'objective'=>$assignment->objective,'sample_output'=>$assignment->sample_output]);
        $this->withSession(['teacher_id'=>$teacher->id,'student_id'=>$student->id]);
        return compact('teacher','other','student','assignment','workspace','submission');
    }
    private function draft(): array
    {
        return [
            'rubric'=>['correctness'=>1,'logic'=>2,'readability'=>3,'explanation'=>2],
            'rationale'=>['correctness'=>'AI_ONLY_RATIONALE: cout uses the wrong stream operator.','logic'=>'An output step exists but cannot produce the required output.','readability'=>'The short main function is understandable.','explanation'=>'The explanation describes output but does not mention testing.'],
            'feedback'=>'Your main function is organized. Change cout >> to cout <<, then test whether the output is exactly 42.',
            'limitations'=>['Static source assessment only; no compilation was performed.'],
        ];
    }
    private function resetHttp(): void
    {
        Http::swap(new \Illuminate\Http\Client\Factory());
        Http::preventStrayRequests();
    }
    private function providerResponse(?array $draft=null): array
    {
        return ['id'=>'resp_test','status'=>'completed','output'=>[['type'=>'message','content'=>[['type'=>'output_text','text'=>json_encode($draft ?? $this->draft())]]]]];
    }

    public function test_ai_saves_only_a_draft_uses_immutable_context_and_reuses_it_without_charging_again(): void
    {
        extract($this->fixture());
        Http::fake(['api.openai.com/*'=>Http::response($this->providerResponse())]);
        $workspace->update(['code'=>'int main(){return 9;}']);
        $assignment->update(['objective'=>'A different objective edited later.']);
        $this->postJson(route('teacher.code-lab.ai',$submission),['score'=>16,'reviewed_at'=>now()])->assertOk()->assertJsonPath('draft_only',true)->assertJsonPath('suggestion.rubric.correctness',1);
        $current=$submission->fresh();
        $this->assertSame($this->draft(),$current->ai_suggestion);
        $this->assertNotNull($current->ai_generated_at);
        $this->assertNull($current->score);
        $this->assertNull($current->rubric);
        $this->assertNull($current->feedback);
        $this->assertNull($current->reviewed_at);
        $this->assertNull($current->reviewed_by_teacher_id);
        $this->assertSame(200,$student->fresh()->xp);
        Http::assertSent(function ($request) use ($submission) {
            $input=json_decode($request['input'][0]['content'],true);
            return $request->url()==='https://api.openai.com/v1/responses' &&
                $request->hasHeader('Authorization','Bearer test-server-key-not-real') &&
                $request['store']===false && !isset($request['tools']) &&
                $request['text']['format']['strict']===true &&
                $input['submitted_cpp_source']===$submission->code &&
                $input['original_objective']==='Print 42 on one line.' &&
                !str_contains($request->body(),'PRIVATE-STUDENT-ID') &&
                !str_contains($request->body(),'PrivateStudentName') &&
                !str_contains($request->body(),'Private school name');
        });
        $this->postJson(route('teacher.code-lab.ai',$submission))->assertOk();
        Http::assertSentCount(1);
        $this->get(route('teacher.code-lab'))->assertOk()->assertSee('AI-assisted rubric draft')->assertSee('Use saved AI suggestions')->assertDontSee('test-server-key-not-real');
        $this->get(route('student.code-lab',['assignment'=>$assignment->id]))->assertOk()->assertDontSee('AI_ONLY_RATIONALE')->assertDontSee($this->draft()['feedback']);
    }
    public function test_teacher_can_change_all_ai_scores_and_feedback_and_only_final_save_publishes_them(): void
    {
        extract($this->fixture());
        Http::fake(['api.openai.com/*'=>Http::response($this->providerResponse())]);
        $this->postJson(route('teacher.code-lab.ai',$submission))->assertOk();
        $rubric=['correctness'=>2,'logic'=>1,'readability'=>4,'explanation'=>1];
        $feedback='Teacher decision: improve the stream operator and explain the test you used.';
        $this->post(route('teacher.code-lab.review',$submission),['rubric'=>$rubric,'feedback'=>$feedback])->assertRedirect();
        $current=$submission->fresh();
        $this->assertSame($rubric,$current->rubric);
        $this->assertSame(8,$current->score);
        $this->assertSame($feedback,$current->feedback);
        $this->assertSame($teacher->id,$current->reviewed_by_teacher_id);
        $this->assertSame($this->draft(),$current->ai_suggestion);
        $this->assertSame(200,$student->fresh()->xp);
        $this->get(route('student.code-lab',['assignment'=>$assignment->id]))->assertSee($feedback)->assertDontSee('AI_ONLY_RATIONALE');
        $this->postJson(route('teacher.code-lab.ai',$submission))->assertConflict();
        $this->postJson(route('teacher.code-lab.review',$submission),['rubric'=>$rubric,'feedback'=>'An attempt to change a finalized review.'])->assertUnprocessable();
        $this->assertSame($feedback,$submission->fresh()->feedback);
        Http::assertSentCount(1);
    }
    public function test_students_guests_and_other_teachers_cannot_generate_or_finalize_a_review(): void
    {
        extract($this->fixture());Http::fake();
        $payload=['rubric'=>array_fill_keys(array_keys($this->draft()['rubric']),4),'feedback'=>'Trying to publish a final grade.'];
        foreach ([null,$other->id] as $teacherId) {
            $this->withSession(['teacher_id'=>$teacherId])->postJson(route('teacher.code-lab.ai',$submission))->assertForbidden();
            $this->postJson(route('teacher.code-lab.review',$submission),$payload)->assertForbidden();
        }
        $this->withSession(['teacher_id'=>null,'student_id'=>null])->postJson(route('teacher.code-lab.ai',$submission))->assertForbidden();
        Http::assertNothingSent();
        $this->assertNull($submission->fresh()->reviewed_at);
        $this->assertSame(200,$student->fresh()->xp);
    }
    public function test_missing_key_and_provider_errors_keep_manual_review_usable_and_hide_provider_details(): void
    {
        extract($this->fixture());Http::fake();
        config(['services.openai.api_key'=>'']);
        $this->postJson(route('teacher.code-lab.ai',$submission))->assertStatus(503)->assertJsonPath('message','AI suggestions are not configured yet. Ask the administrator to connect the AI service. You can still review manually.');
        $this->get(route('teacher.code-lab'))->assertOk()->assertSee('AI is not configured yet')->assertSee('Save feedback & learning score',false);
        Http::assertNothingSent();
        config(['services.openai.api_key'=>'test-server-key-not-real']);
        foreach ([401,429,500] as $status) {
            $this->resetHttp();
            Http::fake(['api.openai.com/*'=>Http::response(['error'=>['message'=>'SENSITIVE provider message containing a key']],$status)]);
            $this->postJson(route('teacher.code-lab.ai',$submission))->assertStatus(503)->assertDontSee('SENSITIVE')->assertDontSee('test-server-key-not-real');
        }
        $this->resetHttp();
        Http::fake(fn ()=>throw new ConnectionException('SENSITIVE connection diagnostics'));
        $this->postJson(route('teacher.code-lab.ai',$submission))->assertStatus(503)->assertSee('did not respond in time')->assertDontSee('SENSITIVE');
        $this->assertNull($submission->fresh()->ai_suggestion);
        $this->assertNull($submission->fresh()->score);
        $this->post(route('teacher.code-lab.review',$submission),['rubric'=>array_fill_keys(array_keys($this->draft()['rubric']),2),'feedback'=>'Manual teacher feedback works even while AI is unavailable.'])->assertRedirect();
        $this->assertSame(8,$submission->fresh()->score);
    }
    public function test_invalid_refused_and_incomplete_ai_results_never_save_scores_or_drafts(): void
    {
        extract($this->fixture());
        $tooHigh=$this->draft();$tooHigh['rubric']['correctness']=9;
        $stringScore=$this->draft();$stringScore['rubric']['logic']='4';
        $missing=$this->draft();unset($missing['rationale']['explanation']);
        $responses=[
            $this->providerResponse($tooHigh),$this->providerResponse($stringScore),$this->providerResponse($missing),
            ['status'=>'incomplete','output'=>[]],
            ['status'=>'completed','output'=>'not an array'],
            ['status'=>'completed','output'=>[['type'=>'message','content'=>[['type'=>'refusal','refusal'=>'Unable to assess.']]]]],
            ['status'=>'completed','output'=>[['type'=>'message','content'=>[['type'=>'output_text','text'=>'not JSON']]]]],
        ];
        foreach ($responses as $response) {
            $this->resetHttp();
            Http::fake(['api.openai.com/*'=>Http::response($response)]);
            $this->postJson(route('teacher.code-lab.ai',$submission))->assertStatus(502);
            $this->assertNull($submission->fresh()->ai_suggestion);
            $this->assertNull($submission->fresh()->reviewed_at);
        }
        $this->assertSame(200,$student->fresh()->xp);
    }
    public function test_teacher_finalization_during_generation_is_never_overwritten_by_the_ai(): void
    {
        extract($this->fixture());
        Http::fake(function () use ($submission,$teacher) {
            $submission->update(['rubric'=>array_fill_keys(array_keys($this->draft()['rubric']),1),'score'=>4,'feedback'=>'Final teacher feedback saved while the AI was preparing its draft.','reviewed_at'=>now(),'reviewed_by_teacher_id'=>$teacher->id]);
            return Http::response($this->providerResponse());
        });
        $this->postJson(route('teacher.code-lab.ai',$submission))->assertConflict();
        $this->assertSame(4,$submission->fresh()->score);
        $this->assertNull($submission->fresh()->ai_suggestion);
        $this->assertSame($teacher->id,$submission->fresh()->reviewed_by_teacher_id);
    }
    public function test_cached_drafts_work_without_a_key_and_parallel_generation_is_locked(): void
    {
        extract($this->fixture());Http::fake();
        $lock=Cache::lock('lab-ai-draft:'.$submission->id,45);$this->assertTrue($lock->get());
        $this->postJson(route('teacher.code-lab.ai',$submission))->assertConflict();
        $lock->release();
        $submission->update(['ai_suggestion'=>$this->draft(),'ai_model'=>'gpt-4.1-mini','ai_generated_at'=>now()]);
        config(['services.openai.api_key'=>'']);
        $this->postJson(route('teacher.code-lab.ai',$submission))->assertOk()->assertJsonPath('suggestion.rubric.readability',3);
        Http::assertNothingSent();
    }
}
