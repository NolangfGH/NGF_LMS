<?php

namespace Tests\Feature;

use App\Models\ChallengeNotice;
use App\Models\Classroom;
use App\Models\CodingMatch;
use App\Models\CodingMatchSubmission;
use App\Models\Student;
use App\Models\TeacherAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CodingMatchesTest extends TestCase
{
    use RefreshDatabase;

    private TeacherAccount $owner;

    private TeacherAccount $other;

    private Student $alpha;

    private Student $beta;

    private Student $peer;

    private Student $foreign;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->startOfSecond());
        $this->owner = TeacherAccount::create(['first_name' => 'Owner', 'last_name' => 'Teacher', 'email' => 'matches@example.test', 'school' => 'School', 'password' => 'password123']);
        $this->other = TeacherAccount::create(['first_name' => 'Other', 'last_name' => 'Teacher', 'email' => 'other-matches@example.test', 'school' => 'School', 'password' => 'password123']);
        $a = Classroom::create(['teacher_account_id' => $this->owner->id, 'name' => 'C++', 'grade' => 'Grade 8', 'section' => 'A', 'join_code' => 'MATCHA']);
        $b = Classroom::create(['teacher_account_id' => $this->owner->id, 'name' => 'C++', 'grade' => 'Grade 8', 'section' => 'B', 'join_code' => 'MATCHB']);
        $c = Classroom::create(['teacher_account_id' => $this->other->id, 'name' => 'Private', 'grade' => 'Grade 8', 'section' => 'PRIVATE', 'join_code' => 'MATCHC']);
        foreach (['alpha' => [$a, 'ALPHA'], 'beta' => [$b, 'BETA'], 'peer' => [$a, 'PEER'], 'foreign' => [$c, 'FOREIGN']] as $property => [$room,$label]) {
            $this->$property = Student::create(['classroom_id' => $room->id, 'first_name' => $label, 'surname' => 'Coder', 'nickname' => $label, 'student_id' => 'MATCH-'.$label, 'password' => 'password123', 'xp' => 100]);
        }
    }

    private function asTeacher(?TeacherAccount $teacher = null): self
    {
        return $this->withSession(['teacher_id' => ($teacher ?? $this->owner)->id, 'teacher_session_version' => 1, 'student_id' => null]);
    }

    private function asStudent(?Student $student = null): self
    {
        return $this->withSession(['teacher_id' => null, 'student_id' => ($student ?? $this->alpha)->id, 'student_session_version' => 1, 'student_class_verified' => true]);
    }

    private function payload(array $changes = []): array
    {
        return array_replace(['title' => 'Output race', 'instructions' => 'Write a C++ loop that prints one and two on separate lines.',
            'sample_output' => "1\n2", 'time_limit_minutes' => 1, 'winner_xp' => 100, 'award_title' => 'Loop Champion',
            'students' => [$this->alpha->id, $this->beta->id]], $changes);
    }

    private function invite(array $changes = []): CodingMatch
    {
        $this->asTeacher()->post(route('teacher.matches.store'), $this->payload($changes))->assertRedirect();

        return CodingMatch::latest('id')->firstOrFail();
    }

    private function begin(CodingMatch $match): void
    {
        foreach ($match->participants as $p) {
            $this->asStudent($p->student)->postJson(route('student.matches.respond', $match), ['decision' => 'accepted'])->assertOk();
        }
        $this->travel(6)->seconds();
    }

    private function submitCode(CodingMatch $match, Student $student, int $revision = 0, string $code = "#include <iostream>\nint main(){std::cout << \"1\\n2\";}\n"): CodingMatchSubmission
    {
        $saved = $this->asStudent($student)->putJson(route('student.matches.save', $match), ['revision' => $revision, 'code' => $code])->assertOk()->json('revision');
        $id = $this->postJson(route('student.matches.submit', $match), ['revision' => $saved])->assertOk()->json('id');

        return CodingMatchSubmission::findOrFail($id);
    }

    private function reviewPayload(array $changes = []): array
    {
        return array_replace(['verdict' => 'correct', 'observed_output' => "1\n2", 'feedback' => 'Tested the submitted loop and output. Well done.', 'verified' => 1], $changes);
    }

    private function review(CodingMatch $match, CodingMatchSubmission $submission, array $changes = []): void
    {
        $this->asTeacher()->postJson(route('teacher.matches.review', [$match, $submission]), $this->reviewPayload($changes))->assertOk();
    }

    private function award(CodingMatch $match, CodingMatchSubmission $submission)
    {
        return $this->asTeacher()->postJson(route('teacher.matches.award', $match), ['submission_id' => $submission->id, 'note' => 'First correct submitted solution. Keep practicing!', 'confirmed' => 1]);
    }

    public function test_teacher_can_invite_two_coders_from_same_or_different_owned_sections_and_see_only_their_roster(): void
    {
        $this->get(route('teacher.challenges'))->assertRedirect(route('teacher.login'));
        $this->asTeacher()->get(route('teacher.challenges'))->assertOk()->assertSee('Challenge Studio')->assertSee('Create challenge')->assertSee('ALPHA')->assertSee('BETA')->assertDontSee('FOREIGN');
        $match = $this->invite();
        $this->assertCount(2, $match->participants);
        $this->assertSame(2, $match->participants->pluck('section_label')->unique()->count());
        $same = $this->invite(['students' => [$this->alpha->id, $this->peer->id]]);
        $this->assertSame(1, $same->participants->pluck('section_label')->unique()->count());
        $this->assertDatabaseCount('challenge_notices', 4);
        $this->asTeacher()->postJson(route('teacher.matches.store'), $this->payload(['students' => [$this->alpha->id, $this->alpha->id]]))->assertUnprocessable();
        $this->postJson(route('teacher.matches.store'), $this->payload(['students' => [$this->alpha->id, $this->foreign->id]]))->assertUnprocessable();
        $this->postJson(route('teacher.matches.store'), $this->payload(['students' => [$this->alpha->id, $this->beta->id, $this->peer->id]]))->assertUnprocessable();
        $this->postJson(route('teacher.matches.store'), $this->payload(['time_limit_minutes' => 0]))->assertUnprocessable();
        $this->assertDatabaseCount('coding_matches', 2);
        $this->asStudent()->get(route('student.challenges'))->assertOk()->assertSee('Output race')->assertSee('Invitation: your response is needed');
    }

    public function test_timer_starts_only_after_both_accept_and_preparation_blocks_early_writes(): void
    {
        $match = $this->invite();
        $this->asStudent()->postJson(route('student.matches.respond', $match), ['decision' => 'accepted'])->assertOk();
        $this->assertNull($match->fresh()->started_at);
        $this->putJson(route('student.matches.save', $match), ['revision' => 0, 'code' => 'early'])->assertUnprocessable();
        $this->asStudent($this->beta)->postJson(route('student.matches.respond', $match), ['decision' => 'accepted'])->assertOk();
        $match->refresh();
        $this->assertTrue($match->started_at->equalTo(now()->addSeconds(5)));
        $this->assertTrue($match->deadline_at->equalTo($match->started_at->copy()->addMinute()));
        $this->getJson(route('student.matches.status', $match))->assertJsonPath('phase', 'countdown');
        $this->putJson(route('student.matches.save', $match), ['revision' => 0, 'code' => 'early'])->assertUnprocessable();
        $this->postJson(route('student.matches.respond', $match), ['decision' => 'accepted'])->assertOk();
        $this->assertTrue($match->fresh()->started_at->equalTo($match->started_at));
        $this->travel(5)->seconds();
        $this->putJson(route('student.matches.save', $match), ['revision' => 0, 'code' => 'int main(){}'])->assertOk();
        $this->asTeacher()->getJson(route('teacher.match-notices'))->assertOk()->assertJsonPath('unread', 3);
        $this->assertDatabaseCount('challenge_notices', 7);
    }

    public function test_student_sees_only_their_code_and_teachers_monitor_is_scoped_to_ownership(): void
    {
        $match = $this->invite();
        $this->begin($match);
        $alphaCode = 'int main(){ /* PRIVATE ALPHA CODE */ }';
        $betaCode = 'int main(){ /* PRIVATE BETA CODE */ }';
        $this->submitCode($match, $this->alpha, 0, $alphaCode);
        $this->submitCode($match, $this->beta, 0, $betaCode);
        $this->beta->update(['leaderboard_visible' => false]);
        $state = $this->asStudent()->getJson(route('student.matches.status', $match))->assertOk()->assertJsonPath('participants.0.code', $alphaCode)->assertDontSee('PRIVATE BETA CODE', false)->json();
        $opponent = collect($state['participants'])->firstWhere('mine', false);
        $this->assertArrayNotHasKey('code', $opponent);
        $this->assertArrayNotHasKey('submissions', $opponent);
        $this->assertStringStartsWith('Coder-', $opponent['name']);
        $this->get(route('student.matches.show', $match))->assertOk()->assertSee('Your private workspace')->assertDontSee($betaCode, false);
        $this->asStudent($this->peer)->getJson(route('student.matches.status', $match))->assertNotFound();
        $this->postJson(route('student.matches.respond', $match), ['decision' => 'accepted'])->assertNotFound();
        $this->asTeacher($this->other)->get(route('teacher.matches.show', $match))->assertNotFound();
        $this->getJson(route('teacher.matches.status', $match))->assertNotFound();
        $this->asTeacher()->getJson(route('teacher.matches.status', $match))->assertOk()->assertJsonPath('participants.0.code', $alphaCode)->assertJsonPath('participants.1.code', $betaCode);
        $this->get(route('teacher.matches.show', $match))->assertOk()->assertSee('Live main.cpp')->assertSee('Final winner award')
            ->assertSee('data-onecompiler-editor="match-live-code-'.$match->participants->first()->id.'"', false);
    }

    public function test_final_submission_is_immutable_idempotent_and_locks_later_revisions(): void
    {
        $match = $this->invite();
        $this->begin($match);
        $code = "  #include <iostream>\nint main() {}\n";
        $submission = $this->submitCode($match, $this->alpha, 0, $code);
        $this->assertSame($code, $submission->code);
        $this->postJson(route('student.matches.submit', $match), ['revision' => 1])->assertOk()->assertJsonPath('id', $submission->id);
        $this->assertDatabaseCount('coding_match_submissions', 1);
        $this->putJson(route('student.matches.save', $match), ['revision' => 0, 'code' => 'stale overwrite'])->assertConflict();
        $this->putJson(route('student.matches.save', $match), ['revision' => 1, 'code' => 'int main(){return 1;}'])->assertConflict();
        $this->assertSame($code, $submission->fresh()->code);
        $this->postJson(route('student.matches.submit', $match), ['revision' => 2])->assertConflict();
        $this->assertDatabaseCount('coding_match_submissions', 1);
        $this->getJson(route('student.assessment-activity.status', ['kind' => 'coding_match', 'id' => $match->id]))->assertOk()->assertJsonPath('active', false);
        $this->assertTrue($submission->on_time);
        $this->assertSame(100, $this->alpha->fresh()->xp);
    }

    public function test_overtime_is_allowed_and_server_marks_exact_deadline_submissions_late(): void
    {
        $match = $this->invite();
        $this->begin($match);
        $this->travelTo($match->fresh()->deadline_at);
        $submission = $this->submitCode($match, $this->alpha);
        $this->assertFalse($submission->on_time);
        $this->getJson(route('student.matches.status', $match))->assertJsonPath('phase', 'overtime');
        $this->review($match, $submission);
        $this->award($match, $submission)->assertOk();
        $this->assertSame(200, $this->alpha->fresh()->xp);
        $this->asStudent()->putJson(route('student.matches.save', $match), ['revision' => 1, 'code' => 'after award'])->assertUnprocessable();
        $this->postJson(route('student.matches.submit', $match), ['revision' => 1])->assertUnprocessable();
    }

    public function test_only_teacher_can_verify_actual_output_and_download_the_correct_match_submission(): void
    {
        $match = $this->invite(['sample_output' => " 1\n2 "]);
        $this->begin($match);
        $submission = $this->submitCode($match, $this->alpha);
        $this->postJson(route('teacher.matches.review', [$match, $submission]), $this->reviewPayload())->assertForbidden();
        $this->asTeacher($this->other)->get(route('teacher.matches.download', [$match, $submission]))->assertNotFound();
        $this->asTeacher()->postJson(route('teacher.matches.review', [$match, $submission]), $this->reviewPayload(['verified' => 0]))->assertUnprocessable();
        $this->postJson(route('teacher.matches.review', [$match, $submission]), $this->reviewPayload())->assertUnprocessable()->assertJsonValidationErrors('observed_output');
        $this->postJson(route('teacher.matches.review', [$match, $submission]), $this->reviewPayload(['observed_output' => " 1\r\n2 \r\n"]))->assertOk();
        $this->assertSame('correct', $submission->fresh()->verdict);
        $this->get(route('teacher.matches.download', [$match, $submission]))->assertOk()->assertHeader('Content-Disposition', 'attachment; filename="submission-'.$submission->id.'.cpp"')->assertContent($submission->code);
        $otherMatch = $this->invite();
        $this->postJson(route('teacher.matches.review', [$otherMatch, $submission]), $this->reviewPayload())->assertNotFound();
        $this->get(route('teacher.matches.download', [$otherMatch, $submission]))->assertNotFound();
    }

    public function test_first_verified_correct_solution_wins_only_after_earlier_pending_versions_are_reviewed(): void
    {
        $match = $this->invite(['winner_xp' => 650]);
        $this->begin($match);
        $first = $this->submitCode($match, $this->alpha);
        $second = $this->submitCode($match, $this->beta);
        $this->review($match, $second);
        $this->award($match, $second)->assertUnprocessable()->assertJsonValidationErrors('award');
        $this->review($match, $first);
        $this->award($match, $second)->assertUnprocessable()->assertJsonValidationErrors('submission_id');
        $this->award($match, $first)->assertOk();
        $this->award($match, $first)->assertOk();
        $this->assertSame(750, $this->alpha->fresh()->xp);
        $this->assertSame(2, $this->alpha->fresh()->level);
        $this->assertSame(100, $this->beta->fresh()->xp);
        $this->assertSame($first->id, $match->fresh()->winning_submission_id);
        $this->assertSame(650, $match->fresh()->xp_awarded);
        $this->postJson(route('teacher.matches.review', [$match, $first]), $this->reviewPayload(['verdict' => 'revise']))->assertUnprocessable();
        $this->award($match, $second)->assertConflict();
        $this->postJson(route('teacher.matches.cancel', $match), ['reason' => 'Change winner later'])->assertUnprocessable();
        $this->asStudent()->get(route('student.leaderboard'))->assertOk()->assertSee('750');
        $this->getJson(route('student.matches.status', $match))->assertJsonPath('phase', 'awarded')->assertJsonPath('xp_awarded', 650)->assertJsonPath('winner', 'ALPHA');
    }

    public function test_revised_earlier_work_does_not_block_a_later_correct_submission(): void
    {
        $match = $this->invite();
        $this->begin($match);
        $first = $this->submitCode($match, $this->alpha);
        $second = $this->submitCode($match, $this->beta);
        $this->review($match, $first, ['verdict' => 'revise', 'verified' => 0, 'observed_output' => 'Incorrect output', 'feedback' => 'Check the loop boundary and try again.']);
        $this->review($match, $second);
        $this->award($match, $second)->assertOk();
        $this->assertSame(200, $this->beta->fresh()->xp);
    }

    public function test_decline_or_teacher_cancellation_keeps_history_and_blocks_coding(): void
    {
        $match = $this->invite();
        $this->asStudent()->postJson(route('student.matches.respond', $match), ['decision' => 'declined'])->assertOk();
        $this->assertSame('cancelled', $match->fresh()->status);
        $this->asStudent($this->beta)->postJson(route('student.matches.respond', $match), ['decision' => 'accepted'])->assertUnprocessable();
        $next = $this->invite();
        $this->begin($next);
        $submission = $this->submitCode($next, $this->alpha);
        $this->asStudent()->postJson(route('teacher.matches.cancel', $next), ['reason' => 'Invalid student action'])->assertForbidden();
        $this->asTeacher()->postJson(route('teacher.matches.cancel', $next), ['reason' => 'Reschedule with new learning criteria.'])->assertOk();
        $this->assertDatabaseHas('coding_match_submissions', ['id' => $submission->id]);
        $this->asStudent()->putJson(route('student.matches.save', $next), ['revision' => 1, 'code' => 'closed'])->assertUnprocessable();
        $this->assertSame(100, $this->alpha->fresh()->xp);
    }

    public function test_enrollment_changes_block_acceptance_saving_and_winner_awards(): void
    {
        $match = $this->invite();
        $this->begin($match);
        $submission = $this->submitCode($match, $this->alpha);
        $this->review($match, $submission);
        $this->beta->update(['classroom_id' => $this->foreign->classroom_id]);
        $this->award($match, $submission)->assertUnprocessable();
        $this->asStudent()->getJson(route('student.matches.status', $match))->assertJsonPath('roster_valid', false);
        $this->putJson(route('student.matches.save', $match), ['revision' => 1, 'code' => 'moved'])->assertUnprocessable();
        $this->assertSame(100, $this->alpha->fresh()->xp);
        $new = $this->invite(['students' => [$this->alpha->id, $this->peer->id]]);
        $this->peer->delete();
        $this->asStudent()->postJson(route('student.matches.respond', $new), ['decision' => 'accepted'])->assertUnprocessable();
    }

    public function test_refreshed_history_pagination_links_to_pages_rather_than_json_endpoints(): void
    {
        $match = $this->invite();
        $match->load('participants');
        for ($i = 0; $i < 12; $i++) {
            $copy = $match->replicate();
            $copy->save();
            foreach ($match->participants as $participant) {
                $copy->participants()->save($participant->replicate());
            }
        }
        $teacherHtml = $this->asTeacher()->getJson(route('teacher.matches.records'))->assertOk()->json('html');
        $this->assertStringContainsString(route('teacher.challenges').'?page=2', $teacherHtml);
        $this->assertStringNotContainsString('/teacher-challenges/records?page=2', $teacherHtml);
        $studentHtml = $this->asStudent()->getJson(route('student.matches.records'))->assertOk()->json('html');
        $this->assertStringContainsString(route('student.challenges').'?page=2', $studentHtml);
        $this->assertStringNotContainsString('/coding-challenges/records?page=2', $studentHtml);
        $this->get(route('student.challenges', ['page' => 2]))->assertOk()->assertSee('Output race');
    }

    public function test_notifications_are_persisted_scoped_read_safely_and_keep_unanswered_invitations_visible(): void
    {
        $match = $this->invite();
        $alphaNotice = ChallengeNotice::where('student_id', $this->alpha->id)->firstOrFail();
        $betaNotice = ChallengeNotice::where('student_id', $this->beta->id)->firstOrFail();
        $this->asStudent()->getJson(route('student.match-notices'))->assertJsonPath('unread', 1)->assertJsonPath('invitations', 1)->assertJsonPath('items.0.url', route('student.matches.show', $match));
        $this->postJson(route('student.match-notices.read'), ['through' => 99999])->assertOk();
        $this->assertNotNull($alphaNotice->fresh()->read_at);
        $this->assertNull($betaNotice->fresh()->read_at);
        $this->getJson(route('student.match-notices'))->assertJsonPath('unread', 0)->assertJsonPath('invitations', 1);
        $this->asStudent($this->foreign)->getJson(route('student.match-notices'))->assertJsonPath('items', [])->assertJsonPath('invitations', 0);
        $this->asTeacher($this->other)->getJson(route('teacher.match-notices'))->assertJsonPath('items', []);
        $this->asStudent()->postJson(route('student.matches.respond', $match), ['decision' => 'accepted'])->assertOk();
        $this->asTeacher()->getJson(route('teacher.match-notices'))->assertJsonPath('unread', 1)->assertSee('accepted');
        $this->getJson(route('teacher.matches.records'))->assertOk()->assertSee('Output race');
        $this->asStudent($this->foreign)->getJson(route('student.matches.records'))->assertOk()->assertDontSee('Output race');
    }
}
