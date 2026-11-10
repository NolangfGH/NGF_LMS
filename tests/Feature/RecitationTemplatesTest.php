<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\RecitationRound;
use App\Models\Student;
use App\Models\TeacherAccount;
use App\Services\ReadyMadeActivities;
use App\Services\RecitationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class RecitationTemplatesTest extends TestCase
{
    use RefreshDatabase;

    private function classroom(): Classroom
    {
        $teacher = TeacherAccount::create(['first_name' => 'Pack', 'last_name' => 'Teacher', 'school' => 'School', 'email' => fake()->unique()->safeEmail(), 'password' => 'Password123']);

        return Classroom::create(['teacher_account_id' => $teacher->id, 'name' => 'Programming', 'grade' => 'Grade 8', 'section' => 'AM', 'join_code' => Str::upper(Str::random(8))]);
    }

    private function payload(Classroom $room, string $key = 'introduction'): array
    {
        return ['classroom_ids' => [$room->id], 'school_term' => 'Term1', 'school_year' => '2026-2027', 'questions' => app(ReadyMadeActivities::class)->lesson($key)['recitation']];
    }

    public function test_all_fifty_topics_have_five_usable_short_answer_questions_and_private_preview_links(): void
    {
        $room = $this->classroom();
        $this->withSession(['teacher_id' => $room->teacher_account_id]);
        $total = 0;
        foreach (['cpp', 'arduino-uno'] as $course) {
            $catalog = app(ReadyMadeActivities::class)->catalog($course);
            $this->assertCount(25, $catalog);
            foreach ($catalog as $key => $lesson) {
                $this->assertCount(5, $lesson['recitation'], $key);
                $this->assertCount(5, array_unique(array_column($lesson['recitation'], 'question')), $key);
                foreach ($lesson['recitation'] as $question) {
                    $this->assertNotEmpty($question['title']);
                    $this->assertNotEmpty(app(RecitationService::class)->acceptedAnswers($question['accepted_answers']));
                    $this->assertLessThanOrEqual(160, mb_strlen($question['title']));
                    $total++;
                }
            }
            $this->get(route('teacher.library', ['course' => $course]))->assertOk()->assertSee('125')->assertSee('Review 5 recitation questions');
        }
        $this->assertSame(250, $total);
        $page = $this->get(route('teacher.library.show', ['arduino-blink', 'recitation']))->assertOk()->assertSee('Save private recitation drafts')->assertSee('questions[4][accepted_answers]', false);
        if ($preview = getenv('RECITATION_PACK_PREVIEW')) {
            file_put_contents($preview.'/recitation-pack.html', $page->getContent());
        }
    }

    public function test_edits_are_saved_as_five_private_drafts_per_owned_section_and_are_not_opened_by_a_forged_status(): void
    {
        $room = $this->classroom();
        $second = Classroom::create(['teacher_account_id' => $room->teacher_account_id, 'name' => 'Programming', 'grade' => 'Grade 8', 'section' => 'BP', 'join_code' => 'RECITBP']);
        $payload = $this->payload($room);
        $payload['classroom_ids'][] = $second->id;
        $payload['questions'][0]['question'] = 'Teacher-edited question: what is 3 + 4?';
        $payload['questions'][0]['accepted_answers'] = "7\nseven\n7";
        $payload['status'] = 'open';
        $this->withSession(['teacher_id' => $room->teacher_account_id])->post(route('teacher.library.store', ['introduction', 'recitation']), $payload)->assertRedirect(route('teacher.recitations'))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('recitation_rounds', 10);
        $this->assertSame(10, RecitationRound::where('status', 'draft')->count());
        $round = RecitationRound::where('classroom_id', $room->id)->firstOrFail();
        $this->assertSame(['7', 'seven'], $round->accepted_answers);
        $this->assertSame($payload['questions'][0]['question'], $round->question);
        $this->get(route('teacher.recitations.show', $round))->assertOk()->assertSee('Edit this question');
        $student = Student::create(['classroom_id' => $room->id, 'first_name' => 'Learner', 'nickname' => 'RecitationLearner', 'student_id' => 'RECPACK001', 'password' => 'Password123', 'xp' => 100]);
        $this->withSession(['teacher_id' => null, 'student_id' => $student->id])->get(route('student.recitations.show', $round))->assertForbidden();
        $this->get(route('student.recitations'))->assertOk()->assertDontSee('Teacher-edited question');
        $this->withSession(['teacher_id' => $room->teacher_account_id, 'student_id' => null])->post(route('teacher.recitations.action', $round), ['status' => 'open'])->assertRedirect();
        $this->withSession(['teacher_id' => null, 'student_id' => $student->id])->get(route('student.recitations.show', $round))->assertOk()->assertSee('Teacher-edited question')->assertDontSee('seven');
    }

    public function test_foreign_archived_or_invalid_packs_cannot_create_partial_rounds(): void
    {
        $room = $this->classroom();
        $foreign = $this->classroom();
        $this->withSession(['teacher_id' => $room->teacher_account_id]);
        $url = route('teacher.library.store', ['introduction', 'recitation']);
        $payload = $this->payload($room);
        $this->postJson($url, array_replace($payload, ['classroom_ids' => [$foreign->id]]))->assertUnprocessable()->assertJsonValidationErrors('classroom_ids.0');
        $invalid = $payload;
        $invalid['questions'][4]['accepted_answers'] = implode("\n", range(1, 21));
        $this->postJson($url, $invalid)->assertUnprocessable()->assertJsonValidationErrors('questions.4.accepted_answers');
        $invalid = $payload;
        $invalid['questions'] = array_slice($payload['questions'], 0, 4);
        $this->postJson($url, $invalid)->assertUnprocessable()->assertJsonValidationErrors('questions');
        $this->postJson($url, array_replace($payload, ['school_year' => '2026-2029']))->assertUnprocessable()->assertJsonValidationErrors('school_year');
        $room->update(['archived_at' => now()]);
        $this->postJson($url, $payload)->assertUnprocessable();
        $this->assertDatabaseEmpty('recitation_rounds');
    }

    public function test_students_and_principals_cannot_read_keys_or_copy_packs_and_missing_topics_return_404(): void
    {
        $room = $this->classroom();
        $show = route('teacher.library.show', ['introduction', 'recitation']);
        $this->get($show)->assertRedirect(route('teacher.login'));
        $principal = $room->teacher;
        $principal->forceFill(['role' => 'principal'])->save();
        $this->withSession(['teacher_id' => $principal->id])->get($show)->assertForbidden();
        $this->postJson(route('teacher.library.store', ['introduction', 'recitation']), $this->payload($room))->assertForbidden();
        $principal->forceFill(['role' => 'teacher'])->save();
        $this->get(route('teacher.library.show',['missing-topic', 'recitation']))->assertNotFound();
        $this->assertDatabaseEmpty('recitation_rounds');
    }
}
