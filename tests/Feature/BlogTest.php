<?php

namespace Tests\Feature;

use App\Models\BlogComment;
use App\Models\BlogPost;
use App\Models\Classroom;
use App\Models\Student;
use App\Models\TeacherAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BlogTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        Storage::fake('local');
        $teacher = TeacherAccount::create(['first_name'=>'Blog','last_name'=>'Teacher','email'=>'blog@example.test','school'=>'School','password'=>'password123']);
        $other = TeacherAccount::create(['first_name'=>'Other','last_name'=>'Teacher','email'=>'otherblog@example.test','school'=>'School','password'=>'password123']);
        $rooms = collect(['AM','BP','DB'])->map(fn ($section) => Classroom::create(['teacher_account_id'=>$teacher->id,'name'=>'C++','grade'=>'Grade 8','section'=>$section,'join_code'=>'BLOG'.$section]));
        $foreign = Classroom::create(['teacher_account_id'=>$other->id,'name'=>'Other class','grade'=>'Grade 8','section'=>'AM','join_code'=>'OTHERBLOG']);
        $student = Student::create(['classroom_id'=>$rooms[0]->id,'first_name'=>'Alex','nickname'=>'BlogAlex','student_id'=>'BLOG001','password'=>'password123','xp'=>100]);
        $peer = Student::create(['classroom_id'=>$rooms[0]->id,'first_name'=>'Peer','nickname'=>'BlogPeer','student_id'=>'BLOG002','password'=>'password123','xp'=>100]);
        $sibling = Student::create(['classroom_id'=>$rooms[2]->id,'first_name'=>'Third','nickname'=>'OtherSection','student_id'=>'BLOG003','password'=>'password123','xp'=>100]);
        $outsider = Student::create(['classroom_id'=>$foreign->id,'first_name'=>'Outsider','nickname'=>'Outsider','student_id'=>'BLOG004','password'=>'password123','xp'=>100]);

        return compact('teacher','other','rooms','student','peer','sibling','outsider');
    }

    private function teacher(TeacherAccount $teacher): static
    {
        return $this->withSession(['teacher_id'=>$teacher->id,'teacher_session_version'=>1,'student_id'=>null]);
    }

    private function student(Student $student): static
    {
        return $this->withSession(['teacher_id'=>null,'student_id'=>$student->id,'student_session_version'=>1]);
    }

    private function image(string $name = 'topic.png'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII='));
    }

    private function createPost(TeacherAccount $teacher, array $changes = []): BlogPost
    {
        $this->teacher($teacher)->post(route('teacher.blog.store'), array_replace(['title'=>'Understanding for loops','body'=>'Trace how the loop changes the counter. What will it print?','category'=>'C++ Basics','status'=>'published'], $changes))->assertRedirect()->assertSessionHas('blog_notice');

        return BlogPost::latest('id')->firstOrFail();
    }

    public function test_teacher_publishes_searchable_articles_to_all_own_sections_and_can_edit_or_archive(): void
    {
        extract($this->fixture());
        $this->teacher($teacher)->get(route('teacher.blog'))->assertOk()->assertSee('Your C++ reading journey starts here');
        $this->get(route('teacher.blog.create'))->assertOk()->assertSee('Share a C++ idea');
        $post = $this->createPost($teacher, ['image'=>$this->image()]);
        Storage::disk('local')->assertExists($post->image_path);
        $this->assertNotNull($post->published_at);
        foreach ([$student, $peer, $sibling] as $learner) {
            $this->student($learner)->get(route('student.blog'))->assertOk()->assertSee($post->title)->assertHeader('Cache-Control','no-store, private');
            $this->get(route('student.blog.show',$post))->assertOk()->assertSee($post->body)->assertSee('Your private conversation');
        }
        $this->get(route('student.blog',['search'=>'nonexistent']))->assertOk()->assertDontSee($post->title)->assertSee('No matching articles');
        $this->get(route('student.blog',['category'=>'Projects']))->assertOk()->assertDontSee($post->title);
        $this->teacher($teacher)->get(route('teacher.blog.edit',$post))->assertOk();
        $this->put(route('teacher.blog.update',$post),['title'=>'Updated loops','body'=>'Use a loop to count down.','category'=>'Problem Solving','status'=>'published'])->assertRedirect();
        $post->refresh();
        $this->assertSame('Updated loops',$post->title);
        $this->assertNotNull($post->image_path);
        $this->delete(route('teacher.blog.destroy',$post))->assertRedirect(route('teacher.blog'));
        $this->assertSoftDeleted('blog_posts',['id'=>$post->id]);
        $this->student($student)->get(route('student.blog.show',$post))->assertNotFound();
        $this->get(route('student.blog.image',$post))->assertNotFound();
    }

    public function test_drafts_and_other_teachers_posts_are_not_available_to_students_or_foreign_teachers(): void
    {
        extract($this->fixture());
        $draft = $this->createPost($teacher,['title'=>'Private draft topic','status'=>'draft','image'=>$this->image()]);
        $this->assertNull($draft->published_at);
        $this->get(route('teacher.blog.show',$draft))->assertOk()->assertSee('Draft · visible only to you');
        $this->get(route('teacher.blog.image',$draft))->assertOk();
        $this->student($student)->get(route('student.blog'))->assertOk()->assertDontSee('Private draft topic');
        $this->get(route('student.blog.show',$draft))->assertForbidden();
        $this->get(route('student.blog.image',$draft))->assertForbidden();
        $this->postJson(route('student.blog.comment',$draft),['body'=>'Trying to comment'])->assertForbidden();
        $post = $this->createPost($teacher);
        $this->student($outsider)->get(route('student.blog'))->assertOk()->assertDontSee($post->title);
        $this->get(route('student.blog.show',$post))->assertForbidden();
        $this->teacher($other)->get(route('teacher.blog.show',$post))->assertForbidden();
        $this->get(route('teacher.blog.edit',$post))->assertForbidden();
        $this->putJson(route('teacher.blog.update',$post),[])->assertForbidden();
        $this->delete(route('teacher.blog.destroy',$post))->assertForbidden();
        $this->withSession(['teacher_id'=>null,'student_id'=>null])->get(route('student.blog'))->assertRedirect(route('student.login'));
        $this->get(route('teacher.blog'))->assertRedirect(route('teacher.login'));
        $this->postJson(route('teacher.blog.store'),[])->assertUnauthorized();
        $this->postJson(route('student.blog.comment',$post),[])->assertUnauthorized();
    }

    public function test_private_comments_and_replies_never_leak_to_classmates_in_html_or_live_fragments(): void
    {
        extract($this->fixture());
        $post = $this->createPost($teacher);
        $this->student($student)->post(route('student.blog.comment',$post),['body'=>'Alex private insight','student_id'=>$peer->id,'teacher_reply'=>'Forged reply'])->assertRedirect();
        $first = BlogComment::firstOrFail();
        $this->assertSame($student->id,$first->student_id);
        $this->assertNull($first->teacher_reply);
        $this->student($peer)->post(route('student.blog.comment',$post),['body'=>'Peer confidential question'])->assertRedirect();
        $second = BlogComment::latest('id')->firstOrFail();
        $this->teacher($teacher)->get(route('teacher.blog.show',$post))->assertOk()->assertSee('Alex private insight')->assertSee('Peer confidential question');
        $this->post(route('teacher.blog.reply',['post'=>$post,'comment'=>$first]),['body'=>'Private response for Alex'])->assertRedirect();
        $this->post(route('teacher.blog.reply',['post'=>$post,'comment'=>$second]),['body'=>'Private response for Peer'])->assertRedirect();
        foreach ([[],['thread'=>1]] as $query) {
            $this->student($student)->get(route('student.blog.show',['post'=>$post,...$query]))->assertOk()->assertSee('Alex private insight')->assertSee('Private response for Alex')->assertDontSee('Peer confidential question')->assertDontSee('Private response for Peer')->assertDontSee('BlogPeer');
            $this->student($peer)->get(route('student.blog.show',['post'=>$post,...$query]))->assertOk()->assertSee('Peer confidential question')->assertSee('Private response for Peer')->assertDontSee('Alex private insight')->assertDontSee('Private response for Alex');
            $this->student($sibling)->get(route('student.blog.show',['post'=>$post,...$query]))->assertOk()->assertDontSee('Alex private insight')->assertDontSee('Peer confidential question');
        }
        $this->student($student)->get(route('student.blog'))->assertViewHas('posts', fn ($posts) => $posts->first()->comments_count === 1);
        // Even when both roles are signed in, student URLs must stay in student scope.
        $this->withSession(['teacher_id'=>$teacher->id])->get(route('student.blog.show',$post))->assertOk()->assertDontSee('Peer confidential question');
        $this->teacher($teacher)->post(route('teacher.blog.reply',['post'=>$post,'comment'=>$first]),['body'=>'Revised response for Alex'])->assertRedirect();
        $this->assertDatabaseCount('blog_comments',2);
        $this->assertSame(['Private response for Alex','Revised response for Alex'],$first->responses()->pluck('body')->all());
        $this->assertSame(100,$student->fresh()->xp);
    }

    public function test_comment_and_reply_images_are_private_including_direct_url_access(): void
    {
        extract($this->fixture());
        $post = $this->createPost($teacher,['body'=>null,'image'=>$this->image()]);
        $this->student($student)->post(route('student.blog.comment',$post),['image'=>$this->image('insight.png')])->assertRedirect();
        $comment = BlogComment::firstOrFail();
        $this->teacher($teacher)->post(route('teacher.blog.reply',['post'=>$post,'comment'=>$comment]),['image'=>$this->image('response.png')])->assertRedirect();
        $comment->refresh();
        $response = $comment->responses()->firstOrFail();
        Storage::disk('local')->assertExists($comment->image_path);
        Storage::disk('local')->assertExists($response->image_path);
        $this->assertStringStartsWith('blog/comments/',$comment->image_path);
        $this->assertStringStartsWith('blog/responses/',$response->image_path);
        foreach (['comment','reply'] as $kind) {
            $studentUrl = $kind === 'comment' ? route('student.blog.comment-image', ['comment'=>$comment,'kind'=>$kind]) : route('student.blog.response-image', $response);
            $teacherUrl = $kind === 'comment' ? route('teacher.blog.comment-image', ['comment'=>$comment,'kind'=>$kind]) : route('teacher.blog.response-image', $response);
            $this->student($student)->get($studentUrl)->assertOk()->assertHeader('Content-Type','image/png')->assertHeader('Cache-Control','no-store, private')->assertHeader('X-Content-Type-Options','nosniff');
            $this->student($peer)->get($studentUrl)->assertForbidden();
            $this->student($sibling)->get($studentUrl)->assertForbidden();
            $this->teacher($other)->get($teacherUrl)->assertForbidden();
            $this->teacher($teacher)->get($teacherUrl)->assertOk();
            $this->withSession(['student_id'=>null,'teacher_id'=>null])->get($studentUrl)->assertUnauthorized();
            $this->get($teacherUrl)->assertUnauthorized();
        }
        $this->get(route('student.blog.image',$post))->assertUnauthorized();
        $this->student($student)->get('/storage/'.$comment->image_path)->assertForbidden();
        $this->get(route('student.blog.show',$post))->assertDontSee($comment->image_path)->assertDontSee($response->image_path);
        $this->get(route('student.blog.comment-image',['comment'=>$comment,'kind'=>'invalid']))->assertNotFound();
    }

    public function test_upload_and_content_validation_rejects_empty_unsafe_or_oversized_content(): void
    {
        extract($this->fixture());
        $data = ['title'=>'Valid title','category'=>'C++ Basics','status'=>'published'];
        $this->teacher($teacher)->postJson(route('teacher.blog.store'),$data)->assertUnprocessable()->assertJsonValidationErrors('body');
        $this->postJson(route('teacher.blog.store'),[...$data,'image'=>UploadedFile::fake()->createWithContent('evil.png','not an image')])->assertUnprocessable()->assertJsonValidationErrors('image');
        $this->postJson(route('teacher.blog.store'),[...$data,'image'=>UploadedFile::fake()->createWithContent('evil.svg','<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>')])->assertUnprocessable()->assertJsonValidationErrors('image');
        $this->postJson(route('teacher.blog.store'),[...$data,'image'=>$this->image()->size(2049)])->assertUnprocessable()->assertJsonValidationErrors('image');
        $this->assertDatabaseCount('blog_posts',0);
        $this->assertCount(0,Storage::disk('local')->allFiles('blog'));
        $post = $this->createPost($teacher,['body'=>null,'image'=>$this->image()]);
        $this->putJson(route('teacher.blog.update',$post),[...$data,'remove_image'=>1])->assertUnprocessable()->assertJsonValidationErrors('body');
        $this->student($student)->postJson(route('student.blog.comment',$post),[])->assertUnprocessable()->assertJsonValidationErrors('body');
        $this->postJson(route('student.blog.comment',$post),['image'=>UploadedFile::fake()->createWithContent('no.png','invalid')])->assertUnprocessable()->assertJsonValidationErrors('image');
        $this->post(route('student.blog.comment',$post),['body'=>'<script>alert("student")</script>'])->assertRedirect();
        $comment = BlogComment::firstOrFail();
        $this->get(route('student.blog.show',$post))->assertSee('<script>alert("student")</script>')->assertDontSee('<script>alert("student")</script>',false);
        $this->teacher($teacher)->postJson(route('teacher.blog.reply',['post'=>$post,'comment'=>$comment]),[])->assertUnprocessable()->assertJsonValidationErrors('body');
        $this->post(route('teacher.blog.reply',['post'=>$post,'comment'=>$comment]),['body'=>'<b>Teacher response</b>'])->assertRedirect();
        $this->get(route('teacher.blog.show',$post))->assertSee('<b>Teacher response</b>')->assertDontSee('<b>Teacher response</b>',false);
    }

    public function test_only_the_post_teacher_can_reply_and_comments_cannot_be_attached_to_another_article(): void
    {
        extract($this->fixture());
        $post = $this->createPost($teacher);
        $second = $this->createPost($teacher,['title'=>'Another article']);
        $this->student($student)->post(route('student.blog.comment',$post),['body'=>'Private question'])->assertRedirect();
        $comment = BlogComment::firstOrFail();
        $this->postJson(route('teacher.blog.reply',['post'=>$post,'comment'=>$comment]),['body'=>'Spoofed teacher'])->assertUnauthorized();
        $this->teacher($other)->postJson(route('teacher.blog.reply',['post'=>$post,'comment'=>$comment]),['body'=>'Foreign response'])->assertForbidden();
        $this->teacher($teacher)->postJson(route('teacher.blog.reply',['post'=>$second,'comment'=>$comment]),['body'=>'Wrong article'])->assertNotFound();
        $this->assertNull($comment->fresh()->teacher_reply);
        $this->student($outsider)->postJson(route('student.blog.comment',$post),['body'=>'Foreign student'])->assertForbidden();
        $student->delete();
        $this->teacher($teacher)->get(route('teacher.blog.show',$post))->assertOk()->assertSee('Private question')->assertSee('Archived account');
        $this->student($student)->get(route('student.blog.show',$post))->assertRedirect(route('student.login'));
        $this->assertDatabaseCount('blog_comments',1);
    }

    public function test_image_replacement_and_removal_keep_existing_comments(): void
    {
        extract($this->fixture());
        $post = $this->createPost($teacher,['image'=>$this->image()]);
        $oldPath = $post->image_path;
        $this->student($student)->post(route('student.blog.comment',$post),['body'=>'Keep this insight'])->assertRedirect();
        $this->teacher($teacher)->put(route('teacher.blog.update',$post),['title'=>$post->title,'body'=>'Updated article','category'=>'C++ Basics','status'=>'published','image'=>$this->image('replacement.png')])->assertRedirect();
        $post->refresh();
        $this->assertNotSame($oldPath,$post->image_path);
        Storage::disk('local')->assertMissing($oldPath);
        Storage::disk('local')->assertExists($post->image_path);
        $this->put(route('teacher.blog.update',$post),['title'=>$post->title,'body'=>'Text remains after removing image','category'=>'C++ Basics','status'=>'published','remove_image'=>1])->assertRedirect();
        $this->assertNull($post->fresh()->image_path);
        $this->assertDatabaseCount('blog_comments',1);
        $this->delete(route('teacher.blog.destroy',$post))->assertRedirect();
        $this->assertDatabaseCount('blog_comments',1);
    }
}
