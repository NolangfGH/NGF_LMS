<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\CodeWorkspace;
use App\Models\LabSubmission;
use App\Models\LessonProgress;
use App\Models\Student;
use App\Models\StudentAchievement;
use App\Models\TeacherAccount;
use App\Models\TeacherAssignment;
use App\Models\TeacherQuiz;
use App\Services\HomepageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class HomepageLiveTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $teacher = TeacherAccount::create(['first_name' => 'Owner', 'last_name' => 'Teacher', 'school' => 'School', 'email' => 'home-owner@example.test', 'password' => 'password123']);
        $outsider = TeacherAccount::create(['first_name' => 'Private', 'last_name' => 'Teacher', 'school' => 'Other School', 'email' => 'home-private@example.test', 'password' => 'password123']);
        $room = Classroom::create(['teacher_account_id' => $teacher->id, 'name' => 'Secret own class', 'grade' => 'Grade 8', 'section' => 'A', 'join_code' => 'JOINSECRET']);
        $other = Classroom::create(['teacher_account_id' => $outsider->id, 'name' => 'Private foreign class', 'grade' => 'Grade 8', 'section' => 'B', 'join_code' => 'JOINFOREIGN']);
        $student = Student::create(['classroom_id' => $room->id, 'first_name' => 'Alex', 'nickname' => 'OwnCoder', 'student_id' => 'HOMEPRIVATE1', 'password' => 'password123', 'xp' => 100]);
        $peer = Student::create(['classroom_id' => $other->id, 'first_name' => 'Private', 'nickname' => 'ForeignCoder', 'student_id' => 'HOMEPRIVATE2', 'password' => 'password123', 'xp' => 900]);
        $assignment = TeacherAssignment::create(['teacher_account_id' => $teacher->id, 'classroom_id' => $room->id, 'title' => 'Private lesson task', 'kind' => 'assignment', 'status' => 'published']);
        $quiz = TeacherQuiz::create(['teacher_account_id' => $teacher->id, 'classroom_id' => $room->id, 'title' => 'Private quiz title', 'status' => 'published', 'questions' => [['prompt' => 'A secret exam prompt', 'correct' => ['secret answer']]]]);
        TeacherAssignment::create(['teacher_account_id' => $teacher->id, 'classroom_id' => $room->id, 'title' => 'Draft task', 'status' => 'draft']);
        TeacherQuiz::create(['teacher_account_id' => $outsider->id, 'classroom_id' => $other->id, 'title' => 'Draft quiz', 'status' => 'draft', 'questions' => []]);

        return compact('teacher', 'outsider', 'room', 'other', 'student', 'peer', 'assignment', 'quiz');
    }

    public function test_guest_home_uses_real_totals_and_does_not_publish_classroom_or_identity_records(): void
    {
        extract($this->fixture());
        $response = $this->get(route('home'))->assertOk()->assertSee('Your learning platform, right now')->assertSee('Build your C++ foundations')
            ->assertDontSee('14,000')->assertDontSee('60+ Guided Missions')->assertDontSee('420')->assertDontSee('TEST PASSING')
            ->assertDontSee('OwnCoder')->assertDontSee('ForeignCoder')->assertDontSee('JOINSECRET')->assertDontSee('Secret own class')
            ->assertDontSee('A secret exam prompt')->assertDontSee('Private lesson task');
        $home = $response->viewData('home');
        $this->assertSame(['students' => 2, 'educators' => 2, 'sections' => 2, 'published_activities' => 2, 'published_assignments' => 1, 'published_quizzes' => 1], $home['metrics']);
        $this->assertSame(count(config('lessons')), $home['catalog_totals']['lessons']);
        $this->assertNull($home['student']);
        $this->assertNull($home['teacher']);
        $this->assertArrayNotHasKey('checks', $home['catalog'][0]);
        $this->getJson(route('home.status', ['student_id' => $student->id, 'teacher_id' => $teacher->id]))->assertOk()
            ->assertDontSee('OwnCoder')->assertDontSee('JOINSECRET')->assertDontSee('HOMEPRIVATE1')->assertDontSee('secret answer');
    }

    public function test_live_endpoint_reads_changes_immediately_and_does_not_cache_old_totals(): void
    {
        extract($this->fixture());
        $first = $this->getJson(route('home.status'))->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $revision = $first->json('revision');
        $this->travel(1)->seconds();
        $this->getJson(route('home.status'))->assertOk()->assertJsonPath('revision', $revision);
        Student::create(['classroom_id' => $room->id, 'first_name' => 'New', 'nickname' => 'NewCoder', 'student_id' => 'HOMENEW', 'password' => 'password123', 'xp' => 100]);
        $next = $this->getJson(route('home.status'))->assertOk();
        $this->assertNotSame($revision, $next->json('revision'));
        $this->assertStringContainsString('Registered students', $next->json('fragments.stats'));
        $this->assertSame(3, app(HomepageService::class)->snapshot()['metrics']['students']);
        $assignment->delete();
        $this->assertSame(1, app(HomepageService::class)->snapshot()['metrics']['published_activities']);
    }

    public function test_signed_in_student_sees_only_own_saved_progress_and_reads_do_not_award_xp(): void
    {
        extract($this->fixture());
        $student->update(['nickname' => 'SafeCoder', 'xp' => 430, 'level' => 1]);
        StudentAchievement::create(['student_id' => $student->id, 'badge_code' => 'first_code', 'earned_at' => now(), 'evidence' => []]);
        LessonProgress::create(['student_id' => $student->id, 'lesson_key' => 'output', 'started_at' => now(), 'last_opened_at' => now(), 'completed_at' => now()]);
        $workspace = CodeWorkspace::create(['student_id' => $student->id, 'activity_key' => 'practice:greeting', 'title' => 'Code', 'code' => 'PRIVATE SOURCE']);
        LabSubmission::create(['code_workspace_id' => $workspace->id, 'teacher_account_id' => $teacher->id, 'revision' => 1, 'title' => 'Code', 'code' => 'PRIVATE SOURCE', 'reflection' => 'PRIVATE REFLECTION']);
        foreach ([1, 2] as $attempt) {
            DB::table('quiz_submissions')->insert(['student_id' => $student->id, 'teacher_quiz_id' => $quiz->id, 'attempt_number' => $attempt, 'answers' => '["PRIVATE ANSWER"]', 'total_questions' => 2, 'created_at' => now(), 'updated_at' => now()]);
        }
        $this->withSession(['student_id' => $student->id, 'student_session_version' => 1]);
        $page = $this->get(route('home'))->assertOk()->assertSee('Welcome, SafeCoder')->assertSee('430')->assertSee('170 XP to go')->assertDontSee('ForeignCoder')->assertDontSee('PRIVATE SOURCE');
        $data = $page->viewData('home')['student'];
        $this->assertSame(1, $data['quizzes_submitted']);
        $this->assertSame(1, $data['coding_submitted']);
        $this->assertSame(1, $data['lessons_completed']);
        $this->assertSame(1, $data['badges']);
        $this->getJson(route('home.status'))->assertOk()->assertDontSee('PRIVATE ANSWER')->assertDontSee('PRIVATE REFLECTION')->assertDontSee('HOMEPRIVATE1');
        $this->assertSame(430, $student->fresh()->xp);
        $this->assertDatabaseCount('student_achievements', 1);
    }

    public function test_teacher_summary_is_scoped_and_revisions_count_once_in_review_queue(): void
    {
        extract($this->fixture());
        $workspace = CodeWorkspace::create(['student_id' => $student->id, 'activity_key' => 'practice:greeting', 'title' => 'Code', 'code' => 'PRIVATE CODE']);
        foreach ([1, 2] as $revision) {
            LabSubmission::create(['code_workspace_id' => $workspace->id, 'teacher_account_id' => $teacher->id, 'revision' => $revision, 'title' => 'Code', 'code' => 'PRIVATE CODE', 'reflection' => 'Private explanation.']);
        }
        $foreign = CodeWorkspace::create(['student_id' => $peer->id, 'activity_key' => 'practice:greeting', 'title' => 'Foreign', 'code' => 'Foreign code']);
        LabSubmission::create(['code_workspace_id' => $foreign->id, 'teacher_account_id' => $outsider->id, 'revision' => 1, 'title' => 'Foreign', 'code' => 'Foreign code', 'reflection' => 'Private explanation.']);
        LessonProgress::create(['student_id' => $student->id, 'lesson_key' => 'output', 'started_at' => now(), 'last_opened_at' => now(), 'confidence' => 'need_help']);
        $this->withSession(['teacher_id' => $teacher->id, 'teacher_session_version' => 1]);
        $response = $this->get(route('home'))->assertOk()->assertSee('Owner Teacher')->assertDontSee('Private Teacher')->assertDontSee('ForeignCoder')->assertDontSee('JOINSECRET')->assertDontSee('PRIVATE CODE');
        $summary = $response->viewData('home')['teacher'];
        $this->assertSame(1, $summary['sections']);
        $this->assertSame(1, $summary['students']);
        $this->assertSame(1, $summary['pending_reviews']);
        $this->assertSame(1, $summary['support_learners']);
        $this->getJson(route('home.status'))->assertOk()->assertDontSee('home-owner@example.test')->assertDontSee('Foreign code');
    }

    public function test_expired_student_sessions_are_replaced_by_public_portal_content(): void
    {
        extract($this->fixture());
        $this->withSession(['student_id' => $student->id, 'student_session_version' => 1])->getJson(route('home.status'))->assertOk()->assertSee('OwnCoder');
        $student->forceFill(['session_version' => 2])->save();
        $this->getJson(route('home.status'))->assertOk()->assertDontSee('OwnCoder')->assertSessionMissing('student_id');
    }

    public function test_empty_database_and_outage_do_not_show_demo_counts_or_sql_details(): void
    {
        $page = $this->get(route('home'))->assertOk()->assertSee('No student accounts have been registered yet');
        $this->assertSame(0, $page->viewData('home')['metrics']['students']);
        Schema::drop('teacher_accounts');
        $page = $this->get(route('home'))->assertOk()->assertSee('Live classroom data is temporarily unavailable');
        $this->assertNull($page->viewData('home')['metrics']);
        $this->getJson(route('home.status'))->assertStatus(503)->assertJsonPath('available', false)->assertDontSee('SQLSTATE')->assertDontSee('select count');
    }

    public function test_course_search_uses_the_real_library_and_escapes_input(): void
    {
        $page = $this->get(route('home', ['q' => 'loops']))->assertOk()->assertSee('for loop')->assertSee('home-search');
        $this->assertSame(count(config('lessons')), count($page->viewData('home')['catalog']));
        $this->get(route('home', ['q' => '<script>alert(1)</script>']))->assertOk()->assertDontSee('<script>alert(1)</script>', false)->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
        $this->getJson(route('home', ['q' => ['invalid']]))->assertUnprocessable();
    }

    public function test_classmates_on_a_shared_ip_have_independent_refresh_allowances(): void
    {
        $firstSession = clone app('session.store');
        $secondSession = clone app('session.store');
        $firstSession->setId(str_repeat('a', 40));
        $secondSession->setId(str_repeat('b', 40));
        $first = Request::create('/home/status', server: ['REMOTE_ADDR' => '192.0.2.10']);
        $second = Request::create('/home/status', server: ['REMOTE_ADDR' => '192.0.2.10']);
        $first->setLaravelSession($firstSession);
        $second->setLaravelSession($secondSession);
        $limiter = RateLimiter::limiter('home-live');
        $firstLimit = $limiter($first);
        $secondLimit = $limiter($second);
        $this->assertSame(12, $firstLimit->maxAttempts);
        $this->assertNotSame($firstLimit->key, $secondLimit->key);
    }
}
