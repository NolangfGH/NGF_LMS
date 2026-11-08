<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\LearningNotification;
use App\Models\LessonProgress;
use App\Models\Student;
use App\Models\TeacherAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CoursePostTestTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_a_ready_student_can_use_their_own_teachers_current_code(): void
    {
        [$teacher, $student, $peer] = $this->classroom();
        $show = route('student.post-test.show');
        $request = route('student.post-test.request');
        $start = route('student.post-test.start');
        $this->get($show)->assertRedirect(route('student.login'));
        $this->asStudent($student);
        $this->get($show)->assertOk()->assertSee('Post-test locked');
        $this->postJson($request)->assertForbidden();
        $this->finishCourse($student);
        $this->finishCourse($peer);
        $this->get($show)->assertOk()->assertSee('Request a post-test code');
        $this->postJson($start, ['code' => 'AB01234567'])->assertUnprocessable();
        $this->post($request)->assertRedirect($show);
        $attempt = DB::table('course_post_test_attempts')->sole();
        $this->assertDatabaseHas(LearningNotification::class, ['teacher_account_id' => $teacher->id, 'target_type' => 'post_test_code', 'target_id' => $attempt->id]);

        $other = TeacherAccount::create(['first_name' => 'Another', 'last_name' => 'Teacher', 'school' => 'Another school', 'email' => 'other@example.test', 'password' => 'Password123']);
        $this->asTeacher($other);
        $this->get(route('teacher.post-test.index'))->assertOk()->assertDontSee($student->nickname);
        $issue = route('teacher.post-test.issue', $attempt->id);
        $this->post($issue)->assertNotFound();

        $this->asTeacher($teacher);
        $this->get(route('teacher.post-test.index'))->assertOk()->assertSee($student->nickname)->assertSee('Generate post-test code');
        $this->post($issue)->assertRedirect()->assertSessionHas('post_test_code.code');
        $oldCode = session('post_test_code.code');
        $this->get(route('teacher.post-test.index'))->assertOk()->assertSee($oldCode);
        $this->get(route('teacher.post-test.index'))->assertOk()->assertDontSee($oldCode);
        $this->post($issue)->assertRedirect();
        $code = session('post_test_code.code');
        $this->assertNotSame($oldCode, $code);
        $this->assertNotSame($code, DB::table('course_post_test_attempts')->value('access_code_hash'));

        $this->asStudent($peer);
        $this->post($request)->assertRedirect($show);
        $this->postJson($start, ['code' => $code])->assertUnprocessable();
        $this->asStudent($student);
        $this->postJson($start, ['code' => $oldCode])->assertUnprocessable();
        $this->travel(25)->hours();
        $this->postJson($start, ['code' => $code])->assertUnprocessable();
        $this->asTeacher($teacher);
        $this->post($issue)->assertRedirect();
        $code = session('post_test_code.code');
        $this->asStudent($student);
        $this->post($start, ['code' => strtolower($code)])->assertRedirect($show);
        $this->assertDatabaseHas('course_post_test_attempts', ['id' => $attempt->id, 'access_code_hash' => null, 'question_seconds' => 90]);
        $this->asTeacher($teacher);
        $this->post($issue)->assertConflict();
    }

    public function test_first_attempt_has_ninety_seconds_and_forty_four_unlocks_a_final_sixty_second_attempt(): void
    {
        [$teacher, $student] = $this->classroom();
        $this->finishCourse($student);
        $this->freezeSecond();
        $show = route('student.post-test.show');
        $advance = route('student.post-test.question');
        $draft = route('student.post-test.draft');
        $first = $this->openExam($teacher, $student);
        $items = json_decode($first->questions, true);
        $this->assertCount(50, $items);

        $this->get($show)->assertOk()->assertHeader('Permissions-Policy', 'display-capture=()')
            ->assertSee('Question 1 of 50')->assertSee('90 seconds per question')
            ->assertSee($items[0]['prompt'])->assertDontSee($items[1]['prompt'])
            ->assertViewHas('state', fn (array $state): bool => ! isset($state['current']['question']['correct']));
        $this->postJson($draft, ['attempt_id' => $first->id, 'question_index' => 0, 'answer' => $items[0]['correct'][0]])
            ->assertOk()->assertExactJson(['advanced' => false, 'finished' => false]);
        $this->travel(89)->seconds();
        $this->get($show)->assertOk()->assertSee($items[0]['prompt'])
            ->assertViewHas('state', fn (array $state): bool => $state['current']['answer'] === $items[0]['correct'][0]);
        $this->travel(1)->second();
        $this->postJson($advance, ['attempt_id' => $first->id, 'question_index' => 0, 'answer' => 'Too late'])
            ->assertOk()->assertExactJson(['advanced' => true, 'finished' => false]);
        $this->postJson($advance, ['attempt_id' => $first->id, 'question_index' => 0])->assertConflict();
        $this->get($show)->assertOk()->assertSee($items[1]['prompt'])->assertDontSee($items[0]['prompt']);

        $this->finishExam($first, 44, from: 1);
        $this->get($show)->assertOk()->assertSee('44 / 50')->assertSee('Failed')->assertSee('Second and final attempt')
            ->assertDontSee($items[0]['prompt'])->assertDontSee('Correct answers');
        $this->get(route('student.assessments.certificate'))->assertNotFound();

        $second = $this->openExam($teacher, $student);
        $this->assertSame(2, $second->attempt_number);
        $this->assertNotSame(array_column($items, 'prompt'), array_column(json_decode($second->questions, true), 'prompt'));
        $this->get($show)->assertOk()->assertSee('60 seconds per question');
        $this->postJson($advance, ['attempt_id' => $first->id, 'question_index' => 0])->assertConflict();
        $this->finishExam($second, 45);
        $this->get($show)->assertOk()->assertSee('45 / 50')->assertSee('Passed')->assertDontSee('Request a post-test code');
        $this->assertDatabaseHas('course_certificates', ['student_id' => $student->id, 'award_type' => 'excellence', 'title' => 'Certificate of Excellence in C++ Average Programmer']);
        $this->assertDatabaseHas('course_post_test_attempts', ['id' => $second->id, 'score' => 45, 'answers' => null, 'questions' => null]);
        $this->postJson(route('student.post-test.request'))->assertConflict();
        $this->assertSame(45, $student->fresh()->xp);
    }

    public function test_expired_questions_finish_the_exam_and_a_second_failure_does_not_allow_a_third_attempt(): void
    {
        [$teacher, $student] = $this->classroom();
        $this->finishCourse($student);
        $this->freezeSecond();
        $this->openExam($teacher, $student);
        $this->travel(75)->minutes();
        $this->get(route('student.post-test.show'))->assertOk()->assertSee('0 / 50')->assertSee('Second and final attempt');
        $this->openExam($teacher, $student);
        $this->travel(50)->minutes();
        $this->get(route('student.post-test.show'))->assertOk()->assertSee('0 / 50')->assertSee('Failed')->assertDontSee('Request a post-test code');
        $this->postJson(route('student.post-test.request'))->assertConflict();
        $this->assertDatabaseEmpty('course_certificates');
        $this->assertDatabaseCount('course_post_test_attempts', 2);
    }

    public function test_first_attempt_pass_at_the_deadline_issues_a_certificate_without_a_retry(): void
    {
        [$teacher, $student] = $this->classroom();
        $this->finishCourse($student);
        $this->freezeSecond();
        $attempt = $this->openExam($teacher, $student);
        $this->finishExam($attempt, 45, until: 45);

        $this->travel(450)->seconds();
        $this->get(route('student.post-test.show'))->assertOk()->assertSee('45 / 50')->assertSee('Passed')->assertDontSee('Second and final attempt');
        $this->get(route('student.assessments.certificate'))->assertOk()->assertSee('Certificate of Excellence in C++ Average Programmer');
        $this->postJson(route('student.post-test.request'))->assertConflict();
        $this->assertDatabaseCount('course_certificates', 1);
        $this->assertDatabaseCount('course_post_test_attempts', 1);
    }

    private function classroom(): array
    {
        $teacher = TeacherAccount::create(['first_name' => 'Course', 'last_name' => 'Teacher', 'school' => 'Regional Science High School', 'email' => 'exam@example.test', 'password' => 'Password123']);
        $room = Classroom::create(['teacher_account_id' => $teacher->id, 'name' => 'C++', 'grade' => 'Grade 8', 'section' => 'AM', 'join_code' => 'EXAM8']);
        $student = Student::create(['classroom_id' => $room->id, 'first_name' => 'Test', 'surname' => 'Cadet', 'nickname' => 'ExamCadet', 'student_id' => 'EXAM001', 'password' => 'Password123', 'xp' => 100]);
        $peer = Student::create(['classroom_id' => $room->id, 'first_name' => 'Peer', 'nickname' => 'PeerCadet', 'student_id' => 'EXAM002', 'password' => 'Password123']);

        return [$teacher, $student, $peer];
    }

    private function finishCourse(Student $student): void
    {
        $this->recordCompletedPretest($student);
        foreach (array_keys(config('lessons')) as $key) {
            LessonProgress::create(['student_id' => $student->id, 'lesson_key' => $key, 'started_at' => now(), 'last_opened_at' => now(), 'completed_at' => now()]);
        }
    }

    private function openExam(TeacherAccount $teacher, Student $student): object
    {
        $this->asStudent($student);
        $this->post(route('student.post-test.request'))->assertRedirect(route('student.post-test.show'));
        $id = DB::table('course_post_test_attempts')->where('student_id', $student->id)->latest('id')->value('id');
        $this->asTeacher($teacher);
        $this->post(route('teacher.post-test.issue', $id))->assertRedirect();
        $code = session('post_test_code.code');
        $this->asStudent($student);
        $this->post(route('student.post-test.start'), ['code' => $code])->assertRedirect(route('student.post-test.show'));

        return DB::table('course_post_test_attempts')->where('id', $id)->first();
    }

    private function finishExam(object $attempt, int $score, int $from = 0, int $until = 50): void
    {
        $questions = json_decode($attempt->questions, true);
        for ($index = $from; $index < $until; $index++) {
            $this->postJson(route('student.post-test.question'), [
                'attempt_id' => $attempt->id, 'question_index' => $index,
                'answer' => $index < $score ? $questions[$index]['correct'][0] : null,
            ])->assertOk();
        }
    }

    private function asStudent(Student $student): void
    {
        $this->withSession(['student_id' => $student->id, 'teacher_id' => null, 'post_test_code' => null]);
    }

    private function asTeacher(TeacherAccount $teacher): void
    {
        $this->withSession(['teacher_id' => $teacher->id, 'student_id' => null]);
    }
}
