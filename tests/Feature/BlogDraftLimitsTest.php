<?php

namespace Tests\Feature;

use App\Models\BlogPost;
use App\Models\BlogResponseAttempt;
use App\Models\Classroom;
use App\Models\Student;
use App\Models\TeacherAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BlogDraftLimitsTest extends TestCase
{
    use RefreshDatabase;

    public function test_students_on_the_same_school_network_have_independent_draft_limits(): void
    {
        $this->travelTo(now()->startOfSecond());
        $teacher = TeacherAccount::create(['first_name' => 'Teacher', 'last_name' => 'Test', 'email' => 'draftlimit@example.test', 'school' => 'School', 'password' => 'password123']);
        $room = Classroom::create(['teacher_account_id' => $teacher->id, 'name' => 'C++', 'grade' => 'Grade 8', 'section' => 'AM', 'join_code' => 'DRAFTLIMIT']);
        $first = Student::create(['classroom_id' => $room->id, 'first_name' => 'First', 'nickname' => 'FirstCoder', 'student_id' => 'DL1', 'password' => 'password123']);
        $second = Student::create(['classroom_id' => $room->id, 'first_name' => 'Second', 'nickname' => 'SecondCoder', 'student_id' => 'DL2', 'password' => 'password123']);
        $post = new BlogPost(['title' => 'Timed topic', 'body' => 'Explain the loop.', 'category' => 'C++ Basics', 'status' => 'published', 'response_time_limit_minutes' => 5]);
        $post->forceFill(['teacher_account_id' => $teacher->id, 'published_at' => now()])->save();
        $this->withSession(['student_id' => $first->id])->postJson(route('student.blog.timer.start', $post), [])->assertOk();
        $attempt = BlogResponseAttempt::firstOrFail();
        $url = route('student.blog.timer.draft', ['post' => $post, 'attempt' => $attempt]);
        for ($i = 0; $i < 240; $i++) {
            $this->postJson($url, ['body' => 'Saved draft'])->assertOk();
        }
        $this->postJson($url, ['body' => 'Limited request'])->assertStatus(429);
        $this->withSession(['student_id' => $second->id])->postJson(route('student.blog.timer.start', $post), [])->assertOk();
        $peerAttempt = BlogResponseAttempt::latest('id')->firstOrFail();
        $this->postJson(route('student.blog.timer.draft', ['post' => $post, 'attempt' => $peerAttempt]), ['body' => 'The other student can still save'])->assertOk();
        $this->assertSame('The other student can still save', $peerAttempt->fresh()->body);
    }
}
