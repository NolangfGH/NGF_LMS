<?php

namespace App\Services;

use App\Models\CodeRun;
use App\Models\CodeWorkspace;
use App\Models\Student;
use App\Models\TeacherAccount;
use App\Models\TeacherAssignment;
use App\Models\TeacherQuiz;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    public function student(Student $student): array
    {
        $path = app(LessonService::class)->path($student);
        $assignments = TeacherAssignment::where('teacher_account_id', $student->classroom->teacher_account_id)->forClassroom($student->classroom_id)->where('status', 'published')->orderByRaw('due_at IS NULL')->orderBy('due_at')->limit(8)->get();
        $workspaces = CodeWorkspace::with('submissions')->where('student_id', $student->id)->whereIn('teacher_assignment_id', $assignments->pluck('id'))->get()->keyBy('teacher_assignment_id');
        $quizzes = TeacherQuiz::where('teacher_account_id', $student->classroom->teacher_account_id)->forClassroom($student->classroom_id)->where('status', 'published')->orderByRaw('closes_at IS NULL')->orderBy('closes_at')->limit(6)->get();
        $quizResults = DB::table('quiz_submissions')->where('student_id', $student->id)->whereIn('teacher_quiz_id', $quizzes->pluck('id'))->pluck('teacher_quiz_id');

        return compact('path', 'assignments', 'workspaces', 'quizzes', 'quizResults');
    }

    public function teacher(TeacherAccount $teacher): array
    {
        $rooms = $teacher->classrooms()->withCount('students')->orderBy('section')->get();
        $report = app(ClassReportService::class)->build($teacher, $rooms, null, now()->addSecond());
        $assignments = TeacherAssignment::where('teacher_account_id', $teacher->id)->where('status', 'published')->where(fn ($query) => $query->whereNull('due_at')->orWhere('due_at', '>', now()))->count();
        $errors = [];
        $passed = 0;
        $total = 0;
        foreach (CodeRun::where('teacher_account_id', $teacher->id)->where('mode', 'tests')->where('status', 'completed')->where('created_at', '>=', now()->subDays(30))->get(['results']) as $run) {
            foreach ($run->results ?? [] as $result) {
                $total++;
                if ($result['passed'] ?? false) {
                    $passed++;
                } elseif (isset($result['status'])) {
                    $errors[$result['status']] = ($errors[$result['status']] ?? 0) + 1;
                }
            }
        }
        arsort($errors);
        $registrationQueue = app(StudentRegistrationApproval::class)->owned($teacher)->where('registration_status', 'pending');
        $pendingRegistrations = (clone $registrationQueue)->count();
        $recentRegistrations = $registrationQueue->with('classroom')->oldest()->limit(5)->get();

        return compact('rooms', 'report', 'assignments', 'errors', 'passed', 'total', 'pendingRegistrations', 'recentRegistrations');
    }
}
