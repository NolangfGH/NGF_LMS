<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Models\TeacherAccount;
use App\Services\CourseAssessments;
use App\Services\QuizGrader;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CourseAssessmentController extends Controller
{
    public function __construct(private CourseAssessments $assessments) {}

    public function index(): Response|RedirectResponse
    {
        $student = Student::with('classroom')->find(session('student_id'));
        if (! $student) {
            return redirect()->route('student.login');
        }

        return $this->studentView('course-assessments.index', $student, $this->assessments->results($student), [
            'certificate' => $this->assessments->certificate($student, CourseAssessments::selectedCourse()),
        ]);
    }

    public function show(string $assessment): Response|RedirectResponse
    {
        $student = Student::with('classroom')->find(session('student_id'));
        if (! $student) {
            return redirect()->route('student.login');
        }
        $results = $this->assessments->results($student);
        $result = $results->get($assessment);

        return $this->studentView('course-assessments.show', $student, $results, [
            'assessment' => $assessment, 'title' => CourseAssessments::TITLES[$assessment],
            'result' => $result, 'skills' => $result ? json_decode($result->skill_scores, true, flags: JSON_THROW_ON_ERROR) : [],
            'questions' => $result ? [] : $this->assessments->publicQuestions($assessment),
        ]);
    }

    public function submit(Request $request, string $assessment, QuizGrader $grader): RedirectResponse
    {
        $student = Student::with('classroom')->find(session('student_id'));
        abort_unless($student, 401);
        if ($this->assessments->hasCompleted($student, $assessment)) {
            return redirect()->route('student.assessments.show', [$assessment] + CourseAssessments::courseQuery());
        }
        $questions = $this->assessments->questions($assessment);
        $data = $request->validate($this->assessments->answerRules($questions));
        $this->assessments->submit($student, $assessment, $questions, $data['answers'], $grader);

        return redirect()->route('student.assessments.show', [$assessment] + CourseAssessments::courseQuery());
    }

    public function studentCertificate(): Response|RedirectResponse
    {
        $student = Student::find(session('student_id'));
        if (! $student) {
            return redirect()->route('student.login');
        }
        $certificate = $this->assessments->certificate($student, CourseAssessments::selectedCourse());
        abort_unless($certificate, 404);

        return response()->view('course-assessments.certificate', ['certificate' => $certificate, 'backRoute' => route('student.assessments.index', CourseAssessments::courseQuery())])
            ->header('Cache-Control', 'private, no-store');
    }

    public function editCertificate(int $student): Response
    {
        [$teacher, $learner] = $this->teacherAndStudent($student);

        return response()->view('course-assessments.edit-certificate', [
            'pageTitle' => 'Course certificate', 'teacher' => $teacher, 'student' => $learner,
            'certificate' => $this->assessments->certificate($learner, CourseAssessments::selectedCourse()), 'courseProgress' => $this->assessments->progress($learner, CourseAssessments::selectedCourse()), 'courseQuery' => CourseAssessments::courseQuery(),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function updateCertificate(Request $request, int $student): RedirectResponse
    {
        [$teacher, $learner] = $this->teacherAndStudent($student);
        $data = $request->validate([
            'certificate_id' => ['required', 'integer', 'min:1'], 'revision' => ['required', 'integer', 'min:1'],
            'title' => ['required', 'string', 'max:120'], 'message' => ['required', 'string', 'max:600'],
        ]);
        $updated = DB::transaction(function () use ($teacher, $learner, $data): int {
            $current = Student::whereKey($learner->id)->lockForUpdate()->firstOrFail();
            abort_unless($current->classroom?->teacher_account_id === $teacher->id && ! $current->classroom->archived_at, 403);

            return DB::table('course_certificates')->where('student_id', $current->id)->where('teacher_account_id', $teacher->id)
                ->where('course', CourseAssessments::selectedCourse())->where('id', $data['certificate_id'])->where('revision', $data['revision'])->update([
                    'title' => $data['title'], 'message' => $data['message'], 'revision' => (int) $data['revision'] + 1,
                ]);
        }, 3);
        abort_unless($updated === 1, 409, 'This certificate is unavailable for editing or was already updated. Reload the page.');

        return redirect()->route('teacher.certificates.edit', [$student] + CourseAssessments::courseQuery())->with('lesson_notice', 'Certificate wording updated.');
    }

    public function teacherCertificate(int $student): Response
    {
        [, $learner] = $this->teacherAndStudent($student);
        $certificate = $this->assessments->certificate($learner, CourseAssessments::selectedCourse());
        abort_unless($certificate, 404);

        return response()->view('course-assessments.certificate', ['certificate' => $certificate, 'backRoute' => route('teacher.certificates.edit', [$student] + CourseAssessments::courseQuery())])
            ->header('Cache-Control', 'private, no-store');
    }

    private function studentView(string $view, Student $student, Collection $results, array $data = []): Response
    {
        $courseKey = ($data['assessment'] ?? null) === CourseAssessments::ARDUINO_PRE ? 'arduino-uno' : CourseAssessments::selectedCourse();

        return response()->view($view, $data + [
            'student' => $student, 'classroom' => $student->classroom,
            'lastSyncedAt' => now(config('learning.timezone'))->format('M j, Y · g:i A'),
            'courseKey' => $courseKey, 'courseQuery' => CourseAssessments::courseQuery($courseKey), 'courseTitle' => $courseKey === 'arduino-uno' ? 'Arduino Uno' : 'C++', 'pretest' => $results->get($courseKey === 'arduino-uno' ? CourseAssessments::ARDUINO_PRE : CourseAssessments::PRE),
            'reasoning' => $results->get(CourseAssessments::REASONING), 'courseProgress' => $this->assessments->progress($student, $courseKey),
        ])->header('Cache-Control', 'private, no-store');
    }

    private function teacherAndStudent(int $id): array
    {
        $teacher = TeacherAccount::find(session('teacher_id'));
        abort_unless($teacher?->hasStaffAccess() && ! $teacher->isPrincipal(), 403);
        $student = Student::whereHas('classroom', fn ($query) => $query->where('teacher_account_id', $teacher->id)->whereNull('archived_at'))->findOrFail($id);

        return [$teacher, $student];
    }
}
