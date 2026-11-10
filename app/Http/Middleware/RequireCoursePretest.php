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
        $arduino = config('arduino_curriculum.lessons.'.$request->route('lesson')) !== null && $request->route('lesson') !== null;
        if ($student && ! $this->assessments->hasCompleted($student, $arduino ? CourseAssessments::ARDUINO_PRE : CourseAssessments::PRE)) {
            return $request->expectsJson()
                ? response()->json(['message' => 'Finish the C++ pre-test to unlock the lessons.', 'redirect' => route('student.assessments.show', $arduino ? CourseAssessments::ARDUINO_PRE : CourseAssessments::PRE)], 403)
                : redirect()->route('student.assessments.show', $arduino ? CourseAssessments::ARDUINO_PRE : CourseAssessments::PRE);
        }

        return $next($request);
    }
}
