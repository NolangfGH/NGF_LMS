<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\Classroom;
use App\Models\CodeWorkspace;
use App\Models\CodingMatch;
use App\Models\QuizAttempt;
use App\Models\QuizBeeMatch;
use App\Models\RecitationRound;
use App\Models\Student;
use App\Models\TeacherAccount;
use App\Models\TeacherAssignment;
use App\Models\TeacherQuiz;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class AssessmentActivityTest extends TestCase
{
    use RefreshDatabase;

    private function classroom(): Classroom
    {
        $teacher = TeacherAccount::create(['first_name' => 'Class', 'last_name' => 'Teacher', 'school' => 'Test School', 'email' => fake()->unique()->safeEmail(), 'password' => 'ClassPassword123']);

        return Classroom::create(['teacher_account_id' => $teacher->id, 'name' => 'C++', 'grade' => 'Grade 8', 'section' => 'AM', 'join_code' => fake()->unique()->bothify('FOC####')]);
    }

    private function learner(Classroom $classroom, string $nickname = 'FocusCadet'): Student
    {
        return Student::create(['classroom_id' => $classroom->id, 'first_name' => 'Learner', 'nickname' => $nickname, 'student_id' => fake()->unique()->bothify('FOC#######'), 'password' => 'ClassPassword123', 'xp' => 100]);
    }

    private function signal(string $kind, int $id): array
    {
        return ['kind' => $kind, 'id' => $id, 'signal' => 'page_visible', 'request_key' => (string) Str::uuid(), 'observed_at' => now()->toISOString(), 'away_seconds' => 4];
    }

    public function test_active_assessment_types_record_browser_activity_without_changing_attempts_or_xp(): void
    {
        $classroom = $this->classroom();
        $student = $this->learner($classroom);
        $peer = $this->learner($classroom, 'Peer');
        $teacherId = $classroom->teacher_account_id;
        $quiz = TeacherQuiz::create(['classroom_id' => $classroom->id, 'teacher_account_id' => $teacherId, 'title' => 'Term exam', 'questions' => [['correct' => 'Private key']], 'status' => 'published']);
        $quizAttempt = QuizAttempt::create(['student_id' => $student->id, 'teacher_quiz_id' => $quiz->id, 'questions' => $quiz->questions, 'answers' => [], 'started_at' => now(), 'expires_at' => now()->addMinutes(10)]);
        $postTest = DB::table('course_post_test_attempts')->insertGetId(['student_id' => $student->id, 'course' => 'arduino-uno', 'attempt_number' => 1, 'started_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        $round = RecitationRound::factory()->forClassroom($classroom)->create();
        $bee = QuizBeeMatch::create(['teacher_account_id' => $teacherId, 'title' => 'Quiz Bee', 'category' => 'group', 'status' => 'running', 'questions' => []]);
        $team = DB::table('quiz_bee_teams')->insertGetId(['quiz_bee_match_id' => $bee->id, 'name' => 'Team A', 'captain_id' => $peer->id]);
        DB::table('quiz_bee_members')->insert(['quiz_bee_match_id' => $bee->id, 'quiz_bee_team_id' => $team, 'student_id' => $student->id]);
        $match = CodingMatch::create(['teacher_account_id' => $teacherId, 'title' => 'Coding match', 'instructions' => 'Print a greeting.', 'sample_output' => 'Hello', 'time_limit_minutes' => 5, 'status' => 'active', 'started_at' => now()->subSecond(), 'deadline_at' => now()->addMinutes(5)]);
        foreach ([$student, $peer] as $slot => $participant) {
            $match->participants()->create(['student_id' => $participant->id, 'slot' => $slot + 1, 'student_name' => $participant->nickname, 'section_label' => 'Grade 8 AM', 'response' => 'accepted', 'draft_code' => 'int main() {}']);
        }
        $challenge = TeacherAssignment::create(['teacher_account_id' => $teacherId, 'classroom_id' => $classroom->id, 'kind' => 'challenge', 'title' => 'Coding lab', 'status' => 'published']);
        $workspace = CodeWorkspace::create(['student_id' => $student->id, 'teacher_assignment_id' => $challenge->id, 'activity_key' => 'challenge:'.$challenge->id, 'title' => $challenge->title, 'code' => 'int main() {}']);

        $this->withSession(['student_id' => $student->id]);
        foreach (['quiz' => $quizAttempt->id, 'post_test' => $postTest, 'recitation' => $round->id, 'quiz_bee' => $bee->id, 'coding_match' => $match->id, 'challenge_lab' => $workspace->id] as $kind => $id) {
            $this->getJson(route('student.assessment-activity.status', compact('kind', 'id')))->assertOk()->assertJsonPath('active', true)->assertJsonCount(2);
            $this->postJson(route('student.assessment-activity.store'), $this->signal($kind, $id) + ['answer' => 'Do not log this'])->assertOk()->assertExactJson(['saved' => true]);
        }

        $events = AuditEvent::where('action', 'assessment.page_visible')->get();
        $this->assertCount(6, $events);
        $this->assertStringNotContainsString('Do not log this', $events->toJson());
        $this->assertStringNotContainsString('Private key', $events->toJson());
        $this->assertSame(100, $student->fresh()->xp);
        $this->assertSame([], $quizAttempt->fresh()->answers);
        $this->assertNull($quizAttempt->fresh()->submitted_at);
        $this->assertNull($workspace->fresh()->timer_started_at);
    }

    public function test_signals_require_an_active_owned_activity_and_duplicate_delivery_is_idempotent(): void
    {
        $classroom = $this->classroom();
        $student = $this->learner($classroom);
        $round = RecitationRound::factory()->forClassroom($classroom)->create();
        $foreign = RecitationRound::factory()->forClassroom($this->classroom())->create();
        $data = $this->signal('recitation', $round->id);
        $this->postJson(route('student.assessment-activity.store'), $data)->assertUnauthorized();
        $this->withSession(['student_id' => $student->id]);
        $this->getJson(route('student.assessment-activity.status', ['kind' => 'recitation', 'id' => $foreign->id]))->assertNotFound();
        $this->postJson(route('student.assessment-activity.store'), $data)->assertOk();
        $this->postJson(route('student.assessment-activity.store'), $data)->assertOk();
        $this->postJson(route('student.assessment-activity.store'), array_replace($data, ['signal' => 'page_hidden']))->assertConflict();
        $this->assertSame(1, AuditEvent::where('action', 'assessment.page_visible')->count());
        $round->update(['status' => 'closed']);
        $this->getJson(route('student.assessment-activity.status', ['kind' => 'recitation', 'id' => $round->id]))->assertOk()->assertJsonPath('active', false);
        $this->postJson(route('student.assessment-activity.store'), $this->signal('recitation', $round->id))->assertConflict();
        $this->assertSame(100, $student->fresh()->xp);
    }

    public function test_teacher_report_only_shows_own_students_and_omits_shared_locations(): void
    {
        $classroom = $this->classroom();
        $student = $this->learner($classroom);
        $foreignClassroom = $this->classroom();
        $foreignStudent = $this->learner($foreignClassroom, 'PrivateCadet');
        $this->postJson(route('student.login.store'), ['student_id' => $student->student_id, 'password' => 'ClassPassword123', 'audit_location_opt_in' => 1, 'audit_latitude' => 12.3, 'audit_longitude' => 122.0, 'audit_accuracy_km' => 10])->assertOk();
        $round = RecitationRound::factory()->forClassroom($classroom)->create(['title' => 'Own activity']);
        $foreignRound = RecitationRound::factory()->forClassroom($foreignClassroom)->create(['title' => 'Private activity']);
        $this->postJson(route('student.assessment-activity.store'), $this->signal('recitation', $round->id))->assertOk();
        $this->get(route('teacher.assessment-activity'))->assertForbidden();
        $this->withSession(['student_id' => $foreignStudent->id])->postJson(route('student.assessment-activity.store'), $this->signal('recitation', $foreignRound->id))->assertOk();
        $this->withSession(['teacher_id' => $classroom->teacher_account_id])->get(route('teacher.assessment-activity'))
            ->assertOk()->assertSee('Own activity')->assertSee('FocusCadet')->assertDontSee('Private activity')->assertDontSee('12.3')->assertDontSee('Optional approximate location');
        $csv = $this->get(route('teacher.assessment-activity.export'))->assertOk()->streamedContent();
        $this->assertStringContainsString('Own activity', $csv);
        $this->assertStringNotContainsString('Private activity', $csv);
        $this->assertStringNotContainsString('12.3', $csv);
        $student->update(['classroom_id' => $foreignClassroom->id]);
        $this->withSession(['teacher_id' => $foreignClassroom->teacher_account_id])->get(route('teacher.assessment-activity'))->assertOk()->assertDontSee('Own activity');
        $foreignClassroom->teacher->forceFill(['role' => 'principal'])->save();
        $this->get(route('teacher.assessment-activity'))->assertForbidden();
    }

    public function test_students_sharing_a_school_network_have_separate_rate_limits(): void
    {
        $classroom = $this->classroom();
        $first = $this->learner($classroom);
        $second = $this->learner($classroom, 'Peer');
        $round = RecitationRound::factory()->forClassroom($classroom)->create();
        $url = route('student.assessment-activity.status', ['kind' => 'recitation', 'id' => $round->id]);
        $this->withSession(['student_id' => $first->id]);
        for ($request = 0; $request < 60; $request++) {
            $this->getJson($url)->assertOk();
        }
        $this->getJson($url)->assertTooManyRequests();
        $this->withSession(['student_id' => $second->id])->getJson($url)->assertOk();
    }
}
