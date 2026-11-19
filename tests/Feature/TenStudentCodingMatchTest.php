<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\CodingMatch;
use App\Models\CodingMatchSubmission;
use App\Models\Student;
use App\Models\TeacherAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenStudentCodingMatchTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $this->travelTo(now()->startOfSecond());
        $teacher = TeacherAccount::create(['first_name' => 'Class', 'last_name' => 'Host', 'school' => 'School', 'email' => 'ten-host@example.test', 'password' => 'Password123']);
        $room = Classroom::create(['teacher_account_id' => $teacher->id, 'name' => 'Class race', 'grade' => 'Grade 9', 'section' => 'AM', 'join_code' => 'TENRACE']);
        $students = collect(range(1, 10))->map(fn (int $index): Student => Student::create(['classroom_id' => $room->id, 'first_name' => 'Coder '.$index, 'nickname' => 'TenCoder'.$index, 'student_id' => 'TEN'.$index, 'password' => 'Password123', 'xp' => 100]));
        $payload = ['participant_count' => '10', 'course' => 'cpp', 'title' => 'Ten-coder race', 'instructions' => 'Print OK exactly and explain your program through readable code.', 'sample_output' => 'OK', 'time_limit_minutes' => 1, 'winner_xp' => 100, 'award_title' => 'Class Champion', 'students' => $students->pluck('id')->all()];
        $this->withSession(['teacher_id' => $teacher->id, 'student_id' => null]);

        return [$teacher, $students, $payload];
    }

    private function studentSession(Student $student): static
    {
        return $this->withSession(['teacher_id' => null, 'student_id' => $student->id, 'student_class_verified' => true]);
    }

    public function test_ten_students_start_together_submit_once_and_report_server_elapsed_seconds(): void
    {
        [$teacher, $students, $payload] = $this->fixture();
        $this->post(route('teacher.matches.store'), $payload)->assertRedirect()->assertSessionHasNoErrors();
        $match = CodingMatch::sole();
        $this->assertSame(10, $match->participant_count);
        $this->assertDatabaseCount('challenge_notices', 10);
        foreach ($students->take(9) as $student) {
            $this->studentSession($student)->postJson(route('student.matches.respond', $match), ['decision' => 'accepted'])->assertOk();
        }
        $this->assertSame('inviting', $match->fresh()->status);
        $this->assertNull($match->fresh()->started_at);
        $this->studentSession($students[9])->postJson(route('student.matches.respond', $match), ['decision' => 'accepted'])->assertOk();
        $match->refresh();
        $this->assertSame('active', $match->status);
        $this->travelTo($match->started_at->copy()->addMilliseconds(12345));
        $this->studentSession($students[0])->putJson(route('student.matches.save', $match), ['revision' => 0, 'code' => '#include <iostream>'.PHP_EOL.'int main(){std::cout<<"OK";}'])->assertOk();
        $id = $this->postJson(route('student.matches.submit', $match), ['revision' => 1, 'elapsed_seconds' => 0, 'submitted_at' => '2000-01-01'])->assertOk()->json('id');
        $this->postJson(route('student.matches.submit', $match), ['revision' => 1])->assertOk()->assertJsonPath('id', $id);
        $this->putJson(route('student.matches.save', $match), ['revision' => 1, 'code' => 'changed'])->assertConflict();
        $this->assertDatabaseCount('coding_match_submissions', 1);
        $this->withSession(['teacher_id' => $teacher->id, 'student_id' => null])->getJson(route('teacher.matches.status', $match))->assertOk()->assertJsonPath('participants.0.submissions.0.elapsed_seconds', 13)->assertJsonPath('participants.0.submissions.0.on_time', true);
        $monitor = $this->get(route('teacher.matches.show', $match))->assertOk()->assertSee('10-coder live monitor');
        if ($preview = getenv('CLASSROOM_UPGRADES_PREVIEW')) {
            file_put_contents($preview.'/ten-student-monitor.html', $monitor->getContent());
            file_put_contents($preview.'/match-status.json', json_encode($monitor->viewData('snapshot'), JSON_THROW_ON_ERROR));
            file_put_contents($preview.'/challenge-builder.html', $this->get(route('teacher.challenges'))->assertOk()->getContent());
        }
        $submission = CodingMatchSubmission::findOrFail($id);
        $this->postJson(route('teacher.matches.review', [$match, $submission]), ['verdict' => 'correct', 'feedback' => 'Tested the exact submitted code and confirmed OK.', 'verified' => 1, 'observed_output' => 'OK'])->assertOk();
        $this->postJson(route('teacher.matches.award', $match), ['submission_id' => $id, 'note' => 'Earliest correct solution, verified by the teacher.', 'confirmed' => 1])->assertOk();
        $this->assertSame(200, $students[0]->fresh()->xp);
        $this->assertSame(100, $students[1]->fresh()->xp);
        $this->assertSame('awarded', $match->fresh()->status);
    }

    public function test_category_limits_duplicate_students_and_foreign_rosters_are_rejected(): void
    {
        [$teacher, $students, $payload] = $this->fixture();
        $this->postJson(route('teacher.matches.store'), [...$payload, 'students' => $students->take(9)->pluck('id')->all()])->assertUnprocessable();
        $duplicate = $payload;
        $duplicate['students'][9] = $students[0]->id;
        $this->postJson(route('teacher.matches.store'), $duplicate)->assertUnprocessable();
        $this->postJson(route('teacher.matches.store'), [...$payload, 'participant_count' => 3])->assertUnprocessable();
        $students[9]->forceFill(['registration_status' => 'pending'])->save();
        $this->postJson(route('teacher.matches.store'), $payload)->assertUnprocessable();
        $this->assertDatabaseCount('coding_matches', 0);
    }

    public function test_arduino_matches_offer_wokwi_and_keep_cpp_compilation_separate(): void
    {
        [$teacher, $students, $payload] = $this->fixture();
        $this->post(route('teacher.matches.store'), [...$payload, 'course' => 'arduino-uno'])->assertRedirect()->assertSessionHasNoErrors();
        $match = CodingMatch::sole();
        $participant = $match->participants()->firstOrFail();
        $this->get(route('teacher.matches.show', $match))->assertOk()->assertSee('Live sketch.ino')->assertSee('data-wokwi-editor="match-live-code-'.$participant->id.'"', false)->assertDontSee('data-onecompiler-editor="match-live-code-', false);
        $this->studentSession($students[0])->get(route('student.matches.show', $match))->assertOk()->assertSee('sketch.ino')->assertSee('data-wokwi-editor="match-code"', false)->assertSee('maxlength="50000"', false)->assertDontSee('data-onecompiler-editor="match-code"', false);
    }
}
