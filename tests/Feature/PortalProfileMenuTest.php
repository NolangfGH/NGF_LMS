<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Student;
use App\Models\TeacherAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PortalProfileMenuTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_menu_links_follow_each_staff_role(): void
    {
        foreach (['teacher', 'principal', 'school_admin'] as $role) {
            $teacher = TeacherAccount::create(['first_name' => 'Account', 'last_name' => 'Owner', 'school' => 'School', 'email' => $role.'@example.test', 'password' => 'Password123']);
            $teacher->forceFill(['role' => $role])->save();
            $this->withSession(['teacher_id' => $teacher->id, 'student_id' => null]);
            $page = $this->get(route('teacher.settings'))->assertOk()
                ->assertSee('Open '.$teacher->roleLabel().' account menu')
                ->assertSee('data-profile-menu', false)
                ->assertSee(route('teacher.settings').'#profile', false)
                ->assertSee(route('teacher.settings').'#account', false)
                ->assertSee(route('teacher.settings').'#preferences', false)
                ->assertSee(route('system.about').'#feedback', false);
            if ($role === 'school_admin') {
                $page->assertSee('School administration');
            } else {
                $page->assertDontSee('School administration');
            }
            if ($preview = getenv('PORTAL_UI_PREVIEW')) {
                file_put_contents($preview.'/'.$role.'.html', $this->get(route($teacher->dashboardRoute()))->assertOk()->getContent());
            }
        }
    }

    public function test_student_menu_uses_own_account_links_and_post_logout(): void
    {
        $teacher = TeacherAccount::create(['first_name' => 'Test', 'last_name' => 'Teacher', 'school' => 'School', 'email' => 'teacher@example.test', 'password' => 'Password123']);
        $room = Classroom::create(['teacher_account_id' => $teacher->id, 'name' => 'C++', 'grade' => 'Grade 8', 'section' => 'AM', 'join_code' => 'MENUS8']);
        $student = Student::create(['classroom_id' => $room->id, 'first_name' => 'Student', 'nickname' => 'MenuCoder', 'student_id' => 'MENU001', 'password' => 'Password123']);
        $this->withSession(['teacher_id' => null, 'student_id' => $student->id, 'student_class_verified' => true]);
        $page = $this->get(route('student.dashboard'))->assertOk()
            ->assertSee('Open Student account menu')
            ->assertSee('My learning progress')
            ->assertSee(route('student.settings').'#profile', false)
            ->assertSee(route('student.settings').'#display', false)
            ->assertSee('method="POST" action="'.route('student.logout').'"', false)
            ->assertDontSee('School administration');
        $this->post(route('student.logout'))->assertRedirect(route('student.login'));
        $this->get(route('student.dashboard'))->assertRedirect(route('student.register'));
        if ($preview = getenv('PORTAL_UI_PREVIEW')) {
            file_put_contents($preview.'/student.html', $page->getContent());
        }
    }

    public function test_both_login_forms_mark_only_credentials_for_clearing_on_first_entry(): void
    {
        foreach (['student', 'teacher'] as $role) {
            $page = $this->get(route($role.'.login'))->assertOk();
            $this->assertSame(2, substr_count($page->getContent(), 'data-clear-on-entry'));
            $page->assertSee('js/portal-ui.js', false)->assertSee('autocomplete="current-password" data-clear-on-entry', false);
            if ($preview = getenv('PORTAL_UI_PREVIEW')) {
                file_put_contents($preview.'/'.$role.'-login.html', $page->getContent());
            }
        }

    }
}
