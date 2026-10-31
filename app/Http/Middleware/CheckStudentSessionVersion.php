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
        $protectedStudentPage = $request->routeIs('student.*') && ! $request->routeIs('student.login*', 'student.register*', 'student.registration.*', 'student.logout');
        if ($request->session()->has('student_id')) {
            $student = Student::withUnapproved()->with('classroom:id,archived_at')->select('id', 'classroom_id', 'session_version', 'registration_status')->find($request->session()->get('student_id'));
            if ($student && ! $student->isRegistrationApproved()) {
                $request->session()->forget(['student_id', 'student_session_version', 'student_name', 'student_class_verified', 'classroom_id', 'classroom_name', 'verified_join_code']);
                $request->session()->put('pending_registration_id', $student->id);
                $request->session()->regenerateToken();
                if ($protectedStudentPage) {
                    return $request->expectsJson()
                        ? response()->json(['message' => 'Your registration needs approval.', 'redirect' => route('student.registration.status')], 403)
                        : redirect()->route('student.registration.status');
                }
            } elseif (! $student || $student->classroom?->archived_at || (int) $request->session()->get('student_session_version', 1) !== $student->session_version) {
                $request->session()->forget(['student_id', 'student_session_version', 'student_name', 'student_class_verified', 'classroom_id', 'classroom_name', 'verified_join_code']);
                $request->session()->regenerateToken();
            } else {
                $request->session()->put('student_session_version', $student->session_version);
            }
        }
        if ($protectedStudentPage && ! $request->session()->has('student_id') && $request->session()->has('pending_registration_id')) {
            $pending = Student::withUnapproved()->find($request->session()->get('pending_registration_id'));
            if ($pending && ! $pending->isRegistrationApproved()) {
                return $request->expectsJson()
                    ? response()->json(['message' => 'Your registration needs approval.', 'redirect' => route('student.registration.status')], 403)
                    : redirect()->route('student.registration.status');
            }
        }

        return $next($request);
    }
}
