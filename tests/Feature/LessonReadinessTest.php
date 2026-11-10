<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\LessonContent;
use App\Models\LessonProgress;
use App\Models\Student;
use App\Models\TeacherAccount;
use App\Services\CourseAssessments;
use App\Services\LessonService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LessonReadinessTest extends TestCase
{
    use RefreshDatabase;

    private function learner(): array
    {
        $teacher = TeacherAccount::create(['first_name' => 'Ready', 'last_name' => 'Teacher', 'school' => 'School', 'email' => 'readiness@example.test', 'password' => 'Password123']);
        $classroom = Classroom::create(['teacher_account_id' => $teacher->id, 'name' => 'Learning', 'grade' => 'Grade 9', 'section' => 'AM', 'join_code' => 'READY9']);
        $student = Student::create(['classroom_id' => $classroom->id, 'first_name' => 'Ready', 'nickname' => 'LearningCoder', 'student_id' => 'READY1', 'password' => 'Password123', 'xp' => 100]);
        $this->withSession(['student_id' => $student->id, 'student_class_verified' => true]);

        return [$teacher, $student];
    }

    private function completed(Student $student, string $assessment): void
    {
        DB::table('course_assessment_results')->insert(['student_id' => $student->id, 'assessment' => $assessment, 'score' => 0, 'total_questions' => 50, 'skill_scores' => '{}', 'completed_at' => now()]);
    }

    public function test_reasoning_is_required_for_both_course_readers_and_direct_writes_even_after_pretests(): void
    {
        [, $student] = $this->learner();
        $this->completed($student, CourseAssessments::PRE);
        $this->completed($student, CourseAssessments::ARDUINO_PRE);
        foreach (['cpp' => 'introduction', 'arduino-uno' => 'arduino-robotics'] as $course => $key) {
            $query = CourseAssessments::courseQuery($course);
            $target = route('student.assessments.show', ['reasoning'] + $query);
            $this->get(route('student.lesson.show', $key))->assertRedirect($target);
            $this->get(route('student.lesson', $query))->assertRedirect($target);
            foreach (['checkpoint', 'bookmark', 'support'] as $action) {
                $this->postJson(route('student.lesson.'.$action, $key), [])->assertForbidden()->assertJsonPath('redirect', $target)->assertJsonPath('message', 'Complete the Student Reasoning Challenge to unlock the lessons.');
            }
            $this->get(route('student.learn', $query))->assertOk()->assertSee('Reasoning challenge required')->assertViewHas('lessonPath', fn ($path) => $path['reasoning_required'] && $path['next'] === null && $path['cards']->every('locked'));
        }
        $this->get(route('student.dashboard'))->assertOk()->assertSee('Complete the Student Reasoning Challenge');
        $this->assertDatabaseEmpty('lesson_progress');
        $this->assertSame(100, $student->fresh()->xp);
    }

    public function test_one_valid_reasoning_submission_counts_for_both_courses_and_preserves_the_selected_course(): void
    {
        [, $student] = $this->learner();
        $service = app(CourseAssessments::class);
        $questions = $service->questions(CourseAssessments::REASONING);
        $answers = array_map(fn ($question) => $question['type'] === 'multiple_answer' ? $question['correct'] : $question['correct'][0], $questions);
        $query = ['course' => 'arduino-uno'];
        $show = route('student.assessments.show', ['reasoning'] + $query);
        $submit = route('student.assessments.submit', ['reasoning'] + $query);
        $this->postJson($submit, ['answers' => []])->assertUnprocessable();
        $this->assertDatabaseEmpty('course_assessment_results');
        $this->get($show)->assertOk()->assertSee('Student Reasoning Challenge')->assertSee($submit, false);
        $this->post($submit, ['answers' => $answers])->assertRedirect($show);
        $this->get($show)->assertOk()->assertSee('Starting requirement complete for both')->assertSee(route('student.assessments.show', 'arduino-pre-test'), false);
        $this->post($submit, ['answers' => $answers])->assertRedirect($show);
        $this->assertDatabaseCount('course_assessment_results', 1);
        $this->get(route('student.lesson.show', 'introduction'))->assertRedirect(route('student.assessments.show', 'pre-test'));
        $this->get(route('student.lesson.show', 'arduino-robotics'))->assertRedirect(route('student.assessments.show', 'arduino-pre-test'));
        $this->completed($student, CourseAssessments::PRE);
        $this->get(route('student.lesson.show', 'introduction'))->assertOk();
        $this->get(route('student.lesson.show', 'arduino-robotics'))->assertRedirect(route('student.assessments.show', 'arduino-pre-test'));
        $this->completed($student, CourseAssessments::ARDUINO_PRE);
        $this->get(route('student.lesson.show', 'arduino-robotics'))->assertOk();
        $this->assertDatabaseCount('course_assessment_results', 3);
        $this->assertSame(100, $student->fresh()->xp);
    }

    public function test_existing_zero_score_reasoning_results_count_without_resetting_saved_progress(): void
    {
        [, $student] = $this->learner();
        $this->completed($student, CourseAssessments::REASONING);
        $this->completed($student, CourseAssessments::PRE);
        $record = LessonProgress::create(['student_id' => $student->id, 'lesson_key' => 'introduction', 'started_at' => now(), 'last_opened_at' => now(), 'completed_at' => now(), 'best_score' => 5]);
        $this->get(route('student.lesson.show', 'introduction'))->assertOk();
        $this->assertTrue($record->fresh()->completed_at->equalTo($record->completed_at));
        $this->assertSame(5, $record->fresh()->best_score);
        $this->assertFalse(app(LessonService::class)->path($student)['reasoning_required']);
        $this->get(route('student.assessments.index'))->assertOk()->assertSee('✓ Completed')->assertSee('0 / 50');
        DB::table('course_assessment_results')->where('student_id', $student->id)->where('assessment', 'reasoning')->delete();
        $this->get(route('student.assessments.show', 'pre-test'))->assertOk()->assertSee('Complete the Student Reasoning Challenge to unlock lessons.')->assertDontSee('You can now open');
        $this->assertFalse(app(CourseAssessments::class)->canTakePostTest($student));
        $this->assertDatabaseHas('lesson_progress', ['id' => $record->id, 'best_score' => 5]);
    }

    public function test_course_choices_have_one_highlighted_current_link_on_assessments_learn_and_library(): void
    {
        [$teacher] = $this->learner();
        foreach (['student.assessments.index', 'student.learn', 'teacher.library'] as $route) {
            if ($route === 'teacher.library') {
                $this->withSession(['student_id' => null, 'teacher_id' => $teacher->id]);
            }
            foreach (['cpp', 'arduino-uno'] as $course) {
                $url = route($route, CourseAssessments::courseQuery($course));
                $response = $this->get($url)->assertOk()->assertSee('Arduino Uno Robotics')->assertDontSee('Grade 9 Arduino Uno')->assertDontSee('Grade 8 Reasoning Challenge');
                preg_match('~<nav aria-label="(?:Assessment course|Learning course|Library course)"[^>]*>(.*?)</nav>~s', $response->getContent(), $navigation);
                $this->assertNotEmpty($navigation);
                preg_match_all('~<a[^>]*aria-current="page"[^>]*>~s', $navigation[1], $selected);
                $this->assertCount(1, $selected[0]);
                $this->assertStringContainsString('href="'.$url.'"', $selected[0][0]);
                if ($preview = getenv('READINESS_PREVIEW')) {
                    file_put_contents($preview.'/'.str_replace('.', '-', $route).'-'.$course.'.html', $response->getContent());
                }
            }
        }
    }

    public function test_private_arduino_lesson_images_use_the_arduino_prerequisites(): void
    {
        [$teacher,$student] = $this->learner();
        Storage::fake('local');
        Storage::disk('local')->put('lesson-resource.png', 'test image');
        $content = LessonContent::create(['teacher_account_id' => $teacher->id, 'lesson_key' => 'arduino-robotics', 'definition' => config('arduino_curriculum.lessons.arduino-robotics'), 'status' => 'published', 'revision' => 1, 'image_path' => 'lesson-resource.png', 'image_mime' => 'image/png']);
        $image = route('student.lesson.image', $content);
        $this->getJson($image)->assertForbidden()->assertJsonPath('redirect', route('student.assessments.show', ['reasoning', 'course' => 'arduino-uno']));
        $this->completed($student, CourseAssessments::REASONING);
        $this->get($image)->assertRedirect(route('student.assessments.show', 'arduino-pre-test'));
        $this->completed($student, CourseAssessments::ARDUINO_PRE);
        $this->get($image)->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->assertFalse(app(CourseAssessments::class)->hasCompleted($student, CourseAssessments::PRE));
    }
}
