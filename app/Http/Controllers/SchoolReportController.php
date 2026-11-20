<?php

namespace App\Http\Controllers;

use App\Models\Classroom;
use App\Models\Student;
use App\Models\TeacherAccount;
use App\Services\ClassReportService;
use App\Services\DepEdSubjectGrading;
use App\Services\LearningCourses;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SchoolReportController extends Controller
{
    private function data(Request $request, ClassReportService $service): array
    {
        $teacher = TeacherAccount::find(session('teacher_id'));
        abort_unless($teacher?->hasStaffAccess() && ($teacher->isPrincipal() || $teacher->isSchoolAdmin()), 403);
        $filters = $request->validate(['classroom' => ['nullable', 'integer', 'min:1'], 'grade' => ['nullable', Rule::in(['Grade 7', 'Grade 8', 'Grade 9', 'Grade 10'])],
            'course' => ['nullable', Rule::in(LearningCourses::keys())], 'range' => ['nullable', Rule::in(['all', 'week', 'month', 'custom'])],
            'start' => ['required_if:range,custom', 'nullable', 'date_format:Y-m-d'], 'end' => ['required_if:range,custom', 'nullable', 'date_format:Y-m-d', 'after_or_equal:start'],
            'search' => ['nullable', 'string', 'max:100']]);
        $owners = TeacherAccount::where('school', $teacher->school)->where('staff_access_status', 'active')->whereIn('role', ['teacher', 'school_admin'])->get()->keyBy('id');
        $rooms = Classroom::whereIn('teacher_account_id', $owners->keys())->whereNull('archived_at')->orderBy('grade')->orderBy('section')->get();
        $selected = isset($filters['classroom']) ? $rooms->firstWhere('id', (int) $filters['classroom']) : null;
        abort_if(isset($filters['classroom']) && ! $selected, 404);
        $scope = $selected ? $rooms->where('id', $selected->id) : $rooms;
        if (isset($filters['grade'])) {
            $scope = $scope->where('grade', $filters['grade']);
        }
        $range = $filters['range'] ?? 'all';
        $now = now(config('learning.timezone'));
        $until = $now->copy()->addDay()->startOfDay();
        $from = match ($range) {
            'week' => $now->copy()->startOfWeek(), 'month' => $now->copy()->startOfMonth(),
            'custom' => Carbon::createFromFormat('!Y-m-d', $filters['start'], config('learning.timezone')),
            default => null,
        };
        if ($range === 'custom') {
            $until = Carbon::createFromFormat('!Y-m-d', $filters['end'], config('learning.timezone'))->addDay();
            if ($from->diffInDays($until) > 366) {
                throw ValidationException::withMessages(['end' => 'Select a date range of 366 days or fewer.']);
            }
        }
        $course = $filters['course'] ?? null;
        $rows = collect();
        $metrics = ['students' => 0, 'completed' => 0, 'opportunities' => 0, 'graded_quizzes' => 0, 'confirmed' => 0, 'support' => 0, 'pending' => 0];
        foreach ($scope->groupBy('teacher_account_id') as $ownerId => $classrooms) {
            $report = $service->build($owners[$ownerId], $classrooms, $from?->copy()->utc(), $until->copy()->utc(), $course);
            $rows = $rows->concat($report['rows']->map(fn (array $row): array => $row + ['teacher' => $owners[$ownerId]->fullName()]));
            foreach ($metrics as $key => $value) {
                $metrics[$key] += $report['metrics'][$key];
            }
        }
        $metrics['completion'] = $metrics['opportunities'] ? round(100 * $metrics['completed'] / $metrics['opportunities'], 1) : null;
        $scored = $rows->whereNotNull('quiz_average');
        $metrics['quiz_average'] = $scored->isNotEmpty() ? round($scored->avg('quiz_average'), 1) : null;
        $sectionSummaries = $rows->groupBy('classroom_id')->map(fn ($learners) => ['grade' => $learners->first()['grade'], 'section' => $learners->first()['section'], 'teacher' => $learners->first()['teacher'],
            'learners' => $learners->count(), 'completed' => $learners->sum('lessons'), 'opportunities' => $learners->sum('lesson_total'), 'quiz_average' => $learners->whereNotNull('quiz_average')->avg('quiz_average'), 'pending' => $learners->sum('pending')]);
        $grading = app(DepEdSubjectGrading::class);
        $rosters = Student::whereIn('classroom_id', $scope->pluck('id'))->get(['id', 'classroom_id'])->groupBy('classroom_id');
        $gradebooks = DB::table('subject_gradebooks')->whereIn('classroom_id', $scope->pluck('id'))->whereIn('teacher_account_id', $owners->keys())->when($course, fn ($query) => $query->where('course', $course))->latest('school_year')->latest('term')->get()->filter(fn ($sheet) => (int) $scope->firstWhere('id', $sheet->classroom_id)?->teacher_account_id === (int) $sheet->teacher_account_id)->map(function ($sheet) use ($grading, $rosters, $scope, $owners): array {
            $definition = json_decode($sheet->definition, true, 512, JSON_THROW_ON_ERROR);
            $entries = json_decode($sheet->entries, true, 512, JSON_THROW_ON_ERROR);
            $learners = $rosters->get($sheet->classroom_id, collect());
            $grades = [];
            foreach ($learners as $learner) {
                $entry = $entries[$learner->id] ?? [];
                if (! empty($entry['reviewed'])) {
                    $result = $grading->calculate($definition, $entry['scores'] ?? [], $sheet->school_year);
                    if ($result['complete']) {
                        $grades[] = $result['grade'];
                    }
                }
            }

            return ['subject' => LearningCourses::title($sheet->course), 'year' => $sheet->school_year, 'term' => $sheet->term, 'classroom' => $scope->firstWhere('id', $sheet->classroom_id), 'teacher' => $owners[$sheet->teacher_account_id]->fullName(), 'reviewed' => count($grades), 'learners' => $learners->count(), 'average' => $grades ? round(array_sum($grades) / count($grades), 1) : null];
        })->values();
        $search = trim($filters['search'] ?? '');
        $rows = $rows->filter(fn ($row) => $search === '' || str_contains(mb_strtolower($row['nickname'].' '.$row['student_id'].' '.$row['section']), mb_strtolower($search)))->sortBy(['grade', 'section', 'nickname'])->values();
        $period = $range === 'all' ? 'All saved history' : $from->format('M j, Y').' – '.$until->copy()->subDay()->format('M j, Y');

        return compact('teacher', 'owners', 'rooms', 'selected', 'filters', 'range', 'course', 'rows', 'metrics', 'sectionSummaries', 'period', 'gradebooks');
    }

    public function index(Request $request, ClassReportService $service): Response
    {
        return response()->view('school-reports', $this->data($request, $service))->header('Cache-Control', 'private, no-store');
    }

    public function export(Request $request, ClassReportService $service): StreamedResponse
    {
        $data = $this->data($request, $service);
        $safe = fn ($value) => is_string($value) && preg_match('/^[\s]*[=+@-]/u', $value) ? "'".$value : $value;

        return response()->streamDownload(function () use ($data, $safe): void {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['School report', $data['period'], $data['course'] ? LearningCourses::title($data['course']) : 'Grade-recommended checkpoints; all quizzes and practical tasks'], ',', '"', '');
            fputcsv($file, ['Student ID', 'Nickname', 'Grade', 'Section', 'Teacher', 'Checkpoints', 'Path total', 'Quiz average (%)', 'Activities', 'Pending reviews', 'Coaching status'], ',', '"', '');
            foreach ($data['rows'] as $row) {
                fputcsv($file, array_map($safe, [$row['student_id'], $row['nickname'], $row['grade'], $row['section'], $row['teacher'], $row['lessons'], $row['lesson_total'], $row['quiz_average'], $row['coding_count'], $row['pending'], $row['status']]), ',', '"', '');
            }
            fclose($file);
        }, 'school-learning-report.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store']);
    }
}
