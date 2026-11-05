<?php

namespace App\Http\Middleware;

use App\Models\Student;
use App\Services\CourseAssessments;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireCoursePretest
{
    public function __construct(private CourseAssessments $assessments) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $student = $request->session()->has('student_id') ? Student::find($request->session()->get('student_id')) : null;
        if ($student && ! $this->assessments->hasCompleted($student, CourseAssessments::PRE)) {
            return $request->expectsJson()
                ? response()->json(['message' => 'Finish the C++ pre-test to unlock the lessons.', 'redirect' => route('student.assessments.show', CourseAssessments::PRE)], 403)
                : redirect()->route('student.assessments.show', CourseAssessments::PRE);
        }

        return $next($request);
    }
}
