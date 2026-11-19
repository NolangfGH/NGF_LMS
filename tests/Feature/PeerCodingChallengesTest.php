<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\CodingMatch;
use App\Models\LessonContent;
use App\Models\Student;
use App\Models\TeacherAccount;
use App\Services\CourseAssessments;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PeerCodingChallengesTest extends TestCase
{
    use RefreshDatabase;

    private function room(string $grade = 'Grade 8'): Classroom
    {
        $teacher = TeacherAccount::create(['first_name' => 'Supervisor', 'last_name' => 'Teacher', 'email' => fake()->unique()->safeEmail(), 'school' => 'School', 'password' => 'Password123']);

        return Classroom::create(['teacher_account_id' => $teacher->id, 'name' => 'Programming', 'grade' => $grade, 'section' => 'AM', 'join_code' => fake()->unique()->bothify('PEER#####')]);
    }

    private function student(Classroom $room, bool $ready = true): Student
    {
        $student = Student::create(['classroom_id' => $room->id, 'first_name' => 'SecretPersonal'.fake()->unique()->numerify('#####'), 'nickname' => fake()->unique()->bothify('Nickname#####'),
            'student_id' => fake()->unique()->bothify('PRIVATE-LRN-#####'), 'password' => 'Password123', 'xp' => 100]);
        if ($ready) {
            foreach ([CourseAssessments::REASONING, CourseAssessments::PRE] as $assessment) {
                DB::table('course_assessment_results')->insert(['student_id' => $student->id, 'assessment' => $assessment, 'score' => 0, 'total_questions' => 50, 'skill_scores' => '{}', 'completed_at' => now()]);
            }
        }

        return $student;
    }

    private function fixture(bool $differentTeachers = false): array
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        $this->travelTo(now()->startOfSecond());
        $a = $this->room();
        $b = $differentTeachers ? $this->room() : $a;

        return [$a, $b, $this->student($a), $this->student($b)];
    }

    private function asStudent(Student $student): static
    {
        return $this->withSession(['teacher_id' => null, 'student_id' => $student->id, 'student_session_version' => 1, 'student_class_verified' => true]);
    }

    private function asTeacher(int $id): static
    {
        return $this->withSession(['student_id' => null, 'teacher_id' => $id, 'teacher_session_version' => 1]);
    }

    private function requestMatch(Student $a, Student $b): CodingMatch
    {
        $this->asStudent($a)->post(route('student.peer-challenges.store'), ['course' => 'cpp', 'lesson' => 'introduction', 'opponent' => $b->id, 'winner_xp' => 9999])->assertRedirect()->assertSessionHasNoErrors();

        return CodingMatch::latest('id')->firstOrFail();
    }

    private function approval(CodingMatch $match): array
    {
        return ['decision' => 'approved', 'title' => $match->title, 'instructions' => 'Write a complete program that prints OK exactly.', 'sample_output' => 'OK', 'time_limit_minutes' => 2, 'winner_xp' => 80, 'award_title' => 'Peer Champion'];
    }

    public function test_same_teacher_requests_require_approval_and_then_use_the_normal_verified_winner_flow(): void
    {
        [$room, , $a, $b] = $this->fixture();
        $match = $this->requestMatch($a, $b);
        $this->assertSame('awaiting_approval', $match->status);
        $this->assertSame(100, $match->winner_xp);
        $this->assertDatabaseCount('coding_match_approvals', 1);
        $this->assertDatabaseHas('challenge_notices', ['coding_match_id' => $match->id, 'teacher_account_id' => $room->teacher_account_id, 'event_key' => 'peer-request']);
        if ($preview = getenv('CLASSROOM_UPGRADES_PREVIEW')) {
            file_put_contents($preview.'/peer-matches.html', $this->get(route('student.peer-challenges', ['opponent' => $b->id]))->assertOk()->getContent());
        }
        $this->get(route('student.matches.show', $match))->assertRedirect(route('student.peer-challenges'));
        $this->asStudent($b)->postJson(route('student.matches.respond', $match), ['decision' => 'accepted'])->assertUnprocessable();
        $this->putJson(route('student.matches.save', $match), ['revision' => 0, 'code' => 'early code'])->assertUnprocessable();
        $this->assertDatabaseCount('learning_rewards', 0);
        $this->asTeacher($room->teacher_account_id)->get(route('teacher.challenges'))->assertOk()->assertSee('Student requests awaiting teacher approval');
        $reviewPage = $this->get(route('teacher.peer-challenges.review', $match))->assertOk()->assertSee('Approve participation');
        if ($preview = getenv('CLASSROOM_UPGRADES_PREVIEW')) {
            file_put_contents($preview.'/peer-review.html', $reviewPage->getContent());
        }
        $this->post(route('teacher.peer-challenges.decide', $match), $this->approval($match))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('inviting', $match->fresh()->status);
        $this->asStudent($b)->postJson(route('student.matches.respond', $match), ['decision' => 'accepted'])->assertOk();
        $this->travel(6)->seconds();
        $this->asStudent($a)->postJson(route('student.peer-challenges.withdraw', $match))->assertConflict();
        $this->asStudent($a)->putJson(route('student.matches.save', $match), ['revision' => 0, 'code' => '#include <iostream>'.PHP_EOL.'int main(){std::cout<<"OK";}'])->assertOk();
        $submissionId = $this->postJson(route('student.matches.submit', $match), ['revision' => 1])->assertOk()->json('id');
        $this->asTeacher($room->teacher_account_id)->postJson(route('teacher.matches.review', [$match, $submissionId]), ['verdict' => 'correct', 'feedback' => 'Tested this submitted program and verified OK.', 'observed_output' => 'OK', 'verified' => 1])->assertOk();
        $this->postJson(route('teacher.matches.award', $match), ['submission_id' => $submissionId, 'note' => 'First correct verified solution wins this match.', 'confirmed' => 1])->assertOk();
        $this->assertSame(180, $a->fresh()->xp);
        $this->assertSame(100, $b->fresh()->xp);
    }

    public function test_different_teachers_each_approve_and_guest_monitor_cannot_manage_the_host_match(): void
    {
        [$host, $guest, $a, $b] = $this->fixture(true);
        $match = $this->requestMatch($a, $b);
        $this->asTeacher($guest->teacher_account_id)->postJson(route('teacher.peer-challenges.decide', $match), ['decision' => 'approved'])->assertUnprocessable();
        $this->asTeacher($host->teacher_account_id)->post(route('teacher.peer-challenges.decide', $match), $this->approval($match))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('awaiting_approval', $match->fresh()->status);
        $this->asStudent($b)->postJson(route('student.matches.respond', $match), ['decision' => 'accepted'])->assertUnprocessable();
        $this->asTeacher($guest->teacher_account_id)->post(route('teacher.peer-challenges.decide', $match), ['decision' => 'approved', 'winner_xp' => 9999, 'title' => 'Tampered title'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(80, $match->fresh()->winner_xp);
        $this->assertNotSame('Tampered title', $match->fresh()->title);
        $this->get(route('teacher.matches.show', $match))->assertOk()->assertViewHas('snapshot', fn (array $data): bool => $data['can_manage'] === false);
        $this->getJson(route('teacher.matches.status', $match))->assertOk()->assertJsonPath('can_manage', false);
        $this->asStudent($b)->postJson(route('student.matches.respond', $match), ['decision' => 'accepted'])->assertOk();
        $this->travel(6)->seconds();
        $this->asStudent($a)->putJson(route('student.matches.save', $match), ['revision' => 0, 'code' => 'PRIVATE_CREATOR_SOURCE'])->assertOk();
        $submissionId = $this->postJson(route('student.matches.submit', $match), ['revision' => 1])->assertOk()->json('id');
        $this->asStudent($b)->getJson(route('student.matches.status', $match))->assertOk()->assertDontSee('PRIVATE_CREATOR_SOURCE');
        $this->asTeacher($guest->teacher_account_id)->get(route('teacher.matches.download', [$match, $submissionId]))->assertOk()->assertSee('PRIVATE_CREATOR_SOURCE');
        $this->postJson(route('teacher.matches.review', [$match, $submissionId]), ['verdict' => 'correct', 'feedback' => 'I should not be able to manage this record.', 'verified' => 1, 'observed_output' => 'OK'])->assertNotFound();
        $this->postJson(route('teacher.matches.award', $match), ['submission_id' => $submissionId, 'confirmed' => 1, 'note' => 'Guest must not award'])->assertNotFound();
        $this->postJson(route('teacher.matches.cancel', $match), ['reason' => 'Guest must not close active match'])->assertNotFound();
        $stranger = $this->room();
        $this->asTeacher($stranger->teacher_account_id)->get(route('teacher.matches.show', $match))->assertNotFound();
        $this->assertSame(100, $a->fresh()->xp);
    }

    public function test_directory_and_search_respect_grade_and_nickname_privacy(): void
    {
        [$room, , $a, $b] = $this->fixture();
        $b->forceFill(['leaderboard_visible' => false])->save();
        $differentGrade = $this->student($this->room('Grade 9'));
        $this->asStudent($a)->get(route('student.peer-challenges'))->assertOk()->assertSee($b->rankingAlias())->assertDontSee($b->first_name)->assertDontSee($b->student_id)->assertDontSee($b->nickname)->assertDontSee($differentGrade->nickname);
        $this->get(route('student.peer-challenges', ['q' => $b->nickname]))->assertOk()->assertDontSee($b->rankingAlias());
        $this->get(route('student.peer-challenges', ['q' => $b->rankingAlias()]))->assertOk()->assertSee($b->rankingAlias());
        $this->postJson(route('student.peer-challenges.store'), ['course' => 'cpp', 'lesson' => 'introduction', 'opponent' => $differentGrade->id])->assertUnprocessable();
        $this->postJson(route('student.peer-challenges.store'), ['course' => 'cpp', 'lesson' => 'introduction', 'opponent' => $a->id])->assertUnprocessable();
    }

    public function test_locked_topics_unapproved_opponents_and_changed_enrollment_cannot_start_a_match(): void
    {
        [$host, $guest, $a, $b] = $this->fixture(true);
        $host->update(['sequential_learning' => true]);
        $guest->update(['sequential_learning' => true]);
        $this->asStudent($a)->postJson(route('student.peer-challenges.store'), ['course' => 'cpp', 'lesson' => 'functions', 'opponent' => $b->id])->assertUnprocessable();
        $b->forceFill(['registration_status' => 'pending'])->save();
        $this->postJson(route('student.peer-challenges.store'), ['course' => 'cpp', 'lesson' => 'introduction', 'opponent' => $b->id])->assertUnprocessable();
        $b->forceFill(['registration_status' => 'approved'])->save();
        $match = $this->requestMatch($a, $b);
        $guest->update(['grade' => 'Grade 9']);
        $this->asTeacher($host->teacher_account_id)->postJson(route('teacher.peer-challenges.decide', $match), $this->approval($match))->assertUnprocessable();
        $this->assertSame('awaiting_approval', $match->fresh()->status);
        $this->assertSame(0, DB::table('coding_match_approvals')->where('decision', 'approved')->count());
    }

    public function test_students_can_withdraw_and_teachers_can_decline_with_a_shared_reason(): void
    {
        [$room, , $a, $b] = $this->fixture();
        $match = $this->requestMatch($a, $b);
        $this->asStudent($b)->post(route('student.peer-challenges.withdraw', $match))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('cancelled', $match->fresh()->status);
        $this->asTeacher($room->teacher_account_id)->postJson(route('teacher.peer-challenges.decide', $match), $this->approval($match))->assertConflict();
        $next = $this->requestMatch($a, $b);
        $this->asTeacher($room->teacher_account_id)->postJson(route('teacher.peer-challenges.decide', $next), ['decision' => 'rejected'])->assertUnprocessable();
        $this->post(route('teacher.peer-challenges.decide', $next), ['decision' => 'rejected', 'reason' => 'Please finish the assigned practice first.'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('cancelled', $next->fresh()->status);
        $this->assertDatabaseCount('learning_rewards', 0);
    }

    public function test_duplicate_pairs_and_more_than_three_open_requests_are_rejected(): void
    {
        [$room, , $a, $b] = $this->fixture();
        $this->requestMatch($a, $b);
        $this->asStudent($a)->postJson(route('student.peer-challenges.store'), ['course' => 'cpp', 'lesson' => 'introduction', 'opponent' => $b->id])->assertUnprocessable();
        foreach (range(1, 2) as $index) {
            $this->requestMatch($a, $this->student($room));
        }
        $this->asStudent($a)->postJson(route('student.peer-challenges.store'), ['course' => 'cpp', 'lesson' => 'introduction', 'opponent' => $this->student($room)->id])->assertUnprocessable();
        $this->assertDatabaseCount('coding_matches', 3);
    }

    public function test_unsigned_users_and_unrelated_staff_cannot_review_peer_requests(): void
    {
        $this->get(route('student.peer-challenges'))->assertRedirect(route('student.login'));
        [$room, , $a, $b] = $this->fixture();
        $match = $this->requestMatch($a, $b);
        $foreign = $this->room();
        $this->asTeacher($foreign->teacher_account_id)->get(route('teacher.peer-challenges.review', $match))->assertNotFound();
        $this->postJson(route('teacher.peer-challenges.decide', $match), ['decision' => 'approved'])->assertNotFound();
        TeacherAccount::findOrFail($foreign->teacher_account_id)->forceFill(['role' => 'principal'])->save();
        $this->get(route('teacher.peer-challenges.review', $match))->assertForbidden();
        $this->assertSame('awaiting_approval', $match->fresh()->status);
    }

    public function test_arduino_requests_require_arduino_prerequisites_and_keep_the_sketch_environment(): void
    {
        [$room, , $a, $b] = $this->fixture();
        $room->update(['grade' => 'Grade 9']);
        $body = ['course' => 'arduino-uno', 'lesson' => 'arduino-robotics', 'opponent' => $b->id];
        $this->asStudent($a)->postJson(route('student.peer-challenges.store'), $body)->assertUnprocessable();
        foreach ([$a, $b] as $student) {
            DB::table('course_assessment_results')->insert(['student_id' => $student->id, 'assessment' => CourseAssessments::ARDUINO_PRE, 'score' => 0, 'total_questions' => 50, 'skill_scores' => '{}', 'completed_at' => now()]);
        }
        $this->post(route('student.peer-challenges.store'), $body)->assertRedirect()->assertSessionHasNoErrors();
        $match = CodingMatch::sole();
        $this->assertSame('arduino-uno', $match->course);
        $this->assertSame('Grade 9', $match->requested_grade);
        $this->asTeacher($room->teacher_account_id)->post(route('teacher.peer-challenges.decide', $match), $this->approval($match))->assertRedirect()->assertSessionHasNoErrors();
        $this->get(route('teacher.matches.show', $match))->assertOk()->assertSee('Live sketch.ino')->assertSee('Open in Wokwi');
    }

    public function test_teacher_specific_custom_lessons_are_not_shared_across_teachers_by_a_student_request(): void
    {
        [$host, , $a, $b] = $this->fixture(true);
        LessonContent::create(['teacher_account_id' => $host->teacher_account_id, 'lesson_key' => 'custom-private-lesson', 'status' => 'published', 'revision' => 1, 'definition' => config('lessons.introduction')]);
        $this->asStudent($a)->postJson(route('student.peer-challenges.store'), ['course' => 'cpp', 'lesson' => 'custom-private-lesson', 'opponent' => $b->id])->assertUnprocessable()->assertJsonValidationErrors('lesson');
        $this->assertDatabaseCount('coding_matches', 0);
    }
}
