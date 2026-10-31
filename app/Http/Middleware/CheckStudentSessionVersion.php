<?php

namespace App\Http\Middleware;

use App\Models\Student;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckStudentSessionVersion
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->session()->has('student_id')) {
            $student = Student::with('classroom:id,archived_at')->select('id', 'classroom_id', 'session_version')->find($request->session()->get('student_id'));
            if (! $student || $student->classroom?->archived_at || (int) $request->session()->get('student_session_version', 1) !== $student->session_version) {
                $request->session()->forget(['student_id', 'student_session_version', 'student_name', 'student_class_verified', 'classroom_id', 'classroom_name', 'verified_join_code']);
                $request->session()->regenerateToken();
            } else {
                $request->session()->put('student_session_version', $student->session_version);
            }
        }

        return $next($request);
    }
}
