<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\LessonProgress;
use App\Models\QuizBeeMatch;
use App\Models\Student;
use App\Models\TeacherAccount;
use App\Models\TeacherQuiz;
use App\Services\AssessmentStatistics;
use App\Services\CourseAssessments;
use App\Services\LearningAnalytics;
use App\Services\QuizGrader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LearningAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private function classroom(): Classroom
    {
        $teacher = TeacherAccount::create(['first_name' => 'Statistics', 'last_name' => 'Teacher', 'email' => fake()->unique()->safeEmail(), 'school' => 'School', 'password' => 'Password123']);

        return Classroom::create(['teacher_account_id' => $teacher->id, 'name' => 'Robotics', 'grade' => 'Grade 9', 'section' => 'AM', 'join_code' => fake()->unique()->bothify('STAT#####')]);
    }

    private function student(Classroom $room): Student
    {
        return Student::create(['classroom_id' => $room->id, 'first_name' => 'HiddenPersonalLearner', 'nickname' => fake()->unique()->bothify('Statistics#####'), 'student_id' => fake()->unique()->bothify('STATS######'), 'password' => 'Password123']);
    }

    public function test_historical_totals_are_scoped_and_new_item_snapshots_are_private(): void
    {
        $room = $this->classroom();
        $learner = $this->student($room);
        $other = $this->student($room);
        $foreign = $this->student($this->classroom());
        foreach ([[$learner, 30], [$other, 40], [$foreign, 0]] as [$student, $score]) {
            DB::table('course_assessment_results')->insert(['student_id' => $student->id, 'assessment' => CourseAssessments::REASONING, 'score' => $score, 'total_questions' => 50,
                'skill_scores' => json_encode(['Logic' => ['score' => $score, 'total' => 50]]), 'completed_at' => now()]);
        }
        $this->withSession(['teacher_id' => $room->teacher_account_id]);
        $this->get(route('teacher.analytics', ['kind' => 'reasoning']))->assertOk()->assertSee('70%')->assertSee('historical results')->assertDontSee('HiddenPersonalLearner')
            ->assertViewHas('insight', fn (array $data): bool => $data['metrics']['count'] === 2 && $data['metrics']['mean'] === 35.0 && $data['missing_items'] === 2);
        $course = app(CourseAssessments::class);
        $questions = $course->questions(CourseAssessments::ARDUINO_PRE);
        $answers = array_map(fn (array $question): string => $question['correct'][0], $questions);
        $course->submit($learner, CourseAssessments::ARDUINO_PRE, $questions, $answers, app(QuizGrader::class));
        $snapshot = DB::table('assessment_item_snapshots')->sole();
        $this->assertSame(50, $snapshot->score);
        $item = json_decode($snapshot->items, true)[0];
        $this->assertArrayNotHasKey('expected', $item);
        $this->assertArrayNotHasKey('answer', $item);
        $this->get(route('teacher.analytics', ['kind' => 'pre', 'course' => 'arduino-uno']))->assertOk()
            ->assertViewHas('insight', fn (array $data): bool => $data['metrics']['mps'] === 100.0 && count($data['items']) === 50);
        $this->get(route('teacher.analytics', ['kind' => 'pre', 'course' => 'cpp']))->assertOk()->assertViewHas('insight', fn (array $data): bool => $data['metrics']['count'] === 0);
        $this->get(route('teacher.analytics', ['kind' => 'reasoning', 'classroom' => $foreign->classroom_id]))->assertNotFound();
        $this->withSession(['teacher_id' => null, 'student_id' => $learner->id])->get(route('teacher.analytics', ['kind' => 'pre']))->assertRedirect(route('teacher.login'));
    }

    public function test_post_tests_use_first_or_latest_finished_attempts_without_in_progress_results(): void
    {
        $room = $this->classroom();
        $student = $this->student($room);
        foreach ([1 => 20, 2 => 45] as $number => $score) {
            DB::table('course_post_test_attempts')->insert(['student_id' => $student->id, 'course' => 'cpp', 'attempt_number' => $number, 'score' => $score, 'finished_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        }
        DB::table('course_post_test_attempts')->insert(['student_id' => $this->student($room)->id, 'course' => 'cpp', 'attempt_number' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $service = app(LearningAnalytics::class);
        $this->assertSame(40.0, $service->courseResults(collect([$room->id]), 'post', 'cpp', null)['metrics']['mps']);
        $latest = $service->courseResults(collect([$room->id]), 'post', 'cpp', null, 'latest');
        $this->assertSame(90.0, $latest['metrics']['mps']);
        $this->assertSame(1, $latest['metrics']['count']);
    }

    public function test_checkpoint_statistics_use_saved_correctness_and_exclude_unknown_legacy_counts(): void
    {
        $room = $this->classroom();
        $student = $this->student($room);
        $lesson = config('arduino_curriculum.lessons.arduino-blink');
        $progress = LessonProgress::create(['student_id' => $student->id, 'lesson_key' => 'arduino-blink', 'attempts' => 1, 'latest_score' => 1, 'started_at' => now(), 'last_opened_at' => now()]);
        $results = array_map(fn (array $question, int $index): array => ['correct' => $index === 0, 'answer' => $index === 0 ? $question['correct'] : ($question['correct'] + 1) % 3], $lesson['checks'], array_keys($lesson['checks']));
        app(AssessmentStatistics::class)->capture('checkpoint', $progress->id, $lesson['checks'], $results, $lesson['topic']);
        LessonProgress::create(['student_id' => $this->student($room)->id, 'lesson_key' => 'arduino-blink', 'attempts' => 1, 'latest_score' => 0, 'started_at' => now(), 'last_opened_at' => now()]);
        $this->withSession(['teacher_id' => $room->teacher_account_id])->get(route('teacher.analytics', ['kind' => 'checkpoint', 'course' => 'arduino-uno', 'lesson' => 'arduino-blink']))->assertOk()
            ->assertViewHas('insight', fn (array $data): bool => $data['metrics']['mps'] === 20.0 && $data['excluded'] === 1 && count($data['least_mastered']) === 4);
    }

    public function test_group_quiz_bee_counts_each_team_once_and_excludes_xp_bonuses(): void
    {
        $room = $this->classroom();
        $students = collect(range(1, 4))->map(fn (): Student => $this->student($room));
        $bank = QuizBeeMatch::questionBank('arduino-uno');
        $match = QuizBeeMatch::create(['teacher_account_id' => $room->teacher_account_id, 'course' => 'arduino-uno', 'title' => 'Robotics statistics match', 'category' => 'group', 'status' => 'completed', 'questions' => $bank]);
        $teamIds = [];
        foreach ($students->chunk(2)->values() as $index => $members) {
            $id = DB::table('quiz_bee_teams')->insertGetId(['quiz_bee_match_id' => $match->id, 'name' => 'Team '.$index, 'captain_id' => $members->first()->id]);
            $teamIds[] = $id;
            foreach ($members as $student) {
                DB::table('quiz_bee_members')->insert(['quiz_bee_match_id' => $match->id, 'quiz_bee_team_id' => $id, 'student_id' => $student->id, 'xp_awarded' => 500]);
            }
        }
        foreach (QuizBeeMatch::LEVELS as $index => $level) {
            $run = DB::table('quiz_bee_runs')->insertGetId(['quiz_bee_match_id' => $match->id, 'sequence' => $index + 1, 'level' => $level, 'kind' => 'regular', 'question_index' => 0, 'question' => json_encode($bank[$level]['regular'][0]), 'eligible_team_ids' => json_encode($teamIds), 'opened_at' => now(), 'closes_at' => now(), 'closed_at' => now()]);
            foreach ($teamIds as $position => $teamId) {
                DB::table('quiz_bee_answers')->insert(['quiz_bee_run_id' => $run, 'quiz_bee_team_id' => $teamId, 'correct' => $position === 0 && $index < 2, 'elapsed_ms' => 1000, 'xp' => 15, 'submitted_at' => now()]);
            }
        }
        $this->withSession(['teacher_id' => $room->teacher_account_id])->get(route('teacher.analytics', ['kind' => 'bee-group', 'course' => 'arduino-uno', 'bee' => $match->id]))->assertOk()
            ->assertViewHas('insight', fn (array $data): bool => $data['metrics']['count'] === 2 && $data['metrics']['mean'] === 1.0 && $data['metrics']['mps'] === 33.33 && $data['unit'] === 'Teams')->assertDontSee('HiddenPersonalLearner');
        $this->get(route('teacher.analytics', ['kind' => 'bee-individual', 'course' => 'arduino-uno', 'bee' => $match->id]))->assertNotFound();
    }

    public function test_term_and_summative_filters_find_the_corresponding_assessments(): void
    {
        $room = $this->classroom();
        foreach (['Term 1 · Summative Exam 1', 'Term 1 · Summative Exam 2', 'Term 2 · Term Exam'] as $title) {
            TeacherQuiz::create(['teacher_account_id' => $room->teacher_account_id, 'classroom_id' => $room->id, 'title' => $title, 'status' => 'published', 'questions' => []]);
        }
        $this->withSession(['teacher_id' => $room->teacher_account_id])->get(route('teacher.analytics', ['kind' => 'summative-1', 'term' => 'Term1']))->assertOk()->assertSee('Term 1 · Summative Exam 1')->assertDontSee('Term 1 · Summative Exam 2');
        $this->get(route('teacher.analytics', ['kind' => 'term-exam', 'term' => 'Term2']))->assertOk()->assertSee('Term 2 · Term Exam')->assertDontSee('Term 1 · Summative Exam 1');
        $this->get(route('teacher.analytics', ['kind' => 'summative-2', 'term' => 'Term1']))->assertOk()->assertSee('Term 1 · Summative Exam 2');
    }

    public function test_mixed_versions_keep_item_denominators_and_allow_version_selection(): void
    {
        $a = hash('sha256', 'version-a');
        $b = hash('sha256', 'version-b');
        $records = [['score' => 1, 'total' => 2, 'items' => [], 'version' => $a, 'skills' => []], ['score' => 4, 'total' => 4, 'items' => [], 'version' => $b, 'skills' => []]];
        $service = app(LearningAnalytics::class);
        $this->assertSame(75.0, $service->summarize($records)['metrics']['mps']);
        $selected = $service->summarize($records, $b);
        $this->assertSame(1, $selected['metrics']['count']);
        $this->assertSame(100.0, $selected['metrics']['mps']);
    }

    public function test_new_pretest_cohorts_show_mode_and_least_mastered_questions_together(): void
    {
        $room = $this->classroom();
        $course = app(CourseAssessments::class);
        $questions = $course->questions(CourseAssessments::ARDUINO_PRE);
        foreach ([5, 5, 10] as $wrong) {
            $answers = array_map(fn (array $question): string => $question['correct'][0], $questions);
            for ($index = 0; $index < $wrong; $index++) {
                $answers[$index] = collect($questions[$index]['options'])->first(fn (string $option): bool => $option !== $questions[$index]['correct'][0]);
            }
            $course->submit($this->student($room), CourseAssessments::ARDUINO_PRE, $questions, $answers, app(QuizGrader::class));
        }
        $response = $this->withSession(['teacher_id' => $room->teacher_account_id])->get(route('teacher.analytics', ['kind' => 'pre', 'course' => 'arduino-uno']))->assertOk()
            ->assertViewHas('insight', fn (array $data): bool => $data['metrics']['mps'] === 86.67 && $data['metrics']['mean'] === 43.33 && $data['metrics']['median'] === 45.0 && $data['metrics']['modes'] === [45] && count($data['least_mastered']) === 5 && $data['least_mastered'][0]['percent_correct'] === 0.0);
        if ($preview = getenv('CLASSROOM_UPGRADES_PREVIEW')) {
            file_put_contents($preview.'/statistics.html', $response->getContent());
        }
    }
}
