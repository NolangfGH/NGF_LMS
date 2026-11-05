<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Student;
use App\Models\TeacherAccount;
use App\Services\CourseAssessments;
use App\Services\QuizGrader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CourseAssessmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_pretest_is_required_on_the_reader_and_direct_writes_until_every_answer_is_submitted(): void
    {
        [$teacher, $student] = $this->classroom();
        $pretest = route('student.assessments.show', CourseAssessments::PRE);
        $intro = route('student.lesson.show', 'introduction');
        $this->get($pretest)->assertRedirect(route('student.login'));
        $this->withSession(['student_id' => $student->id])->get(route('student.learn'))->assertOk()->assertSee('Take the C++ pre-test')
            ->assertViewHas('lessonPath', fn (array $path): bool => $path['next'] === null && $path['cards']->every('locked'));
        $this->get($intro)->assertRedirect($pretest);
        $this->get(route('student.lesson'))->assertRedirect($pretest);
        $this->postJson(route('student.lesson.checkpoint', 'introduction'), [])->assertForbidden();
        $this->assertDatabaseEmpty('lesson_progress');

        $this->get($pretest)->assertOk()->assertSee('Answer all 50 questions')->assertViewHas('questions', fn (array $items): bool => ! isset($items[0]['correct']));
        $submit = route('student.assessments.submit', CourseAssessments::PRE);
        $this->postJson($submit, ['answers' => ['made up choice']])->assertUnprocessable();
        $this->assertDatabaseEmpty('course_assessment_results');
        $questions = app(CourseAssessments::class)->questions(CourseAssessments::PRE);
        $wrong = array_map(fn (array $question): string => array_values(array_diff($question['options'], $question['correct']))[0], $questions);
        $this->post($submit, ['answers' => $wrong])->assertRedirect($pretest);
        $this->get($pretest)->assertOk()->assertSee('0 / 50 correct')->assertSee('You can now open the C++ lessons');
        $this->get($intro)->assertOk();
        $this->post($submit, ['answers' => $this->answers(CourseAssessments::PRE)])->assertRedirect($pretest);
        $this->assertDatabaseHas('course_assessment_results', ['student_id' => $student->id, 'assessment' => CourseAssessments::PRE, 'score' => 0]);
        $this->assertDatabaseCount('course_assessment_results', 1);
        $this->assertSame(100, $student->fresh()->xp);
    }

    public function test_legacy_completion_certificate_stays_private_printable_and_editable_by_its_teacher(): void
    {
        [$teacher, $student, $peer] = $this->classroom();
        $print = route('student.assessments.certificate');
        $certificate = DB::table('course_certificates')->insertGetId([
            'student_id' => $student->id, 'teacher_account_id' => $teacher->id, 'certificate_code' => 'LEGACY-CERTIFICATE',
            'recipient' => 'Test Cadet', 'school' => $teacher->school, 'issuer' => $teacher->fullName(),
            'title' => 'Original course certificate', 'message' => 'Completed the course.', 'issued_at' => now(),
        ]);
        $this->withSession(['student_id' => $student->id])->get($print)->assertOk()->assertSee('LEGACY-CERTIFICATE')->assertSee('Print / Save as PDF');

        $edit = route('teacher.certificates.edit', $student);
        $update = route('teacher.certificates.update', $student);
        $payload = ['certificate_id' => $certificate, 'title' => 'C++ Course Completion', 'message' => 'Completed the core C++ learning path.', 'revision' => 1, 'recipient' => 'Forged name'];
        $this->withSession(['student_id' => $peer->id])->get($print)->assertNotFound();
        $this->putJson($update, $payload)->assertForbidden();
        $this->withSession(['student_id' => null, 'teacher_id' => $this->staff('other-course')->id])->get($edit)->assertNotFound();
        $this->withSession(['teacher_id' => $teacher->id])->get($edit)->assertOk()->assertSee('Save certificate wording');
        $this->put($update, $payload)->assertRedirect($edit);
        $this->putJson($update, $payload)->assertConflict();
        $this->get(route('teacher.certificates.print', $student))->assertOk()->assertSee($payload['title'])->assertDontSee('Forged name');
        $this->withSession(['student_id' => $student->id, 'teacher_id' => null])->get($print)->assertOk()->assertSee($payload['message']);
    }

    public function test_reasoning_has_fifty_varied_questions_and_private_criterion_referenced_results(): void
    {
        [, $student, $peer] = $this->classroom();
        $assessments = app(CourseAssessments::class);
        $prompts = [];
        foreach ([CourseAssessments::PRE => 50, CourseAssessments::POST => 50, CourseAssessments::REASONING => 50] as $kind => $count) {
            $questions = $assessments->questions($kind);
            $this->assertCount($count, $questions);
            $this->assertSame($count, app(QuizGrader::class)->grade($questions, $this->answers($kind))['score']);
            foreach ($questions as $question) {
                if ($question['options'] !== []) {
                    $this->assertSame([], array_diff($question['correct'], $question['options']));
                    $this->assertSame($question['options'], array_values(array_unique($question['options'])));
                }
            }
            array_push($prompts, ...array_column($questions, 'prompt'));
        }
        $this->assertCount(150, array_unique($prompts));
        $questions = $assessments->questions(CourseAssessments::REASONING);
        $this->assertCount(5, array_unique(array_column($questions, 'skill')));
        $this->assertEqualsCanonicalizing(['multiple_choice', 'identification', 'multiple_answer', 'true_false'], array_values(array_unique(array_column($questions, 'type'))));

        $reasoning = route('student.assessments.show', CourseAssessments::REASONING);
        $submit = route('student.assessments.submit', CourseAssessments::REASONING);
        $this->withSession(['student_id' => $student->id])->get($reasoning)->assertOk()->assertSee('Answer all 50 questions');
        $answers = $this->answers(CourseAssessments::REASONING);
        $firstMulti = array_search('multiple_answer', array_column($questions, 'type'), true);
        $answers[$firstMulti] = [$answers[$firstMulti][0]];
        $this->post($submit, ['answers' => $answers])->assertRedirect($reasoning);
        $this->get($reasoning)->assertOk()->assertSee('49 / 50 correct')->assertSee('Quantitative reasoning')->assertSee('9 / 10')
            ->assertSee('Ready for a stretch task');
        $this->assertSame('Start with guided examples', CourseAssessments::practiceBand(3, 10));
        $this->assertSame('Developing with practice', CourseAssessments::practiceBand(4, 10));
        $this->assertSame('Secure on these items', CourseAssessments::practiceBand(7, 10));
        $this->assertSame(100, $student->fresh()->xp);
        $this->get(route('student.lesson.show', 'introduction'))->assertRedirect(route('student.assessments.show', CourseAssessments::PRE));
        $this->withSession(['student_id' => $peer->id])->get($reasoning)->assertOk()->assertDontSee('49 / 50 correct');
    }

    private function answers(string $assessment): array
    {
        return array_map(fn (array $question): string|array => $question['type'] === 'multiple_answer' ? $question['correct'] : $question['correct'][0],
            app(CourseAssessments::class)->questions($assessment));
    }

    private function classroom(): array
    {
        $teacher = $this->staff('teacher-course');
        $room = Classroom::create(['teacher_account_id' => $teacher->id, 'name' => 'C++', 'grade' => 'Grade 8', 'section' => 'AM', 'join_code' => 'COURSE8']);
        $student = Student::create(['classroom_id' => $room->id, 'first_name' => 'Test', 'surname' => 'Cadet', 'nickname' => 'CppCadet', 'student_id' => 'COURSE001', 'password' => 'Password123', 'xp' => 100]);
        $peer = Student::create(['classroom_id' => $room->id, 'first_name' => 'Peer', 'nickname' => 'AnotherCadet', 'student_id' => 'COURSE002', 'password' => 'Password123', 'xp' => 100]);

        return [$teacher, $student, $peer];
    }

    private function staff(string $name): TeacherAccount
    {
        return TeacherAccount::create(['first_name' => 'Course', 'last_name' => 'Teacher', 'school' => 'Regional Science High School', 'email' => $name.'@example.test', 'password' => 'Password123']);
    }
}
