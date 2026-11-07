<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\ParentAccount;
use App\Models\TeacherAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DonationAuditTest extends TestCase
{
    use RefreshDatabase;

    private function staff(string $role = 'school_admin'): TeacherAccount
    {
        $teacher = TeacherAccount::create(['first_name' => 'Report', 'last_name' => 'Admin', 'email' => fake()->unique()->safeEmail(), 'school' => 'School', 'password' => 'password123']);
        $teacher->forceFill(['role' => $role])->save();

        return $teacher;
    }

    public function test_donations_are_inactive_until_admin_publishes_safe_payment_details(): void
    {
        $this->get(route('donations'))->assertOk()->assertSee('Donation details are being prepared');
        $this->get(route('donations.qr'))->assertNotFound();
        $teacher = $this->staff('teacher');
        $this->withSession(['teacher_id' => $teacher->id])->put(route('school.donations.save'), ['enabled' => 1])->assertForbidden();
        $admin = $this->staff();
        $this->withSession(['teacher_id' => $admin->id])->get(route('school.donations'))->assertOk();
        $this->put(route('school.donations.save'), ['enabled' => 1, 'payment_url' => 'javascript:alert(1)'])->assertSessionHasErrors('payment_url');
        Storage::fake('local');
        $qr = UploadedFile::fake()->createWithContent('qr.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jX1cAAAAASUVORK5CYII='));
        $this->put(route('school.donations.save'), ['enabled' => 1, 'recipient' => 'Sample School Fund', 'instructions' => 'Confirm the recipient.', 'payment_url' => 'https://example.test/donate', 'qr' => $qr])->assertRedirect()->assertSessionHasNoErrors();
        $page = $this->get(route('donations'))->assertOk()->assertSee('Sample School Fund')->assertSee('https://example.test/donate')->assertSee(route('donations.qr'), false);
        $this->get(route('donations.qr'))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        if ($preview = getenv('COMMUNITY_PREVIEW')) {
            file_put_contents($preview.'/donations.html', $page->getContent());
            file_put_contents($preview.'/donation-settings.html', $this->get(route('school.donations'))->getContent());
        }
        $this->put(route('school.donations.save'), ['enabled' => 0])->assertRedirect();
        $this->get(route('donations'))->assertDontSee('https://example.test/donate');
        $this->get(route('donations.qr'))->assertNotFound();
    }

    public function test_audit_captures_outcomes_and_actor_without_request_secrets(): void
    {
        $parent = ParentAccount::factory()->create();
        $this->post(route('parent.login.store'), ['email' => $parent->email, 'password' => 'WrongSecretPassword!'])->assertSessionHasErrors();
        $event = AuditEvent::latest('id')->firstOrFail();
        $this->assertSame(302, $event->status);
        $this->assertSame('rejected', $event->outcome);
        $this->assertSame('parent.login.store', $event->action);
        $this->post(route('parent.login.store'), ['email' => $parent->email, 'password' => 'ParentPassword123!'])->assertRedirect();
        $event = AuditEvent::latest('id')->firstOrFail();
        $this->assertSame('parent', $event->actor_type);
        $this->assertSame('success', $event->outcome);
        $this->assertSame($parent->id, $event->actor_id);
        $this->assertStringNotContainsString($parent->email, AuditEvent::all()->toJson());
        $this->assertStringNotContainsString('SecretPassword', AuditEvent::all()->toJson());
        $this->assertStringNotContainsString('ParentPassword123!', AuditEvent::all()->toJson());
        $this->get(route('school.audit'))->assertForbidden();
        $teacher = $this->staff('teacher');
        $this->withSession(['parent_id' => null, 'teacher_id' => $teacher->id])->get(route('school.audit'))->assertForbidden();
        $admin = $this->staff();
        $this->withSession(['teacher_id' => $admin->id])->get(route('school.audit', ['actor' => 'parent', 'action' => 'login']))->assertOk()->assertSee('parent.login.store')->assertDontSee('WrongSecretPassword');
        $export = $this->get(route('school.audit.export'))->assertOk()->assertDownload('NGF-audit-trail.csv');
        $this->assertStringContainsString('parent.login.store', $export->streamedContent());
        $this->assertSame('school.audit.export', AuditEvent::latest('id')->firstOrFail()->action);
        if ($preview = getenv('COMMUNITY_PREVIEW')) {
            file_put_contents($preview.'/audit-report.html', $this->get(route('school.audit'))->getContent());
        }
        $this->get(route('school.audit', ['from' => '2026-11-01', 'to' => '2026-11-30', 'failed' => 1]))->assertOk();
    }
}
