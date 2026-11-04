<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\CodeWorkspace;
use App\Models\LabSubmission;
use App\Models\LessonProgress;
use App\Models\Student;
use App\Models\TeacherAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class TeacherSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $teacher = TeacherAccount::create(['first_name' => 'Owner', 'last_name' => 'Teacher', 'email' => 'settings@example.test', 'school' => 'Our School', 'password' => 'password123']);
        $outsider = TeacherAccount::create(['first_name' => 'Private', 'last_name' => 'Teacher', 'email' => 'private-settings@example.test', 'school' => 'Other School', 'password' => 'password123']);
        $room = Classroom::create(['teacher_account_id' => $teacher->id, 'name' => 'C++ Programming', 'grade' => 'Grade 8', 'section' => 'A', 'period' => 'Period 3', 'join_code' => 'SETTINGA']);
        $foreign = Classroom::create(['teacher_account_id' => $outsider->id, 'name' => 'Private Classroom', 'grade' => 'Grade 8', 'section' => 'Private', 'join_code' => 'PRIVATE']);
        $student = Student::create(['classroom_id' => $room->id, 'first_name' => 'Alex', 'nickname' => 'CodeAlex', 'student_id' => 'SETTING1', 'password' => 'password123', 'xp' => 100]);
        $stranger = Student::create(['classroom_id' => $foreign->id, 'first_name' => 'Private', 'nickname' => 'PrivateCoder', 'student_id' => 'PRIVATE1', 'password' => 'password123']);
        $this->withSession(['teacher_id' => $teacher->id, 'teacher_session_version' => 1]);

        return compact('teacher', 'outsider', 'room', 'foreign', 'student', 'stranger');
    }

    private function classPayload(): array
    {
        return ['name' => 'C++ Programming', 'grade' => 'Grade 8', 'section' => 'B', 'period' => 'Period 4', 'enrollment_open' => true];
    }

    private function preferencePayload(): array
    {
        return ['text_size' => 'large', 'high_contrast' => true, 'reduce_motion' => true, 'notify_enrollment' => true, 'notify_submissions' => false, 'notify_support' => true];
    }

    public function test_settings_require_teacher_authentication_and_display_only_owned_classes(): void
    {
        $this->get(route('teacher.settings'))->assertRedirect(route('teacher.login'));
        $this->putJson(route('teacher.settings.profile'), [])->assertForbidden();
        extract($this->fixture());
        $this->get(route('teacher.settings'))->assertOk()->assertSee('Teacher Settings')->assertSee('Managed Classrooms')->assertSee('SETTINGA')
            ->assertDontSee('Private Classroom')->assertDontSee('PRIVATE1')->assertDontSee('HUD Simulator')->assertDontSee('FERPA Compliant')->assertDontSee($teacher->password, false);
        $this->withSession(['teacher_id' => null, 'student_id' => $student->id])->get(route('teacher.settings'))->assertRedirect(route('teacher.login'));
    }

    public function test_account_edits_are_saved_and_email_changes_require_the_current_password(): void
    {
        extract($this->fixture());
        $account = ['first_name' => 'Updated', 'last_name' => 'Educator', 'school' => 'New School', 'email' => 'settings@example.test'];
        $this->put(route('teacher.settings.account'), $account)->assertRedirect();
        $this->assertSame('Updated', $teacher->fresh()->first_name);
        $this->putJson(route('teacher.settings.account'), [...$account, 'email' => 'updated@example.test'])->assertUnprocessable()->assertJsonValidationErrors('current_password');
        $this->putJson(route('teacher.settings.account'), [...$account, 'email' => 'private-settings@example.test', 'current_password' => 'password123'])->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->put(route('teacher.settings.account'), [...$account, 'email' => 'UPDATED@EXAMPLE.TEST', 'current_password' => 'password123', 'teacher_id' => $outsider->id, 'session_version' => 999])->assertRedirect()->assertSessionHas('teacher_session_version', 2);
        $this->assertSame('updated@example.test', $teacher->fresh()->email);
        $this->assertSame(2, $teacher->fresh()->session_version);
        $this->assertSame('private-settings@example.test', $outsider->fresh()->email);
        $this->postJson(route('teacher.login.store'), ['identifier' => 'settings@example.test', 'password' => 'password123'])->assertUnprocessable();
        $this->postJson(route('teacher.login.store'), ['identifier' => 'updated@example.test', 'password' => 'password123'])->assertOk();
    }

    public function test_password_change_hashes_the_password_and_revokes_stale_teacher_sessions(): void
    {
        extract($this->fixture());
        $password = ['password' => 'NewPassword123', 'password_confirmation' => 'NewPassword123', 'current_password' => 'wrong'];
        $this->postJson(route('teacher.settings.password'), $password)->assertUnprocessable()->assertJsonValidationErrors('current_password');
        $this->postJson(route('teacher.settings.password'), [...$password, 'current_password' => 'password123', 'password' => 'short', 'password_confirmation' => 'short'])->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->post(route('teacher.settings.password'), [...$password, 'current_password' => 'password123'])->assertRedirect()->assertSessionHas('teacher_session_version', 2);
        $this->assertTrue(Hash::check('NewPassword123', $teacher->fresh()->password));
        $this->assertFalse(Hash::check('password123', $teacher->fresh()->password));
        $this->assertNotNull($teacher->fresh()->password_changed_at);
        $this->get(route('teacher.settings'))->assertOk();
        $this->withSession(['teacher_id' => $teacher->id, 'teacher_session_version' => 1, 'student_id' => $student->id])
            ->get(route('teacher.settings'))->assertRedirect(route('teacher.login'))->assertSessionMissing('teacher_id');
        $this->get(route('student.learn'))->assertOk();
        $this->postJson(route('teacher.login.store'), ['identifier' => $teacher->email, 'password' => 'password123'])->assertUnprocessable();
        $this->postJson(route('teacher.login.store'), ['identifier' => $teacher->email, 'password' => 'NewPassword123'])->assertOk()->assertSessionHas('teacher_session_version', 2);
    }

    public function test_revoking_other_sessions_keeps_the_current_session_and_requires_password(): void
    {
        extract($this->fixture());
        $this->postJson(route('teacher.settings.sessions'), ['current_password' => 'wrong'])->assertUnprocessable();
        $this->assertSame(1, $teacher->fresh()->session_version);
        $this->post(route('teacher.settings.sessions'), ['current_password' => 'password123'])->assertRedirect()->assertSessionHas('teacher_session_version', 2);
        $this->get(route('teacher.settings'))->assertOk();
        $this->withSession(['teacher_id' => $teacher->id, 'teacher_session_version' => 1])->get(route('teacher.reports'))->assertRedirect(route('teacher.login'));
    }

    public function test_profile_visibility_changes_what_enrolled_students_see_and_escapes_text(): void
    {
        extract($this->fixture());
        $this->put(route('teacher.settings.profile'), ['display_name' => 'Ms. Coder', 'bio' => 'Welcome <script>alert(1)</script> to C++.', 'profile_visible' => 1])->assertRedirect();
        $this->withSession(['student_id' => $student->id])->get(route('student.learn'))->assertOk()->assertSee('Ms. Coder')
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
        $this->withSession(['student_id' => $stranger->id])->get(route('student.learn'))->assertOk()->assertDontSee('Ms. Coder');
        $this->put(route('teacher.settings.profile'), ['display_name' => 'Ms. Coder', 'bio' => 'Private bio', 'profile_visible' => 0])->assertRedirect();
        $this->withSession(['student_id' => $student->id])->get(route('student.learn'))->assertOk()->assertSee('Owner Teacher')->assertDontSee('Private bio')->assertDontSee('Ms. Coder');
    }

    public function test_preferences_persist_and_apply_to_teacher_pages_and_activity_summaries(): void
    {
        extract($this->fixture());
        $this->put(route('teacher.settings.preferences'), $this->preferencePayload())->assertRedirect();
        $this->assertSame('large', $teacher->fresh()->effectivePreferences()['text_size']);
        $this->assertFalse($teacher->fresh()->effectivePreferences()['notify_submissions']);
        $this->get(route('teacher.settings'))->assertOk()->assertSee('dataset.teacherText = "large"', false)->assertSee('dataset.teacherContrast = "high"', false);
        $this->get(route('teacher.lessons'))->assertOk()->assertSee('dataset.teacherMotion = "reduce"', false);
        $this->get(route('teacher.reports'))->assertOk()->assertSee('teacher-preferences.css');
        $this->putJson(route('teacher.settings.preferences'), [...$this->preferencePayload(), 'text_size' => 'tiny'])->assertUnprocessable()->assertJsonValidationErrors('text_size');
    }

    public function test_teachers_can_create_and_edit_sections_without_changing_other_teachers_or_student_xp(): void
    {
        extract($this->fixture());
        foreach (['B', 'C'] as $section) {
            $this->post(route('teacher.settings.classes.store'), [...$this->classPayload(), 'section' => $section, 'teacher_account_id' => $outsider->id])->assertRedirect();
        }
        $rooms = $teacher->classrooms()->get();
        $this->assertCount(3, $rooms);
        $this->assertCount(3, $rooms->pluck('join_code')->unique());
        $this->postJson(route('teacher.settings.classes.store'), $this->classPayload())->assertUnprocessable()->assertJsonValidationErrors('section');
        $this->putJson(route('teacher.settings.classes.update', $foreign), $this->classPayload())->assertForbidden();
        $updated = $student->updated_at->toDateTimeString();
        $this->travel(1)->days();
        $this->put(route('teacher.settings.classes.update', $room), [...$this->classPayload(), 'name' => 'Updated Class', 'grade' => 'Grade 9', 'section' => 'A', 'enrollment_open' => 0])->assertRedirect();
        $this->assertSame('Updated Class', $room->fresh()->name);
        $this->assertSame('Grade 9', $student->fresh()->grade_level);
        $this->assertSame('A', $student->fresh()->section);
        $this->assertSame($updated, $student->fresh()->updated_at->toDateTimeString());
        $this->assertSame(100, $student->fresh()->xp);
        $this->assertFalse($room->fresh()->enrollment_open);
    }

    public function test_classroom_creation_accepts_an_empty_optional_schedule_and_generates_a_usable_join_code(): void
    {
        extract($this->fixture());

        $this->post(route('teacher.settings.classes.store'), [...$this->classPayload(), 'section' => 'AM', 'period' => '', 'weekly_xp_goal' => 1000, 'sequential_learning' => 0])
            ->assertRedirect(route('teacher.settings').'#classes')->assertSessionHasNoErrors();

        $created = Classroom::where('teacher_account_id', $teacher->id)->where('section', 'AM')->sole();
        $this->assertSame('', $created->period);
        $this->assertMatchesRegularExpression('/^[A-Z0-9]{8}$/', $created->join_code);
        $this->assertDatabaseCount('classrooms', 3);
        $this->postJson(route('student.register.verify'), ['classroom_id' => $created->id, 'grade_level' => 'Grade 8', 'class_code' => $created->join_code])->assertOk();
    }

    public function test_optional_schedule_can_be_cleared_or_omitted_when_editing_a_classroom(): void
    {
        extract($this->fixture());
        $code = $room->join_code;

        $this->put(route('teacher.settings.classes.update', $room), [...$this->classPayload(), 'section' => 'AM', 'period' => null])
            ->assertRedirect(route('teacher.settings').'#classes')->assertSessionHasNoErrors();
        $this->assertSame('', $room->fresh()->period);
        $this->assertSame($code, $room->fresh()->join_code);
        $payload = $this->classPayload();
        unset($payload['period']);
        $this->post(route('teacher.settings.classes.store'), $payload)->assertRedirect(route('teacher.settings').'#classes')->assertSessionHasNoErrors();
        $this->assertDatabaseHas('classrooms', ['teacher_account_id' => $teacher->id, 'section' => 'B', 'period' => '']);
        $this->assertSame(100, $student->fresh()->xp);
    }

    public function test_paused_enrollment_blocks_verification_and_completion_but_existing_students_can_log_in(): void
    {
        extract($this->fixture());
        $room->update(['section' => 'AM']);
        $this->postJson(route('student.register.verify'), ['classroom_id' => $room->id, 'grade_level' => 'Grade 8', 'class_code' => $room->join_code])->assertOk();
        $this->put(route('teacher.settings.classes.update', $room), [...$this->classPayload(), 'section' => 'AM', 'enrollment_open' => false])->assertRedirect();
        $registration = ['first_name' => 'New', 'surname' => 'Student', 'grade_level' => 'Grade 8', 'classroom_id' => $room->id, 'nickname' => 'NewCoder', 'student_id' => 'NEW123', 'password' => 'password123', 'password_confirmation' => 'password123'];
        $this->postJson(route('student.register.store'), $registration)->assertUnprocessable()->assertJsonValidationErrors('class_code');
        $this->postJson(route('student.register.verify'), ['classroom_id' => $room->id, 'grade_level' => 'Grade 8', 'class_code' => $room->join_code])->assertUnprocessable();
        $this->get(route('student.register'))->assertOk()->assertViewHas('enrollmentOptions', fn ($options) => ! $options->contains(fn ($option) => $option['grade'] === $room->grade && $option['section'] === $room->section));
        $this->postJson(route('student.login.store'), ['student_id' => $student->student_id, 'password' => 'password123'])->assertOk();
        $this->get(route('student.learn'))->assertOk();
        $this->assertSame(100, $student->fresh()->xp);
    }

    public function test_regenerated_join_codes_reject_old_verifications_and_preserve_existing_students(): void
    {
        extract($this->fixture());
        $room->update(['section' => 'AM']);
        $code = $room->join_code;
        $this->postJson(route('student.register.verify'), ['classroom_id' => $room->id, 'grade_level' => 'Grade 8', 'class_code' => $code])->assertOk();
        $this->postJson(route('teacher.settings.classes.code', $foreign), ['current_password' => 'password123', 'confirm_rotation' => 1])->assertForbidden();
        $this->post(route('teacher.settings.classes.code', $room), ['current_password' => 'password123', 'confirm_rotation' => 1])->assertRedirect();
        $this->assertNotSame($code, $room->fresh()->join_code);
        $registration = ['first_name' => 'New', 'surname' => 'Student', 'grade_level' => 'Grade 8', 'classroom_id' => $room->id, 'nickname' => 'NewCoder', 'student_id' => 'NEW123', 'password' => 'password123', 'password_confirmation' => 'password123'];
        $this->postJson(route('student.register.store'), $registration)->assertUnprocessable();
        $this->postJson(route('student.register.verify'), ['classroom_id' => $room->id, 'grade_level' => 'Grade 8', 'class_code' => $code])->assertUnprocessable();
        $this->postJson(route('student.register.verify'), ['classroom_id' => $room->id, 'grade_level' => 'Grade 8', 'class_code' => $room->fresh()->join_code])->assertOk();
        $this->postJson(route('student.register.store'), $registration)->assertOk();
        $this->assertSame(100, $student->fresh()->xp);
    }

    public function test_profile_photos_are_validated_private_and_follow_profile_visibility(): void
    {
        extract($this->fixture());
        Storage::fake('local');
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=');
        $this->post(route('teacher.settings.avatar.store'), ['avatar' => UploadedFile::fake()->createWithContent('photo.png', $png)])->assertRedirect();
        $path = $teacher->fresh()->avatar_path;
        Storage::disk('local')->assertExists($path);
        $this->get(route('teacher.settings.avatar', $teacher))->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->withSession(['teacher_id' => null, 'student_id' => $student->id])->get(route('teacher.settings.avatar', $teacher))->assertOk();
        $this->withSession(['student_id' => $stranger->id])->get(route('teacher.settings.avatar', $teacher))->assertForbidden();
        $this->withSession(['teacher_id' => $teacher->id, 'teacher_session_version' => 1]);
        $this->put(route('teacher.settings.profile'), ['display_name' => 'Teacher', 'bio' => 'Bio', 'profile_visible' => false])->assertRedirect();
        $this->withSession(['teacher_id' => null, 'student_id' => $student->id])->get(route('teacher.settings.avatar', $teacher))->assertForbidden();
        $this->withSession(['teacher_id' => $teacher->id, 'teacher_session_version' => 1]);
        $this->postJson(route('teacher.settings.avatar.store'), ['avatar' => UploadedFile::fake()->create('bad.svg', 1, 'image/svg+xml')])->assertUnprocessable();
        $this->delete(route('teacher.settings.avatar.destroy'))->assertRedirect();
        Storage::disk('local')->assertMissing($path);
        $this->assertNull($teacher->fresh()->avatar_path);
    }

    #[TestWith(['principal'])]
    #[TestWith(['teacher'])]
    #[TestWith(['school_admin'])]
    public function test_uploaded_profile_photo_updates_staff_headers_and_profile_card(string $role): void
    {
        extract($this->fixture());
        $teacher->forceFill(['role' => $role, 'profile_visible' => false])->save();
        Storage::fake('local');
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=');

        $this->post(route('teacher.settings.avatar.store'), ['avatar' => UploadedFile::fake()->createWithContent('profile.png', $png)])->assertRedirect();
        $initialPath = $teacher->fresh()->avatar_path;
        Storage::disk('local')->assertExists($initialPath);
        $response = $this->get(route('teacher.settings'))->assertOk();
        $sources = $this->staffAvatarSources($response);
        $this->assertCount(2, $sources);
        $this->assertSame($sources[0], $sources[1]);
        $this->assertStringStartsWith(route('teacher.settings.avatar', $teacher).'?v=', $sources[0]);
        $this->assertStringNotContainsString('teacher-avatars/', $sources[0]);
        $this->get($sources[0])->assertOk()->assertStreamedContent($png)->assertHeader('Content-Type', 'image/png');
        $dashboard = $this->get(route($teacher->dashboardRoute()))->assertOk();
        $this->assertSame([$sources[0]], $this->staffAvatarSources($dashboard));

        $this->post(route('teacher.settings.avatar.store'), ['avatar' => UploadedFile::fake()->createWithContent('replacement.png', $png)])->assertRedirect();
        Storage::disk('local')->assertMissing($initialPath);
        Storage::disk('local')->assertExists($teacher->fresh()->avatar_path);
        $replacementSources = $this->staffAvatarSources($this->get(route('teacher.settings'))->assertOk());
        $this->assertCount(2, $replacementSources);
        $this->assertSame($replacementSources[0], $replacementSources[1]);
        $this->assertNotSame($sources[0], $replacementSources[0]);
        $this->get($replacementSources[0])->assertOk()->assertStreamedContent($png);

        $this->delete(route('teacher.settings.avatar.destroy'))->assertRedirect();
        $this->assertNull($teacher->fresh()->avatar_path);
        $fallback = asset($role === 'school_admin' ? 'images/branding/system-admin-profile.png' : 'images/branding/nf-codequest-tab-icon.png');
        $this->assertSame([$fallback, $fallback], $this->staffAvatarSources($this->get(route('teacher.settings'))->assertOk()));
    }

    private function staffAvatarSources(TestResponse $response): array
    {
        $document = new \DOMDocument;
        $document->loadHTML($response->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
        $images = (new \DOMXPath($document))->query('//img[contains(concat(" ", normalize-space(@class), " "), " app-admin-avatar ")]/@src');

        return array_map(fn (\DOMAttr $source): string => $source->value, iterator_to_array($images));
    }

    public function test_activity_summaries_use_latest_owned_submissions_and_saved_help_signals(): void
    {
        extract($this->fixture());
        $work = CodeWorkspace::create(['student_id' => $student->id, 'activity_key' => 'practice:greeting', 'title' => 'Practice', 'code' => 'int main(){}']);
        LabSubmission::create(['code_workspace_id' => $work->id, 'teacher_account_id' => $teacher->id, 'revision' => 1, 'title' => 'Practice', 'code' => 'int main(){}', 'reflection' => 'I traced my program.']);
        LabSubmission::create(['code_workspace_id' => $work->id, 'teacher_account_id' => $teacher->id, 'revision' => 2, 'title' => 'Practice', 'code' => 'int main(){}', 'reflection' => 'I tested a new case.']);
        LessonProgress::create(['student_id' => $student->id, 'lesson_key' => 'output', 'started_at' => now(), 'last_opened_at' => now(), 'confidence' => 'need_help']);
        $this->get(route('teacher.settings'))->assertOk()->assertViewHas('activity', fn ($activity) => $activity['submissions'] === 1 && $activity['support'] === 1 && $activity['enrollment'] === 1);
    }
}
