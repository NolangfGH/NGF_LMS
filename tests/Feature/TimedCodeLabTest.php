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

class TimedCodeLabTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $this->travelTo(now()->startOfSecond());
        $teacher = TeacherAccount::create(['first_name' => 'Lab', 'last_name' => 'Teacher', 'school' => 'School', 'email' => 'timedlab@example.test', 'password' => 'password123']);
        $room = Classroom::create(['teacher_account_id' => $teacher->id, 'name' => 'C++', 'grade' => 'Grade 8', 'section' => 'A', 'join_code' => 'TIMEDLAB']);
        $student = Student::create(['classroom_id' => $room->id, 'first_name' => 'Alex', 'nickname' => 'TimedCoder', 'student_id' => 'TIMEDLAB', 'password' => 'password123', 'xp' => 100]);
        $assignment = TeacherAssignment::create(['teacher_account_id' => $teacher->id, 'classroom_id' => $room->id, 'title' => 'Timed output assignment', 'school_term' => 'Term1', 'objective' => 'Print the expected output.', 'xp' => 150, 'status' => 'published', 'time_limit_minutes' => 2, 'sample_output' => "Target output\n42"]);
        $this->withSession(['teacher_id' => $teacher->id, 'student_id' => $student->id]);

        return [$student, $teacher, $assignment];
    }

    public function test_teacher_can_create_edit_and_remove_an_allotted_time_limit_with_validation(): void
    {
        [$student,,$assignment] = $this->fixture();
        $payload = ['title' => 'Configured timer', 'school_term' => 'Term2', 'classroom_ids' => [$student->classroom_id], 'difficulty' => 'Easy', 'xp' => 100, 'status' => 'published', 'time_limit_minutes' => 25];
        $this->get(route('teacher.assignments'))->assertOk()->assertSee('Allotted time limit (minutes)')->assertSee('name="time_limit_minutes"', false);
        foreach ([0, -1, 241, 1.5, 'invalid'] as $invalid) {
            $this->postJson(route('teacher.assignments.store'), [...$payload, 'time_limit_minutes' => $invalid])->assertUnprocessable()->assertJsonValidationErrors('time_limit_minutes');
        }
        $this->postJson(route('teacher.assignments.store'), $payload)->assertOk();
        $created = TeacherAssignment::latest('id')->firstOrFail();
        $this->assertSame(25, $created->time_limit_minutes);
        $this->get(route('teacher.assignments', ['edit' => $created->id]))->assertViewHas('assignmentEditor', fn ($editor) => $editor['time_limit_minutes'] === 25);
        $this->putJson(route('teacher.assignments.update', $created), [...$payload, 'time_limit_minutes' => 240])->assertOk();
        $this->assertSame(240, $created->fresh()->time_limit_minutes);
        $this->putJson(route('teacher.assignments.update', $created), [...$payload, 'time_limit_minutes' => ''])->assertOk();
        $this->assertNull($created->fresh()->time_limit_minutes);
        $this->assertSame(2, $assignment->fresh()->time_limit_minutes);
    }

    public function test_timer_starts_once_survives_refresh_and_ignores_client_supplied_timing(): void
    {
        [$student,,$assignment] = $this->fixture();
        $started = now()->getTimestampMs();
        $this->post(route('student.assignments.open', $assignment))->assertRedirect();
        $workspace = CodeWorkspace::firstOrFail();
        $this->assertSame($started, $workspace->timer_started_at->getTimestampMs());
        $this->assertSame(120, $workspace->time_limit_seconds);
        $this->travel(31)->seconds();
        $this->get(route('student.code-lab', ['assignment' => $assignment->id]))->assertOk()->assertSee('Assignment time remaining')->assertViewHas('labClient', fn ($client) => $client['timer']['expiresAt'] === $started + 120000 && $client['timer']['expired'] === false);
        $this->post(route('student.assignments.open', $assignment))->assertRedirect();
        $this->putJson(route('student.code-lab.save', $workspace), ['revision' => 1, 'code' => 'int main(){return 0;}', 'reflection' => 'I matched the sample output using output statements.', 'timer_started_at' => now()->addDay()->toIso8601String(), 'time_limit_seconds' => 99999])->assertOk();
        $this->getJson(route('student.code-lab.status', $workspace))->assertOk()->assertJsonPath('timer.expiresAt', $started + 120000)->assertJsonPath('timer.durationSeconds', 120);
        $this->assertSame($started, $workspace->fresh()->timer_started_at->getTimestampMs());
        $this->assertSame(250, $student->fresh()->xp);
    }

    public function test_submission_saves_exact_code_and_output_snapshot_with_elapsed_time_for_the_owning_teacher(): void
    {
        [$student,$teacher,$assignment] = $this->fixture();
        $this->get(route('student.code-lab', ['assignment' => $assignment->id]))->assertOk();
        $workspace = CodeWorkspace::firstOrFail();
        $this->travel(18)->seconds();
        $code = "  #include <iostream>\nint main() {\n    std::cout << \"Target output\\n42\";\n    return 0;\n}\n";
        $this->putJson(route('student.code-lab.save', $workspace), ['revision' => 1, 'code' => $code, 'prediction' => $assignment->sample_output, 'reflection' => 'I used cout to display the teacher sample on two lines.'])->assertOk()->assertJsonPath('revision', 2);
        $this->assertSame($code, $workspace->fresh()->code);
        $this->travel(2)->seconds();
        $this->postJson(route('student.code-lab.submit', $workspace), ['revision' => 2])->assertOk()->assertSessionHas('lab_submitted');
        $submission = LabSubmission::firstOrFail();
        $this->assertSame($code, $submission->code);
        $this->assertSame($assignment->sample_output, $submission->sample_output);
        $this->assertSame($assignment->objective, $submission->objective);
        $this->assertSame(20, $submission->time_spent_seconds);
        $this->assertSame(120, $submission->time_limit_seconds);
        $this->assertSame($teacher->id, $submission->teacher_account_id);
        $assignment->update(['sample_output' => 'Changed later']);
        $this->putJson(route('student.code-lab.save', $workspace), ['revision' => 2, 'code' => 'int main(){return 9;}'])->assertOk();
        $this->assertSame($code, $submission->fresh()->code);
        $this->get(route('teacher.code-lab'))->assertOk()->assertSee('Submitted main.cpp')->assertSee($code)->assertSee("Target output\n42")->assertSee('Time spent: 20 seconds')->assertSee('Allotted: 2 minutes')->assertDontSee('Changed later')
            ->assertSee('data-onecompiler-editor="review-code-'.$submission->id.'"', false)->assertSee('data-onecompiler-review="true"', false);
        $other = TeacherAccount::create(['first_name' => 'Other', 'last_name' => 'Teacher', 'school' => 'School', 'email' => 'timedother@example.test', 'password' => 'password123']);
        $this->withSession(['teacher_id' => $other->id])->get(route('teacher.code-lab'))->assertOk()->assertDontSee($code);
        $this->assertSame(100, $student->fresh()->xp);
    }

    public function test_expiry_blocks_saves_submissions_and_reopening_without_resetting_or_losing_saved_code(): void
    {
        [$student,,$assignment] = $this->fixture();
        $this->post(route('student.assignments.open', $assignment))->assertRedirect();
        $workspace = CodeWorkspace::firstOrFail();
        $started = $workspace->timer_started_at->getTimestampMs();
        $this->travel(119)->seconds();
        $this->putJson(route('student.code-lab.save', $workspace), ['revision' => 1, 'code' => 'int main(){return 1;}', 'reflection' => 'My solution returns a status after displaying the output.'])->assertOk();
        $this->travel(1)->seconds();
        $this->putJson(route('student.code-lab.save', $workspace), ['revision' => 2, 'code' => 'int main(){return 2;}'])->assertUnprocessable()->assertJsonValidationErrors('time');
        $this->postJson(route('student.code-lab.submit', $workspace), ['revision' => 2])->assertUnprocessable()->assertJsonValidationErrors('time');
        $this->postJson(route('student.assignments.open', $assignment))->assertUnprocessable()->assertJsonValidationErrors('time');
        $this->getJson(route('student.code-lab.status', $workspace))->assertJsonPath('timer.expired', true);
        $this->get(route('student.code-lab', ['assignment' => $assignment->id]))->assertOk()->assertSee('return 1;')->assertViewHas('labClient', fn ($client) => $client['timer']['expired'] === true);
        $this->get(route('student.assignments'))->assertOk()->assertSee('Code Lab closed');
        $this->assertSame('int main(){return 1;}', $workspace->fresh()->code);
        $this->assertSame($started, $workspace->fresh()->timer_started_at->getTimestampMs());
        $this->assertSame(250, $student->fresh()->xp);
        $this->assertDatabaseCount('lab_submissions', 0);
    }

    public function test_due_date_wins_over_allotted_time_and_updates_are_reflected_in_server_status(): void
    {
        [,, $assignment] = $this->fixture();
        $assignment->update(['due_at' => now()->addSeconds(30)]);
        $this->get(route('student.code-lab', ['assignment' => $assignment->id]));
        $workspace = CodeWorkspace::firstOrFail();
        $this->getJson(route('student.code-lab.status', $workspace))->assertJsonPath('timer.expiresAt', now()->addSeconds(30)->getTimestampMs());
        $this->travel(30)->seconds();
        $this->putJson(route('student.code-lab.save', $workspace), ['revision' => 1, 'code' => 'int main(){}'])->assertUnprocessable()->assertJsonValidationErrors('time');
        $this->getJson(route('student.code-lab.status', $workspace))->assertJsonPath('closed', true);
        $assignment->update(['due_at' => now()->addHour()]);
        $this->getJson(route('student.code-lab.status', $workspace))->assertJsonPath('closed', false)->assertJsonPath('timer.expired', false)->assertJsonPath('timer.expiresAt', $workspace->timer_started_at->addSeconds(120)->getTimestampMs());
    }

    public function test_each_student_gets_a_separate_timer_and_started_limits_cannot_be_reset_by_teacher_edits(): void
    {
        [$student,,$assignment] = $this->fixture();
        $this->get(route('student.code-lab', ['assignment' => $assignment->id]));
        $first = CodeWorkspace::firstOrFail();
        $this->travel(60)->seconds();
        $assignment->update(['time_limit_minutes' => 3]);
        $this->get(route('student.code-lab', ['assignment' => $assignment->id]));
        $this->assertSame(120, $first->fresh()->time_limit_seconds);
        $other = Student::create(['classroom_id' => $student->classroom_id, 'first_name' => 'Other', 'nickname' => 'AnotherCoder', 'student_id' => 'OTHERLAB', 'password' => 'password123']);
        $this->withSession(['student_id' => $other->id])->getJson(route('student.code-lab.status', $first))->assertForbidden();
        $this->get(route('student.code-lab', ['assignment' => $assignment->id]));
        $second = CodeWorkspace::where('student_id', $other->id)->firstOrFail();
        $this->assertSame(180, $second->time_limit_seconds);
        $this->assertSame(60,(int) $first->timer_started_at->diffInSeconds($second->timer_started_at));
        $assignment->update(['time_limit_minutes' => null]);
        $this->get(route('student.code-lab',['assignment' => $assignment->id]));
        $this->assertSame(180,$second->fresh()->time_limit_seconds);
    }
}
