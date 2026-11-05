<?php

namespace Tests\Feature;

use App\Models\LearningNotification;
use App\Models\LessonContent;
use App\Models\TeacherAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class IlawPlanTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_topic_has_a_teacher_owned_worksheet_and_download(): void
    {
        $this->get(route('teacher.ilaw.edit', 'arrays'))->assertForbidden();
        $teacher = $this->staff('author');
        $this->asStaff($teacher);
        $this->get(route('teacher.library'))->assertOk()->assertSee(route('teacher.ilaw.edit', 'arrays'));
        foreach (config('lessons') as $key => $lesson) {
            $this->get(route('teacher.ilaw.edit', $key))->assertOk()->assertSee($lesson['title'])->assertSee('ILAW lesson plan')
                ->assertSee('I · Intentions')->assertSee('L · Learning Experience')->assertSee('A · Assessing Learning')->assertSee('W · Ways Forward');
            $this->get(route('teacher.ilaw.download', $key))->assertOk()->assertDownload('ilaw-'.$key.'.html')->assertSee($lesson['title']);
        }
        $this->assertDatabaseEmpty('ilaw_lesson_plans');
        LessonContent::create(['teacher_account_id' => $teacher->id, 'lesson_key' => 'custom-robot', 'status' => 'draft', 'revision' => 1,
            'definition' => ['title' => 'Robot practice', 'topic' => 'Robots']]);
        $this->get(route('teacher.ilaw.edit', 'custom-robot'))->assertOk()->assertSee('Robot practice');
        $this->asStaff($this->staff('peer'))->get(route('teacher.ilaw.download', 'custom-robot'))->assertNotFound();
    }

    public function test_principal_can_review_submitted_revisions_once_and_notify_only_the_author(): void
    {
        $author = $this->staff('author');
        $peer = $this->staff('peer');
        $principal = $this->staff('principal', 'principal');
        $foreign = $this->staff('foreign', 'principal', 'Other School');
        $save = route('teacher.ilaw.save', 'arrays');
        $edit = route('teacher.ilaw.edit', 'arrays');
        $download = route('teacher.ilaw.download', 'arrays');
        $data = [
            'title' => 'Trace a class inventory', 'learning_area' => 'C++ Programming', 'grade_section' => 'Grade 8 AM', 'sessions' => 1,
            'intentions' => 'Find the valid index for each item.', 'learning_experience' => 'Trace the example in pairs.',
            'assessing_learning' => 'Explain the highest valid index.', 'ways_forward' => 'Reteach indexes on paper when needed.',
        ];

        $this->asStaff($author)->putJson($save, ['revision' => 0, 'action' => 'submit'])->assertUnprocessable()->assertJsonValidationErrors('intentions');
        $this->put($save, $data + ['revision' => 0, 'action' => 'draft'])->assertRedirect($edit);
        $plan = DB::table('ilaw_lesson_plans')->first();
        $review = route('school.ilaw.show', $plan->id);
        $evaluate = route('school.ilaw.evaluate', $plan->id);
        $this->asStaff($principal)->get($review)->assertNotFound();
        $this->get($download)->assertForbidden();
        $this->putJson($save, $data + ['revision' => 1, 'action' => 'submit'])->assertForbidden();
        $this->asStaff($peer)->get($download)->assertOk()->assertDontSee($data['title']);
        $this->postJson($evaluate, ['revision' => 1])->assertForbidden();

        $this->asStaff($author)->put($save, $data + ['revision' => 1, 'action' => 'submit'])->assertRedirect($edit);
        $this->putJson($save, $data + ['revision' => 1, 'action' => 'draft'])->assertConflict();
        $this->asStaff($foreign)->get($review)->assertNotFound();
        $this->postJson($evaluate, ['revision' => 2])->assertNotFound();
        $this->asStaff($principal)->get(route('school.ilaw.index'))->assertOk()->assertSee($data['title']);
        $this->get($review)->assertOk()->assertSee($data['intentions'])->assertSee('Mark evaluated & notify teacher');
        $this->post($evaluate, ['revision' => 2, 'comment' => 'Add a two-item practice case.'])->assertRedirect($review);
        $this->postJson($evaluate, ['revision' => 2])->assertConflict();
        $this->assertDatabaseHas('ilaw_lesson_plans', ['id' => $plan->id, 'evaluated_by' => $principal->id, 'revision' => 3, 'status' => 'evaluated']);
        $this->assertDatabaseCount(LearningNotification::class, 1);
        $this->asStaff($author)->getJson(route('teacher.notifications'))->assertOk()->assertJsonPath('items.0.url', $edit);
        $this->get($download)->assertOk()->assertSee('Add a two-item practice case.');
        $this->put($save, $data + ['revision' => 3, 'action' => 'submit'])->assertRedirect($edit);
        $this->asStaff($principal)->post($evaluate, ['revision' => 4])->assertRedirect($review);
        $this->assertDatabaseCount(LearningNotification::class, 2);
        $this->assertDatabaseHas('ilaw_lesson_plans', ['id' => $plan->id, 'status' => 'evaluated', 'review_comment' => null]);
    }

    private function staff(string $name, string $role = 'teacher', string $school = 'Test School'): TeacherAccount
    {
        $staff = new TeacherAccount(['first_name' => ucfirst($name), 'last_name' => 'Example', 'school' => $school, 'email' => $name.'@example.test', 'password' => 'Password123']);
        $staff->forceFill(['role' => $role])->save();

        return $staff;
    }

    private function asStaff(TeacherAccount $staff): self
    {
        return $this->withSession(['teacher_id' => $staff->id, 'teacher_session_version' => $staff->session_version]);
    }
}
