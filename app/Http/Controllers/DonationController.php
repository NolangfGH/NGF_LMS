<?php

namespace App\Http\Controllers;

use App\Models\PortalSetting;
use App\Models\TeacherAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class DonationController extends Controller
{
    private function admin(): TeacherAccount
    {
        $teacher = TeacherAccount::find(session('teacher_id'));
        abort_unless($teacher?->hasStaffAccess() && $teacher->isSchoolAdmin(), 403);

        return $teacher;
    }

    public function index(): Response
    {
        $donation = PortalSetting::where('key', 'donations')->value('value') ?? [];

        return response()->view('donations', compact('donation'))->header('Cache-Control', 'no-store');
    }

    public function settings(): Response
    {
        $teacher = $this->admin();
        $donation = PortalSetting::where('key', 'donations')->value('value') ?? [];

        $receipts = DB::table('donation_receipts')->latest('id')->paginate(15)->withQueryString();

        return response()->view('donation-settings', compact('teacher', 'donation', 'receipts'))->header('Cache-Control', 'private, no-store');
    }

    public function receipt(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'request_key' => ['required', 'uuid'], 'sender_name' => ['nullable', 'string', 'max:120'],
            'amount' => ['required', 'numeric', 'decimal:0,2', 'min:1', 'max:999999.99'],
            'proof' => ['required', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048', 'dimensions:max_width=4096,max_height=4096'],
            'message' => ['nullable', 'string', 'max:500'],
        ]);
        $hash = hash_hmac('sha256', $data['request_key'], config('app.key'));
        $existing = DB::table('donation_receipts')->where('submission_hash', $hash)->first();
        if ($existing) {
            return redirect()->route('donations')->withFragment('record-donation')->with('portal_notice', 'Your donation record is saved. Reference '.$existing->reference.'. It is awaiting administrator verification.');
        }
        $path = $request->file('proof')->store('donation-receipts', 'local');
        if (! $path) {
            throw ValidationException::withMessages(['proof' => 'The screenshot could not be saved. Please try again.']);
        }
        try {
            $inserted = DB::table('donation_receipts')->insertOrIgnore([
                'reference' => (string) Str::ulid(), 'submission_hash' => $hash, 'sender_name' => $data['sender_name'] ?? null,
                'amount' => $data['amount'], 'currency' => 'PHP', 'proof_path' => $path, 'proof_mime' => $request->file('proof')->getMimeType(),
                'message' => $data['message'] ?? null, 'status' => 'pending', 'revision' => 1, 'created_at' => now(), 'updated_at' => now(),
            ]);
            if (! $inserted) {
                Storage::disk('local')->delete($path);
            }
            $reference = DB::table('donation_receipts')->where('submission_hash', $hash)->value('reference');
            if (! $reference) {
                throw ValidationException::withMessages(['proof' => 'Your donation record could not be saved. Please try again.']);
            }
        } catch (\Throwable $error) {
            Storage::disk('local')->delete($path);
            throw $error;
        }

        return redirect()->route('donations')->withFragment('record-donation')->with('portal_notice', 'Thank you. Your donation record is saved. Reference '.$reference.'. It is awaiting administrator verification.');
    }

    public function receiptImage(int $receipt): Response
    {
        $this->admin();
        $record = DB::table('donation_receipts')->find($receipt);
        abort_unless($record && Storage::disk('local')->exists($record->proof_path), 404);

        return response()->file(Storage::disk('local')->path($record->proof_path), ['Content-Type' => $record->proof_mime,
            'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff'])->setPrivate();
    }

    public function reviewReceipt(Request $request, int $receipt): RedirectResponse
    {
        $teacher = $this->admin();
        $data = $request->validate(['revision' => ['required', 'integer', 'min:1'], 'status' => ['required', Rule::in(['verified', 'not_verified'])], 'review_note' => ['nullable', 'string', 'max:500']]);
        abort_unless(DB::table('donation_receipts')->where('id', $receipt)->exists(), 404);
        $updated = DB::table('donation_receipts')->where('id', $receipt)->where('revision', $data['revision'])->update([
            'status' => $data['status'], 'review_note' => $data['review_note'] ?? null, 'revision' => (int) $data['revision'] + 1,
            'reviewed_by' => $teacher->id, 'reviewed_at' => now(), 'updated_at' => now(),
        ]);
        abort_unless($updated === 1, 409, 'This donation record changed in another tab. Reload before reviewing again.');

        return redirect()->route('school.donations')->withFragment('receipt-'.$receipt)->with('portal_notice', 'Donation review saved. No student grades or XP were changed.');
    }

    public function save(Request $request): Response
    {
        $this->admin();
        $data = $request->validate(['enabled' => ['required', 'boolean'], 'recipient' => ['nullable', 'string', 'max:180'], 'instructions' => ['nullable', 'string', 'max:3000'], 'payment_url' => ['nullable', 'url:https', 'max:1500'], 'qr' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048']]);
        $setting = PortalSetting::firstOrNew(['key' => 'donations']);
        $value = $setting->value ?? [];
        $value = array_replace($value, array_diff_key($data, ['qr' => true]));
        if ($request->hasFile('qr')) {
            $value['qr_path'] = $request->file('qr')->store('donation-qr', 'local');
        }
        $setting->value = $value;
        $setting->save();

        return back()->with('portal_notice', 'Donation page settings saved.');
    }

    public function image(): Response
    {
        $value = PortalSetting::where('key', 'donations')->value('value') ?? [];
        abort_unless(($value['enabled'] ?? false) && ! empty($value['qr_path']) && Storage::disk('local')->exists($value['qr_path']), 404);

        return Storage::disk('local')->response($value['qr_path'], null, ['Cache-Control' => 'no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}
