<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Models\TeacherAccount;
use App\Services\CourseAssessments;
use App\Services\CoursePostTest;
use App\Services\LearningNotifications;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class CoursePostTestController extends Controller
{
    public function __construct(private CoursePostTest $exam) {}

    public function show(CourseAssessments $course): Response|RedirectResponse
    {
        $student = Student::with('classroom')->find(session('student_id'));
        if (! $student) {
            return redirect()->route('student.login');
        }

        $state = $this->exam->state($student);
        $student->refresh();

        return response()->view('course-assessments.post-test', [
            'student' => $student, 'classroom' => $student->classroom,
            'lastSyncedAt' => now(config('learning.timezone'))->format('M j, Y · g:i A'),
            'state' => $state, 'courseProgress' => $course->progress($student),
        ], 200, ['Cache-Control' => 'private, no-store', 'Permissions-Policy' => 'display-capture=()']);
    }

    public function requestCode(LearningNotifications $notifications): RedirectResponse
    {
        $this->exam->requestCode($this->student(), $notifications);

        return redirect()->route('student.post-test.show')->with('post_test_notice', 'Your teacher has been notified.');
    }

    public function start(Request $request): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'regex:/^[a-fA-F0-9]{10}$/']]);
        $this->exam->start($this->student(), $data['code']);

        return redirect()->route('student.post-test.show');
    }

    public function draft(Request $request): JsonResponse|RedirectResponse
    {
        return $this->answer($request, false);
    }

    public function question(Request $request): JsonResponse|RedirectResponse
    {
        return $this->answer($request, true);
    }

    public function teacherRequests(Request $request): Response
    {
        $teacher = $this->teacher();
        $requests = DB::table('course_post_test_attempts as exam')
            ->join('students', 'students.id', '=', 'exam.student_id')
            ->join('classrooms', 'classrooms.id', '=', 'students.classroom_id')
            ->where('classrooms.teacher_account_id', $teacher->id)->whereNull('classrooms.archived_at')
            ->where('students.registration_status', 'approved')->whereNull('students.deleted_at')->whereNull('exam.started_at')
            ->when($request->integer('request'), fn ($query, int $id) => $query->orderByRaw('CASE WHEN exam.id = ? THEN 0 ELSE 1 END', [$id]))
            ->latest('exam.id')->paginate(25, ['exam.id', 'exam.attempt_number', 'exam.created_at', 'exam.code_issued_by', 'exam.code_expires_at',
                'students.first_name', 'students.surname', 'students.nickname', 'classrooms.grade', 'classrooms.section', 'classrooms.name']);

        return response()->view('course-assessments.code-requests', ['pageTitle' => 'Post-test codes', 'teacher' => $teacher, 'requests' => $requests])
            ->header('Cache-Control', 'private, no-store');
    }

    public function issue(int $requestId): RedirectResponse
    {
        $code = $this->exam->issueCode($this->teacher(), $requestId);

        return redirect()->route('teacher.post-test.index', ['request' => $requestId])->withFragment('request-'.$requestId)
            ->with('post_test_code', ['id' => $requestId, 'code' => $code]);
    }

    private function answer(Request $request, bool $advance): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'attempt_id' => ['required', 'integer', 'min:1'],
            'question_index' => ['required', 'integer', 'between:0,'.(CoursePostTest::QUESTIONS - 1)],
            'answer' => ['nullable', 'string', 'max:500'],
        ]);
        $result = $this->exam->answer($this->student(), (int) $data['attempt_id'], (int) $data['question_index'], $data['answer'] ?? null, $advance);

        return $request->expectsJson()
            ? response()->json($result)->header('Cache-Control', 'private, no-store')
            : redirect()->route('student.post-test.show');
    }

    private function student(): Student
    {
        $student = Student::find(session('student_id'));
        abort_unless($student, 401);

        return $student;
    }

    private function teacher(): TeacherAccount
    {
        $teacher = TeacherAccount::find(session('teacher_id'));
        abort_unless($teacher?->hasStaffAccess() && ! $teacher->isPrincipal(), 403);

        return $teacher;
    }
}
