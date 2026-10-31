<?php

namespace App\Http\Controllers;

use App\Models\TeacherAccount;
use App\Services\ClassReportService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ClassReportController extends Controller
{
    private function data(Request $request, ClassReportService $service): array
    {
        $teacher = TeacherAccount::find(session('teacher_id'));
        abort_unless($teacher, 403);
        $rooms = $teacher->classrooms()->withCount('students')->orderBy('grade')->orderBy('section')->get();
        $filters = $request->validate([
            'classroom' => ['nullable', 'integer', 'min:1'], 'range' => ['nullable', Rule::in(['all', 'week', 'month', 'custom'])],
            'start' => ['required_if:range,custom', 'nullable', 'date_format:Y-m-d'],
            'end' => ['required_if:range,custom', 'nullable', 'date_format:Y-m-d', 'after_or_equal:start'],
            'search' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', Rule::in(['all', 'support', 'confirmed', 'progress', 'not_started'])],
            'sort' => ['nullable', Rule::in(['name', 'xp', 'lessons', 'quiz', 'active'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])], 'page' => ['nullable', 'integer', 'min:1'], 'print' => ['nullable', 'boolean'],
        ]);
        $selected = ! empty($filters['classroom']) ? (int) $filters['classroom'] : null;
        abort_if($selected && ! $rooms->contains('id', $selected), 403);
        $scope = $selected ? $rooms->where('id', $selected) : $rooms;
        $range = $filters['range'] ?? 'all';
        $now = now(config('learning.timezone'));
        $until = $now->copy()->addDay()->startOfDay();
        $from = match ($range) {
            'week' => $now->copy()->startOfWeek(),
            'month' => $now->copy()->startOfMonth(),
            'custom' => Carbon::createFromFormat('!Y-m-d', $filters['start'], config('learning.timezone')),
            default => null,
        };
        if ($range === 'custom') {
            $until = Carbon::createFromFormat('!Y-m-d', $filters['end'], config('learning.timezone'))->addDay();
            if ($from->diffInDays($until) > 366) {
                throw ValidationException::withMessages(['end' => 'Choose a custom range of 366 days or fewer.']);
            }
        }
        $label = $range === 'all' ? 'All saved history' : $from->format('M j, Y').' – '.$until->copy()->subDay()->format('M j, Y');
        $report = $service->build($teacher, $scope, $from?->copy()->utc(), $until->copy()->utc());
        $search = trim($filters['search'] ?? '');
        $status = $filters['status'] ?? 'all';
        $rows = $report['rows']->filter(fn ($row) => ($status === 'all' || $row['status'] === $status)
            && ($search === '' || str_contains(mb_strtolower($row['nickname'].' '.$row['student_id'].' '.$row['grade'].' '.$row['section']), mb_strtolower($search))));
        $sort = $filters['sort'] ?? 'name';
        $direction = $filters['direction'] ?? ($sort === 'name' ? 'asc' : 'desc');
        $sortKey = ['name' => 'nickname', 'xp' => 'xp', 'lessons' => 'lessons', 'quiz' => 'quiz_average', 'active' => 'last_active'][$sort];
        // Missing scores/activity always sort after recorded values.
        $withValues = $rows->filter(fn ($row) => $row[$sortKey] !== null);
        $rows = ($direction === 'asc' ? $withValues->sortBy($sortKey, SORT_NATURAL | SORT_FLAG_CASE) : $withValues->sortByDesc($sortKey, SORT_NATURAL | SORT_FLAG_CASE))
            ->concat($rows->filter(fn ($row) => $row[$sortKey] === null))->values();
        $query = array_filter(['classroom' => $selected, 'range' => $range, 'start' => $range === 'custom' ? $filters['start'] : null,
            'end' => $range === 'custom' ? $filters['end'] : null, 'search' => $search, 'status' => $status, 'sort' => $sort, 'direction' => $direction], fn ($value) => $value !== null && $value !== '');

        return compact('teacher', 'rooms', 'selected', 'report', 'rows', 'search', 'status', 'sort', 'direction', 'query', 'range', 'label')
            + ['scopeLabel' => $selected ? $scope->first()->grade.' · '.($scope->first()->section ?: $scope->first()->name) : 'All my sections',
                'generatedAt' => $now, 'statuses' => ['all' => 'All learners', 'support' => 'Needs support', 'confirmed' => 'Strong reviewed solution', 'progress' => 'Making progress', 'not_started' => 'No activity in period']];
    }

    public function index(Request $request, ClassReportService $service)
    {
        if (! TeacherAccount::find(session('teacher_id'))) {
            return redirect()->route('teacher.login');
        }
        $data = $this->data($request, $service);
        $print = $request->boolean('print');
        $page = $print ? 1 : (int) $request->input('page', 1);
        $perPage = $print ? max(1, $data['rows']->count()) : 25;
        $data['students'] = new LengthAwarePaginator($data['rows']->forPage($page, $perPage)->values(), $data['rows']->count(), $perPage, $page, ['path' => route('teacher.reports'), 'query' => $data['query'], 'fragment' => 'student-roster']);

        return response()->view('reports', $data)->header('Cache-Control', 'private, no-store');
    }

    public function export(Request $request, ClassReportService $service)
    {
        $data = $this->data($request, $service);

        return response()->streamDownload(function () use ($data) {
            $stream = fopen('php://output', 'w');
            fputcsv($stream, ['Report period (UTC+08:00)', 'Student ID', 'Coder Cadet', 'Grade', 'Section', 'Current XP (all time)', 'Level', 'Checkpoints completed in period', 'Quiz average (%)', 'Quizzes submitted', 'Coding activities submitted', 'Strong reviewed solutions', 'Pending latest reviews', 'Last learning activity (UTC+08:00)', 'Coaching status'], ',', '"', '');
            foreach ($data['rows'] as $row) {
                $cells = [$data['label'], $row['student_id'], $row['nickname'], $row['grade'], $row['section'], $row['xp'], $row['level'], $row['lessons'],
                    $row['quiz_average'] ?? '', $row['quiz_count'], $row['coding_count'], $row['confirmed'], $row['pending'],
                    $row['last_active']?->copy()->timezone(config('learning.timezone'))->format('Y-m-d H:i:s') ?? '', $data['statuses'][$row['status']]];
                $safe = array_map(fn ($cell) => preg_match('/^(?:\s*[=+@-]|[\t\r\n])/u', (string) $cell) ? "'".$cell : $cell, $cells);
                fputcsv($stream, $safe, ',', '"', '');
            }
            fclose($stream);
        }, 'codequest-class-report-'.$data['generatedAt']->format('Y-m-d').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
