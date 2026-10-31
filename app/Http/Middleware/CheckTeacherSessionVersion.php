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
            $teacher = TeacherAccount::select('id', 'session_version')->find($request->session()->get('teacher_id'));
            $version = $request->session()->get('teacher_session_version', 1);
            if (! $teacher || (int) $version !== (int) $teacher->session_version) {
                $request->session()->forget(['teacher_id', 'teacher_session_version', 'teacher_authenticated_at']);
                $request->session()->regenerateToken();
            } else {
                $request->session()->put('teacher_session_version', $teacher->session_version);
            }
        }

        return $next($request);
    }
}
