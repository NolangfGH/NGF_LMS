<?php

namespace Tests\Feature;

use App\Models\TeacherAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class DonationReceiptsTest extends TestCase
{
    use RefreshDatabase;

    private function payload(?string $key = null): array
    {
        return ['request_key' => $key ?? (string) Str::uuid(), 'sender_name' => 'Private supporter', 'amount' => '125.50', 'message' => 'Private message for learning.',
            'proof' => UploadedFile::fake()->createWithContent('confirmation.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jX1cAAAAASUVORK5CYII='))];
    }

    private function staff(string $role): TeacherAccount
    {
        $teacher = TeacherAccount::create(['first_name' => 'Donation', 'last_name' => 'Reviewer', 'school' => 'School', 'email' => fake()->unique()->safeEmail(), 'password' => 'Password123']);
        $teacher->forceFill(['role' => $role])->save();

        return $teacher;
    }

    public function test_donor_can_save_a_private_record_once_without_confirming_payment_or_awarding_xp(): void
    {
        Storage::fake('local');
        $key = (string) Str::uuid();
        $this->post(route('donations.receipt'), $this->payload($key))->assertRedirect(route('donations').'#record-donation')->assertSessionHasNoErrors()->assertSessionHas('portal_notice');
        $record = DB::table('donation_receipts')->first();
        $this->assertSame('pending', $record->status);
        $this->assertEquals(125.50, $record->amount);
        $this->assertSame('PHP', $record->currency);
        $this->assertSame('Private message for learning.', $record->message);
        Storage::disk('local')->assertExists($record->proof_path);
        $this->post(route('donations.receipt'), $this->payload($key))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseCount('donation_receipts', 1);
        $this->assertCount(1, Storage::disk('local')->allFiles('donation-receipts'));
        $this->assertDatabaseEmpty('xp_events');
        $this->get(route('donations'))->assertOk()->assertDontSee('Private supporter')->assertDontSee('Private message for learning.')->assertDontSee($record->proof_path);
        $this->get(route('school.donations.receipt.image', $record->id))->assertForbidden();
        $this->putJson(route('school.donations.receipt.review', $record->id), ['revision' => 1, 'status' => 'verified'])->assertForbidden();
    }

    public function test_receipts_require_valid_amount_and_safe_bounded_image(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        Storage::fake('local');
        foreach (['0', '-10', '1.234', '1000000', 'not a number'] as $amount) {
            $this->postJson(route('donations.receipt'), array_replace($this->payload(), ['amount' => $amount]))->assertUnprocessable()->assertJsonValidationErrors('amount');
        }
        $this->postJson(route('donations.receipt'), array_replace($this->payload(), ['proof' => UploadedFile::fake()->createWithContent('script.svg', '<svg><script>alert(1)</script></svg>')]))->assertUnprocessable()->assertJsonValidationErrors('proof');
        $this->postJson(route('donations.receipt'), array_replace($this->payload(), ['proof' => UploadedFile::fake()->create('large.png', 2049, 'image/png')]))->assertUnprocessable()->assertJsonValidationErrors('proof');
        $this->assertDatabaseEmpty('donation_receipts');
        $this->assertCount(0, Storage::disk('local')->allFiles('donation-receipts'));
    }

    public function test_only_system_admin_can_view_private_images_and_review_with_a_current_revision(): void
    {
        Storage::fake('local');
        $this->post(route('donations.receipt'), $this->payload())->assertRedirect();
        $record = DB::table('donation_receipts')->first();
        foreach (['teacher', 'principal'] as $role) {
            $this->withSession(['teacher_id' => $this->staff($role)->id])->get(route('school.donations'))->assertForbidden();
            $this->get(route('school.donations.receipt.image', $record->id))->assertForbidden();
            $this->putJson(route('school.donations.receipt.review', $record->id), ['revision' => 1, 'status' => 'verified'])->assertForbidden();
        }
        $admin = $this->staff('school_admin');
        $page = $this->withSession(['teacher_id' => $admin->id])->get(route('school.donations'))->assertOk()->assertSee('Private supporter')->assertSee('Private message for learning.')->assertSee('125.50')->assertSee('Awaiting verification');
        $this->get(route('school.donations.receipt.image', $record->id))->assertOk()->assertHeader('Content-Type', 'image/png')->assertHeader('Cache-Control', 'no-store, private');
        $this->putJson(route('school.donations.receipt.review', $record->id), ['revision' => 1, 'status' => 'made-up'])->assertUnprocessable();
        $this->put(route('school.donations.receipt.review', $record->id), ['revision' => 1, 'status' => 'verified', 'review_note' => 'Matched provider record.'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('donation_receipts', ['id' => $record->id, 'status' => 'verified', 'revision' => 2, 'reviewed_by' => $admin->id, 'review_note' => 'Matched provider record.']);
        $this->putJson(route('school.donations.receipt.review', $record->id), ['revision' => 1, 'status' => 'not_verified'])->assertConflict();
        $this->get(route('school.donations.receipt.image', 999999))->assertNotFound();
        if ($preview = getenv('LEARNING_SUPPORT_PREVIEW')) {
            file_put_contents($preview.'/donation-records.html', $page->getContent());
        }
    }
}
