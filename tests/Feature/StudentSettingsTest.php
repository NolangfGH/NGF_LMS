<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\CodeWorkspace;
use App\Models\LabSubmission;
use App\Models\LessonProgress;
use App\Models\Student;
use App\Models\StudentAchievement;
use App\Models\TeacherAccount;
use App\Models\TeacherAssignment;
use App\Models\TeacherQuiz;
use App\Services\StudentSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class StudentSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $teacher = TeacherAccount::create(['first_name' => 'Owner', 'last_name' => 'Teacher', 'school' => 'Our School', 'email' => 'student-settings@example.test', 'password' => 'password123']);
        $outsider = TeacherAccount::create(['first_name' => 'Private', 'last_name' => 'Teacher', 'school' => 'Private School', 'email' => 'other-student-settings@example.test', 'password' => 'password123']);
        $room = Classroom::create(['teacher_account_id' => $teacher->id, 'name' => 'C++', 'grade' => 'Grade 8', 'section' => 'A', 'join_code' => 'SSETTA']);
        $other = Classroom::create(['teacher_account_id' => $teacher->id, 'name' => 'C++', 'grade' => 'Grade 8', 'section' => 'B', 'join_code' => 'SSETTB']);
        $foreign = Classroom::create(['teacher_account_id' => $outsider->id, 'name' => 'PrivateClass', 'grade' => 'Grade 8', 'section' => 'C', 'join_code' => 'SSETTC']);
        $student = Student::create(['classroom_id' => $room->id, 'first_name' => 'Alex', 'surname' => 'Student', 'nickname' => 'MyCoder', 'student_id' => 'SSETT1', 'password' => 'password123', 'avatar' => 'Astro Bot', 'xp' => 100]);
        $peer = Student::create(['classroom_id' => $other->id, 'first_name' => 'Peer', 'nickname' => 'PeerCoder', 'student_id' => 'SSETT2', 'password' => 'password123', 'xp' => 200]);
        $stranger = Student::create(['classroom_id' => $foreign->id, 'first_name' => 'Private', 'nickname' => 'PrivateCoder', 'student_id' => 'SSETT3', 'password' => 'password123']);
        $this->withSession(['student_id' => $student->id, 'student_session_version' => 1, 'student_class_verified' => true, 'teacher_id' => $teacher->id, 'teacher_session_version' => 1]);

        return compact('teacher', 'room', 'other', 'foreign', 'student', 'peer', 'stranger');
    }

    public function test_student_settings_require_student_auth_and_show_real_owned_account_data(): void
    {
        $this->get(route('student.settings'))->assertRedirect(route('student.login'));
        $this->putJson(route('student.settings.profile'), ['nickname' => 'NewCoder'])->assertUnauthorized();
        extract($this->fixture());
        $this->get(route('student.settings'))->assertOk()->assertSee('My Settings')->assertSee('MyCoder')->assertSee('SSETT1')->assertSee('Our School')
            ->assertDontSee('PrivateCoder')->assertDontSee('PrivateClass')->assertDontSee($student->password, false)
            ->assertDontSee('State Simulator')->assertDontSee('Gems')->assertDontSee('FERPA &amp; COPPA');
        $this->withSession(['student_id' => null])->get(route('student.settings'))->assertRedirect(route('student.login'));
    }

    public function test_nickname_can_change_but_identity_class_xp_and_other_students_are_immutable(): void
    {
        extract($this->fixture());
        $originalUpdated = $student->updated_at->toDateTimeString();
        $this->travel(1)->days();
        $this->put(route('student.settings.profile'), ['nickname' => 'New-Coder', 'student_id' => $peer->id, 'classroom_id' => $foreign->id, 'xp' => 99999, 'first_name' => 'Fake', 'level' => 99])->assertRedirect();
        $student->refresh();
        $this->assertSame('New-Coder', $student->nickname);
        $this->assertSame('SSETT1', $student->student_id);
        $this->assertSame($room->id, $student->classroom_id);
        $this->assertSame('Alex', $student->first_name);
        $this->assertSame(100, $student->xp);
        $this->assertSame($originalUpdated, $student->updated_at->toDateTimeString());
        $this->assertSame('PeerCoder', $peer->fresh()->nickname);
        $this->putJson(route('student.settings.profile'), ['nickname' => '<script>alert(1)</script>'])->assertUnprocessable();
        $this->putJson(route('student.settings.profile'), ['nickname' => 'ab'])->assertUnprocessable();
        $this->putJson(route('student.settings.profile'), ['nickname' => '-----'])->assertUnprocessable();
        $this->get(route('student.dashboard'))->assertOk()->assertSee('New-Coder');
    }

    public function test_avatar_unlocks_are_server_checked_free_and_reflect_in_student_pages(): void
    {
        extract($this->fixture());
        $this->putJson(route('student.settings.avatar'), ['avatar' => 'Fox', 'xp' => 9999])->assertUnprocessable()->assertJsonValidationErrors('avatar');
        $this->put(route('student.settings.avatar'), ['avatar' => 'Solar'])->assertRedirect();
        $this->assertSame(100, $student->fresh()->xp);
        $this->assertSame('Solar', $student->fresh()->avatar);
        $this->get(route('student.learn'))->assertOk()->assertSee('Solar avatar');
        $student->update(['xp' => 600, 'level' => 2]);
        $this->put(route('student.settings.avatar'), ['avatar' => 'Fox'])->assertRedirect();
        $this->assertSame(600, $student->fresh()->xp);
        $this->putJson(route('student.settings.avatar'), ['avatar' => 'Code Wizard'])->assertUnprocessable();
        StudentAchievement::create(['student_id' => $student->id, 'badge_code' => 'problem_solver', 'earned_at' => now(), 'evidence' => []]);
        $this->put(route('student.settings.avatar'), ['avatar' => 'Code Wizard'])->assertRedirect();
        $this->assertSame('Code Wizard', $student->fresh()->avatar);
        $this->assertSame(600, $student->fresh()->xp);
        $this->get(route('student.settings'))->assertOk()->assertSee('Code Wizard');
        $this->putJson(route('student.settings.avatar'), ['avatar' => 'unknown'])->assertUnprocessable();
    }

    public function test_registration_cannot_bypass_avatar_learning_requirements(): void
    {
        extract($this->fixture());
        $room->update(['section' => 'AM']);
        $this->postJson(route('student.register.verify'), ['classroom_id' => $room->id, 'grade_level' => 'Grade 8', 'class_code' => $room->join_code])->assertOk();
        $payload = ['first_name' => 'New', 'surname' => 'Student', 'nickname' => 'NewCoder', 'student_id' => 'SNEW1', 'password' => 'password123', 'password_confirmation' => 'password123', 'classroom_id' => $room->id, 'grade_level' => 'Grade 8', 'avatar' => 'Code Wizard'];
        $this->postJson(route('student.register.store'), $payload)->assertUnprocessable()->assertJsonValidationErrors('avatar');
        $this->postJson(route('student.register.store'), [...$payload, 'avatar' => 'Byte Owl'])->assertOk();
    }

    public function test_display_and_notification_preferences_merge_and_apply_without_changing_rewards(): void
    {
        extract($this->fixture());
        $this->put(route('student.settings.display'), ['text_size' => 'large', 'high_contrast' => true, 'reduce_motion' => true])->assertRedirect();
        $this->put(route('student.settings.notifications'), ['quiet_mode' => true, 'notify_deadlines' => false, 'notify_feedback' => true, 'notify_achievements' => false, 'xp' => 9999])->assertRedirect();
        $prefs = $student->fresh()->effectivePreferences();
        $this->assertSame('large', $prefs['text_size']);
        $this->assertTrue($prefs['high_contrast']);
        $this->assertTrue($prefs['reduce_motion']);
        $this->assertTrue($prefs['quiet_mode']);
        $this->get(route('student.settings'))->assertOk()->assertSee('dataset.studentText = "large"', false)->assertSee('Quiet mode is on');
        $this->get(route('student.dashboard'))->assertOk()->assertSee('Quiet mode is on');
        $this->get(route('student.code-lab'))->assertOk()->assertSee('student-preferences.css');
        $this->putJson(route('student.settings.display'), ['text_size' => 'huge', 'high_contrast' => 0, 'reduce_motion' => 0])->assertUnprocessable();
        $this->assertSame(100, $student->fresh()->xp);
    }

    public function test_leaderboard_privacy_hides_nickname_and_avatar_from_peers_in_all_and_section_views(): void
    {
        extract($this->fixture());
        $student->update(['nickname' => 'HiddenCoder', 'avatar' => 'Solar']);
        $original = $this->get(route('student.leaderboard'))->viewData('overallRanking')->firstWhere('id', $student->id)['rank'];
        $this->put(route('student.settings.privacy'), ['leaderboard_visible' => false, 'xp' => 999])->assertRedirect();
        $alias = $student->rankingAlias();
        $this->withSession(['student_id' => $peer->id, 'student_session_version' => 1])->get(route('student.leaderboard'))->assertOk()->assertDontSee('HiddenCoder')->assertSee($alias)
            ->assertViewHas('overallRanking', fn ($rows) => $rows->firstWhere('id', $student->id)['avatar'] === '👤' && $rows->firstWhere('id', $student->id)['rank'] === $original);
        $this->get(route('student.leaderboard', ['section' => $room->id]))->assertOk()->assertDontSee('HiddenCoder')->assertSee($alias);
        $this->get(route('teacher.reports'))->assertOk()->assertSee('HiddenCoder');
        $this->withSession(['student_id' => $student->id, 'student_session_version' => 1])->get(route('student.leaderboard'))->assertOk()->assertSee('HiddenCoder');
        $this->assertSame(100, $student->fresh()->xp);
    }

    public function test_password_changes_hash_password_revoke_old_student_sessions_and_preserve_teacher_role(): void
    {
        extract($this->fixture());
        $workspace = CodeWorkspace::create(['student_id' => $student->id, 'activity_key' => 'practice:greeting', 'title' => 'Saved code', 'code' => 'int main(){return 0;}']);
        $payload = ['current_password' => 'wrong', 'password' => 'BetterPass123', 'password_confirmation' => 'BetterPass123'];
        $this->postJson(route('student.settings.password'), $payload)->assertUnprocessable();
        $this->post(route('student.settings.password'), [...$payload, 'current_password' => 'password123'])->assertRedirect()->assertSessionHas('student_session_version', 2)->assertSessionHas('teacher_id', $teacher->id);
        $this->assertTrue(Hash::check('BetterPass123', $student->fresh()->password));
        $this->assertFalse(Hash::check('password123', $student->fresh()->password));
        $this->assertSame(100, $student->fresh()->xp);
        $this->assertSame('int main(){return 0;}', $workspace->fresh()->code);
        $this->withSession(['student_id' => $student->id, 'student_session_version' => 1])->get(route('student.settings'))->assertRedirect(route('student.login'))->assertSessionMissing('student_id')->assertSessionHas('teacher_id', $teacher->id);
        $this->postJson(route('student.login.store'), ['student_id' => $student->student_id, 'password' => 'password123'])->assertUnprocessable();
        $this->postJson(route('student.login.store'), ['student_id' => $student->student_id, 'password' => 'BetterPass123'])->assertOk()->assertSessionHas('student_session_version', 2);
    }

    public function test_signout_is_post_only_clears_student_access_and_keeps_teacher_session(): void
    {
        extract($this->fixture());
        $this->get(route('student.logout'))->assertStatus(405);
        $this->post(route('student.logout'))->assertRedirect(route('student.login'))->assertSessionMissing('student_id')->assertSessionMissing('student_class_verified')->assertSessionHas('teacher_id', $teacher->id);
        $this->get(route('student.learn'))->assertRedirect(route('student.login'));
        $this->get(route('teacher.settings'))->assertOk();
    }

    public function test_alert_summaries_use_owned_deadlines_feedback_and_badges(): void
    {
        extract($this->fixture());
        TeacherAssignment::create(['teacher_account_id' => $teacher->id, 'classroom_id' => $room->id, 'title' => 'Soon', 'status' => 'published', 'due_at' => now()->addHour()]);
        TeacherAssignment::create(['teacher_account_id' => $teacher->id, 'classroom_id' => $other->id, 'title' => 'Other section', 'status' => 'published', 'due_at' => now()->addHour()]);
        TeacherQuiz::create(['teacher_account_id' => $teacher->id, 'classroom_id' => $room->id, 'title' => 'Quiz soon', 'questions' => [], 'status' => 'published', 'closes_at' => now()->addHours(2)]);
        $workspace = CodeWorkspace::create(['student_id' => $student->id, 'activity_key' => 'practice:greeting', 'title' => 'Code', 'code' => 'int main(){}']);
        LabSubmission::create(['code_workspace_id' => $workspace->id, 'teacher_account_id' => $teacher->id, 'revision' => 1, 'title' => 'Code', 'code' => 'int main(){}', 'reflection' => 'I tested output.', 'reviewed_at' => now(), 'feedback' => 'Good work on your output.']);
        LessonProgress::create(['student_id' => $student->id, 'lesson_key' => 'output', 'started_at' => now(), 'last_opened_at' => now(), 'teacher_feedback' => 'Test another message.']);
        StudentAchievement::create(['student_id' => $student->id, 'badge_code' => 'first_code', 'earned_at' => now(), 'evidence' => []]);
        $summary = app(StudentSettingsService::class)->alerts($student);
        $this->assertSame(1, $summary['assignment_deadlines']);
        $this->assertSame(1,$summary['quiz_deadlines']);
        $this->assertSame(2,$summary['feedback']);
        $this->assertSame(1,$summary['badges']);
        $this->assertSame(100,$student->fresh()->xp);
    }
}
