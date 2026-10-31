<?php

namespace App\Services;

use App\Models\Student;
use App\Models\TeacherAccount;

class StudentRosterService
{
    public function build(TeacherAccount $teacher, array $filters, bool $all = false): array
    {
        $rooms = $teacher->classrooms()->withCount('students')->orderBy('grade')->orderBy('section')->get();
        $report = app(ClassReportService::class)->build($teacher, $rooms, null, now()->addMicrosecond());
        $evidence = $report['rows']->keyBy('id');
        $query = Student::with('classroom')->withCount('achievements')->whereIn('classroom_id', $rooms->pluck('id'));
        if ($filters['status'] === 'deleted') {
            $query->onlyTrashed();
        }
        if ($filters['classroom']) {
            $query->where('classroom_id', $filters['classroom']);
        }
        if ($filters['q'] !== '') {
            $term = '%'.$filters['q'].'%';
            $query->where(fn ($q) => $q->where('first_name', 'like', $term)->orWhere('middle_name', 'like', $term)->orWhere('surname', 'like', $term)->orWhere('nickname', 'like', $term)->orWhere('student_id', 'like', $term));
        }
        if ($filters['progress'] !== 'all' && $filters['status'] === 'active') {
            $query->whereIn('id', $evidence->where('status', $filters['progress'])->pluck('id'));
        }
        match ($filters['sort']) {
            'xp' => $query->orderByDesc('xp')->orderBy('id'),
            'newest' => $query->orderByDesc('created_at')->orderByDesc('id'),
            default => $query->orderBy('surname')->orderBy('first_name')->orderBy('id'),
        };
        $students = $all ? $query->get() : $query->paginate($filters['per_page'])->withQueryString()->withPath(route('teacher.students'));

        return [
            'students' => $students, 'classrooms' => $rooms, 'evidence' => $evidence, 'filters' => $filters,
            'metrics' => ['active' => $rooms->sum('students_count'), 'sections' => $rooms->count(),
                'support' => $evidence->where('status', 'support')->count(),
                'deleted' => Student::onlyTrashed()->whereIn('classroom_id', $rooms->pluck('id'))->count()],
            'updatedAt' => now(config('learning.timezone'))->format('M j, Y · g:i:s A'),
        ];
    }

    public function evidence(TeacherAccount $teacher, Student $student): ?array
    {
        $rooms = $teacher->classrooms()->get();

        return app(ClassReportService::class)->build($teacher, $rooms, null, now()->addMicrosecond())['rows']->firstWhere('id', $student->id);
    }
}
