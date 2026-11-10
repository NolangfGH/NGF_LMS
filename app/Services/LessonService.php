<?php

namespace App\Services;

use App\Models\CodeWorkspace;
use App\Models\LessonContent;
use App\Models\LessonProgress;
use App\Models\Student;
use App\Models\TeacherLessonPlan;

class LessonService
{
    public function catalog(?string $course = 'cpp'): array
    {
        $route = request()->route()?->getName() ?? '';
        $teacherId = str_starts_with($route, 'teacher.') || str_starts_with($route, 'school.')
            ? session('teacher_id') : Student::find(session('student_id'))?->classroom?->teacher_account_id;
        if (! $teacherId) {
            $teacherId = session('teacher_id');
        }

        return $this->catalogForTeacher($teacherId, $course);
    }

    public function catalogForTeacher(?int $teacherId, ?string $course = 'cpp'): array
    {
        $catalog = [...config('lessons'), ...config('arduino_curriculum.lessons')];
        $inCourse = static fn (array $lesson): bool => $course === null || ($lesson['course'] ?? 'cpp') === $course;
        if (! $teacherId) {
            return array_filter($catalog, $inCourse);
        }
        $retired = array_diff_key(config('legacy_lessons'), $catalog);
        $savedKeys = TeacherLessonPlan::where('teacher_account_id', $teacherId)->whereIn('lesson_key', array_keys($retired))
            ->whereHas('classroom', fn ($query) => $query->where('teacher_account_id', $teacherId))->pluck('lesson_key')
            ->merge(LessonProgress::whereIn('lesson_key', array_keys($retired))
                ->whereHas('student.classroom', fn ($query) => $query->where('teacher_account_id', $teacherId))->pluck('lesson_key'));
        foreach ($savedKeys->unique() as $key) {
            $catalog[$key] = $retired[$key] + ['legacy' => true];
        }
        foreach (LessonContent::where('teacher_account_id', $teacherId)->get() as $content) {
            if ($content->status === 'published') {
                $catalog[$content->lesson_key] = $content->definition + ['content_id' => $content->id]
                    + array_intersect_key($catalog[$content->lesson_key] ?? [], array_flip(['term', 'subtopics', 'reference_url', 'legacy', 'course', 'grade_level', 'ilaw']));
            } else {
                unset($catalog[$content->lesson_key]);
            }
        }

        return array_filter($catalog, $inCourse);
    }

    public function lesson(string $key): array
    {
        abort_unless(array_key_exists($key, $this->catalog(null)), 404);

        return ['key' => $key, ...$this->catalog(null)[$key]];
    }

    public function path(Student $student, string $course = 'cpp'): array
    {
        $progress = LessonProgress::where('student_id', $student->id)->get()->keyBy('lesson_key');
        $plans = TeacherLessonPlan::where('classroom_id', $student->classroom_id)->whereHas('classroom', fn ($query) => $query->whereColumn('classrooms.teacher_account_id', 'teacher_lesson_plans.teacher_account_id'))
            ->where('status', 'published')->get()->keyBy('lesson_key');
        $workspaces = CodeWorkspace::with(['submissions' => fn ($query) => $query->select('id', 'code_workspace_id', 'rubric', 'score', 'feedback', 'reviewed_at')])
            ->where('student_id', $student->id)->whereIn('activity_key', collect($this->catalog($course))->pluck('practice')->map(fn ($key) => 'practice:'.$key))->get(['id', 'student_id', 'activity_key']);
        $workspaces = $workspaces->keyBy('activity_key');
        $cards = [];
        $catalog = $this->catalogForTeacher($student->classroom?->teacher_account_id, $course);
        $pretestRequired = ! app(CourseAssessments::class)->hasCompleted($student, $course === 'arduino-uno' ? CourseAssessments::ARDUINO_PRE : CourseAssessments::PRE);
        $previousComplete = true;
        foreach ($catalog as $key => $lesson) {
            $record = $progress->get($key);
            $locked = $pretestRequired || ((bool) $student->classroom?->sequential_learning && ! $previousComplete && ! $record?->completed_at);
            $cards[] = ['key' => $key, ...$lesson, 'locked' => $locked, 'progress' => $record, 'plan' => $plans->get($key), 'status' => $record?->completed_at ? 'completed' : ($record ? 'in_progress' : 'not_started'), 'practice_progress' => app(ChallengeService::class)->describe($workspaces->get('practice:'.$lesson['practice']))];
            $previousComplete = $previousComplete && (bool) $record?->completed_at;
        }
        $cards = collect($cards);
        $available = $cards->where('locked', false);
        $next = $available->where('status', 'in_progress')->sortByDesc(fn ($item) => $item['progress']->last_opened_at)->first()
            ?? $available->filter(fn ($item) => $item['status'] !== 'completed' && $item['plan'])->first()
            ?? $available->firstWhere('status', 'not_started');

        return ['course' => $course, 'title' => $course === 'arduino-uno' ? config('arduino_curriculum.title') : 'C++ Programming', 'cards' => $cards, 'completed' => $cards->where('status', 'completed')->count(), 'started' => $progress->whereIn('lesson_key', array_keys($catalog))->count(), 'assigned' => $plans->whereIn('lesson_key', array_keys($catalog))->count(), 'practice_confirmed' => $cards->where('practice_progress.status', 'completed')->unique('practice')->count(), 'next' => $next, 'total' => $cards->count(), 'pretest_required' => $pretestRequired];
    }
}
