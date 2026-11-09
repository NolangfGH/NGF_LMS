<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\ParentAccount;
use App\Models\Student;
use App\Models\TeacherAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MobileAppTest extends TestCase
{
    use RefreshDatabase;

    public function test_install_page_and_shared_head_offer_both_installation_methods_without_staff_login_exposure(): void
    {
        Storage::fake('local');
        $response = $this->get(route('mobile.app'))->assertOk()->assertSee('Install website app')->assertSee('iPhone / iPad')->assertSee('Android APK')->assertSee('APK is being prepared')->assertSee('Private learning pages and exam questions')->assertSee('/manifest.webmanifest')->assertSee('/js/mobile-app.js')->assertDontSee('href="'.route('teacher.login').'"', false)->assertHeader('Cache-Control', 'no-store, private');
        $this->get(route('home'))->assertOk()->assertSee(route('mobile.app'), false)->assertSee('Get app');
        $this->get(route('student.login'))->assertOk()->assertSee('/manifest.webmanifest')->assertSee('/js/mobile-app.js');
        if (getenv('MOBILE_APP_PREVIEW')) {
            file_put_contents(storage_path('framework/testing/mobile-app-preview.html'), $response->getContent());
        }
    }

    public function test_android_download_appears_only_after_the_fixed_artifact_is_available(): void
    {
        Storage::fake('local');
        $this->get(route('mobile.android'))->assertNotFound();
        Storage::disk('local')->put('mobile/ngf-codequest-1.0.0.apk', 'test-package');
        $this->get(route('mobile.app'))->assertOk()->assertSee('Download Android APK')->assertSee(hash('sha256', 'test-package'))->assertDontSee('APK is being prepared');
        $this->get(route('mobile.android', ['path' => '.env']))->assertOk()->assertDownload('NGF-CodeQuest-1.0.0.apk')->assertHeader('Content-Type', 'application/vnd.android.package-archive')->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_start_uses_the_current_role_without_bypassing_registration_or_session_checks(): void
    {
        $this->get(route('mobile.start'))->assertRedirect(route('student.login'));
        $teacher = TeacherAccount::create(['first_name' => 'Mobile', 'last_name' => 'Teacher', 'email' => fake()->unique()->safeEmail(), 'school' => 'School', 'password' => 'Password123']);
        $room = Classroom::create(['teacher_account_id' => $teacher->id, 'name' => 'Mobile C++', 'grade' => 'Grade 8', 'section' => 'AM', 'join_code' => 'MOBILEAPP']);
        $student = Student::create(['classroom_id' => $room->id, 'first_name' => 'Mobile', 'nickname' => 'MobileCoder', 'student_id' => 'MOBILE001', 'password' => 'Password123']);
        $this->withSession(['student_id' => $student->id, 'student_class_verified' => true])->get(route('mobile.start'))->assertRedirect(route('student.dashboard'));
        if (getenv('MOBILE_APP_PREVIEW')) {
            file_put_contents(storage_path('framework/testing/mobile-student-preview.html'), $this->get(route('student.dashboard'))->assertOk()->getContent());
        }
        $this->withSession(['student_session_version' => 999])->get(route('mobile.start'))->assertRedirect(route('student.login'));
        $student->forceFill(['registration_status' => 'pending'])->save();
        $this->withSession(['student_id' => $student->id])->get(route('mobile.start'))->assertRedirect(route('student.login'))->assertSessionMissing('student_id');
        $this->withSession(['teacher_id' => $teacher->id])->get(route('mobile.start'))->assertRedirect(route('teacher.dashboard'));
        if (getenv('MOBILE_APP_PREVIEW')) {
            file_put_contents(storage_path('framework/testing/mobile-teacher-preview.html'), $this->get(route('teacher.dashboard'))->assertOk()->getContent());
        }
        $teacher->forceFill(['role' => 'principal'])->save();
        $this->get(route('mobile.start'))->assertRedirect(route('school.overview'));
        $this->withSession(['teacher_session_version' => 999])->get(route('mobile.start'))->assertRedirect(route('student.login'));
        $parent = ParentAccount::factory()->create();
        $this->withSession(['parent_id' => $parent->id])->get(route('mobile.start'))->assertRedirect(route('parent.dashboard'));
    }

    public function test_manifest_and_square_app_icons_define_a_same_origin_standalone_application(): void
    {
        $manifest = json_decode(file_get_contents(public_path('manifest.webmanifest')), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('NGF_C++CodeQuest', $manifest['name']);
        $this->assertSame('standalone', $manifest['display']);
        $this->assertSame('/app/start', $manifest['start_url']);
        $this->assertSame('/', $manifest['scope']);
        foreach ($manifest['icons'] as $icon) {
            [$width, $height] = getimagesize(public_path(ltrim($icon['src'], '/')));
            $this->assertSame($width, $height);
            $this->assertSame($width.'x'.$height, $icon['sizes']);
        }
        $offline = file_get_contents(public_path('offline.html'));
        $this->assertStringContainsString('Submissions are not queued offline', $offline);
        $this->assertStringNotContainsString('teacher-login', $offline);
    }
}
