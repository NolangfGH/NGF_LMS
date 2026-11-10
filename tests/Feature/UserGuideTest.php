<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserGuideTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_guide_covers_every_role_without_exposing_staff_signin_or_private_data(): void
    {
        $page = $this->get(route('system.guide'))->assertOk()->assertSee('Your guide to learning and teaching')->assertSee('data-guide-search', false)->assertSee('data-guide-role', false)->assertSee('private administrator review');
        foreach (['student', 'teacher', 'principal', 'admin', 'parent'] as $role) {
            $this->assertNotEmpty(array_filter(config('user_guide'), fn ($topic) => in_array($role, $topic['roles'], true)));
        }
        $page->assertSee('5 XP per correct answer')->assertSee('3 XP deduction')->assertSee('90 seconds')->assertSee('Arduino IDE')->assertSee('System Admin')->assertDontSee('href="'.route('teacher.login').'"', false);
        $this->get(route('home'))->assertOk()->assertSee(route('system.guide'), false);
        $this->get(route('donations'))->assertOk()->assertSee(route('system.guide'), false);
        if ($preview = getenv('LEARNING_SUPPORT_PREVIEW')) {
            file_put_contents($preview.'/guide.html', $page->getContent());
            file_put_contents($preview.'/donations.html', $this->get(route('donations'))->getContent());
        }
    }
}
