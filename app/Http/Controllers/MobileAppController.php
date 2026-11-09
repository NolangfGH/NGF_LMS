<?php

namespace App\Http\Controllers;

use App\Models\ParentAccount;
use App\Models\Student;
use App\Models\TeacherAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MobileAppController extends Controller
{
    private const APK_PATH = 'mobile/ngf-codequest-1.0.0.apk';

    public function index(): Response
    {
        $disk = Storage::disk('local');
        $available = $disk->exists(self::APK_PATH);

        return response()->view('mobile-app', [
            'apkAvailable' => $available,
            'apkSize' => $available ? round($disk->size(self::APK_PATH) / 1048576, 2) : null,
            'apkChecksum' => $available ? hash_file('sha256', $disk->path(self::APK_PATH)) : null,
        ])->header('Cache-Control', 'private, no-store');
    }

    public function start(): RedirectResponse
    {
        $teacher = TeacherAccount::find(session('teacher_id'));
        if ($teacher?->hasStaffAccess()) {
            return redirect()->route($teacher->dashboardRoute());
        }
        if (Student::whereHas('classroom', fn ($query) => $query->whereNull('archived_at'))->find(session('student_id'))) {
            return redirect()->route('student.dashboard');
        }
        if (ParentAccount::find(session('parent_id'))) {
            return redirect()->route('parent.dashboard');
        }

        return redirect()->route('student.login');
    }

    public function android(): StreamedResponse
    {
        abort_unless(Storage::disk('local')->exists(self::APK_PATH), 404, 'The Android download is not available yet. Use website installation in the meantime.');

        return Storage::disk('local')->download(self::APK_PATH, 'NGF-CodeQuest-1.0.0.apk', [
            'Content-Type' => 'application/vnd.android.package-archive',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
