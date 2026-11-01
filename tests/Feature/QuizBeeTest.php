<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\LearningNotification;
use App\Models\QuizBeeMatch;
use App\Models\Student;
use App\Models\TeacherAccount;
use App\Services\LearningNotificationTargets;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class QuizBeeTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $this->travelTo(Carbon::parse('2026-11-01 02:00:00.000000'));
        $teacher = TeacherAccount::create(['first_name' => 'Bee', 'last_name' => 'Teacher', 'school' => 'School', 'email' => 'bee@example.test', 'password' => 'password123']);
        $other = TeacherAccount::create(['first_name' => 'Other', 'last_name' => 'Teacher', 'school' => 'Other', 'email' => 'other-bee@example.test', 'password' => 'password123']);
        $room = Classroom::create(['teacher_account_id' => $teacher->id, 'name' => 'C++', 'grade' => 'Grade 8', 'section' => 'AM', 'join_code' => 'BEEAM']);
        $foreign = Classroom::create(['teacher_account_id' => $other->id, 'name' => 'Private', 'grade' => 'Grade 8', 'section' => 'DB', 'join_code' => 'BEEDB']);
        $students = collect(range(1, 4))->map(fn ($i) => Student::create(['classroom_id' => $room->id, 'first_name' => 'Coder '.$i, 'nickname' => 'BeeCoder'.$i, 'student_id' => 'BEE'.$i, 'password' => 'password123', 'xp' => 100]));
        $stranger = Student::create(['classroom_id' => $foreign->id, 'first_name' => 'Private', 'nickname' => 'PrivateCoder', 'student_id' => 'BEEPRIVATE', 'password' => 'password123', 'xp' => 100]);
        $this->withSession(['teacher_id' => $teacher->id]);
        $questions = config('quiz_bee.questions');
        foreach ($questions as &$level) {
            $level['regular'] = array_slice($level['regular'], 0, 1);
        }unset($level);
        $payload = ['title' => 'Class C++ Quiz Bee', 'category' => 'group', 'status' => 'lobby', 'questions' => $questions, 'teams' => [
            ['name' => 'Loop Legends', 'captain_id' => $students[0]->id, 'members' => [$students[1]->id]], ['name' => 'Array Aces', 'captain_id' => $students[2]->id, 'members' => [$students[3]->id]]]];

        return compact('teacher', 'other', 'room', 'students', 'stranger', 'payload');
    }

    private function createMatch(array $payload): QuizBeeMatch
    {
        $this->post(route('teacher.quiz-bee.store'), $payload)->assertRedirect()->assertSessionHasNoErrors();

        return QuizBeeMatch::latest('id')->firstOrFail();
    }

    private function studentAction(QuizBeeMatch $m, Student $s, string $action, array $extra = [])
    {
        return $this->withSession(['teacher_id' => null, 'student_id' => $s->id, 'student_class_verified' => true])->postJson(route('student.quiz-bee.action', $m), ['action' => $action, ...$extra]);
    }

    private function host(QuizBeeMatch $m, string $action, array $extra = [])
    {
        return $this->withSession(['teacher_id' => $m->teacher_account_id, 'student_id' => null])->postJson(route('teacher.quiz-bee.action', $m), ['action' => $action, ...$extra]);
    }

    private function start(QuizBeeMatch $m): void
    {
        foreach (DB::table('quiz_bee_teams')->where('quiz_bee_match_id', $m->id)->get() as $team) {
            $this->studentAction($m, Student::findOrFail($team->captain_id), 'ready')->assertOk();
        }
        $this->host($m, 'start')->assertOk();
    }

    private function currentRun(QuizBeeMatch $m): object
    {
        return DB::table('quiz_bee_runs')->find($m->fresh()->current_run_id);
    }

    private function answerBoth(QuizBeeMatch $m, bool $first, bool $second): void
    {
        $run = $this->currentRun($m);
        $correct = json_decode($run->question, true)['correct'];
        foreach (DB::table('quiz_bee_teams')->where('quiz_bee_match_id', $m->id)->orderBy('id')->get() as $index => $team) {
            $this->studentAction($m, Student::findOrFail($team->captain_id), 'answer', ['run_id' => $run->id, 'answer' => ($index === 0 ? $first : $second) ? $correct : ($correct + 1) % 4])->assertOk();
        }
    }

    public function test_drafts_publish_invitations_and_pages_enforce_ownership(): void
    {
        $this->get(route('teacher.quiz-bee.index'))->assertRedirect(route('teacher.login'));
        $this->get(route('student.quiz-bee.index'))->assertRedirect(route('student.login'));
        extract($this->fixture());
        $this->get(route('teacher.quiz-bee.create'))->assertOk();
        $m = $this->createMatch([...$payload, 'status' => 'draft']);
        $this->assertDatabaseCount('challenge_notices', 0);
        $this->get(route('teacher.quiz-bee.edit', $m))->assertOk();
        $this->get(route('teacher.quiz-bee.show', $m))->assertOk();
        $this->withSession(['teacher_id' => null, 'student_id' => $students[0]->id])->get(route('student.quiz-bee.show', $m))->assertNotFound();
        $this->withSession(['teacher_id' => $teacher->id])->put(route('teacher.quiz-bee.update', $m), $payload)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseCount('challenge_notices', 4);
        $notice = LearningNotification::where('student_id', $students[0]->id)->firstOrFail();
        $targets = app(LearningNotificationTargets::class);
        $this->assertSame(route('student.quiz-bee.show', $m), $targets->url($notice, $students[0]));
        $this->assertNull($targets->url($notice, $stranger));
        $this->get(route('teacher.quiz-bee.index'))->assertOk()->assertSee($payload['title']);
        $this->get(route('teacher.quiz-bee.show', $m))->assertOk()->assertSee('Live standings');
        $this->putJson(route('teacher.quiz-bee.update', $m), $payload)->assertConflict();
        $this->withSession(['teacher_id' => $other->id])->getJson(route('teacher.quiz-bee.status', $m))->assertNotFound();
        $this->withSession(['teacher_id' => null, 'student_id' => $stranger->id])->getJson(route('student.quiz-bee.status', $m))->assertNotFound();
        $this->withSession(['student_id' => $students[0]->id])->get(route('student.quiz-bee.index'))->assertOk()->assertSee($payload['title']);
        $this->get(route('student.quiz-bee.show', $m))->assertOk()->assertSee('Submit final answer');
    }

    public function test_roster_and_question_limits_reject_invalid_matches(): void
    {
        extract($this->fixture());
        $url = route('teacher.quiz-bee.store');
        foreach ([['category' => 'unsupported'], ['teams' => [$payload['teams'][0]]], ['teams' => array_fill(0, 41, $payload['teams'][0])], ['category' => 'individual']] as $change) {
            $this->postJson($url, array_replace($payload, $change))->assertUnprocessable();
        }
        $bad = $payload;
        $bad['teams'][1]['members'] = [$stranger->id];
        $this->postJson($url, $bad)->assertUnprocessable();
        $bad = $payload;
        $bad['teams'][1]['members'] = [$students[0]->id];
        $this->postJson($url, $bad)->assertUnprocessable();
        foreach ([['regular', 16], ['regular', 0], ['tiebreak', 4], ['tiebreak', 6]] as [$kind,$count]) {
            $bad = $payload;
            $bad['questions']['easy'][$kind] = array_fill(0, $count, $payload['questions']['easy']['regular'][0]);
            $this->postJson($url, $bad)->assertUnprocessable()->assertJsonValidationErrors('questions.easy.'.$kind);
        }
        $this->assertDatabaseCount('quiz_bee_matches', 0);
    }

    public function test_forty_groups_of_five_and_forty_individual_entries_are_supported(): void
    {
        extract($this->fixture());
        $roster = $students->concat(collect(range(5, 200))->map(fn ($i) => Student::create(['classroom_id' => $room->id, 'first_name' => 'Coder', 'nickname' => 'Coder'.$i, 'student_id' => 'MAXBEE'.$i, 'password' => 'password123'])));
        $teams = $roster->chunk(5)->values()->map(fn ($members, $index) => ['name' => 'Team '.($index + 1), 'captain_id' => $members->first()->id, 'members' => $members->slice(1)->pluck('id')->values()->all()])->all();
        $m = $this->createMatch([...$payload, 'teams' => $teams, 'status' => 'draft']);
        $this->assertDatabaseCount('quiz_bee_teams', 40);
        $this->assertDatabaseCount('quiz_bee_members', 200);
        $this->get(route('teacher.quiz-bee.edit', $m))->assertOk();
        $individual = $roster->take(40)->map(fn ($s) => ['captain_id' => $s->id, 'members' => []])->values()->all();
        $this->createMatch([...$payload, 'category' => 'individual', 'teams' => $individual]);
        $this->assertDatabaseCount('quiz_bee_teams', 80);
        $this->assertDatabaseCount('quiz_bee_members', 240);
        $teams[0]['members'][] = $roster[5]->id;
        $this->postJson(route('teacher.quiz-bee.store'), [...$payload, 'teams' => $teams])->assertUnprocessable();
    }

    public function test_only_ready_captains_can_answer_and_answer_keys_stay_private_until_close(): void
    {
        extract($this->fixture());
        $m = $this->createMatch($payload);
        $this->host($m, 'start')->assertUnprocessable();
        $this->studentAction($m, $students[1], 'ready')->assertForbidden();
        $this->studentAction($m, $students[0], 'ready')->assertOk();
        $this->studentAction($m, $students[0], 'ready')->assertOk();
        $this->assertSame(1, LearningNotification::where('teacher_account_id', $teacher->id)->count());
        $this->start($m);
        $run = $this->currentRun($m);
        $this->host($m, 'start')->assertConflict();
        $this->host($m, 'next', ['run_id' => $run->id])->assertConflict();
        $this->studentAction($m, $students[1], 'answer', ['run_id' => $run->id, 'answer' => 0])->assertForbidden();
        $this->withSession(['student_id' => $students[0]->id])->getJson(route('student.quiz-bee.status', $m))->assertOk()->assertJsonPath('question.correct', null)->assertJsonPath('question.why', null)->assertJsonPath('question.own_correct', null)->assertDontSee($payload['questions']['difficult']['regular'][0]['prompt']);
        $this->assertArrayNotHasKey('questions', $m->toArray());
    }

    public function test_server_timing_rewards_and_final_answers_are_idempotent(): void
    {
        extract($this->fixture());
        $m = $this->createMatch($payload);
        $this->start($m);
        $run = $this->currentRun($m);
        $this->travelTo(Carbon::parse($run->opened_at)->addSeconds(30));
        $this->studentAction($m, $students[0], 'answer', ['run_id' => $run->id, 'answer' => 0, 'elapsed_ms' => 0, 'xp' => 9999])->assertOk();
        $this->assertSame(100, $students[0]->fresh()->xp);
        $this->studentAction($m, $students[0], 'answer', ['run_id' => $run->id, 'answer' => 1])->assertConflict();
        $this->travelTo(Carbon::parse($run->opened_at)->addMilliseconds(30001));
        $this->studentAction($m, $students[2], 'answer', ['run_id' => $run->id, 'answer' => 0, 'elapsed_ms' => 0])->assertOk();
        foreach ([0, 1] as $i) {
            $this->assertSame(115, $students[$i]->fresh()->xp);
        }foreach ([2, 3] as $i) {
            $this->assertSame(110, $students[$i]->fresh()->xp);
        }
        $this->studentAction($m, $students[0], 'answer', ['run_id' => $run->id, 'answer' => 0])->assertOk();
        $this->getJson(route('student.quiz-bee.status', $m))->assertOk()->assertJsonPath('question.closed', true)->assertJsonPath('question.own_seconds', 30)->assertJsonPath('question.own_xp', 15);
        $this->assertDatabaseCount('learning_rewards', 4);
        $this->assertSame([30000, 30001], DB::table('quiz_bee_answers')->orderBy('id')->pluck('elapsed_ms')->all());
    }

    public function test_timeouts_persist_and_refresh_does_not_restart_the_question(): void
    {
        extract($this->fixture());
        $m = $this->createMatch($payload);
        $this->start($m);
        $run = $this->currentRun($m);
        $this->travelTo(Carbon::parse($run->closes_at));
        $this->studentAction($m, $students[0], 'answer', ['run_id' => $run->id, 'answer' => 0])->assertConflict();
        $this->getJson(route('student.quiz-bee.status', $m))->assertOk()->assertJsonPath('question.closed', true)->assertJsonPath('question.own_seconds', 60);
        $this->assertSame(2, DB::table('quiz_bee_answers')->where('elapsed_ms', 60000)->whereNull('submitted_at')->count());
        $this->assertSame($run->closes_at, $this->currentRun($m)->closes_at);
        $this->assertSame(100, $students[0]->fresh()->xp);
        $this->host($m, 'next', ['run_id' => $run->id])->assertOk();
        $this->assertSame('tiebreak', $this->currentRun($m)->kind);
        $this->host($m, 'next', ['run_id' => $run->id])->assertConflict();
    }

    public function test_three_levels_complete_and_perfect_bonus_is_once_per_member(): void
    {
        extract($this->fixture());
        $m = $this->createMatch($payload);
        $this->start($m);
        foreach (QuizBeeMatch::LEVELS as $level) {
            $run = $this->currentRun($m);
            $this->assertSame($level, $run->level);
            $this->answerBoth($m, true, false);
            $this->host($m, 'next', ['run_id' => $run->id])->assertOk();
        }
        $this->assertSame('completed', $m->fresh()->status);
        foreach ([0, 1] as $i) {
            $this->assertSame(245, $students[$i]->fresh()->xp);
        }foreach ([2, 3] as $i) {
            $this->assertSame(100, $students[$i]->fresh()->xp);
        }
        $this->getJson(route('teacher.quiz-bee.status', $m))->assertOk()->assertJsonPath('standings.0.correct', 3)->assertJsonPath('standings.0.xp_per_member', 145);
        $this->host($m, 'next', ['run_id' => $run->id])->assertConflict();
        $this->assertDatabaseCount('learning_rewards', 8);
        $this->assertSame(245, (int) DB::table('xp_events')->where('student_id', $students[0]->id)->sum('amount'));
    }

    public function test_five_tie_breakers_per_level_end_in_shared_rank_if_unresolved(): void
    {
        extract($this->fixture());
        $m = $this->createMatch($payload);
        $this->start($m);
        foreach (QuizBeeMatch::LEVELS as $level) {
            $run = $this->currentRun($m);
            $this->answerBoth($m, false, false);
            $this->host($m, 'next', ['run_id' => $run->id])->assertOk();
            for ($i = 0; $i < 5; $i++) {
                $run = $this->currentRun($m);
                $this->assertSame($level, $run->level);
                $this->assertSame('tiebreak', $run->kind);
                $this->assertSame($i, $run->question_index);
                $this->answerBoth($m, false, false);
                $this->host($m, 'next', ['run_id' => $run->id])->assertOk();
            }
        }
        $this->assertSame('completed', $m->fresh()->status);
        $this->assertDatabaseCount('quiz_bee_runs', 18);
        $this->getJson(route('teacher.quiz-bee.status', $m))->assertOk()->assertJsonPath('standings.0.rank', 1)->assertJsonPath('standings.1.rank', 1);
        $this->assertDatabaseCount('learning_rewards', 0);
    }

    public function test_tie_breaker_excludes_non_leaders_then_returns_all_entries_to_regular_play(): void
    {
        extract($this->fixture());
        $payload['category'] = 'individual';
        $payload['teams'] = $students->take(3)->map(fn ($s) => ['captain_id' => $s->id, 'members' => []])->all();
        $m = $this->createMatch($payload);
        $this->start($m);
        $run = $this->currentRun($m);
        foreach ($students->take(3) as $i => $s) {
            $this->studentAction($m, $s, 'answer', ['run_id' => $run->id, 'answer' => $i === 2 ? 1 : 0])->assertOk();
        }
        $this->host($m, 'next', ['run_id' => $run->id])->assertOk();
        $run = $this->currentRun($m);
        $this->assertCount(2, json_decode($run->eligible_team_ids, true));
        $this->studentAction($m, $students[2], 'answer', ['run_id' => $run->id, 'answer' => 1])->assertForbidden();
        $this->studentAction($m, $students[0], 'answer', ['run_id' => $run->id, 'answer' => 1])->assertOk();
        $this->studentAction($m, $students[1], 'answer', ['run_id' => $run->id, 'answer' => 0])->assertOk();
        $this->host($m, 'next', ['run_id' => $run->id])->assertOk();
        $this->assertSame('average', $this->currentRun($m)->level);
        $this->assertSame('regular', $this->currentRun($m)->kind);
        $this->assertCount(3, json_decode($this->currentRun($m)->eligible_team_ids, true));
    }

    public function test_cancellation_stops_answers_without_an_extra_perfect_bonus(): void
    {
        extract($this->fixture());
        $m = $this->createMatch($payload);
        $this->start($m);
        $run = $this->currentRun($m);
        $this->answerBoth($m, true, false);
        $this->host($m, 'cancel')->assertOk();
        $this->studentAction($m, $students[0], 'answer', ['run_id' => $run->id, 'answer' => 0])->assertConflict();
        $this->assertSame(115, $students[0]->fresh()->xp);
        $this->assertSame('cancelled', $m->fresh()->status);
        $this->assertSame(0, DB::table('learning_rewards')->where('event_key', 'like', '%:perfect')->count());
    }

    public function test_sixty_question_banks_accept_the_full_limit_and_question_text_is_escaped(): void
    {
        extract($this->fixture());
        foreach (QuizBeeMatch::LEVELS as $level) {
            $payload['questions'][$level]['regular'] = array_fill(0, 15, $payload['questions'][$level]['regular'][0]);
        }
        $payload['questions']['easy']['regular'][0]['prompt'] = '<script>alert(9)</script>';
        $match = $this->createMatch([...$payload, 'status' => 'draft']);
        $this->assertSame(60, collect($match->questions)->sum(fn ($bank) => count($bank['regular']) + count($bank['tiebreak'])));
        $this->get(route('teacher.quiz-bee.edit', $match))->assertOk()->assertDontSee('<script>alert(9)</script>', false)->assertSee('&lt;script&gt;alert(9)&lt;/script&gt;', false);
    }

    public function test_invalid_answer_keys_duplicate_choices_and_unapproved_students_are_rejected(): void
    {
        extract($this->fixture());
        $bad = $payload;
        $bad['questions']['easy']['regular'][0]['correct'] = 4;
        $this->postJson(route('teacher.quiz-bee.store'), $bad)->assertUnprocessable()->assertJsonValidationErrors('questions.easy.regular.0.correct');
        $bad = $payload;
        $bad['questions']['easy']['regular'][0]['options'][1] = $bad['questions']['easy']['regular'][0]['options'][0];
        $this->postJson(route('teacher.quiz-bee.store'), $bad)->assertUnprocessable()->assertJsonValidationErrors('questions');
        $students[1]->forceFill(['registration_status' => 'pending'])->save();
        $this->postJson(route('teacher.quiz-bee.store'), $payload)->assertUnprocessable()->assertJsonValidationErrors('teams');
        $this->assertDatabaseCount('quiz_bee_matches', 0);
    }
}
