<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\CodeWorkspace;
use App\Models\CodingMatch;
use App\Models\LabSubmission;
use App\Models\LessonProgress;
use App\Models\Student;
use App\Models\TeacherAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class TeacherStudentsTest extends TestCase
{
    use RefreshDatabase;

    private TeacherAccount $teacher;

    private TeacherAccount $other;

    private array $rooms;

    private array $students;

    private Student $foreign;

    protected function setUp(): void
    {
        parent::setUp();
        $this->teacher = TeacherAccount::create(['first_name' => 'Owner', 'last_name' => 'Teacher', 'email' => 'roster@example.test', 'school' => 'School', 'password' => 'password123']);
        $this->other = TeacherAccount::create(['first_name' => 'Other', 'last_name' => 'Teacher', 'email' => 'foreign-roster@example.test', 'school' => 'School', 'password' => 'password123']);
        foreach (['AM', 'BP', 'DB'] as $index => $section) {
            $room = Classroom::create(['teacher_account_id' => $this->teacher->id, 'name' => 'C++ Programming', 'grade' => 'Grade 8', 'section' => $section, 'join_code' => 'ROSTER'.$section]);
            $this->rooms[$index] = $room;
            $this->students[$index] = Student::create(['classroom_id' => $room->id, 'first_name' => 'Learner'.$section, 'middle_name' => 'Middle', 'surname' => 'Surname'.$section, 'nickname' => 'Coder'.$section,
                'student_id' => '000'.$index, 'password' => 'password123', 'xp' => 100, 'level' => 1, 'grade_level' => 'Grade 8', 'section' => $section]);
        }
        $room = Classroom::create(['teacher_account_id' => $this->other->id, 'name' => 'Private classroom', 'grade' => 'Grade 9', 'section' => 'FOREIGN', 'join_code' => 'FOREIGNROSTER']);
        $this->foreign = Student::create(['classroom_id' => $room->id, 'first_name' => 'Private', 'surname' => 'Person', 'nickname' => 'PrivateCoder', 'student_id' => 'PRIVATE01', 'password' => 'password123']);
    }

    private function asTeacher(?TeacherAccount $teacher = null): self
    {
        return $this->withSession(['teacher_id' => ($teacher ?? $this->teacher)->id, 'teacher_session_version' => 1, 'student_id' => null]);
    }

    private function asStudent(Student $student, ?int $version = null): self
    {
        return $this->withSession(['teacher_id' => null, 'student_id' => $student->id, 'student_session_version' => $version ?? $student->session_version, 'student_class_verified' => true]);
    }

    private function payload(array $changes = []): array
    {
        return array_replace(['first_name' => 'New', 'middle_name' => 'Middle', 'surname' => 'Student', 'nickname' => 'NewCoder', 'student_id' => 'new001',
            'classroom_id' => $this->rooms[0]->id, 'password' => 'password123', 'password_confirmation' => 'password123'], $changes);
    }

    private function updateData(Student $student, array $changes = []): array
    {
        return array_replace($this->payload(['student_id' => $student->student_id, 'first_name' => $student->first_name, 'surname' => $student->surname, 'nickname' => $student->nickname,
            'classroom_id' => $student->classroom_id, 'password' => '', 'password_confirmation' => '']), ['revision' => $student->roster_revision], $changes);
    }

    private function remove(Student $student)
    {
        return $this->asTeacher()->delete(route('teacher.students.destroy', $student->id), ['revision' => $student->roster_revision, 'confirm_student_id' => $student->student_id, 'confirmed' => 1]);
    }

    public function test_all_three_sections_are_visible_with_correct_counts_search_filters_and_navigation(): void
    {
        $this->get(route('teacher.students'))->assertRedirect(route('teacher.login'));
        $this->asTeacher()->get(route('teacher.students'))->assertOk()->assertSee('Students')->assertSee('LearnerAM')->assertSee('LearnerBP')->assertSee('LearnerDB')->assertDontSee('PrivateCoder')
            ->assertViewHas('metrics', fn ($metrics) => $metrics['active'] === 3 && $metrics['sections'] === 3);
        $this->get(route('teacher.students', ['classroom' => $this->rooms[1]->id]))->assertOk()->assertSee('LearnerBP')->assertDontSee('LearnerAM')->assertDontSee('LearnerDB');
        $this->get(route('teacher.students', ['q' => '0002']))->assertOk()->assertSee('LearnerDB')->assertDontSee('LearnerAM');
        $this->get(route('teacher.students', ['q' => 'Middle', 'sort' => 'xp']))->assertOk()->assertViewHas('students', fn ($students) => $students->total() === 3);
        $this->get(route('teacher.dashboard'))->assertOk()->assertSee(route('teacher.students'), false)->assertSee('LearnerAM')->assertSee('LearnerBP')->assertSee('LearnerDB')->assertViewHas('registeredUsers', 3);
        $this->getJson(route('teacher.students.status', ['classroom' => $this->foreign->classroom_id]))->assertUnprocessable();
    }

    public function test_teacher_can_create_an_account_with_hashed_credentials_and_one_welcome_award_in_a_paused_section(): void
    {
        $this->rooms[0]->update(['enrollment_open' => false]);
        $this->asTeacher()->post(route('teacher.students.store'), $this->payload(['xp' => 99999, 'level' => 99, 'teacher_account_id' => $this->other->id, 'grade_level' => 'Grade 12', 'section' => 'Injected']))->assertRedirect();
        $student = Student::where('student_id', 'NEW001')->firstOrFail();
        $this->assertTrue(Hash::check('password123', $student->password));
        $this->assertNotSame('password123', $student->password);
        $this->assertSame(100, $student->xp);
        $this->assertSame(1, $student->level);
        $this->assertSame('Grade 8', $student->grade_level);
        $this->assertSame('AM', $student->section);
        $this->get(route('teacher.students.show', $student->id))->assertOk()->assertDontSee($student->password, false);
        $this->withSession(['teacher_id' => null])->postJson(route('student.login.store'), ['student_id' => 'new001', 'password' => 'password123'])->assertOk();
        $this->get(route('student.dashboard'))->assertOk();
    }

    public function test_validation_blocks_invalid_accounts_foreign_sections_duplicate_ids_and_malformed_inputs(): void
    {
        $this->asTeacher()->postJson(route('teacher.students.store'), $this->payload(['classroom_id' => $this->foreign->classroom_id]))->assertUnprocessable();
        foreach ([['surname' => ''], ['student_id' => '0000'], ['password_confirmation' => 'different'], ['password' => 'short', 'password_confirmation' => 'short'], ['student_id' => ['bad']], ['first_name' => str_repeat('a', 81)]] as $change) {
            $this->postJson(route('teacher.students.store'), $this->payload($change))->assertUnprocessable();
        }
        $this->assertDatabaseCount('students', 4);
    }

    public function test_profile_updates_move_sections_and_preserve_password_xp_and_saved_code(): void
    {
        $student = $this->students[0];
        $hash = $student->password;
        CodeWorkspace::create(['student_id' => $student->id, 'activity_key' => 'practice:greeting', 'title' => 'Saved solution', 'code' => 'int main() {}']);
        $this->asTeacher()->get(route('teacher.students.edit', $student->id))->assertOk()->assertSee('Save student changes')->assertDontSee($hash, false);
        $this->put(route('teacher.students.update', $student->id), $this->updateData($student, ['first_name' => 'Updated', 'classroom_id' => $this->rooms[2]->id, 'xp' => 9000, 'level' => 99, 'section' => 'Wrong']))->assertRedirect();
        $student->refresh();
        $this->assertSame('Updated', $student->first_name);
        $this->assertSame('DB', $student->section);
        $this->assertSame($hash, $student->password);
        $this->assertSame(100, $student->xp);
        $this->assertSame(1, $student->level);
        $this->assertSame(2, $student->roster_revision);
        $this->assertSame(2, $student->session_version);
        $this->assertDatabaseHas('code_workspaces', ['student_id' => $student->id, 'code' => 'int main() {}']);
        $this->asStudent($student, 1)->get(route('student.learn'))->assertRedirect()->assertSessionMissing('student_id');
    }

    public function test_teacher_password_reset_and_student_id_change_revoke_old_credentials_and_sessions(): void
    {
        $student = $this->students[0];
        $this->asTeacher()->put(route('teacher.students.update', $student->id), $this->updateData($student, ['student_id' => ' changed001 ', 'password' => 'NewPassword123', 'password_confirmation' => 'NewPassword123']))->assertRedirect();
        $student->refresh();
        $this->assertSame('CHANGED001', $student->student_id);
        $this->assertTrue(Hash::check('NewPassword123', $student->password));
        $this->assertNotNull($student->password_changed_at);
        $this->withSession(['teacher_id' => null])->postJson(route('student.login.store'), ['student_id' => '0000', 'password' => 'password123'])->assertUnprocessable();
        $this->postJson(route('student.login.store'), ['student_id' => 'CHANGED001', 'password' => 'password123'])->assertUnprocessable();
        $this->postJson(route('student.login.store'), ['student_id' => 'CHANGED001', 'password' => 'NewPassword123'])->assertOk();
        $this->asTeacher()->putJson(route('teacher.students.update', $student->id), $this->updateData($student, ['password' => '', 'password_confirmation' => 'Something123']))->assertUnprocessable();
    }

    public function test_all_read_and_write_endpoints_require_the_owning_teacher(): void
    {
        $student = $this->students[0];
        $this->asStudent($student)->postJson(route('teacher.students.store'), $this->payload())->assertForbidden();
        $this->putJson(route('teacher.students.update', $student->id), $this->updateData($student))->assertForbidden();
        $this->asTeacher($this->other)->get(route('teacher.students.show', $student->id))->assertNotFound();
        $this->get(route('teacher.students.edit', $student->id))->assertNotFound();
        $this->putJson(route('teacher.students.update', $student->id), $this->updateData($student))->assertNotFound();
        $this->deleteJson(route('teacher.students.destroy', $student->id), [])->assertNotFound();
        $this->postJson(route('teacher.students.restore', $student->id), ['revision' => 1])->assertNotFound();
        $this->getJson(route('teacher.students.status'))->assertOk()->assertDontSee('LearnerAM');
        $csv = $this->get(route('teacher.students.export'))->assertOk()->streamedContent();
        $this->assertStringNotContainsString('LearnerAM', $csv);
    }

    public function test_delete_blocks_login_retains_work_and_restore_does_not_award_xp_again(): void
    {
        $student = $this->students[0];
        $workspace = CodeWorkspace::create(['student_id' => $student->id, 'activity_key' => 'practice:greeting', 'title' => 'Kept work', 'code' => 'int main() {}']);
        $submission = LabSubmission::create(['code_workspace_id' => $workspace->id, 'teacher_account_id' => $this->teacher->id, 'revision' => 1, 'title' => 'Kept work', 'code' => 'int main() {}', 'reflection' => 'I tested the program.']);
        $this->remove($student)->assertRedirect(route('teacher.students'));
        $this->assertSoftDeleted($student);
        $this->assertDatabaseHas('lab_submissions', ['id' => $submission->id]);
        $this->assertNotNull($workspace->fresh()->student);
        $this->get(route('teacher.students'))->assertOk()->assertDontSee('LearnerAM');
        $this->get(route('teacher.students', ['status' => 'deleted']))->assertOk()->assertSee('LearnerAM')->assertSee('Restore student');
        $this->get(route('teacher.code-lab'))->assertOk()->assertSee('Kept work')->assertSee('This student account is deleted');
        $this->get(route('teacher.students.show', $student->id))->assertOk()->assertSee('login blocked');
        $this->asStudent($student, 1)->get(route('student.learn'))->assertRedirect()->assertSessionMissing('student_id');
        $this->postJson(route('student.login.store'), ['student_id' => $student->student_id, 'password' => 'password123'])->assertUnprocessable();
        $this->asTeacher()->postJson(route('teacher.students.store'), $this->payload(['student_id' => $student->student_id]))->assertUnprocessable();
        $deleted = Student::withTrashed()->findOrFail($student->id);
        $this->post(route('teacher.students.restore', $student->id), ['revision' => $deleted->roster_revision])->assertRedirect();
        $student = Student::findOrFail($student->id);
        $this->assertSame(100, $student->xp);
        $this->assertSame(3, $student->session_version);
        $this->asStudent($student, 1)->get(route('student.learn'))->assertRedirect();
        $this->postJson(route('student.login.store'), ['student_id' => $student->student_id, 'password' => 'password123'])->assertOk();
    }

    public function test_delete_closes_open_two_student_matches_without_destroying_drafts_or_awarding_xp(): void
    {
        $match = CodingMatch::create(['teacher_account_id' => $this->teacher->id, 'title' => 'Retained match', 'instructions' => 'Print a greeting.', 'sample_output' => 'Hi', 'time_limit_minutes' => 5, 'winner_xp' => 100]);
        foreach ([$this->students[0], $this->students[1]] as $slot => $student) {
            $match->participants()->create(['slot' => $slot + 1, 'student_id' => $student->id, 'student_name' => $student->nickname, 'section_label' => $student->section, 'draft_code' => '// retained draft']);
        }
        $this->remove($this->students[0])->assertRedirect();
        $this->assertSame('cancelled', $match->fresh()->status);
        $this->assertSame(0, $match->fresh()->xp_awarded);
        $this->assertDatabaseHas('coding_match_participants', ['coding_match_id' => $match->id, 'draft_code' => '// retained draft']);
        $this->assertDatabaseHas('challenge_notices', ['coding_match_id' => $match->id, 'student_id' => $this->students[1]->id, 'event_key' => 'cancelled']);
    }

    public function test_stale_edits_and_unconfirmed_deletion_do_not_change_accounts(): void
    {
        $student = $this->students[0];
        $payload = $this->updateData($student, ['first_name' => 'Current']);
        $this->asTeacher()->put(route('teacher.students.update', $student->id), $payload)->assertRedirect();
        $this->putJson(route('teacher.students.update', $student->id), [...$payload, 'first_name' => 'Stale overwrite'])->assertUnprocessable()->assertJsonValidationErrors('revision');
        $this->deleteJson(route('teacher.students.destroy', $student->id), ['revision' => 2, 'confirmed' => 1, 'confirm_student_id' => 'WRONG'])->assertUnprocessable();
        $this->deleteJson(route('teacher.students.destroy', $student->id), ['revision' => 2, 'confirm_student_id' => $student->student_id])->assertUnprocessable();
        $this->assertSame('Current', $student->fresh()->first_name);
        $this->assertFalse($student->fresh()->trashed());
    }

    public function test_live_roster_progress_and_counts_use_saved_learning_records(): void
    {
        $student = $this->students[0];
        LessonProgress::create(['student_id' => $student->id, 'lesson_key' => 'output', 'started_at' => now()->subMinute(), 'last_opened_at' => now()->subMinute(), 'confidence' => 'need_help', 'help_request' => 'I need help with output statements.']);
        $this->asTeacher()->getJson(route('teacher.students.status'))->assertOk()->assertSee('Needs support')->assertSee('LearnerAM')->assertDontSee('PrivateCoder');
        $this->get(route('teacher.students', ['progress' => 'support']))->assertOk()->assertSee('LearnerAM')->assertDontSee('LearnerBP');
        $this->get(route('teacher.students.show', $student->id))->assertOk()->assertSee('Help requested:')->assertSee('Saved learning progress')->assertDontSee($student->password, false);
        $this->post(route('teacher.students.store'), $this->payload())->assertRedirect();
        $this->getJson(route('teacher.students.status'))->assertOk()->assertSee('NEW001')->assertSee('0000');
    }

    public function test_csv_exports_all_filtered_pages_protects_formulas_and_never_exports_passwords(): void
    {
        $hash = Hash::make('password123');
        for ($i = 0; $i < 28; $i++) {
            Student::create(['classroom_id' => $this->rooms[0]->id, 'first_name' => $i === 0 ? '=DANGEROUS()' : 'Bulk'.$i, 'surname' => 'Learner', 'nickname' => 'BulkCoder'.$i, 'student_id' => 'BULK'.$i, 'password' => $hash]);
        }
        $response = $this->asTeacher()->get(route('teacher.students.export', ['classroom' => $this->rooms[0]->id, 'per_page' => 25, 'page' => 2]))->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $csv = $response->streamedContent();
        $this->assertSame(30, count(explode("\n", trim($csv))));
        $this->assertStringContainsString("'=DANGEROUS()", $csv);
        $this->assertStringContainsString('BULK27', $csv);
        $this->assertStringNotContainsString('PRIVATE01',$csv);
        $this->assertStringNotContainsString('LearnerBP',$csv);
        $this->assertStringNotContainsString($hash,$csv);
        $html = $this->getJson(route('teacher.students.status',['per_page' => 25]))->assertOk()->json('fragments.table');
        $this->assertStringContainsString(route('teacher.students',['per_page' => 25, 'page' => 2]),html_entity_decode($html));
        $this->assertStringNotContainsString('/teacher-students/status?',$html);
    }
}
