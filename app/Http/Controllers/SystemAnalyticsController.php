<?php

namespace App\Http\Controllers;

use App\Models\TeacherAccount;
use App\Models\TeacherQuiz;
use App\Services\ExamItemAnalysis;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SystemAnalyticsController extends Controller
{
    public function index(Request $request, ExamItemAnalysis $analysis): Response|RedirectResponse
    {
        $teacher = TeacherAccount::find($request->session()->get('teacher_id'));
        if (! $teacher) {
            return redirect()->route('teacher.login');
        }
        abort_unless($teacher->hasStaffAccess() && ! $teacher->isPrincipal(), 403);
        $filters = $request->validate([
            'classroom' => ['nullable', 'integer', 'min:1'],
            'term' => ['nullable', Rule::in(['Term1', 'Term2', 'Term3'])],
            'exam' => ['nullable', 'integer', 'min:1'],
            'version' => ['nullable', 'string', 'regex:/\A[a-f0-9]{64}\z/'],
        ]);
        $classrooms = $teacher->classrooms()->orderBy('grade')->orderBy('section')->get(['id', 'name', 'grade', 'section']);
        $selectedRoom = isset($filters['classroom']) ? $classrooms->firstWhere('id', (int) $filters['classroom']) : null;
        abort_if(isset($filters['classroom']) && ! $selectedRoom, 404);
        $scope = $selectedRoom ? collect([$selectedRoom->id]) : $classrooms->pluck('id');

        $terms = DB::table('grading_assessments as assessment')
            ->join('grading_sheets as sheet', 'sheet.id', '=', 'assessment.grading_sheet_id')
            ->where('sheet.teacher_account_id', $teacher->id)->whereIn('sheet.classroom_id', $scope)
            ->where('assessment.source_type', 'quiz')->get(['assessment.source_id', 'sheet.term'])->groupBy('source_id');
        $exams = TeacherQuiz::where('teacher_account_id', $teacher->id)->where('status', 'published')
            ->where(fn ($query) => $query->whereIn('classroom_id', $scope)
                ->orWhereHas('classrooms', fn ($rooms) => $rooms->whereIn('classrooms.id', $scope)))
            ->with('classrooms:id')->latest('id')->get(['id', 'classroom_id', 'title']);
        $exams->each(function (TeacherQuiz $exam) use ($terms): void {
            $linked = $terms->get($exam->id, collect())->pluck('term')->unique()->sort()->values()->all();
            if ($linked === [] && preg_match('/\bterm[\s_-]*([123])\b/i', $exam->title, $matches)) {
                $linked = ['Term'.$matches[1]];
            }
            $exam->setAttribute('report_terms', $linked);
        });
        if (isset($filters['term'])) {
            $exams = $exams->filter(fn (TeacherQuiz $exam): bool => in_array($filters['term'], $exam->report_terms, true));
        }

        $exam = isset($filters['exam']) ? $exams->firstWhere('id', (int) $filters['exam']) : null;
        abort_if(isset($filters['exam']) && ! $exam, 404);
        $report = null;
        if ($exam) {
            $targets = $exam->classrooms->pluck('id')->push($exam->classroom_id);
            $exam->questions = TeacherQuiz::where('teacher_account_id', $teacher->id)->where('status', 'published')->findOrFail($exam->id)->questions;
            $report = $analysis->report($exam, $scope->intersect($targets)->values(), $filters['version'] ?? null);
        }

        return response()->view('system-analytics', compact('teacher', 'classrooms', 'selectedRoom', 'filters', 'exams', 'exam', 'report'))
            ->header('Cache-Control', 'private, no-store');
    }
}
