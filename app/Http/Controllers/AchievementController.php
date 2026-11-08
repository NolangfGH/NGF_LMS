<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Models\TeacherAccount;
use App\Services\AchievementService;
use App\Services\XpLedger;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AchievementController extends Controller
{
    public function goal(Request $request, AchievementService $service)
    {
        $student = Student::find(session('student_id'));
        abort_unless($student, 401);
        $data = $request->validate(['badge_code' => ['nullable', Rule::in(array_keys($service->catalog()))]]);
        if (! empty($data['badge_code']) && $service->sync($student)['cards']->firstWhere('code', $data['badge_code'])['earned']) {
            throw ValidationException::withMessages(['badge_code' => 'You already earned this badge. Choose a goal you have not completed.']);
        }
        $student->update(['achievement_goal' => $data['badge_code'] ?? null]);

        return redirect()->route('student.achievements')->with('achievement_notice', 'Your learning goal has been updated.');
    }

    private function teacherData(Request $request): array
    {
        $teacher = TeacherAccount::find(session('teacher_id'));
        abort_unless($teacher, 403);
        $rooms = $teacher->classrooms()->orderBy('grade')->orderBy('section')->get();
        $filter = $request->validate(['classroom' => ['nullable', 'integer', 'min:1'], 'search' => ['nullable', 'string', 'max:100']]);
        $selected = isset($filter['classroom']) ? (int) $filter['classroom'] : null;
        abort_if($selected && ! $rooms->contains('id', $selected), 403);
        $students = Student::with('classroom')->whereIn('classroom_id', $selected ? [$selected] : $rooms->pluck('id'));
        $search = trim($filter['search'] ?? '');
        if ($search !== '') {
            $students->where(fn ($query) => $query->where('nickname', 'like', '%'.$search.'%')->orWhere('student_id', 'like', '%'.$search.'%'));
        }

        return compact('teacher', 'rooms', 'students', 'selected', 'search');
    }

    public function teacher(Request $request, AchievementService $service)
    {
        if (! TeacherAccount::find(session('teacher_id'))) {
            return redirect()->route('teacher.login');
        }
        $data = $this->teacherData($request);
        $students = $data['students']->orderBy('nickname')->orderBy('id')->paginate(25)->withQueryString();
        $reports = $students->getCollection()->mapWithKeys(fn ($student) => [$student->id => $service->sync($student)]);

        return response()->view('teacher-achievements', [...$data, 'students' => $students, 'reports' => $reports, 'catalog' => $service->catalog()])->header('Cache-Control', 'private, no-store');
    }

    public function export(Request $request, AchievementService $service)
    {
        $data = $this->teacherData($request);

        return response()->streamDownload(function () use ($data, $service) {
            $stream = fopen('php://output', 'w');
            fputcsv($stream, ['Student ID', 'Coder cadet', 'Grade', 'Section', 'Badges earned', 'Available badges', 'Quizzes completed', 'Coding activities submitted', 'Strong solutions reviewed', 'Learning days', 'Next goal'], ',', '"', '');
            $data['students']->orderBy('id')->chunkById(100, function ($students) use ($stream, $service) {
                foreach ($students as $student) {
                    $report = $service->sync($student);
                    $cells = [$student->student_id, $student->nickname, $student->classroom->grade, $student->classroom->section ?? $student->classroom->name, $report['earned'], $report['total'], $report['metrics']['quizzes_completed'], $report['metrics']['submitted_activities'], $report['metrics']['strong_solutions'], $report['metrics']['active_days'], $report['focus']['name'] ?? 'All milestones earned'];
                    $safe = array_map(fn ($cell) => preg_match('/^(?:\\s*[=+@-]|[\\t\\r\\n])/u', (string) $cell) ? "'".$cell : $cell, $cells);
                    fputcsv($stream, $safe, ',', '"', '');
                }
            });
            fclose($stream);
        }, 'codequest-achievements.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store']);
    }

    public function teacherXp(Request $request, int $student, XpLedger $ledger): Response
    {
        $teacher = TeacherAccount::find(session('teacher_id'));
        abort_unless($teacher?->hasStaffAccess() && ! $teacher->isPrincipal(), 403);
        $learner = Student::with('classroom')->whereHas('classroom', fn ($query) => $query->where('teacher_account_id', $teacher->id)->whereNull('archived_at'))->findOrFail($student);

        return response()->view('teacher-xp-history', ['teacher' => $teacher, 'learner' => $learner, 'xpHistory' => $ledger->history($learner, $request), 'xpHistoryRoute' => route('teacher.achievements.xp', $learner->id)])->header('Cache-Control', 'private, no-store');
    }
}
