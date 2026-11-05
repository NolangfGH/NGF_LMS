<?php

namespace App\Http\Middleware;

use App\Models\TeacherAccount;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckTeacherSessionVersion
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->session()->has('teacher_id')) {
            $teacher = TeacherAccount::select('id', 'session_version', 'role', 'staff_access_status')->find($request->session()->get('teacher_id'));
            $version = $request->session()->get('teacher_session_version', 1);
            if (! $teacher || ! $teacher->hasStaffAccess() || (int) $version !== (int) $teacher->session_version) {
                $request->session()->forget(['teacher_id', 'teacher_session_version', 'teacher_authenticated_at']);
                $request->session()->regenerateToken();
            } else {
                $request->session()->put('teacher_session_version', $teacher->session_version);
                if ($teacher->isPrincipal() && $request->routeIs('teacher.*', 'school.*', 'excuses.*')) {
                    if ($request->routeIs('teacher.dashboard')) {
                        return redirect()->route('school.overview');
                    }
                    abort_unless($request->routeIs('school.overview', 'school.ilaw.*', 'teacher.settings', 'teacher.settings.account', 'teacher.settings.profile', 'teacher.settings.preferences', 'teacher.settings.password', 'teacher.settings.sessions', 'teacher.settings.avatar*', 'teacher.logout', 'teacher.login*', 'teacher.register*'), 403, 'Principal access is limited to the school overview, lesson plan reviews and personal account settings.');
                }
            }
        }

        return $next($request);
    }
}
