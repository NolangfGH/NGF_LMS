<?php

namespace Tests\Feature;

use App\Models\BlogComment;
use App\Models\BlogPost;
use App\Models\BlogResponse;
use App\Models\BlogResponseAttempt;
use App\Models\ChallengeNotice;
use App\Models\Classroom;
use App\Models\CodeWorkspace;
use App\Models\CodingMatch;
use App\Models\CodingMatchParticipant;
use App\Models\LabSubmission;
use App\Models\LearningNotification;
use App\Models\LessonProgress;
use App\Models\QuizAttempt;
use App\Models\Student;
use App\Models\TeacherAccount;
use App\Models\TeacherAssignment;
use App\Models\TeacherLessonPlan;
use App\Models\TeacherQuiz;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LearningNotificationsTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $teacher = TeacherAccount::create(['first_name' => 'Notice', 'last_name' => 'Teacher', 'email' => 'notices@example.test', 'school' => 'School', 'password' => 'password123']);
        $other = TeacherAccount::create(['first_name' => 'Foreign', 'last_name' => 'Teacher', 'email' => 'foreign-notices@example.test', 'school' => 'School', 'password' => 'password123']);
        $rooms = collect(['AM', 'BP', 'DB'])->map(fn ($section) => Classroom::create(['teacher_account_id' => $teacher->id, 'name' => 'C++', 'grade' => 'Grade 8', 'section' => $section, 'join_code' => 'NOTICE'.$section]));
        $foreignRoom = Classroom::create(['teacher_account_id' => $other->id, 'name' => 'Other class', 'grade' => 'Grade 8', 'section' => 'AM', 'join_code' => 'FOREIGNNOTICE']);
        $students = $rooms->map(fn ($room) => Student::create(['classroom_id' => $room->id, 'first_name' => 'Coder', 'nickname' => 'Coder'.$room->section, 'student_id' => 'NOTICE'.$room->section, 'password' => 'password123', 'xp' => 100]));
        $peer = Student::create(['classroom_id' => $rooms[0]->id, 'first_name' => 'Peer', 'nickname' => 'PeerCoder', 'student_id' => 'NOTICEPEER', 'password' => 'password123', 'xp' => 100]);
        $foreign = Student::create(['classroom_id' => $foreignRoom->id, 'first_name' => 'Other', 'nickname' => 'ForeignCoder', 'student_id' => 'NOTICEFOREIGN', 'password' => 'password123']);

        return compact('teacher', 'other', 'rooms', 'foreignRoom', 'students', 'peer', 'foreign');
    }

    private function asTeacher(TeacherAccount $teacher): static
    {
        return $this->withSession(['teacher_id' => $teacher->id, 'teacher_session_version' => $teacher->fresh()->session_version, 'student_id' => null]);
    }

    private function asStudent(Student $student): static
    {
        return $this->withSession(['teacher_id' => null, 'student_id' => $student->id, 'student_session_version' => $student->fresh()->session_version, 'student_class_verified' => true, 'classroom_id' => $student->classroom_id]);
    }

    private function assignmentPayload(array $ids, array $changes = []): array
    {
        return array_replace(['title' => 'Shared coding task', 'kind' => 'assignment', 'school_term' => 'Term1', 'classroom_ids' => $ids, 'objective' => 'Print a greeting.', 'sample_output' => 'Hello', 'difficulty' => 'Easy', 'xp' => 10, 'status' => 'published'], $changes);
    }

    private function article(TeacherAccount $teacher, string $status = 'published'): BlogPost
    {
        $post = new BlogPost(['title' => 'C++ reading topic', 'body' => 'Trace the loop and explain your result.', 'category' => 'C++ Basics', 'status' => $status]);
        $post->forceFill(['teacher_account_id' => $teacher->id, 'published_at' => $status === 'published' ? now() : null])->save();

        return $post;
    }

    public function test_published_assignments_quizzes_challenges_and_articles_notify_only_their_audiences(): void
    {
        extract($this->fixture());
        $this->asTeacher($teacher)->postJson(route('teacher.assignments.store'), $this->assignmentPayload([$rooms[0]->id, $rooms[1]->id]))->assertOk();
        $assignment = TeacherAssignment::firstOrFail();
        $this->assertSame([$students[0]->id, $students[1]->id, $peer->id], LearningNotification::where('target_type', 'assignment')->orderBy('student_id')->pluck('student_id')->all());
        $this->postJson(route('teacher.assignments.store'), $this->assignmentPayload([$rooms[2]->id], ['title' => 'Draft task', 'status' => 'draft']))->assertOk();
        $draft = TeacherAssignment::latest('id')->firstOrFail();
        $this->assertSame(0, LearningNotification::where('target_type', 'assignment')->where('target_id', $draft->id)->count());
        $this->putJson(route('teacher.assignments.update', $draft), $this->assignmentPayload([$rooms[2]->id], ['title' => 'Published DB task']))->assertOk();
        $this->assertSame(1, LearningNotification::where('target_type', 'assignment')->where('target_id', $draft->id)->where('student_id', $students[2]->id)->count());
        $this->postJson(route('teacher.assignments.store'), $this->assignmentPayload([$rooms[0]->id], ['kind' => 'challenge', 'title' => 'Whole-section challenge']))->assertOk();
        $this->assertSame(2, LearningNotification::where('category', 'challenges')->count());
        $this->postJson(route('teacher.quizzes.store'), ['title' => 'Shared quiz', 'classroom_ids' => [$rooms[1]->id, $rooms[2]->id], 'questions' => [['type' => 'identification', 'prompt' => 'Integer keyword?', 'options' => [], 'correct' => ['int']]], 'xp' => 10, 'status' => 'published', 'time_limit' => 3])->assertOk();
        $this->assertSame(2, LearningNotification::where('category', 'quizzes')->count());
        $this->article($teacher);
        $this->assertSame(4, LearningNotification::where('target_type', 'article')->count());
        $this->asStudent($foreign)->getJson(route('student.notifications'))->assertOk()->assertJsonPath('unread', 0)->assertJsonPath('items', []);
        $feed = $this->asStudent($students[1])->getJson(route('student.notifications'))->assertOk()->assertJsonPath('counts.assignments', 1)->assertJsonPath('counts.quizzes', 1)->assertJsonPath('counts.articles', 1);
        $this->assertTrue(collect($feed->json('items'))->contains('url', route('student.assignments').'#assignment-'.$assignment->id));
    }

    public function test_private_blog_responses_notify_only_the_teacher_and_the_correct_student(): void
    {
        extract($this->fixture());
        $post = $this->article($teacher);
        $this->asStudent($students[0])->post(route('student.blog.comment', $post), ['body' => 'PRIVATE FIRST ANSWER'])->assertRedirect();
        $comment = BlogComment::firstOrFail();
        $this->asStudent($peer)->post(route('student.blog.comment', $post), ['body' => 'PRIVATE PEER ANSWER'])->assertRedirect();
        $this->asTeacher($teacher)->post(route('teacher.blog.reply', ['post' => $post, 'comment' => $comment]), ['body' => 'PRIVATE TEACHER FEEDBACK'])->assertRedirect();
        $response = BlogResponse::firstOrFail();
        $notice = LearningNotification::where('event_key', 'blog-response:'.$response->id)->firstOrFail();
        $this->assertSame($students[0]->id, $notice->student_id);
        $this->assertNull($notice->teacher_account_id);
        $this->asStudent($students[0])->post(route('student.blog.reply', ['post' => $post, 'comment' => $comment]), ['body' => 'PRIVATE FOLLOW-UP'])->assertRedirect();
        $teacherFeed = $this->asTeacher($teacher)->getJson(route('teacher.notifications'))->assertOk()->assertJsonPath('unread', 3)->assertDontSee('PRIVATE FIRST ANSWER')->assertDontSee('PRIVATE TEACHER FEEDBACK');
        $teacherUrl = collect($teacherFeed->json('items'))->firstWhere('title', 'New student blog response')['url'];
        $this->get($teacherUrl)->assertOk()->assertSee('PRIVATE FOLLOW-UP')->assertDontSee('PRIVATE PEER ANSWER');
        $studentFeed = $this->asStudent($students[0])->getJson(route('student.notifications'))->assertOk()->assertJsonPath('unread', 2)->assertDontSee('PRIVATE TEACHER FEEDBACK');
        $privateUrl = collect($studentFeed->json('items'))->firstWhere('title', 'Your teacher replied')['url'];
        $this->get($privateUrl)->assertOk()->assertSee('PRIVATE TEACHER FEEDBACK')->assertDontSee('PRIVATE PEER ANSWER');
        $this->asStudent($peer)->getJson(route('student.notifications'))->assertOk()->assertJsonPath('unread', 1)->assertDontSee('Your teacher replied');
        $this->get($privateUrl)->assertNotFound();
        $this->asTeacher($other)->getJson(route('teacher.notifications'))->assertOk()->assertJsonPath('items', []);
        $this->withSession(['teacher_id' => $teacher->id, 'student_id' => $peer->id])->getJson(route('student.notifications'))->assertJsonPath('unread', 1)->assertDontSee('Your teacher replied');
    }

    public function test_teacher_receives_quiz_and_code_submissions_and_assigned_lesson_completion(): void
    {
        extract($this->fixture());
        $quiz = TeacherQuiz::create(['teacher_account_id' => $teacher->id, 'classroom_id' => $rooms[0]->id, 'title' => 'Finished quiz', 'status' => 'published', 'time_limit' => 1, 'questions' => [['type' => 'identification', 'prompt' => 'Integer keyword?', 'options' => [], 'correct' => ['int']]]]);
        $this->asStudent($students[0])->post(route('student.quiz.start', $quiz))->assertRedirect();
        $attempt = QuizAttempt::firstOrFail();
        $this->postJson(route('student.quiz.question', $quiz), ['question_index' => 0, 'answer' => 'int', 'attempt_id' => $attempt->id])->assertOk();
        $this->assertSame(1, LearningNotification::where('target_type', 'quiz_submission')->where('teacher_account_id', $teacher->id)->count());
        $assignment = TeacherAssignment::create(['teacher_account_id' => $teacher->id, 'classroom_id' => $rooms[0]->id, 'title' => 'Code to review', 'kind' => 'assignment', 'status' => 'published', 'xp' => 10, 'starter_code' => 'int main(){return 0;}']);
        $this->get(route('student.code-lab', ['assignment' => $assignment->id]))->assertOk();
        $workspace = CodeWorkspace::firstOrFail();
        $workspace->update(['reflection' => 'I tested this program with a simple greeting.']);
        $this->postJson(route('student.code-lab.submit', $workspace), ['revision' => $workspace->revision])->assertOk();
        $submission = LabSubmission::firstOrFail();
        $this->assertSame(1, LearningNotification::where('target_type', 'lab_submission')->where('teacher_account_id', $teacher->id)->count());
        $lessonKey = array_key_first(config('lessons'));
        TeacherLessonPlan::create(['teacher_account_id' => $teacher->id, 'classroom_id' => $rooms[0]->id, 'lesson_key' => $lessonKey, 'status' => 'published']);
        $progress = LessonProgress::create(['student_id' => $students[0]->id, 'lesson_key' => $lessonKey, 'started_at' => now(), 'last_opened_at' => now()]);
        $progress->update(['completed_at' => now()]);
        $progress->update(['confidence' => 'ready']);
        $this->assertSame(1, LearningNotification::where('target_type', 'lesson_progress')->count());
        $this->asTeacher($teacher)->getJson(route('teacher.notifications'))->assertOk()->assertJsonPath('unread', 3)->assertJsonPath('counts.quizzes', 1)->assertJsonPath('counts.assignments', 1)->assertJsonPath('counts.lessons', 1);
        $this->get(route('teacher.code-lab', ['submission' => $submission->id]))->assertOk()->assertSee('Code to review');
        $this->get(route('teacher.quiz.reviews', ['submission' => 1]))->assertOk()->assertSee('Finished quiz');
    }

    public function test_notifications_can_be_filtered_paginated_and_read_without_affecting_other_accounts_or_newer_updates(): void
    {
        extract($this->fixture());
        for ($i = 1; $i <= 23; $i++) {
            $this->article($teacher);
        }
        $feed = $this->asStudent($students[0])->getJson(route('student.notifications'))->assertOk()->assertJsonCount(20, 'items')->assertJsonPath('unread', 23);
        $cursor = $feed->json('next_cursor');
        $this->getJson(route('student.notifications', ['before' => $cursor, 'category' => 'articles']))->assertOk()->assertJsonCount(3, 'items')->assertJsonPath('next_cursor', null);
        $this->getJson(route('student.notifications', ['category' => 'quizzes']))->assertOk()->assertJsonPath('items', [])->assertJsonPath('unread', 23);
        $otherNotice = LearningNotification::where('student_id', $peer->id)->firstOrFail();
        $this->postJson(route('student.notifications.read'), ['ids' => [$otherNotice->id]])->assertOk();
        $this->assertNull($otherNotice->fresh()->read_at);
        $through = $feed->json('through');
        $this->article($teacher);
        $this->postJson(route('student.notifications.read'), ['through' => $through])->assertOk();
        $this->getJson(route('student.notifications', ['unread_only' => 1]))->assertOk()->assertJsonPath('unread', 1)->assertJsonCount(1, 'items');
        $this->asStudent($peer)->getJson(route('student.notifications'))->assertJsonPath('unread', 24);
        $this->asTeacher($teacher)->postJson(route('teacher.notifications.read'), ['ids' => [$otherNotice->id]])->assertOk();
        $this->assertNull($otherNotice->fresh()->read_at);
    }

    public function test_existing_challenge_notices_and_invitations_stay_visible_and_share_read_state(): void
    {
        extract($this->fixture());
        $match = CodingMatch::create(['teacher_account_id' => $teacher->id, 'title' => 'Pair coding challenge', 'instructions' => 'Print a greeting', 'sample_output' => 'Hello', 'time_limit_minutes' => 5]);
        CodingMatchParticipant::create(['coding_match_id' => $match->id, 'student_id' => $students[0]->id, 'slot' => 1, 'student_name' => $students[0]->nickname, 'section_label' => 'Grade 8 AM', 'draft_code' => 'int main(){}']);
        $legacy = ChallengeNotice::create(['coding_match_id' => $match->id, 'student_id' => $students[0]->id, 'event_key' => 'invited', 'message' => 'You were invited to a coding challenge.', 'created_at' => now()]);
        $this->article($teacher);
        $this->asStudent($students[0])->getJson(route('student.notifications'))->assertOk()->assertJsonPath('unread', 2)->assertJsonPath('invitations', 1)->assertJsonPath('counts.challenges', 1);
        $this->getJson(route('student.match-notices'))->assertOk()->assertJsonPath('unread', 1)->assertJsonPath('items.0.url', route('student.matches.show', $match));
        $this->postJson(route('student.notifications.read'), ['ids' => [$legacy->id]])->assertOk();
        $this->getJson(route('student.match-notices'))->assertJsonPath('unread', 0)->assertJsonPath('invitations', 1);
        $this->getJson(route('student.notifications'))->assertJsonPath('unread', 1)->assertJsonPath('invitations', 1);
    }

    public function test_initial_history_sync_is_idempotent_and_new_students_receive_existing_published_work(): void
    {
        extract($this->fixture());
        $assignment = TeacherAssignment::create(['teacher_account_id' => $teacher->id, 'classroom_id' => $rooms[0]->id, 'title' => 'Existing assigned task', 'status' => 'published', 'xp' => 10]);
        $post = $this->article($teacher);
        $this->asStudent($students[0])->getJson(route('student.notifications'))->assertOk()->assertJsonPath('unread', 2);
        $this->getJson(route('student.notifications'))->assertJsonPath('unread', 2);
        $this->assertSame(1, LearningNotification::where('student_id', $students[0]->id)->where('target_type', 'assignment')->count());
        $new = Student::create(['classroom_id' => $rooms[0]->id, 'first_name' => 'New', 'nickname' => 'NewCoder', 'student_id' => 'NOTICE_NEW', 'password' => 'password123']);
        $this->asStudent($new)->getJson(route('student.notifications'))->assertJsonPath('unread', 2);
        $assignment->delete();
        $feed = $this->getJson(route('student.notifications'))->assertOk();
        $this->assertNull(collect($feed->json('items'))->firstWhere('title', 'New assignment')['url']);
        $new->update(['classroom_id' => $foreignRoom->id]);
        $this->asStudent($new)->getJson(route('student.notifications'))->assertOk()->assertJsonPath('items.0.url', null);
        $this->assertSame(2, LearningNotification::where('student_id', $new->id)->count());
    }

    public function test_new_panels_and_api_require_the_correct_signed_in_role(): void
    {
        extract($this->fixture());
        $this->asStudent($students[0])->get(route('student.dashboard'))->assertOk()->assertSee('Classroom notifications')->assertSee('Assignments')->assertSee('Blog & articles')->assertSee('learning-notifications.js');
        $this->asTeacher($teacher)->get(route('teacher.dashboard'))->assertOk()->assertSee('Teaching notifications')->assertSee('learning-notifications.js');
        $this->withSession(['student_id' => null, 'teacher_id' => null])->getJson(route('student.notifications'))->assertUnauthorized();
        $this->getJson(route('teacher.notifications'))->assertUnauthorized();
        $this->postJson(route('student.notifications.read'), ['through' => 1])->assertUnauthorized();
        $this->asStudent($students[0])->getJson(route('student.notifications', ['category' => 'invented']))->assertUnprocessable();
        $this->postJson(route('student.notifications.read'), ['ids' => [1, 1]])->assertUnprocessable();
        $students[0]->increment('session_version');
        $this->getJson(route('student.notifications'))->assertUnauthorized();
    }

    public function test_dashboard_notification_poll_finalizes_and_announces_an_expired_blog_draft(): void
    {
        extract($this->fixture());
        $this->travelTo(now()->startOfSecond());
        $post = $this->article($teacher);
        $post->update(['response_time_limit_minutes' => 1]);
        $this->asStudent($students[0])->postJson(route('student.blog.timer.start', $post), [])->assertOk();
        $attempt = BlogResponseAttempt::firstOrFail();
        $this->postJson(route('student.blog.timer.draft', ['post' => $post, 'attempt' => $attempt]), ['body' => 'A private draft saved before closing the page.'])->assertOk();
        $this->travel(65)->seconds();
        $this->asTeacher($teacher)->getJson(route('teacher.notifications'))->assertOk()->assertJsonPath('unread', 1)->assertJsonPath('items.0.title', 'New student blog comment')->assertDontSee('A private draft saved');
        $this->assertDatabaseHas('blog_comments', ['student_id' => $students[0]->id, 'body' => 'A private draft saved before closing the page.', 'auto_submitted' => true]);
        $this->getJson(route('teacher.notifications'))->assertJsonPath('unread', 1);
    }
}
