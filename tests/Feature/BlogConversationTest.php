<?php

namespace Tests\Feature;

use App\Models\BlogComment;
use App\Models\BlogPost;
use App\Models\BlogResponse;
use App\Models\Classroom;
use App\Models\Student;
use App\Models\TeacherAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BlogConversationTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        Storage::fake('local');
        $teacher = TeacherAccount::create(['first_name'=>'Discussion','last_name'=>'Teacher','email'=>'thread@example.test','school'=>'School','password'=>'password123']);
        $other = TeacherAccount::create(['first_name'=>'Other','last_name'=>'Teacher','email'=>'foreign-thread@example.test','school'=>'School','password'=>'password123']);
        $room = Classroom::create(['teacher_account_id'=>$teacher->id,'name'=>'C++','grade'=>'Grade 8','section'=>'AM','join_code'=>'THREADAM']);
        $foreignRoom = Classroom::create(['teacher_account_id'=>$other->id,'name'=>'C++','grade'=>'Grade 8','section'=>'BP','join_code'=>'THREADBP']);
        $student = Student::create(['classroom_id'=>$room->id,'first_name'=>'Student','nickname'=>'CoderOne','student_id'=>'THREAD1','password'=>'password123','xp'=>100]);
        $peer = Student::create(['classroom_id'=>$room->id,'first_name'=>'Peer','nickname'=>'CoderTwo','student_id'=>'THREAD2','password'=>'password123']);
        $outsider = Student::create(['classroom_id'=>$foreignRoom->id,'first_name'=>'Outside','nickname'=>'OutsideCoder','student_id'=>'THREAD3','password'=>'password123']);
        $post = new BlogPost(['title'=>'Let us trace a loop','body'=>'What changes after each iteration?','category'=>'C++ Basics','status'=>'published']);
        $post->forceFill(['teacher_account_id'=>$teacher->id,'published_at'=>now()])->save();
        $comment = new BlogComment(['body'=>'Opening insight from the student']);
        $comment->forceFill(['blog_post_id'=>$post->id,'student_id'=>$student->id])->save();

        return compact('teacher','other','student','peer','outsider','post','comment');
    }

    private function asTeacher(TeacherAccount $teacher): static
    {
        return $this->withSession(['teacher_id'=>$teacher->id,'teacher_session_version'=>1,'student_id'=>null]);
    }

    private function asStudent(Student $student): static
    {
        return $this->withSession(['teacher_id'=>null,'student_id'=>$student->id,'student_session_version'=>1]);
    }

    private function image(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('response.png',base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII='));
    }

    public function test_both_people_can_add_ten_responses_and_the_eleventh_is_rejected_without_overwriting_history(): void
    {
        extract($this->fixture());
        $teacherUrl = route('teacher.blog.reply',['post'=>$post,'comment'=>$comment]);
        $studentUrl = route('student.blog.reply',['post'=>$post,'comment'=>$comment]);
        for ($number = 1; $number <= 10; $number++) {
            $this->asTeacher($teacher)->post($teacherUrl,['body'=>'Teacher response '.$number])->assertRedirect();
            $this->asStudent($student)->post($studentUrl,['body'=>'Student response '.$number,'author_role'=>'teacher','student_id'=>$peer->id])->assertRedirect();
        }
        $this->assertDatabaseCount('blog_responses',20);
        $this->assertSame(10,$comment->responses()->where('author_role','teacher')->count());
        $this->assertSame(10,$comment->responses()->where('author_role','student')->count());
        $this->assertSame('Opening insight from the student',$comment->fresh()->body);
        $this->assertSame(['teacher','student'],$comment->responses()->limit(2)->pluck('author_role')->all());
        $this->postJson($studentUrl,['body'=>'Student response eleven'])->assertUnprocessable()->assertJsonValidationErrors('body');
        $this->asTeacher($teacher)->postJson($teacherUrl,['body'=>'Teacher response eleven'])->assertUnprocessable()->assertJsonValidationErrors('body');
        $this->assertDatabaseCount('blog_responses',20);
        foreach ([$teacher,$student] as $actor) {
            $actor instanceof TeacherAccount ? $this->asTeacher($actor) : $this->asStudent($actor);
            $this->get(route($actor instanceof TeacherAccount ? 'teacher.blog.show' : 'student.blog.show',$post))->assertOk()
                ->assertSee('Student responses: 10/10')->assertSee('Teacher responses: 10/10')->assertSee('You have used all 10 responses')
                ->assertSeeInOrder(['Opening insight from the student','Teacher response 1','Student response 1','Teacher response 10','Student response 10'])
                ->assertDontSee('name="conversation_id"',false);
        }
        $this->assertSame(100,$student->fresh()->xp);
    }

    public function test_follow_ups_remain_private_and_images_require_the_conversation_owner_or_post_teacher(): void
    {
        extract($this->fixture());
        $args = ['post'=>$post,'comment'=>$comment];
        $this->asTeacher($teacher)->post(route('teacher.blog.reply',$args),['body'=>'Private teacher guidance','image'=>$this->image()])->assertRedirect();
        $teacherResponse = BlogResponse::firstOrFail();
        $this->asStudent($student)->post(route('student.blog.reply',$args),['body'=>'Private student follow-up','image'=>$this->image()])->assertRedirect();
        $studentResponse = BlogResponse::latest('id')->firstOrFail();
        $this->asStudent($peer)->get(route('student.blog.show',['post'=>$post,'thread'=>1]))->assertOk()->assertDontSee('Private student follow-up')->assertDontSee('Private teacher guidance');
        $this->postJson(route('student.blog.reply',$args),['body'=>'Peer intrusion'])->assertForbidden();
        $this->asStudent($outsider)->postJson(route('student.blog.reply',$args),['body'=>'Other class intrusion'])->assertForbidden();
        $this->asTeacher($other)->postJson(route('teacher.blog.reply',$args),['body'=>'Other teacher intrusion'])->assertForbidden();
        foreach ([$teacherResponse,$studentResponse] as $response) {
            Storage::disk('local')->assertExists($response->image_path);
            $studentImage = route('student.blog.response-image',$response);
            $teacherImage = route('teacher.blog.response-image',$response);
            $this->asStudent($student)->get($studentImage)->assertOk()->assertHeader('Cache-Control','no-store, private')->assertHeader('X-Content-Type-Options','nosniff');
            $this->asStudent($peer)->get($studentImage)->assertForbidden();
            $this->asTeacher($other)->get($teacherImage)->assertForbidden();
            $this->asTeacher($teacher)->get($teacherImage)->assertOk();
            // A teacher session cannot elevate the student-facing image route.
            $this->withSession(['student_id'=>$peer->id])->get($studentImage)->assertForbidden();
            $this->withSession(['teacher_id'=>null,'student_id'=>null])->get($studentImage)->assertUnauthorized();
        }
        $this->asStudent($student)->get(route('student.blog.show',['post'=>$post,'thread'=>1]))->assertOk()->assertSee('Private student follow-up')->assertSee('Private teacher guidance');
        $this->postJson(route('teacher.blog.reply',$args),['body'=>'Pretend teacher'])->assertUnauthorized();
        $post->delete();
        $this->asTeacher($teacher)->get(route('teacher.blog.response-image',$teacherResponse))->assertNotFound();
    }

    public function test_limits_are_independent_rejected_uploads_are_removed_and_student_follow_ups_reopen_teacher_waiting_count(): void
    {
        extract($this->fixture());
        config(['blog.responses_per_person'=>2]);
        $args = ['post'=>$post,'comment'=>$comment];
        $this->asTeacher($teacher)->get(route('teacher.blog'))->assertViewHas('waitingCount',1);
        $this->post(route('teacher.blog.reply',$args),['body'=>'My final teacher response'])->assertRedirect();
        $this->get(route('teacher.blog'))->assertViewHas('waitingCount',0);
        $this->asStudent($student)->post(route('student.blog.reply',$args),['body'=>'First student follow-up'])->assertRedirect();
        $this->asTeacher($teacher)->get(route('teacher.blog'))->assertViewHas('waitingCount',1);
        $this->post(route('teacher.blog.reply',$args),['body'=>'My second teacher response'])->assertRedirect();
        $this->get(route('teacher.blog'))->assertViewHas('waitingCount',0);
        $this->postJson(route('teacher.blog.reply',$args),['body'=>'Rejected teacher image','image'=>$this->image()])->assertUnprocessable();
        $this->assertCount(0,Storage::disk('local')->allFiles('blog/responses'));
        $this->asStudent($student)->get(route('student.blog.show',$post))->assertOk()->assertSee('Continue this conversation')->assertSee('Teacher responses: 2/2');
        $this->post(route('student.blog.reply',$args),['image'=>$this->image()])->assertRedirect();
        $this->postJson(route('student.blog.reply',$args),['image'=>$this->image()])->assertUnprocessable();
        $this->assertCount(1,Storage::disk('local')->allFiles('blog/responses'));
        $this->assertDatabaseCount('blog_responses',4);
        $this->asTeacher($teacher)->get(route('teacher.blog'))->assertViewHas('waitingCount',0);
        $this->get(route('teacher.blog.show',$post))->assertOk()->assertSee('Conversation complete');
    }

    public function test_response_validation_authorization_and_article_binding_cannot_be_bypassed(): void
    {
        extract($this->fixture());
        $args = ['post'=>$post,'comment'=>$comment];
        $this->asStudent($student)->postJson(route('student.blog.reply',$args),[])->assertUnprocessable()->assertJsonValidationErrors('body');
        $this->postJson(route('student.blog.reply',$args),['body'=>str_repeat('a',5001)])->assertUnprocessable();
        $this->postJson(route('student.blog.reply',$args),['image'=>UploadedFile::fake()->createWithContent('bad.png','not an image')])->assertUnprocessable()->assertJsonValidationErrors('image');
        $another = new BlogPost(['title'=>'Another','body'=>'Topic','category'=>'C++ Basics','status'=>'published']);
        $another->forceFill(['teacher_account_id'=>$teacher->id,'published_at'=>now()])->save();
        $this->postJson(route('student.blog.reply',['post'=>$another,'comment'=>$comment]),['body'=>'Wrong discussion'])->assertNotFound();
        $this->post(route('student.blog.reply',$args),['body'=>'<script>alert("private")</script>'])->assertRedirect();
        $this->get(route('student.blog.show',$post))->assertSee('<script>alert("private")</script>')->assertDontSee('<script>alert("private")</script>',false);
        $post->update(['status'=>'draft']);
        $this->postJson(route('student.blog.reply',$args),['body'=>'Hidden topic'])->assertForbidden();
        $student->increment('session_version');
        $this->postJson(route('student.blog.reply',$args),['body'=>'Revoked session'])->assertUnauthorized();
        $this->assertDatabaseCount('blog_responses',1);
    }

    public function test_upgrade_keeps_existing_teacher_replies_images_and_counts_them_toward_the_limit(): void
    {
        extract($this->fixture());
        $path = $this->image()->store('blog/replies','local');
        $comment->forceFill(['teacher_reply'=>'Legacy teacher explanation','reply_image_path'=>$path,'reply_image_mime'=>'image/png','replied_at'=>now()->subMinute()])->save();
        $migration = require database_path('migrations/2026_10_29_000035_create_blog_responses.php');
        $latest = require database_path('migrations/2026_10_29_000036_add_blog_assessment_timers_and_voice.php');
        $latest->down();
        $migration->down();
        $migration->up();
        $latest->up();
        $response = BlogResponse::firstOrFail();
        $this->assertSame('Legacy teacher explanation',$response->body);
        $this->assertSame($comment->id,$response->blog_comment_id);
        $this->assertSame('teacher',$response->author_role);
        $this->assertSame('teacher',$comment->fresh()->last_response_role);
        $this->assertSame($path,$response->image_path);
        $this->asStudent($student)->get(route('student.blog.show',$post))->assertOk()->assertSee('Legacy teacher explanation')->assertSee('Teacher responses: 1/10');
        $this->get(route('student.blog.response-image',$response))->assertOk();
        $this->get(route('student.blog.comment-image',['comment'=>$comment,'kind'=>'reply']))->assertOk();
        $this->asTeacher($teacher)->post(route('teacher.blog.reply',['post'=>$post,'comment'=>$comment]),['body'=>'A new follow-up explanation'])->assertRedirect();
        $this->assertSame(['Legacy teacher explanation','A new follow-up explanation'],$comment->responses()->pluck('body')->all());
        Storage::disk('local')->assertExists($path);
    }
}
