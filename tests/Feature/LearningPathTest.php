<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\CodeWorkspace;
use App\Models\LabSubmission;
use App\Models\LessonProgress;
use App\Models\Student;
use App\Models\TeacherAccount;
use App\Models\TeacherLessonPlan;
use App\Services\AchievementService;
use App\Services\LessonService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class LearningPathTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $teacher = TeacherAccount::create(['first_name' => 'Owner', 'last_name' => 'Teacher', 'school' => 'School', 'email' => 'lessons@example.test', 'password' => 'password123']);
        $outsider = TeacherAccount::create(['first_name' => 'Other', 'last_name' => 'Teacher', 'school' => 'Other', 'email' => 'other-lessons@example.test', 'password' => 'password123']);
        $room = Classroom::create(['teacher_account_id' => $teacher->id, 'name' => 'C++', 'grade' => 'Grade 8', 'section' => 'A', 'join_code' => 'LEARNA']);
        $other = Classroom::create(['teacher_account_id' => $teacher->id, 'name' => 'C++', 'grade' => 'Grade 8', 'section' => 'B', 'join_code' => 'LEARNB']);
        $foreign = Classroom::create(['teacher_account_id' => $outsider->id, 'name' => 'Private', 'grade' => 'Grade 8', 'section' => 'C', 'join_code' => 'LEARNC']);
        $student = Student::create(['classroom_id' => $room->id, 'first_name' => 'Alex', 'nickname' => 'MyLearner', 'student_id' => 'LEARN1', 'password' => 'password123', 'xp' => 100]);
        $peer = Student::create(['classroom_id' => $other->id, 'first_name' => 'Peer', 'nickname' => 'PeerLearner', 'student_id' => 'LEARN2', 'password' => 'password123', 'xp' => 100]);
        $stranger = Student::create(['classroom_id' => $foreign->id, 'first_name' => 'Private', 'nickname' => 'PrivateLearner', 'student_id' => 'LEARN3', 'password' => 'password123', 'xp' => 100]);
        $this->recordCompletedPretest($student, $peer, $stranger);
        $this->withSession(['teacher_id' => $teacher->id, 'student_id' => $student->id]);
        $payload = ['lesson_key' => 'output', 'classroom_ids' => [$room->id, $other->id], 'instructions' => 'Trace both lines before you try the checkpoint.', 'status' => 'published', 'due_at' => '2026-11-01T09:30'];

        return compact('teacher', 'outsider', 'room', 'other', 'foreign', 'student', 'peer', 'stranger', 'payload');
    }

    private function answers(string $key = 'output'): array
    {
        return ['request_key' => (string) Str::uuid(), 'answers' => array_column(config('lessons.'.$key.'.checks'), 'correct'), 'reflection' => 'I traced each statement and would test a different message.', 'confidence' => 'practising'];
    }

    public function test_guests_are_redirected_and_mutations_require_the_correct_role(): void
    {
        $this->get(route('student.learn'))->assertRedirect(route('student.login'));
        $this->get(route('student.lesson'))->assertRedirect(route('student.login'));
        $this->get(route('student.lesson.show', 'output'))->assertRedirect(route('student.login'));
        $this->get(route('teacher.lessons'))->assertRedirect(route('teacher.login'));
        $this->get(route('teacher.lessons.progress'))->assertRedirect(route('teacher.login'));
        $this->postJson(route('student.lesson.checkpoint', 'output'), $this->answers())->assertUnauthorized();
        $this->postJson(route('teacher.lessons.store'), [])->assertForbidden();
    }

    public function test_library_reader_and_bookmark_use_real_student_progress(): void
    {
        extract($this->fixture());
        $this->get(route('student.learn'))->assertOk()->assertSee('Your C++ learning path')->assertSee('C++ User Input')->assertSee('C++ Function Parameters')->assertViewHas('lessonPath', fn ($path) => $path['total'] === count(config('lessons')) && $path['completed'] === 0);
        $this->assertDatabaseCount('lesson_progress', 0);
        $this->get(route('student.lesson.show', 'missing'))->assertNotFound();
        $this->get(route('student.lesson.show', 'output'))->assertOk()->assertSee('Worked example')->assertDontSee('main is the program entry function in this example.')->assertDontSee('Compiling');
        $record = LessonProgress::firstOrFail();
        $this->assertSame($student->id, $record->student_id);
        $this->assertSame(0, $record->attempts);
        $this->post(route('student.lesson.bookmark', 'output'), ['section' => 2, 'student_id' => $peer->id])->assertRedirect(route('student.lesson.show', 'output').'#section-2');
        $this->get(route('student.lesson.show', 'output'))->assertOk()->assertViewHas('record', fn ($record) => $record->last_section === 2);
        $this->get(route('student.lesson'))->assertRedirect(route('student.lesson.show', 'output'));
        $this->postJson(route('student.lesson.bookmark', 'output'), ['section' => 10])->assertUnprocessable();
        $this->assertDatabaseCount('lesson_progress', 1);
    }

    public function test_checkpoint_is_server_graded_validated_and_awards_first_completion_xp_once(): void
    {
        extract($this->fixture());
        $this->post(route('student.lesson.checkpoint', 'output'), $this->answers())->assertNotFound();
        $this->get(route('student.lesson.show', 'output'))->assertOk();
        $this->postJson(route('student.lesson.checkpoint', 'output'), ['answers' => [0, 1], 'reflection' => 'short'])->assertUnprocessable()->assertJsonValidationErrors(['answers', 'reflection']);
        $this->postJson(route('student.lesson.checkpoint', 'output'), [...$this->answers(), 'answers' => [0, 1, 99]])->assertUnprocessable()->assertJsonValidationErrors('answers.2');
        $wrongAnswers = array_map(fn ($correct) => ($correct + 1) % 3, $this->answers()['answers']);
        $oneCorrect = $wrongAnswers;
        $oneCorrect[0] = $this->answers()['answers'][0];
        $this->post(route('student.lesson.checkpoint', 'output'), [...$this->answers(), 'answers' => $oneCorrect, 'score' => 5, 'completed_at' => now(), 'student_id' => $peer->id])->assertRedirect();
        $record = LessonProgress::firstOrFail();
        $this->assertSame(1, $record->latest_score);
        $this->assertNull($record->completed_at);
        $this->get(route('student.lesson.show', 'output'))->assertOk()->assertSee('Review this idea and try again.');
        $this->post(route('student.lesson.checkpoint', 'output'), $this->answers())->assertRedirect();
        $record->refresh();
        $this->assertSame(5, $record->best_score);
        $this->assertNotNull($record->completed_at);
        $this->assertSame(2, $record->attempts);
        $evidence = $record->completion_evidence;
        $completed = $record->completed_at->toIso8601String();
        $this->travel(1)->days();
        $this->post(route('student.lesson.checkpoint', 'output'), [...$this->answers(), 'answers' => $wrongAnswers])->assertRedirect();
        $record->refresh();
        $this->assertSame(0, $record->latest_score);
        $this->assertSame(5, $record->best_score);
        $this->assertSame($evidence, $record->completion_evidence);
        $this->assertSame($completed, $record->completed_at->toIso8601String());
        $this->assertSame(107, $student->fresh()->xp);
        $this->assertSame(0, app(AchievementService::class)->sync($student)['metrics']['quizzes_completed']);
        $this->get(route('student.learn'))->assertOk()->assertViewHas('lessonPath', fn ($path) => $path['completed'] === 1);
        $this->get(route('student.progress'))->assertOk()->assertSee('Your lesson learning path');
        $this->assertDatabaseCount('quiz_submissions', 0);
    }

    public function test_teacher_can_assign_multiple_sections_edit_publish_and_remove_without_deleting_learning(): void
    {
        extract($this->fixture());
        $this->get(route('teacher.lessons'))->assertOk()->assertSee('Lesson Studio')->assertSee('Preview lesson');
        $this->post(route('teacher.lessons.store'), $payload)->assertRedirect(route('teacher.lessons'));
        $this->assertDatabaseCount('teacher_lesson_plans', 2);
        $plan = TeacherLessonPlan::where('classroom_id', $room->id)->firstOrFail();
        $this->assertSame('2026-11-01 01:30', $plan->due_at->format('Y-m-d H:i'));
        $this->get(route('student.learn'))->assertOk()->assertSee($payload['instructions'])->assertViewHas('lessonPath', fn ($path) => $path['assigned'] === 1);
        $this->withSession(['student_id' => $peer->id])->get(route('student.learn'))->assertOk()->assertSee($payload['instructions']);
        $this->withSession(['student_id' => $stranger->id])->get(route('student.learn'))->assertOk()->assertDontSee($payload['instructions']);
        $this->withSession(['student_id' => $student->id]);
        $this->get(route('student.lesson.show', 'output'))->assertOk();
        $this->post(route('teacher.lessons.store'), $payload)->assertRedirect();
        $this->assertDatabaseCount('teacher_lesson_plans', 2);
        $this->put(route('teacher.lessons.update', $plan), ['status' => 'draft', 'instructions' => 'Private teaching note', 'due_at' => '', 'classroom_id' => $foreign->id])->assertRedirect();
        $this->assertSame($room->id, $plan->fresh()->classroom_id);
        $this->get(route('student.learn'))->assertDontSee('Private teaching note')->assertViewHas('lessonPath', fn ($path) => $path['assigned'] === 0);
        $this->put(route('teacher.lessons.update', $plan), ['status' => 'published', 'instructions' => 'Updated visible target', 'due_at' => '2020-01-01T08:00'])->assertRedirect();
        $this->get(route('student.lesson.show', 'output'))->assertOk()->assertSee('Updated visible target');
        $this->post(route('student.lesson.checkpoint', 'output'), $this->answers())->assertRedirect();
        $this->delete(route('teacher.lessons.destroy', $plan))->assertRedirect();
        $this->assertDatabaseHas('lesson_progress', ['student_id' => $student->id, 'best_score' => 5]);
        $this->get(route('student.learn'))->assertViewHas('lessonPath', fn ($path) => $path['completed'] === 1 && $path['assigned'] === 0);
    }

    public function test_teaching_permissions_and_student_feedback_are_private(): void
    {
        extract($this->fixture());
        $this->postJson(route('teacher.lessons.store'), [...$payload, 'classroom_ids' => [$foreign->id]])->assertUnprocessable()->assertJsonValidationErrors('classroom_ids.0');
        $this->post(route('teacher.lessons.store'), $payload)->assertRedirect();
        $plan = TeacherLessonPlan::firstOrFail();
        $this->get(route('student.lesson.show', 'output'))->assertOk();
        $this->post(route('student.lesson.checkpoint', 'output'), [...$this->answers(), 'confidence' => 'need_help', 'help_request' => 'Can we trace the newline together?'])->assertRedirect();
        $record = LessonProgress::firstOrFail();
        $this->get(route('teacher.lessons.progress'))->assertOk()->assertSee('MyLearner')->assertSee('Support requested')->assertSee('Can we trace the newline together?')->assertDontSee('PrivateLearner');
        $this->get(route('teacher.lessons.progress', ['search' => 'LEARN2']))->assertOk()->assertSee('PeerLearner')->assertDontSee('MyLearner');
        $this->get(route('teacher.lessons.progress', ['classroom' => $foreign->id]))->assertForbidden();
        $this->withSession(['teacher_id' => $outsider->id]);
        $this->putJson(route('teacher.lessons.update', $plan), ['status' => 'published'])->assertForbidden();
        $this->delete(route('teacher.lessons.destroy', $plan))->assertForbidden();
        $this->postJson(route('teacher.lessons.feedback', $record), ['teacher_feedback' => 'Foreign feedback blocked.'])->assertForbidden();
        $this->withSession(['teacher_id' => $teacher->id]);
        $this->post(route('teacher.lessons.feedback', $record), ['teacher_feedback' => 'Trace the first statement, then mark where the second output begins.'])->assertRedirect();
        $this->assertSame($teacher->id, $record->fresh()->feedback_by_teacher_id);
        $this->get(route('student.lesson.show', 'output'))->assertOk()->assertSee('Trace the first statement, then mark');
        $this->withSession(['student_id' => $peer->id])->get(route('student.lesson.show', 'output'))->assertOk()->assertDontSee('Can we trace the newline together?')->assertDontSee('Trace the first statement, then mark');
    }

    public function test_every_lesson_links_to_saved_practice_and_only_final_code_reviews_confirm_skills(): void
    {
        extract($this->fixture());
        foreach (config('lessons') as $key => $lesson) {
            $this->get(route('student.lesson.show', $key))->assertOk()->assertSee($lesson['title']);
            $this->get(route('student.code-lab', ['activity' => $lesson['practice']]))->assertOk();
        }
        $workspace = CodeWorkspace::where('activity_key', 'practice:greeting')->firstOrFail();
        $workspace->update(['code' => '// Existing student work']);
        $this->get(route('student.code-lab', ['activity' => 'greeting']))->assertSee('// Existing student work');
        $submission = LabSubmission::create(['code_workspace_id' => $workspace->id, 'teacher_account_id' => $teacher->id, 'revision' => 1, 'title' => 'My program', 'code' => 'int main(){}', 'reflection' => 'I tested the two printed lines.', 'rubric' => ['correctness' => 4, 'logic' => 4], 'score' => 8]);
        $path = app(LessonService::class)->path($student);
        $this->assertSame(0, $path['practice_confirmed']);
        $submission->update(['reviewed_at' => now()]);
        $this->assertSame(1, app(LessonService::class)->path($student)['practice_confirmed']);
        $this->assertSame(100, $student->fresh()->xp);
    }

    public function test_completed_lesson_days_contribute_to_learning_rhythm_once_without_quiz_badges(): void
    {
        extract($this->fixture());
        $this->travelTo(now()->startOfDay()->addHours(17));
        foreach (['output', 'variables', 'input'] as $key) {
            $this->get(route('student.lesson.show', $key))->assertOk();
            $this->post(route('student.lesson.checkpoint', $key), $this->answers($key))->assertRedirect();
            $this->travel(1)->days();
        }
        $report = app(AchievementService::class)->sync($student);
        $this->assertSame(3, $report['metrics']['active_days']);
        $this->assertTrue($report['cards']->firstWhere('code', 'learning_rhythm')['earned']);
        $this->assertFalse($report['cards']->firstWhere('code', 'perfect_quiz')['earned']);
        $this->assertSame(175, $student->fresh()->xp);
        $this->post(route('student.lesson.checkpoint', 'output'), $this->answers())->assertRedirect();
        $this->assertSame(3, app(AchievementService::class)->sync($student)['metrics']['active_days']);
    }

    public function test_student_can_ask_for_help_before_answering_and_user_text_is_escaped(): void
    {
        extract($this->fixture());
        $this->get(route('student.lesson.show', 'input'))->assertOk();
        $this->post(route('student.lesson.support', 'input'), ['help_request' => 'Why does <script>alert(1)</script> not read a value?'])->assertRedirect();
        $record = LessonProgress::firstOrFail();
        $this->assertSame('need_help', $record->confidence);
        $this->assertSame(0, $record->attempts);
        $this->assertNull($record->completed_at);
        $this->assertNull($record->answers);
        $this->get(route('student.lesson.show', 'input'))->assertOk()->assertDontSee('<script>alert(1)</script>', false)->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
        $this->get(route('teacher.lessons.progress'))->assertOk()->assertSee('Support requested')->assertDontSee('<script>alert(1)</script>', false);
        $this->assertSame(100, $student->fresh()->xp);
    }
}
