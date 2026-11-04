<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\CodeWorkspace;
use App\Models\LabSubmission;
use App\Models\Student;
use App\Models\TeacherAccount;
use App\Models\TeacherAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChallengesTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $teacher = TeacherAccount::create(['first_name' => 'Owner', 'last_name' => 'Teacher', 'school' => 'School', 'email' => 'challenges@example.test', 'password' => 'password123']);
        $room = Classroom::create(['teacher_account_id' => $teacher->id, 'name' => 'C++', 'grade' => 'Grade 8', 'section' => 'A', 'join_code' => 'CHALLA']);
        $other = Classroom::create(['teacher_account_id' => $teacher->id, 'name' => 'C++', 'grade' => 'Grade 8', 'section' => 'B', 'join_code' => 'CHALLB']);
        $student = Student::create(['classroom_id' => $room->id, 'first_name' => 'Alex', 'nickname' => 'MyCoder', 'student_id' => 'CHALL1', 'password' => 'password123', 'xp' => 100]);
        $peer = Student::create(['classroom_id' => $other->id, 'first_name' => 'Peer', 'nickname' => 'PeerCoder', 'student_id' => 'CHALL2', 'password' => 'password123']);
        $this->withSession(['teacher_id' => $teacher->id, 'student_id' => $student->id]);
        $payload = ['title' => 'Teacher loop challenge', 'kind' => 'challenge', 'classroom_ids' => [$room->id], 'school_term' => 'Term2', 'objective' => 'Print the values from one to three.', 'sample_output' => "1\n2\n3", 'xp' => 75, 'difficulty' => 'Mid', 'status' => 'published', 'time_limit_minutes' => 1];

        return compact('teacher', 'room', 'other', 'student', 'peer', 'payload');
    }

    public function test_guided_challenges_are_real_saved_code_lab_activities_without_opening_xp(): void
    {
        extract($this->fixture());
        $this->get(route('student.challenges'))->assertOk()->assertSee('Swap with aliases')->assertSee('Record, array and reference helpers')->assertViewHas('challengeCards', fn ($cards) => count($cards) === 25);
        $this->get(route('student.code-lab', ['activity' => 'greeting']))->assertOk()->assertSee('Challenge Sample Output')->assertSee('Hello, CodeQuest!');
        $workspace = CodeWorkspace::firstOrFail();
        $this->putJson(route('student.code-lab.save', $workspace), ['revision' => 1, 'code' => 'int main(){return 0;}', 'reflection' => 'I used two output statements and checked the lines.'])->assertOk();
        $this->get(route('student.challenges'))->assertSee('Draft saved');
        $this->postJson(route('student.code-lab.submit', $workspace), ['revision' => 2])->assertOk();
        $this->get(route('student.challenges'))->assertSee('Awaiting teacher review');
        $submission = LabSubmission::firstOrFail();
        $this->assertSame("Hello, CodeQuest!\nI am learning C++.", $submission->sample_output);
        $this->post(route('teacher.code-lab.review', $submission), ['rubric' => ['correctness' => 3, 'logic' => 3, 'readability' => 3, 'explanation' => 3], 'feedback' => 'Your solution meets the goal and explains the output.'])->assertRedirect();
        $this->get(route('student.challenges'))->assertSee('Teacher-confirmed solution')->assertSee('Your solution meets the goal');
        $this->assertSame(100, $student->fresh()->xp);
        $this->get(route('student.achievements'))->assertOk()->assertSee('First C++ Mission')->assertViewHas('achievementReport', fn ($report) => $report['earned'] >= 3);
        $this->get(route('student.code-lab', ['activity' => 'unknown-challenge']))->assertNotFound();
    }

    public function test_teacher_can_publish_edit_and_delete_challenges_for_the_correct_sections_only(): void
    {
        extract($this->fixture());
        $this->get(route('teacher.challenges'))->assertOk()->assertSee('Challenge Studio')->assertSee('Create challenge');
        $this->postJson(route('teacher.assignments.store'), $payload)->assertOk();
        $challenge = TeacherAssignment::firstOrFail();
        $this->assertSame('challenge', $challenge->kind);
        $this->get(route('student.challenges'))->assertSee('Teacher loop challenge');
        $this->get(route('student.assignments'))->assertDontSee('Teacher loop challenge');
        $this->get(route('teacher.assignments'))->assertDontSee('Teacher loop challenge');
        $this->get(route('teacher.challenges', ['edit' => $challenge->id]))->assertOk()->assertSee('Edit challenge');
        $this->get(route('teacher.assignments', ['edit' => $challenge->id]))->assertNotFound();
        $this->putJson(route('teacher.assignments.update', $challenge), [...$payload, 'title' => 'Updated loop challenge'])->assertOk();
        $this->putJson(route('teacher.assignments.update', $challenge), [...$payload, 'kind' => 'assignment'])->assertUnprocessable();
        $this->withSession(['student_id' => $peer->id])->get(route('student.challenges'))->assertDontSee('Updated loop challenge');
        $this->get(route('student.code-lab', ['assignment' => $challenge->id]))->assertNotFound();
        $this->deleteJson(route('teacher.assignments.destroy', $challenge))->assertOk();
        $this->assertSoftDeleted($challenge);
        $this->withSession(['student_id' => $student->id])->get(route('student.challenges'))->assertDontSee('Updated loop challenge');
    }

    public function test_teacher_challenges_need_clear_objective_and_sample_output_before_publication(): void
    {
        extract($this->fixture());
        $this->postJson(route('teacher.assignments.store'), [...$payload, 'objective' => '', 'sample_output' => ''])->assertUnprocessable()->assertJsonValidationErrors(['objective', 'sample_output']);
        $this->postJson(route('teacher.assignments.store'), [...$payload, 'status' => 'draft', 'objective' => '', 'sample_output' => ''])->assertOk();
        $this->get(route('student.challenges'))->assertDontSee('Teacher loop challenge');
    }

    public function test_teacher_challenge_opening_reward_deadline_and_final_review_filter_use_real_records(): void
    {
        extract($this->fixture());
        $this->postJson(route('teacher.assignments.store'), $payload)->assertOk();
        $challenge = TeacherAssignment::firstOrFail();
        $this->post(route('student.assignments.open', $challenge))->assertRedirect();
        $workspace = CodeWorkspace::firstOrFail();
        $this->post(route('student.assignments.open', $challenge))->assertRedirect();
        $this->assertSame(175, $student->fresh()->xp);
        $this->putJson(route('student.code-lab.save', $workspace), ['revision' => 1, 'code' => 'int main(){return 0;}', 'reflection' => 'I used a loop to display the three values.'])->assertOk();
        $this->postJson(route('student.code-lab.submit', $workspace), ['revision' => 2])->assertOk();
        $this->get(route('teacher.code-lab', ['activity' => 'challenge']))->assertOk()->assertSee('Teacher loop challenge');
        $this->get(route('teacher.code-lab', ['activity' => 'practice']))->assertOk()->assertDontSee('Teacher loop challenge');
        $this->travel(61)->seconds();
        $this->get(route('student.challenges'))->assertOk()->assertSee('Challenge closed')->assertSee('Request one final opportunity');
        $this->postJson(route('student.assignments.open', $challenge))->assertUnprocessable();
        $this->assertSame(175, $student->fresh()->xp);
    }
}
