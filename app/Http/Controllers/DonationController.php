<?php

namespace App\Http\Controllers;

use App\Models\PortalSetting;
use App\Models\TeacherAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
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

        return response()->view('donation-settings', compact('teacher', 'donation'))->header('Cache-Control', 'private, no-store');
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
