<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\CodeWorkspace;
use App\Models\LessonContent;
use App\Models\Student;
use App\Models\TeacherAccount;
use App\Models\TeacherAssignment;
use App\Models\TeacherQuiz;
use App\Services\LearningRewards;
use App\Services\QuizAttemptService;
use App\Services\QuizGrader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class PlatformFeaturesTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        Http::preventStrayRequests();
        $teacher = TeacherAccount::create(['first_name' => 'Teacher', 'last_name' => 'One', 'email' => 'platform@example.test', 'school' => 'School', 'password' => 'password123']);
        $other = TeacherAccount::create(['first_name' => 'Other', 'last_name' => 'Teacher', 'email' => 'other-platform@example.test', 'school' => 'School', 'password' => 'password123']);
        $room = Classroom::create(['teacher_account_id' => $teacher->id, 'name' => 'C++', 'grade' => 'Grade 8', 'section' => 'AM', 'join_code' => 'PLATAM']);
        $foreign = Classroom::create(['teacher_account_id' => $other->id, 'name' => 'Private', 'grade' => 'Grade 8', 'section' => 'DB', 'join_code' => 'PLATDB']);
        $student = Student::create(['classroom_id' => $room->id, 'first_name' => 'Alice', 'nickname' => 'AliceCoder', 'student_id' => 'PLAT1', 'password' => 'password123', 'xp' => 100, 'level' => 1]);
        $peer = Student::create(['classroom_id' => $foreign->id, 'first_name' => 'Private', 'nickname' => 'PrivateCoder', 'student_id' => 'PLAT2', 'password' => 'password123', 'xp' => 100]);
        $workspace = CodeWorkspace::create(['student_id' => $student->id, 'activity_key' => 'practice:variables', 'title' => 'Variables', 'code' => '#include <iostream>\nint main(){std::cout << 12;}', 'reflection' => 'I multiply teams by the devices per team.']);
        $this->withSession(['student_id' => $student->id, 'student_class_verified' => true]);

        return compact('teacher', 'other', 'room', 'foreign', 'student', 'peer', 'workspace');
    }

    private function compiler(array $outputs): void
    {
        Http::swap(new Factory);
        Http::preventStrayRequests();
        config(['compiler.url' => 'https://compiler.school.test', 'compiler.token' => 'private-token']);
        Http::fake(function ($request) use ($outputs) {
            if ($request->method() === 'POST') {
                return Http::response(array_map(fn ($index) => ['token' => 'test-token-0000000'.$index], array_keys($outputs)), 201);
            }

            return Http::response(['submissions' => array_map(fn ($output, $index) => ['token' => 'test-token-0000000'.$index, 'status' => ['id' => $output['status'] ?? 3],
                'stdout' => base64_encode($output['stdout'] ?? ''), 'stderr' => base64_encode($output['stderr'] ?? ''), 'compile_output' => base64_encode($output['compile'] ?? ''), 'time' => '0.01', 'memory' => 2048], $outputs, array_keys($outputs))]);
        });
    }

    private function start(CodeWorkspace $workspace, string $mode = 'tests'): array
    {
        return $this->postJson(route('student.code-runs.store', $workspace), ['revision' => $workspace->fresh()->revision, 'mode' => $mode, 'stdin' => '', 'request_key' => (string) Str::uuid()])->assertAccepted()->json();
    }

    public function test_compiler_is_explicitly_configured_and_workspace_access_is_enforced(): void
    {
        extract($this->fixture());
        config(['compiler.url' => null]);
        $this->postJson(route('student.code-runs.store', $workspace), ['revision' => 1, 'mode' => 'run', 'request_key' => (string) Str::uuid()])->assertUnprocessable();
        $this->assertDatabaseCount('code_runs', 0);
        Http::assertNothingSent();
        $this->withSession(['student_id' => $peer->id])->postJson(route('student.code-runs.store', $workspace), [])->assertForbidden();
        $this->withSession(['student_id' => null, 'teacher_id' => $teacher->id])->postJson(route('student.code-runs.store', $workspace), [])->assertForbidden();
    }

    public function test_compiler_enforces_limits_idempotency_and_practice_rewards_are_awarded_once(): void
    {
        extract($this->fixture());
        $this->compiler([['stdout' => '12']]);
        $payload = ['revision' => 1, 'mode' => 'tests', 'request_key' => (string) Str::uuid()];
        $first = $this->postJson(route('student.code-runs.store', $workspace), $payload)->assertAccepted()->json();
        $this->postJson(route('student.code-runs.store', $workspace), $payload)->assertAccepted()->assertJsonPath('id', $first['id']);
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request->method() === 'POST' && $request->hasHeader('X-Auth-Token', 'private-token')
            && $request['submissions'][0]['enable_network'] === false && $request['submissions'][0]['cpu_time_limit'] === 2
            && $request['submissions'][0]['memory_limit'] === 128000 && ! isset($request['submissions'][0]['callback_url']));
        $this->getJson($first['status_url'])->assertOk()->assertJsonPath('status', 'completed')->assertJsonPath('percentage', 100);
        $this->assertSame(130, $student->fresh()->xp);
        $this->getJson($first['status_url'])->assertOk();
        $this->assertSame(130, $student->fresh()->xp);
        $this->assertDatabaseCount('learning_rewards', 1);
        $this->assertSame(30, (int) DB::table('xp_events')->where('student_id', $student->id)->latest('id')->value('amount'));
        $this->assertDatabaseHas('student_achievements', ['student_id' => $student->id, 'badge_code' => 'first_program']);
    }

    public function test_hidden_tests_stay_private_and_partial_test_scores_do_not_replace_teacher_grades(): void
    {
        extract($this->fixture());
        $assignment = TeacherAssignment::create(['teacher_account_id' => $teacher->id, 'classroom_id' => $room->id, 'title' => 'Private tests', 'status' => 'published', 'test_cases' => [
            ['input' => 'sample', 'output' => 'expected', 'hidden' => false, 'weight' => 2], ['input' => 'SECRET_INPUT', 'output' => 'SECRET_EXPECTED', 'hidden' => true, 'weight' => 3],
        ]]);
        $workspace->update(['teacher_assignment_id' => $assignment->id, 'activity_key' => 'assignment:'.$assignment->id]);
        $this->compiler([['stdout' => 'expected'], ['stdout' => 'leak SECRET_INPUT', 'stderr' => 'SECRET_EXPECTED']]);
        $run = $this->start($workspace);
        $result = $this->getJson($run['status_url'])->assertOk()->assertJsonPath('earned', 2)->assertJsonPath('possible', 5)->assertJsonPath('percentage', 40)
            ->assertDontSee('SECRET_INPUT')->assertDontSee('SECRET_EXPECTED')->assertDontSee('private-token');
        $this->assertArrayNotHasKey('stdout', $result->json('cases.1'));
        $this->assertSame(100, $student->fresh()->xp);
        $this->postJson(route('student.code-lab.submit', $workspace), ['revision' => $workspace->fresh()->revision])->assertOk();
        $this->assertDatabaseHas('lab_submissions', ['code_workspace_id' => $workspace->id, 'code_run_id' => $run['id'], 'score' => null]);
        $this->withSession(['teacher_id' => $teacher->id, 'student_id' => null])->get(route('teacher.code-lab'))->assertOk()->assertSee('Executed test evidence')->assertSee('SECRET_EXPECTED');
        $this->withSession(['teacher_id' => null, 'student_id' => $peer->id])->getJson($run['status_url'])->assertForbidden();
    }

    public function test_compile_errors_timeouts_and_unavailable_results_are_not_fabricated_as_passing(): void
    {
        extract($this->fixture());
        $this->compiler([['status' => 6, 'compile' => 'main.cpp:2: missing semicolon']]);
        $run = $this->start($workspace);
        $this->getJson($run['status_url'])->assertOk()->assertJsonPath('earned', 0)->assertJsonPath('cases.0.status', 'compile_error')->assertSee('missing semicolon');
        $this->assertSame(100, $student->fresh()->xp);
        $this->compiler([['status' => 5]]);
        $run = $this->start($workspace);
        $this->getJson($run['status_url'])->assertJsonPath('cases.0.status', 'timeout');
        Http::swap(new Factory);
        Http::fake(['*' => Http::response(['error' => 'private diagnostic'], 503)]);
        $run = $this->start($workspace);
        $this->assertSame('unavailable', $run['status']);
        $this->assertStringNotContainsString('private diagnostic', json_encode($run));
    }

    public function test_teacher_can_author_tests_hints_and_starter_code_without_leaking_hidden_cases(): void
    {
        extract($this->fixture());
        $this->withSession(['teacher_id' => $teacher->id, 'student_id' => null]);
        $payload = ['title' => 'Tested task', 'classroom_ids' => [$room->id], 'school_term' => 'Term1', 'difficulty' => 'Easy', 'xp' => 20, 'status' => 'published', 'objective' => 'Add input values.', 'starter_code' => 'int main(){}',
            'test_cases' => [['input' => '1 2', 'output' => '3', 'hidden' => 0, 'weight' => 2], ['input' => 'PRIVATE', 'output' => 'SECRET', 'hidden' => 1, 'weight' => 3]], 'hints' => ['Read the input first.']];
        $this->postJson(route('teacher.assignments.store'), $payload)->assertOk();
        $task = TeacherAssignment::firstOrFail();
        $this->assertTrue($task->test_cases[1]['hidden']);
        $this->withSession(['student_id' => $student->id, 'teacher_id' => null])->get(route('student.code-lab', ['assignment' => $task->id]))->assertOk()->assertSee('Check all tests')->assertSee('Read the input first.')->assertDontSee('PRIVATE')->assertDontSee('SECRET');
        $this->withSession(['student_id' => null, 'teacher_id' => $other->id])->putJson(route('teacher.assignments.update', $task), $payload)->assertForbidden();
    }

    public function test_curriculum_content_resources_and_prerequisites_are_scoped_to_the_teacher(): void
    {
        extract($this->fixture());
        Storage::fake('local');
        $definition = config('lessons.arrays');
        $this->withSession(['teacher_id' => $teacher->id, 'student_id' => null]);
        $image = UploadedFile::fake()->createWithContent('array.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jX1cAAAAASUVORK5CYII='));
        $this->post(route('teacher.content.save', 'arrays'), [...$definition, 'title' => 'Our array lesson', 'revision' => 0, 'status' => 'published', 'image' => $image])->assertRedirect()->assertSessionHasNoErrors();
        $content = LessonContent::firstOrFail();
        $this->withSession(['teacher_id' => null, 'student_id' => $student->id])->get(route('student.lesson.show', 'arrays'))->assertOk()->assertSee('Our array lesson')->assertSee(route('student.lesson.image', $content), false);
        $this->get(route('student.lesson.image', $content))->assertOk();
        $this->withSession(['student_id' => $peer->id])->get(route('student.lesson.show', 'arrays'))->assertDontSee('Our array lesson');
        $this->get(route('student.lesson.image', $content))->assertNotFound();
        $room->update(['sequential_learning' => true]);
        $this->withSession(['student_id' => $student->id])->get(route('student.lesson.show', 'arrays'))->assertForbidden();
        $this->get(route('student.lesson.show', 'introduction'))->assertOk();
        $lesson = config('lessons.introduction');
        $answers = array_map(fn ($check) => $check['correct'], $lesson['checks']);
        $this->post(route('student.lesson.checkpoint', 'introduction'), ['answers' => $answers, 'reflection' => 'An algorithm describes the steps before coding.'])->assertRedirect();
        $this->assertSame(125, $student->fresh()->xp);
        $this->post(route('student.lesson.checkpoint', 'introduction'), ['answers' => $answers, 'reflection' => 'I checked the sequence again.'])->assertRedirect();
        $this->assertSame(125, $student->fresh()->xp);
        $this->get(route('student.lesson.show', 'output'))->assertOk();
    }

    public function test_new_quiz_formats_are_checked_and_untimed_attempts_keep_their_snapshot(): void
    {
        extract($this->fixture());
        $questions = [['type' => 'ordering', 'prompt' => 'Order the steps', 'options' => ['Read input', 'Calculate', 'Print output'], 'correct' => ['Read input', 'Calculate', 'Print output']]];
        $this->withSession(['teacher_id' => $teacher->id, 'student_id' => null]);
        $this->postJson(route('teacher.quizzes.store'), ['title' => 'Logic ordering', 'classroom_ids' => [$room->id], 'questions' => $questions, 'status' => 'published', 'xp' => 0, 'time_limit' => 1, 'untimed' => true, 'question_seconds' => 120])->assertOk();
        $quiz = TeacherQuiz::firstOrFail();
        $attempt = app(QuizAttemptService::class)->start($student, $quiz);
        $this->assertTrue($attempt->untimed);
        $this->assertSame(120, $attempt->question_seconds);
        $quiz->update(['untimed' => false, 'question_seconds' => 30]);
        $this->travel(5)->minutes();
        $this->withSession(['student_id' => $student->id, 'teacher_id' => null]);
        $this->get(route('student.quiz'))->assertOk()->assertSee('Untimed assessment');
        $this->postJson(route('student.quiz.question', $quiz), ['question_index' => 0, 'attempt_id' => $attempt->id, 'answer' => $questions[0]['correct']])->assertOk();
        $result = DB::table('quiz_submissions')->first();
        $this->assertSame(1, $result->score);
        $this->assertSame(0, $result->speed_bonus_xp);
        $grader = app(QuizGrader::class);
        foreach (['code_output', 'fill_code', 'debugging'] as $type) {
            $graded = $grader->grade([['type' => $type, 'prompt' => 'C++ answer', 'correct' => ['Hello']]], ['hello']);
            $this->assertSame(0, $graded['score']);
        }
        $this->assertSame(0, $grader->grade($questions, [['Calculate', 'Read input', 'Print output']])['score']);
    }

    public function test_period_leaderboards_use_dated_rewards_and_preserve_total_xp(): void
    {
        extract($this->fixture());
        $this->travelTo(now()->startOfWeek()->addDays(2));
        DB::table('xp_events')->where('student_id', $student->id)->update(['counts_for_period' => false]);
        app(LearningRewards::class)->award($student->id, 'new-lesson', 25);
        app(LearningRewards::class)->award($student->id, 'new-lesson', 25);
        $this->get(route('student.leaderboard', ['period' => 'week']))->assertOk()->assertViewHas('overallRanking', fn ($rows) => $rows->first()['xp'] === 25);
        $this->get(route('student.leaderboard', ['period' => 'all']))->assertViewHas('overallRanking', fn ($rows) => $rows->first()['xp'] === 125);
        $this->assertSame(125, $student->fresh()->xp);
    }

    public function test_admin_permissions_class_archiving_and_dashboards_work_with_real_data(): void
    {
        extract($this->fixture());
        $this->get(route('student.dashboard'))->assertOk()->assertSee('100 XP')->assertSee('Your learning path')->assertDontSee('No saved progress yet');
        $this->getJson(route('student.dashboard.status'))->assertOk()->assertDontSee('PrivateCoder');
        $this->withSession(['student_id' => null, 'teacher_id' => $teacher->id])->get(route('school.admin'))->assertForbidden();
        $this->get(route('teacher.dashboard'))->assertOk()->assertSee('Your classes')->assertSee('PLATAM')->assertDontSee('PLATDB');
        $teacher->forceFill(['role' => 'school_admin'])->save();
        $this->get(route('school.admin'))->assertOk()->assertSee('Teaching accounts');
        $this->put(route('school.roles', $other), ['role' => 'school_admin', 'current_password' => 'password123', 'revision' => 1])->assertRedirect();
        $this->assertTrue($other->fresh()->isSchoolAdmin());
        $this->post(route('teacher.classes.archive', $room), ['archive' => 1])->assertRedirect();
        $this->assertFalse($room->fresh()->acceptsStudentRegistration());
        $this->assertSame(0, $teacher->classrooms()->count());
        $this->withSession(['teacher_id' => null, 'student_id' => null])->postJson(route('student.login.store'), ['student_id' => $student->student_id, 'password' => 'password123'])->assertUnprocessable();
        $this->withSession(['teacher_id' => $teacher->id])->post(route('teacher.classes.archive', $room), ['archive' => 0])->assertRedirect();
        $this->assertFalse($room->fresh()->enrollment_open);
    }

    public function test_full_pages_render_and_generated_browser_scripts_can_be_checked(): void
    {
        extract($this->fixture());
        $directory = storage_path('framework/testing/lms-script-checks');
        File::ensureDirectoryExists($directory);
        $urls = [route('student.dashboard'), route('student.learn'), route('student.lesson.show', 'arrays'), route('student.code-lab', ['activity' => 'arrays']), route('student.leaderboard', ['period' => 'month'])];
        $this->withSession(['teacher_id' => $teacher->id]);
        $urls = [...$urls, route('teacher.dashboard'), route('teacher.assignments'), route('teacher.quizzes'), route('teacher.content'), route('teacher.content.edit', 'arrays'), route('teacher.grading'), route('teacher.reports'), route('teacher.settings')];
        foreach ($urls as $page => $url) {
            $response = $this->get($url)->assertOk();
            preg_match_all('~<script\b[^>]*>(.*?)</script>~s', $response->getContent(), $scripts);
            foreach ($scripts[1] as $index => $script) {
                if (trim($script) !== '') {
                    file_put_contents($directory.'/'.$page.'-'.$index.'.js', $script);
                }
            }
        }
        foreach (config('lessons') as $lesson) {
            $this->assertArrayHasKey($lesson['practice'], config('code_lab.activities'));
            $this->assertStringNotContainsString("'\n'", $lesson['code']);
        }
    }
}
