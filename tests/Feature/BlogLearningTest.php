<?php

namespace Tests\Feature;

use App\Models\BlogComment;
use App\Models\BlogParticipation;
use App\Models\BlogPost;
use App\Models\BlogResponse;
use App\Models\BlogResponseAttempt;
use App\Models\Classroom;
use App\Models\Student;
use App\Models\TeacherAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class BlogLearningTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(bool $graded = true, ?int $minutes = null): array
    {
        $this->travelTo(now()->startOfSecond());
        Storage::fake('local');
        $teacher = TeacherAccount::create(['first_name' => 'Grade', 'last_name' => 'Teacher', 'school' => 'School', 'email' => 'gradeblog@example.test', 'password' => 'password123']);
        $other = TeacherAccount::create(['first_name' => 'Other', 'last_name' => 'Teacher', 'school' => 'School', 'email' => 'gradeblogother@example.test', 'password' => 'password123']);
        $room = Classroom::create(['teacher_account_id' => $teacher->id, 'name' => 'C++', 'grade' => 'Grade 8', 'section' => 'AM', 'join_code' => 'GRADEBLOG']);
        $student = Student::create(['classroom_id' => $room->id, 'first_name' => 'Coder', 'nickname' => 'LearningCoder', 'student_id' => 'GB001', 'password' => 'password123', 'xp' => 100, 'level' => 1]);
        $peer = Student::create(['classroom_id' => $room->id, 'first_name' => 'Peer', 'nickname' => 'PeerCoder', 'student_id' => 'GB002', 'password' => 'password123', 'xp' => 100, 'level' => 1]);
        $this->asTeacher($teacher)->post(route('teacher.blog.store'), ['title' => 'Trace the loop', 'body' => 'Explain the output of the loop.', 'category' => 'C++ Basics', 'status' => 'published', 'is_graded' => $graded, 'response_time_limit_minutes' => $minutes])->assertRedirect();
        $post = BlogPost::firstOrFail();

        return compact('teacher', 'other', 'student', 'peer', 'post');
    }

    private function asTeacher(TeacherAccount $teacher): static
    {
        return $this->withSession(['teacher_id' => $teacher->id, 'teacher_session_version' => 1, 'student_id' => null]);
    }

    private function asStudent(Student $student): static
    {
        return $this->withSession(['teacher_id' => null, 'student_id' => $student->id, 'student_session_version' => 1]);
    }

    private function opening(Student $student, BlogPost $post, array $data = []): BlogComment
    {
        $this->asStudent($student)->post(route('student.blog.comment', $post), $data ?: ['body' => 'My first answer'])->assertRedirect();

        return BlogComment::latest('id')->firstOrFail();
    }

    private function followup(Student $student, BlogPost $post, BlogComment $comment): BlogResponse
    {
        $this->asStudent($student)->post(route('student.blog.reply', ['post' => $post, 'comment' => $comment]), ['body' => 'My second answer'])->assertRedirect();

        return BlogResponse::latest('id')->firstOrFail();
    }

    private function wav(): UploadedFile
    {
        $pcm = str_repeat("\0", 1600);
        $bytes = 'RIFF'.pack('V', 36 + strlen($pcm)).'WAVEfmt '.pack('VvvVVvv', 16, 1, 1, 8000, 16000, 2, 16).'data'.pack('V', strlen($pcm)).$pcm;

        return UploadedFile::fake()->createWithContent('voice.wav', $bytes);
    }

    public static function gradePairs(): array
    {
        return [['semi_correct', 'semi_correct', 10, 5], ['semi_correct', 'slightly_correct', 8, 3], ['slightly_correct', 'totally_correct', 10, 7], ['slightly_correct', 'slightly_correct', 6, 3], ['not_correct', 'totally_correct', 10, 10]];
    }

    #[DataProvider('gradePairs')]
    public function test_first_two_student_submissions_receive_the_correct_capped_xp(string $firstGrade, string $secondGrade, int $total, int $secondXp): void
    {
        extract($this->fixture());
        $first = $this->opening($student, $post);
        $second = $this->followup($student, $post, $first);
        $args = ['post' => $post, 'comment' => $first, 'response' => $second];
        $this->asTeacher($teacher)->postJson(route('teacher.blog.grade-response', $args), ['assessment' => $secondGrade])->assertUnprocessable();
        $this->post(route('teacher.blog.grade-comment', ['post' => $post, 'comment' => $first]), ['assessment' => $firstGrade, 'grade_note' => 'Keep tracing the variable.'])->assertRedirect();
        $this->post(route('teacher.blog.grade-response', $args), ['assessment' => $secondGrade])->assertRedirect();
        $this->assertSame($total, BlogParticipation::firstOrFail()->xp_awarded);
        $this->assertSame(100 + $total, $student->fresh()->xp);
        $this->assertSame($secondXp, $second->fresh()->xp_credited);
        $this->assertSame(1, $first->fresh()->submission_number);
        $this->assertSame(2, $second->fresh()->submission_number);
        $this->post(route('teacher.blog.grade-response', $args), ['assessment' => $secondGrade])->assertRedirect();
        $this->assertSame(100 + $total, $student->fresh()->xp);
        $this->asStudent($student)->get(route('student.blog.show', $post))->assertOk()->assertSee('Keep tracing the variable.')->assertSee('Your article reward: '.$total.'/10 XP');
        $this->get(route('student.leaderboard'))->assertOk()->assertSee((100 + $total).' XP');
    }

    public function test_perfect_first_answer_stops_second_reward_and_new_conversations_do_not_reset_the_cap(): void
    {
        extract($this->fixture());
        $student->update(['xp' => 595]);
        $first = $this->opening($student, $post);
        $second = $this->opening($student, $post, ['body' => 'Second conversation answer']);
        $third = $this->followup($student, $post, $first);
        $this->asTeacher($teacher)->post(route('teacher.blog.grade-comment', ['post' => $post, 'comment' => $first]), ['assessment' => 'totally_correct'])->assertRedirect();
        $this->assertSame(605, $student->fresh()->xp);
        $this->assertSame(2, $student->fresh()->level);
        $this->postJson(route('teacher.blog.grade-comment', ['post' => $post, 'comment' => $second]), ['assessment' => 'semi_correct'])->assertUnprocessable();
        $this->postJson(route('teacher.blog.grade-response', ['post' => $post, 'comment' => $first, 'response' => $third]), ['assessment' => 'totally_correct'])->assertUnprocessable();
        $this->postJson(route('teacher.blog.grade-comment', ['post' => $post, 'comment' => $first]), ['assessment' => 'slightly_correct'])->assertUnprocessable();
        $this->assertSame(605, $student->fresh()->xp);
        $this->assertSame(3, $third->submission_number);
        $this->asStudent($student)->get(route('student.blog.show', $post))->assertSee('Maximum 10 XP already earned');
    }

    public function test_grading_requires_the_article_teacher_and_ungraded_articles_award_no_xp(): void
    {
        extract($this->fixture(false));
        $first = $this->opening($student, $post);
        $url = route('teacher.blog.grade-comment', ['post' => $post, 'comment' => $first]);
        $this->postJson($url, ['assessment' => 'totally_correct', 'xp' => 999])->assertUnauthorized();
        $this->asTeacher($other)->postJson($url, ['assessment' => 'totally_correct'])->assertForbidden();
        $this->asTeacher($teacher)->postJson($url, ['assessment' => 'totally_correct'])->assertUnprocessable();
        $post->update(['is_graded' => true]);
        $this->postJson($url, ['assessment' => 'invented'])->assertUnprocessable();
        $this->post($url, ['assessment' => 'totally_correct', 'xp' => 999])->assertRedirect();
        $this->assertSame(110, $student->fresh()->xp);
        $this->asTeacher($teacher)->get(route('teacher.blog.edit', $post))->assertSee('Article assessment')->assertSee('Student response time limit');
    }

    public function test_each_student_gets_their_own_first_two_graded_comments_regardless_of_other_students_arrival_order(): void
    {
        extract($this->fixture());
        $third = Student::create(['classroom_id' => $student->classroom_id, 'first_name' => 'Third', 'nickname' => 'ThirdCoder', 'student_id' => 'GB003', 'password' => 'password123', 'xp' => 100, 'level' => 1]);

        $firstA = $this->opening($student, $post);
        $firstB = $this->opening($peer, $post);
        $this->asTeacher($teacher)->post(route('teacher.blog.reply', ['post' => $post, 'comment' => $firstA]), ['body' => 'A teacher response does not use a student grading opportunity.'])->assertRedirect();
        $secondA = $this->followup($student, $post, $firstA);
        $firstC = $this->opening($third, $post);
        $secondB = $this->followup($peer, $post, $firstB);
        $secondC = $this->followup($third, $post, $firstC);

        foreach ([$firstA, $firstB, $firstC] as $first) {
            $this->assertSame(1, $first->submission_number);
        }
        foreach ([$secondA, $secondB, $secondC] as $second) {
            $this->assertSame(2, $second->submission_number);
        }

        $this->asTeacher($teacher)->post(route('teacher.blog.grade-comment', ['post' => $post, 'comment' => $firstA]), ['assessment' => 'totally_correct'])->assertRedirect();
        $this->postJson(route('teacher.blog.grade-response', ['post' => $post, 'comment' => $firstA, 'response' => $secondA]), ['assessment' => 'semi_correct'])->assertUnprocessable();
        $this->get(route('teacher.blog.show', $post))->assertOk()
            ->assertSee('Grading for LearningCoder')->assertSee('Grading for PeerCoder')->assertSee('Grading for ThirdCoder')
            ->assertSee(route('teacher.blog.grade-comment', ['post' => $post, 'comment' => $firstB]), false)
            ->assertSee(route('teacher.blog.grade-comment', ['post' => $post, 'comment' => $firstC]), false);

        $this->post(route('teacher.blog.grade-comment', ['post' => $post, 'comment' => $firstB]), ['assessment' => 'semi_correct'])->assertRedirect();
        $this->post(route('teacher.blog.grade-response', ['post' => $post, 'comment' => $firstB, 'response' => $secondB]), ['assessment' => 'slightly_correct'])->assertRedirect();
        $this->post(route('teacher.blog.grade-comment', ['post' => $post, 'comment' => $firstC]), ['assessment' => 'slightly_correct'])->assertRedirect();
        $this->post(route('teacher.blog.grade-response', ['post' => $post, 'comment' => $firstC, 'response' => $secondC]), ['assessment' => 'totally_correct'])->assertRedirect();

        $this->assertSame(110, $student->fresh()->xp);
        $this->assertSame(108, $peer->fresh()->xp);
        $this->assertSame(110, $third->fresh()->xp);
        $this->assertNull($secondA->fresh()->assessment);
        $this->assertSame(0, $secondA->xp_credited);
        $this->assertSame(7, $secondC->fresh()->xp_credited);
        $this->assertDatabaseHas('blog_participations', ['blog_post_id' => $post->id, 'student_id' => $student->id, 'submission_count' => 2, 'xp_awarded' => 10, 'first_assessment' => 'totally_correct']);
        $this->assertDatabaseHas('blog_participations', ['blog_post_id' => $post->id, 'student_id' => $peer->id, 'submission_count' => 2, 'xp_awarded' => 8, 'first_assessment' => 'semi_correct']);
        $this->assertDatabaseHas('blog_participations', ['blog_post_id' => $post->id, 'student_id' => $third->id, 'submission_count' => 2, 'xp_awarded' => 10, 'first_assessment' => 'slightly_correct']);
        $this->assertDatabaseCount('blog_participations', 3);
        $this->asStudent($student)->get(route('student.blog.show', $post))->assertOk()->assertSee('Second comment: no additional XP')->assertDontSee('PeerCoder')->assertDontSee('ThirdCoder');
        $this->asStudent($peer)->get(route('student.blog.show', $post))->assertOk()->assertSee('Your article reward: 8/10 XP')->assertDontSee('LearningCoder')->assertDontSee('ThirdCoder');
        $this->asStudent($third)->get(route('student.blog.show', $post))->assertOk()->assertSee('Your article reward: 10/10 XP');
    }

    public function test_timer_is_persisted_and_the_frozen_final_answer_is_submitted_once_at_expiry(): void
    {
        extract($this->fixture(true, 1));
        $this->asStudent($student)->postJson(route('student.blog.comment', $post), ['body' => 'Bypass timer'])->assertUnprocessable();
        $start = $this->postJson(route('student.blog.timer.start', $post), [])->assertOk();
        $attempt = BlogResponseAttempt::firstOrFail();
        $expires = $attempt->expires_at->timestamp;
        $post->update(['response_time_limit_minutes' => 10]);
        $this->postJson(route('student.blog.timer.start', $post), [])->assertOk()->assertJsonPath('attempt_id', $attempt->id)->assertJsonPath('expires_at', $start->json('expires_at'));
        $this->postJson(route('student.blog.timer.draft', ['post' => $post, 'attempt' => $attempt]), ['body' => 'Saved working answer'])->assertOk()->assertJsonPath('finished', false);
        $this->get(route('student.blog.show', $post))->assertOk()->assertSee('data-blog-time-bar', false)->assertSee('Saved working answer');
        $this->assertSame($expires, $attempt->fresh()->expires_at->timestamp);
        $this->travel(60)->seconds();
        $url = route('student.blog.timer.submit', ['post' => $post, 'attempt' => $attempt]);
        $this->postJson($url, ['body' => 'Final answer typed at zero'])->assertOk()->assertJsonPath('finished', true);
        $this->assertDatabaseHas('blog_comments', ['body' => 'Final answer typed at zero', 'auto_submitted' => true, 'submission_number' => 1]);
        $this->postJson($url, ['body' => 'Changed after submission'])->assertOk()->assertJsonPath('finished', true);
        $this->assertDatabaseCount('blog_comments', 1);
        $this->assertDatabaseCount('blog_response_attempts', 1);
    }

    public function test_expired_timers_send_saved_drafts_without_a_browser_and_ignore_late_edits(): void
    {
        extract($this->fixture(true, 1));
        $this->asStudent($student)->postJson(route('student.blog.timer.start', $post), [])->assertOk();
        $attempt = BlogResponseAttempt::firstOrFail();
        $this->postJson(route('student.blog.timer.draft', ['post' => $post, 'attempt' => $attempt]), ['body' => 'Draft kept by the server'])->assertOk();
        $this->travel(65)->seconds();
        $this->artisan('blog:finalize-responses')->assertSuccessful();
        $this->assertDatabaseHas('blog_comments', ['body' => 'Draft kept by the server', 'auto_submitted' => true]);
        $this->postJson(route('student.blog.timer.submit', ['post' => $post, 'attempt' => $attempt]), ['body' => 'Late replacement'])->assertOk();
        $this->assertDatabaseCount('blog_comments', 1);
        $this->asTeacher($teacher)->get(route('teacher.blog.show', $post))->assertOk()->assertSee('Draft kept by the server')->assertSee('Automatically submitted');
        $comment = BlogComment::firstOrFail();
        $this->asStudent($student)->postJson(route('student.blog.timer.start', $post), ['comment_id' => $comment->id])->assertOk();
        $second = BlogResponseAttempt::latest('id')->firstOrFail();
        $this->postJson(route('student.blog.timer.draft', ['post' => $post, 'attempt' => $second]), ['body' => 'Saved follow-up'])->assertOk();
        $this->travel(65)->seconds();
        $this->postJson(route('student.blog.timer.draft', ['post' => $post, 'attempt' => $second]), ['body' => 'Too late'])->assertOk()->assertJsonPath('finished', true);
        $this->assertDatabaseHas('blog_responses', ['body' => 'Saved follow-up', 'author_role' => 'student', 'submission_number' => 2, 'auto_submitted' => true]);
    }

    public function test_timer_drafts_and_voice_messages_are_private_and_voice_works_for_both_roles(): void
    {
        extract($this->fixture(false, 1));
        $this->asStudent($student)->postJson(route('student.blog.timer.start', $post), [])->assertOk();
        $attempt = BlogResponseAttempt::firstOrFail();
        $args = ['post' => $post, 'attempt' => $attempt];
        $this->post(route('student.blog.timer.draft', $args), ['audio' => $this->wav()])->assertOk();
        $attempt->refresh();
        Storage::disk('local')->assertExists($attempt->audio_path);
        $draftUrl = route('student.blog.timer.media', [...$args, 'kind' => 'audio']);
        $this->get($draftUrl)->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->asStudent($peer)->get($draftUrl)->assertForbidden();
        $this->postJson(route('student.blog.timer.draft', $args), ['body' => 'Peer overwrite'])->assertForbidden();
        $this->asTeacher($teacher)->get($draftUrl)->assertUnauthorized();
        $this->asStudent($student)->postJson(route('student.blog.timer.submit', $args), ['body' => 'Voice answer'])->assertOk();
        $comment = BlogComment::firstOrFail();
        $this->get(route('student.blog.voice', ['kind' => 'comment', 'message' => $comment->id]))->assertOk();
        $this->asTeacher($teacher)->post(route('teacher.blog.reply', ['post' => $post, 'comment' => $comment]), ['audio' => $this->wav()])->assertRedirect();
        $response = BlogResponse::firstOrFail();
        $teacherUrl = route('teacher.blog.voice', ['kind' => 'response', 'message' => $response->id]);
        $studentUrl = route('student.blog.voice', ['kind' => 'response', 'message' => $response->id]);
        $this->get($teacherUrl)->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->asStudent($student)->get($studentUrl)->assertOk();
        $this->asStudent($peer)->get($studentUrl)->assertForbidden();
        $this->asTeacher($other)->get($teacherUrl)->assertForbidden();
        $this->withSession(['teacher_id' => null, 'student_id' => null])->get($studentUrl)->assertUnauthorized();
    }

    public function test_voice_validation_and_removal_of_a_saved_voice_draft(): void
    {
        extract($this->fixture(false));
        foreach (['plain text', ['unexpected' => 'array']] as $invalid) {
            $this->asStudent($student)->postJson(route('student.blog.comment', $post), ['audio' => $invalid])->assertUnprocessable()->assertJsonValidationErrors('audio');
        }
        $this->asStudent($student)->postJson(route('student.blog.comment', $post), ['audio' => UploadedFile::fake()->createWithContent('voice.wav', '<script>bad</script>')])->assertUnprocessable()->assertJsonValidationErrors('audio');
        $this->postJson(route('student.blog.comment', $post), ['audio' => $this->wav()->size(2049)])->assertUnprocessable();
        $comment = $this->opening($student, $post, ['audio' => $this->wav()]);
        $this->assertNotNull($comment->audio_path);
        $post->update(['response_time_limit_minutes' => 1]);
        $this->postJson(route('student.blog.timer.start', $post), ['comment_id' => $comment->id])->assertOk();
        $attempt = BlogResponseAttempt::firstOrFail();
        $args = ['post' => $post, 'attempt' => $attempt];
        $this->post(route('student.blog.timer.draft', $args), ['body' => 'Draft text', 'audio' => $this->wav()])->assertOk();
        $old = $attempt->fresh()->audio_path;
        $this->postJson(route('student.blog.timer.draft', $args), ['body' => 'Draft text', 'remove_audio' => 1])->assertOk();
        $this->assertNull($attempt->fresh()->audio_path);
        Storage::disk('local')->assertMissing($old);
        $this->postJson(route('student.blog.timer.submit', $args), ['body' => 'Draft text'])->assertOk();
        $this->assertNull(BlogResponse::firstOrFail()->audio_path);
    }

    public function test_empty_timed_responses_expire_and_active_attempts_cannot_be_reset_or_retargeted(): void
    {
        extract($this->fixture(true, 1));
        $post->update(['response_time_limit_minutes' => null]);
        $comment = $this->opening($student, $post);
        $post->update(['response_time_limit_minutes' => 1]);
        $this->postJson(route('student.blog.timer.start', $post), ['comment_id' => $comment->id])->assertOk();
        $this->postJson(route('student.blog.timer.start', $post), [])->assertUnprocessable();
        $this->asStudent($peer)->postJson(route('student.blog.timer.start', $post), ['comment_id' => $comment->id])->assertForbidden();
        $this->travel(65)->seconds();
        $this->asTeacher($teacher)->get(route('teacher.blog.show', $post))->assertOk();
        $this->assertDatabaseHas('blog_responses', ['blog_comment_id' => $comment->id, 'body' => null, 'auto_submitted' => true, 'submission_number' => 2]);
        $this->assertDatabaseCount('blog_response_attempts',1);
    }
}
