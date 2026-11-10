<?php

namespace App\Http\Middleware;

use App\Models\LessonContent;
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
        $content = $request->route('content');
        if ($content && ! $content instanceof LessonContent) {
            $content = LessonContent::find($content);
        }
        $lesson = $request->route('lesson') ?? $content?->lesson_key;
        $arduino = $lesson ? isset(config('arduino_curriculum.lessons')[$lesson]) : CourseAssessments::selectedCourse() === 'arduino-uno';
        $course = $arduino ? 'arduino-uno' : 'cpp';
        $required = $student ? $this->assessments->nextLessonAssessment($student, $course) : null;
        if ($required) {
            $redirect = route('student.assessments.show', [$required] + ($required === CourseAssessments::REASONING ? CourseAssessments::courseQuery($course) : []));

            return $request->expectsJson()
                ? response()->json(['message' => 'Complete the '.CourseAssessments::TITLES[$required].' to unlock the lessons.', 'redirect' => $redirect], 403)
                : redirect()->to($redirect);
        }

        return $next($request);
    }
}
