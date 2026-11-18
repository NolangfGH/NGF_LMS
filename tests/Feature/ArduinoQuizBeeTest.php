<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\QuizBeeMatch;
use App\Models\Student;
use App\Models\TeacherAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ArduinoQuizBeeTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $this->travelTo(Carbon::parse('2026-11-18 04:00:00.000000'));
        $teacher = TeacherAccount::create(['first_name' => 'Robotics', 'last_name' => 'Host', 'school' => 'School', 'email' => 'uno-bee@example.test', 'password' => 'Password123']);
        $room = Classroom::create(['teacher_account_id' => $teacher->id, 'name' => 'Robotics', 'grade' => 'Grade 9', 'section' => 'AM', 'join_code' => 'UNOBEE']);
        $students = collect(range(1, 4))->map(fn ($i) => Student::create(['classroom_id' => $room->id, 'first_name' => 'Builder '.$i, 'nickname' => 'Builder'.$i, 'student_id' => 'UNOBEE'.$i, 'password' => 'Password123', 'xp' => 100]));
        $this->withSession(['teacher_id' => $teacher->id]);
        $payload = ['course' => 'arduino-uno', 'title' => 'Grade 9 Uno Bee', 'category' => 'group', 'status' => 'draft', 'questions' => QuizBeeMatch::questionBank('arduino-uno'),
            'teams' => [['name' => 'Builders', 'captain_id' => $students[0]->id, 'members' => [$students[1]->id]], ['name' => 'Inventors', 'captain_id' => $students[2]->id, 'members' => [$students[3]->id]]]];

        return [$teacher, $students, $payload];
    }

    public function test_bank_contains_sixty_distinct_valid_questions_and_both_difficult_types(): void
    {
        $prompts = [];
        foreach (QuizBeeMatch::questionBank('arduino-uno') as $level => $sets) {
            $this->assertCount(15, $sets['regular']);
            $this->assertCount(5, $sets['tiebreak']);
            foreach (array_merge($sets['regular'], $sets['tiebreak']) as $question) {
                $prompts[] = $question['prompt'];
                if ($question['type'] === 'fill_blank') {
                    $this->assertSame('difficult', $level);
                    $this->assertNotEmpty($question['accepted_answers']);
                } else {
                    $this->assertCount(4, array_unique($question['options']), $question['prompt']);
                    $this->assertArrayHasKey($question['correct'], $question['options']);
                }
            }
        }
        $this->assertCount(60, array_unique($prompts));
        $types = array_column(QuizBeeMatch::questionBank('arduino-uno')['difficult']['regular'], 'type');
        $this->assertContains('fill_blank', $types);
        $this->assertContains('multiple_choice', $types);
    }

    public function test_builder_persists_full_bank_filters_courses_and_preserves_cpp_defaults(): void
    {
        [$teacher, $students, $payload] = $this->fixture();
        $response = $this->get(route('teacher.quiz-bee.create', ['course' => 'arduino-uno']))->assertOk()->assertSee('Grade 9 Arduino Uno Quiz Bee')->assertSee('Grade 9 · AM')->assertSee('name="course" value="arduino-uno"', false);
        if ($preview = getenv('ARDUINO_PREVIEW')) {
            file_put_contents($preview.'/arduino-bee.html', $response->getContent());
        }
        $this->post(route('teacher.quiz-bee.store'), $payload)->assertRedirect()->assertSessionHasNoErrors();
        $match = QuizBeeMatch::sole();
        $this->assertSame('arduino-uno', $match->course);
        $this->assertSame($payload['questions'], $match->questions);
        $this->get(route('teacher.quiz-bee.edit', [$match, 'course' => 'cpp']))->assertOk()->assertSee('name="course" value="arduino-uno"', false);
        $cpp = $payload;
        unset($cpp['course']);
        $cpp['title'] = 'Legacy C++ Bee';
        $this->post(route('teacher.quiz-bee.store'), $cpp)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('cpp', QuizBeeMatch::latest('id')->firstOrFail()->course);
        $this->get(route('teacher.quiz-bee.index', ['course' => 'arduino-uno']))->assertOk()->assertSee('Grade 9 Uno Bee')->assertDontSee('Legacy C++ Bee');
        $this->get(route('teacher.quiz-bee.create'))->assertOk()->assertSee('C++ Quiz Bee')->assertDontSee('Grade 9 · Uno R3 concepts');
        $this->postJson(route('teacher.quiz-bee.store'), [...$payload, 'course' => 'unsupported'])->assertUnprocessable();
        $this->withSession(['teacher_id' => null, 'student_id' => $students[0]->id])->getJson(route('student.quiz-bee.show', $match))->assertNotFound();
    }

    public function test_group_rewards_use_server_time_and_perfect_bonus_is_once_per_member(): void
    {
        [$teacher, $students, $payload] = $this->fixture();
        foreach ($payload['questions'] as &$bank) {
            $bank['regular'] = array_slice($bank['regular'], 0, 1);
        }
        unset($bank);
        $payload['questions']['difficult']['regular'] = [QuizBeeMatch::questionBank('arduino-uno')['difficult']['regular'][10]];
        $this->post(route('teacher.quiz-bee.store'), [...$payload, 'status' => 'lobby'])->assertRedirect()->assertSessionHasNoErrors();
        $match = QuizBeeMatch::sole();
        foreach ([0, 2] as $index) {
            $this->withSession(['teacher_id' => null, 'student_id' => $students[$index]->id])->postJson(route('student.quiz-bee.action', $match), ['action' => 'ready'])->assertOk();
        }
        $this->withSession(['teacher_id' => $teacher->id, 'student_id' => null])->postJson(route('teacher.quiz-bee.action', $match), ['action' => 'start'])->assertOk();
        foreach (QuizBeeMatch::LEVELS as $index => $level) {
            $run = DB::table('quiz_bee_runs')->find($match->fresh()->current_run_id);
            $this->assertSame($level, $run->level);
            $this->assertSame(60, (int) Carbon::parse($run->opened_at)->diffInSeconds(Carbon::parse($run->closes_at)));
            $question = json_decode($run->question, true);
            $this->travelTo(Carbon::parse($run->opened_at)->addMilliseconds($index === 1 ? 30001 : 30000));
            $this->withSession(['teacher_id' => null, 'student_id' => $students[0]->id])->getJson(route('student.quiz-bee.status', $match))->assertOk()->assertJsonPath('course', 'arduino-uno')->assertJsonPath('question.accepted_answers', null)->assertJsonPath('question.correct', null);
            $answer = $question['accepted_answers'][0] ?? $question['correct'];
            $this->postJson(route('student.quiz-bee.action', $match), ['action' => 'answer', 'run_id' => $run->id, 'answer' => $answer])->assertOk();
            $wrong = $question['type'] === 'fill_blank' ? 'wrong' : ($answer + 1) % 4;
            $this->withSession(['student_id' => $students[2]->id])->postJson(route('student.quiz-bee.action', $match), ['action' => 'answer', 'run_id' => $run->id, 'answer' => $wrong])->assertOk();
            $this->withSession(['student_id' => null, 'teacher_id' => $teacher->id])->postJson(route('teacher.quiz-bee.action', $match), ['action' => 'next', 'run_id' => $run->id])->assertOk();
        }
        $this->assertSame('completed', $match->fresh()->status);
        foreach ([0, 1] as $index) {
            $this->assertSame(240, $students[$index]->fresh()->xp);
            $this->assertSame(240, (int) DB::table('xp_events')->where('student_id', $students[$index]->id)->sum('amount'));
        }
        $this->assertSame(100, $students[2]->fresh()->xp);
        $this->assertDatabaseCount('learning_rewards', 8);
        $this->getJson(route('teacher.quiz-bee.status', $match))->assertOk()->assertJsonPath('standings.0.xp_per_member', 140);
        $this->getJson(route('teacher.quiz-bee.status', $match))->assertOk();
        $this->assertSame(240, $students[0]->fresh()->xp);
    }
}
