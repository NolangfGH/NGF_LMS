<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Student;
use App\Models\TeacherAccount;
use App\Services\CourseAssessments;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AssessmentRewardsTest extends TestCase
{
    use RefreshDatabase;

    private function learner(int $xp = 100): Student
    {
        $teacher = TeacherAccount::create(['first_name' => 'Reward', 'last_name' => 'Teacher', 'school' => 'School', 'email' => fake()->unique()->safeEmail(), 'password' => 'Password123']);
        $room = Classroom::create(['teacher_account_id' => $teacher->id, 'name' => 'C++', 'grade' => 'Grade 8', 'section' => 'AM', 'join_code' => fake()->unique()->bothify('XP####')]);

        return Student::create(['classroom_id' => $room->id, 'first_name' => 'Learner', 'nickname' => 'RewardCadet', 'student_id' => fake()->unique()->bothify('XP######'), 'password' => 'Password123', 'xp' => $xp]);
    }

    private function answers(string $kind, int $correct): array
    {
        return array_map(static function (array $question, int $index) use ($correct): string|array {
            if ($index < $correct) {
                return $question['type'] === 'multiple_answer' ? $question['correct'] : $question['correct'][0];
            }
            if ($question['type'] === 'identification') {
                return 'incorrect response';
            }
            $wrong = array_values(array_diff($question['options'], $question['correct']))[0];

            return $question['type'] === 'multiple_answer' ? [$wrong] : $wrong;
        }, $questions = app(CourseAssessments::class)->questions($kind), array_keys($questions));
    }

    public function test_each_starting_assessment_awards_once_and_has_detailed_private_history(): void
    {
        $student = $this->learner();
        $this->withSession(['student_id' => $student->id]);
        foreach ([CourseAssessments::REASONING, CourseAssessments::PRE, CourseAssessments::ARDUINO_PRE] as $kind) {
            $submit = route('student.assessments.submit', $kind);
            $this->post($submit, ['answers' => $this->answers($kind, 37)])->assertRedirect()->assertSessionHasNoErrors();
            $this->post($submit, ['answers' => $this->answers($kind, 50)])->assertRedirect();
            $result = DB::table('course_assessment_results')->where('assessment', $kind)->first();
            $this->assertSame(37, $result->score);
            $this->assertDatabaseHas('xp_events', ['student_id' => $student->id, 'source_type' => 'course_assessment', 'source_id' => (string) $result->id, 'amount' => 185]);
            $this->assertDatabaseHas('xp_events', ['student_id' => $student->id, 'source_type' => 'course_assessment', 'source_id' => (string) $result->id, 'amount' => -39]);
            $this->get(route('student.assessments.show', $kind))->assertOk()->assertSee('185')->assertSee('39');
        }
        $this->assertSame(538, $student->fresh()->xp);
        $this->assertSame(6, DB::table('xp_events')->where('source_type', 'course_assessment')->count());
        $this->get(route('student.achievements'))->assertOk()->assertSee('Student Reasoning Challenge')->assertSee('37 correct × 5 XP = +185 XP.')->assertSee('13 incorrect × 3 XP = −39 XP.')->assertSee('Question numbers: 38, 39');
        $teacher = $student->classroom->teacher;
        $this->withSession(['student_id' => null, 'teacher_id' => $teacher->id])->get(route('teacher.achievements.xp', $student))->assertOk()->assertSee('Correct starting-assessment answers');
        $peer = $this->learner();
        $this->withSession(['student_id' => $peer->id, 'teacher_id' => null])->get(route('student.achievements'))->assertOk()->assertDontSee('37 correct × 5 XP');
        $this->withSession(['student_id' => null, 'teacher_id' => $peer->classroom->teacher_account_id])->get(route('teacher.achievements.xp', $student))->assertNotFound();
    }

    public function test_deductions_stop_at_zero_and_earned_points_are_applied_first(): void
    {
        $student = $this->learner(0);
        $this->withSession(['student_id' => $student->id])->post(route('student.assessments.submit', CourseAssessments::PRE), ['answers' => $this->answers(CourseAssessments::PRE, 20)])->assertRedirect();
        $this->assertSame(10, $student->fresh()->xp);
        $this->post(route('student.assessments.submit', CourseAssessments::ARDUINO_PRE), ['answers' => $this->answers(CourseAssessments::ARDUINO_PRE, 0)])->assertRedirect();
        $this->assertSame(0, $student->fresh()->xp);
        $this->assertDatabaseHas('xp_events', ['event_key' => 'starting-assessment:arduino-pre-test:wrong', 'amount' => -10, 'requested_amount' => -150, 'balance_after' => 0]);
        $this->get(route('student.achievements', ['xp_filter' => 'deducted']))->assertOk()->assertSee('150 XP requested; 10 XP applied');
    }

    public function test_invalid_or_unauthorized_submissions_do_not_change_xp_and_old_results_are_not_regraded(): void
    {
        $student = $this->learner();
        $submit = route('student.assessments.submit', CourseAssessments::PRE);
        $this->postJson($submit, ['answers' => $this->answers(CourseAssessments::PRE, 50)])->assertUnauthorized();
        $this->withSession(['student_id' => $student->id])->postJson($submit, ['answers' => ['forged option']])->assertUnprocessable();
        $this->assertDatabaseEmpty('course_assessment_results');
        $this->assertSame(100, $student->fresh()->xp);
        DB::table('course_assessment_results')->insert(['student_id' => $student->id, 'assessment' => CourseAssessments::PRE, 'score' => 25, 'total_questions' => 50, 'skill_scores' => '{}', 'completed_at' => now()]);
        $this->post($submit, ['answers' => $this->answers(CourseAssessments::PRE, 50)])->assertRedirect();
        $this->assertSame(100, $student->fresh()->xp);
        $this->assertSame(0, DB::table('xp_events')->where('source_type', 'course_assessment')->count());
        $this->assertDatabaseHas('course_assessment_results', ['score' => 25]);
    }
}
