<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\GradingSheet;
use App\Models\RecitationAttempt;
use App\Models\RecitationAward;
use App\Models\RecitationRound;
use App\Models\Student;
use App\Models\TeacherAccount;
use App\Services\GradingSheetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class RecitationTest extends TestCase
{
    use RefreshDatabase;

    private function room(): array
    {
        $teacher = TeacherAccount::create(['first_name' => 'Recitation', 'last_name' => 'Teacher', 'email' => fake()->unique()->safeEmail(), 'school' => 'School', 'password' => 'password123']);
        $room = Classroom::create(['teacher_account_id' => $teacher->id, 'name' => 'C++', 'grade' => 'Grade 8', 'section' => 'AM', 'join_code' => Str::upper(Str::random(8))]);
        $students = collect(range(1, 6))->map(fn ($i) => Student::create(['classroom_id' => $room->id, 'first_name' => 'Student '.$i, 'nickname' => 'Reciter'.$i, 'student_id' => Str::uuid()->toString(), 'password' => 'password123', 'xp' => 100]));
        $round = RecitationRound::factory()->forClassroom($room)->create();

        return [$teacher, $room, $students, $round];
    }

    private function asStudent(Student $student): self
    {
        return $this->withSession(['teacher_id' => null, 'student_id' => $student->id, 'student_session_version' => 1, 'student_class_verified' => true]);
    }

    public function test_exactly_five_distinct_correct_students_receive_the_specified_scores_and_xp_once(): void
    {
        [$teacher, $room, $students, $round] = $this->room();
        $this->asStudent($students[0])->postJson(route('student.recitations.submit', $round), ['answer' => 'cin', 'request_key' => Str::uuid()->toString()])->assertOk()->assertJsonPath('correct', false)->assertJsonPath('award', null);
        foreach ([1 => 10, 2 => 5, 3 => 3, 4 => 0, 5 => 0] as $position => $xp) {
            $student = $students[$position];
            $key = Str::uuid()->toString();
            $this->asStudent($student)->postJson(route('student.recitations.submit', $round), ['answer' => 'cout', 'request_key' => $key])->assertOk()->assertJsonPath('award.position', $position)->assertJsonPath('award.score', 10)->assertJsonPath('award.xp', $xp);
            $this->postJson(route('student.recitations.submit', $round), ['answer' => 'cout', 'request_key' => $key])->assertOk()->assertJsonPath('award.position', $position);
            $this->assertSame(100 + $xp, $student->fresh()->xp);
        }
        $this->assertDatabaseCount('recitation_awards', 5);
        $this->assertDatabaseCount('learning_rewards', 3);
        $this->assertSame('completed', $round->fresh()->status);
        $this->asStudent($students[0])->postJson(route('student.recitations.submit', $round), ['answer' => 'cout', 'request_key' => Str::uuid()->toString()])->assertConflict();
        $this->assertSame(95, $students[0]->fresh()->xp);
        $this->assertSame(50, (int) RecitationAward::sum('score'));
        $this->assertSame(6, RecitationAttempt::count());
        $this->withSession(['student_id' => null, 'teacher_id' => $teacher->id])->getJson(route('teacher.recitations.status', $round))->assertOk()->assertJsonCount(5, 'winners')->assertJsonPath('winners.0.answer', 'cout');
        if ($preview = getenv('CLASSROOM_PREVIEW')) {
            file_put_contents($preview.'/recitation-winners.html', $this->get(route('teacher.recitations.show', $round))->assertOk()->getContent());
        }
        $sheet = GradingSheet::create(['teacher_account_id' => $teacher->id, 'classroom_id' => $room->id, 'school_year' => '2026-2027', 'term' => 'Term1', 'weights' => config('grading.weights')]);
        $service = app(GradingSheetService::class);
        $this->assertSame(1, $service->linkSources($sheet, ['recitation:'.$round->id]));
        $assessment = $sheet->assessments()->first();
        $data = $service->build($sheet, $teacher->id, $room->id);
        $this->assertNull($data['rows']->firstWhere('id', $students[0]->id)['cells'][$assessment->id]['score']);
        $this->assertSame(10.0, $data['rows']->firstWhere('id', $students[1]->id)['cells'][$assessment->id]['score']);
    }

    public function test_drafts_are_live_to_the_owner_teacher_and_private_from_other_students_and_staff(): void
    {
        [$teacher, , $students, $round] = $this->room();
        $round->update(['accepted_answers' => ['PRIVATE-ANSWER-KEY']]);
        $this->asStudent($students[0])->putJson(route('student.recitations.save', $round), ['answer' => '<script>private draft</script>', 'revision' => 0])->assertOk()->assertJsonPath('revision', 1);
        $this->putJson(route('student.recitations.save', $round), ['answer' => 'stale draft', 'revision' => 0])->assertConflict();
        $this->asStudent($students[1])->get(route('student.recitations.show', $round))->assertOk()->assertDontSee('private draft')->assertDontSee('PRIVATE-ANSWER-KEY');
        $this->getJson(route('student.recitations.status', $round))->assertOk()->assertDontSee('private draft')->assertDontSee('PRIVATE-ANSWER-KEY');
        $this->withSession(['student_id' => null, 'teacher_id' => $teacher->id])->getJson(route('teacher.recitations.status', $round))->assertOk()->assertJsonPath('drafts.0.answer', '<script>private draft</script>');
        $revision = $this->getJson(route('teacher.recitations.status', $round))->json('revision');
        $this->getJson(route('teacher.recitations.status', ['round' => $round, 'revision' => $revision]))->assertJsonPath('unchanged', true);
        [$other, , $foreign] = $this->room();
        $this->withSession(['teacher_id' => $other->id])->get(route('teacher.recitations.show', $round))->assertForbidden();
        $this->asStudent($foreign[0])->postJson(route('student.recitations.submit', $round), ['answer' => 'PRIVATE-ANSWER-KEY', 'request_key' => Str::uuid()->toString()])->assertForbidden();
    }

    public function test_case_sensitivity_idempotency_and_closed_rounds_are_enforced(): void
    {
        [$teacher, , $students, $round] = $this->room();
        $key = Str::uuid()->toString();
        $this->asStudent($students[0])->postJson(route('student.recitations.submit', $round), ['answer' => 'COUT', 'request_key' => $key])->assertOk()->assertJsonPath('correct', false);
        $this->postJson(route('student.recitations.submit', $round), ['answer' => 'cout', 'request_key' => $key])->assertConflict();
        $round->update(['case_sensitive' => false]);
        $this->postJson(route('student.recitations.submit', $round), ['answer' => 'COUT', 'request_key' => Str::uuid()->toString()])->assertOk()->assertJsonPath('award.position', 1);
        $this->withSession(['student_id' => null, 'teacher_id' => $teacher->id])->post(route('teacher.recitations.action', $round), ['status' => 'closed'])->assertRedirect();
        $this->asStudent($students[1])->putJson(route('student.recitations.save', $round), ['answer' => 'cout', 'revision' => 0])->assertConflict();
        $this->assertDatabaseCount('recitation_awards', 1);
    }

    public function test_teacher_can_prepare_and_edit_until_students_begin_and_pages_render(): void
    {
        [$teacher, $room, $students, $round] = $this->room();
        $payload = ['classroom_id' => $room->id, 'title' => 'Output checkpoint', 'term' => 'Term2', 'question' => 'What keyword declares a whole number?', 'accepted_answers' => "int\ninteger", 'case_sensitive' => 1, 'status' => 'draft'];
        $this->withSession(['teacher_id' => $teacher->id])->post(route('teacher.recitations.store'), [...$payload, 'accepted_answers' => str_repeat('x', 5001)])->assertSessionHasErrors('accepted_answers');
        $this->withSession(['teacher_id' => $teacher->id])->post(route('teacher.recitations.store'), $payload)->assertRedirect();
        $created = RecitationRound::latest('id')->firstOrFail();
        $this->assertSame(['int', 'integer'], $created->accepted_answers);
        $this->put(route('teacher.recitations.update', $round), [...$payload, 'status' => 'open'])->assertRedirect();
        $page = $this->get(route('teacher.recitations.show', $round))->assertOk()->assertSee('Live student drafts');
        $this->get(route('teacher.recitations'))->assertOk()->assertSee('Recitation Studio');
        $studentPage = $this->asStudent($students[0])->get(route('student.recitations.show', $round))->assertOk()->assertSee('Submit answer')->assertDontSee('integer');
        $this->get(route('student.recitations'))->assertOk();
        $this->putJson(route('student.recitations.save', $round), ['answer' => 'work in progress', 'revision' => 0])->assertOk();
        $this->withSession(['student_id' => null, 'teacher_id' => $teacher->id])->putJson(route('teacher.recitations.update', $round), $payload)->assertConflict();
        $this->get(route('system.about'))->assertOk()->assertSee('Nolan G. Fadriquilan');
        if ($preview = getenv('CLASSROOM_PREVIEW')) {
            file_put_contents($preview.'/recitation-teacher.html', $page->getContent());
            file_put_contents($preview.'/recitation-student.html', $studentPage->getContent());
        }
    }

    public function test_each_student_can_earn_only_two_correct_places_per_term_and_year_without_losing_previous_rewards(): void
    {
        [$teacher, $room, $students, $first] = $this->room();
        $second = RecitationRound::factory()->forClassroom($room)->create(['term' => $first->term, 'school_year' => $first->school_year]);
        $third = RecitationRound::factory()->forClassroom($room)->create(['term' => $first->term, 'school_year' => $first->school_year]);
        $student = $students[0];
        $this->asStudent($student)->postJson(route('student.recitations.submit', $first), ['answer' => 'wrong', 'request_key' => Str::uuid()->toString()])->assertOk()->assertJsonPath('term_progress.correct_answers', 0);
        foreach ([$first, $second] as $index => $round) {
            $key = Str::uuid()->toString();
            $this->postJson(route('student.recitations.submit', $round), ['answer' => 'cout', 'request_key' => $key])->assertOk()->assertJsonPath('term_progress.correct_answers', $index + 1)->assertJsonPath('term_progress.score', ($index + 1) * 10);
        }
        $this->postJson(route('student.recitations.submit', $second), ['answer' => 'cout', 'request_key' => $key])->assertOk()->assertJsonPath('term_progress.score', 20);
        $this->postJson(route('student.recitations.submit', $third), ['answer' => 'cout', 'request_key' => Str::uuid()->toString(), 'term' => 'Term2', 'school_year' => '2027-2028'])->assertConflict()->assertSee('20 recitation points');
        $this->putJson(route('student.recitations.save', $third), ['answer' => 'cout', 'revision' => 0])->assertConflict();
        $this->getJson(route('student.recitations.status', $third))->assertOk()->assertJsonPath('term_progress.limit_reached', true)->assertJsonPath('term_progress.remaining', 0);
        $page = $this->get(route('student.recitations.show', $third))->assertOk()->assertSee('Term limit reached')->assertSee('readonly', false)->assertSee('disabled', false);
        $this->get(route('student.recitations'))->assertOk()->assertSee('20 points')->assertSee('Term limit reached');
        $this->assertSame(115, $student->fresh()->xp);
        $this->assertSame(20, (int) RecitationAward::where('student_id', $student->id)->sum('score'));
        $this->assertDatabaseCount('recitation_drafts', 0);
        $this->assertDatabaseCount('recitation_attempts', 3);
        $this->asStudent($students[1])->postJson(route('student.recitations.submit', $third), ['answer' => 'cout', 'request_key' => Str::uuid()->toString()])->assertOk()->assertJsonPath('award.position', 1);
        foreach ([['Term2', $first->school_year], ['Term1', '2027-2028']] as [$term, $year]) {
            $newRound = RecitationRound::factory()->forClassroom($room)->create(['term' => $term, 'school_year' => $year]);
            $this->asStudent($student)->postJson(route('student.recitations.submit', $newRound), ['answer' => 'cout', 'request_key' => Str::uuid()->toString()])->assertOk()->assertJsonPath('term_progress.correct_answers', 1);
        }
        $this->withSession(['student_id' => null, 'teacher_id' => $teacher->id])->getJson(route('teacher.recitations.status', $third))->assertOk()->assertJsonPath('drafts.0.term_score', 20)->assertJsonPath('drafts.0.term_limit_reached', true);
        if ($preview = getenv('CLASSROOM_PREVIEW')) {
            file_put_contents($preview.'/recitation-term-limit.html', $page->getContent());
        }
    }

    public function test_term_limits_survive_section_changes_and_monitor_refresh_detects_awards_in_other_rounds(): void
    {
        [$teacher, $room, $students, $first] = $this->room();
        $student = $students[0];
        $monitor = $this->withSession(['teacher_id' => $teacher->id])->getJson(route('teacher.recitations.status', $first))->assertOk()->json('revision');
        foreach (range(1, 2) as $index) {
            $round = RecitationRound::factory()->forClassroom($room)->create(['term' => $first->term, 'school_year' => $first->school_year]);
            $this->asStudent($student)->postJson(route('student.recitations.submit', $round), ['answer' => 'cout', 'request_key' => Str::uuid()->toString()])->assertOk();
        }
        $this->withSession(['student_id' => null, 'teacher_id' => $teacher->id])->getJson(route('teacher.recitations.status', ['round' => $first, 'revision' => $monitor]))->assertOk()->assertJsonPath('drafts.0.term_limit_reached', true);
        [$otherTeacher, $otherRoom] = $this->room();
        $otherRound = RecitationRound::factory()->forClassroom($otherRoom)->create(['term' => $first->term, 'school_year' => $first->school_year]);
        $student->update(['classroom_id' => $otherRoom->id]);
        $this->asStudent($student)->postJson(route('student.recitations.submit', $otherRound), ['answer' => 'cout', 'request_key' => Str::uuid()->toString()])->assertConflict();
        $this->assertSame(120, $student->fresh()->xp);
        $service = app(GradingSheetService::class);
        $this->assertTrue($service->sources($teacher->id, $room->id, 'Term1', $first->school_year)->contains('key', 'recitation:'.$first->id));
        $this->assertFalse($service->sources($teacher->id, $room->id, 'Term1', '2027-2028')->contains('key', 'recitation:'.$first->id));
    }
}
