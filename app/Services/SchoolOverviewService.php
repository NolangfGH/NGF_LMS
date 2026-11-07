<?php

namespace App\Services;

use App\Models\Classroom;
use App\Models\CodingMatch;
use App\Models\LessonContent;
use App\Models\LessonProgress;
use App\Models\Student;
use App\Models\StudentAchievement;
use App\Models\TeacherAccount;
use App\Models\TeacherAssignment;
use App\Models\TeacherLessonPlan;
use App\Models\TeacherQuiz;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SchoolOverviewService
{
    public const TABS = ['overview' => 'School Overview', 'students' => 'Student Progress', 'achievements' => 'Achievements', 'leaderboard' => 'Leaderboard', 'lessons' => 'Lessons', 'quizzes' => 'Quizzes', 'assignments' => 'Assignments', 'challenges' => 'Challenges'];

    public function build(TeacherAccount $teacher, Request $request): array
    {
        $filter = $request->validate(['tab' => ['nullable', Rule::in(array_keys(self::TABS))], 'classroom' => ['nullable', 'integer', 'min:1'], 'page' => ['nullable', 'integer', 'min:1'], 'content_page' => ['nullable', 'integer', 'min:1']]);
        $tab = $filter['tab'] ?? 'overview';
        $staff = TeacherAccount::where('school', $teacher->school)->where('staff_access_status', 'active')->get(['id', 'first_name', 'middle_initial', 'last_name', 'role']);
        $teachers = $staff->whereIn('role', ['teacher', 'school_admin'])->keyBy('id');
        $allClassrooms = Classroom::whereNull('archived_at')->whereIn('teacher_account_id', $teachers->keys())
            ->select('id', 'teacher_account_id', 'name', 'grade', 'section')->withCount('students')->orderBy('grade')->orderBy('section')->orderBy('id')->get();
        $sections = $allClassrooms->groupBy(fn (Classroom $room): string => json_encode([
            Str::lower(Str::squish($room->grade)),
            Str::lower(Str::squish($room->section ?: $room->name)),
        ], JSON_THROW_ON_ERROR))->map(function (Collection $rooms) use ($teachers): array {
            $rooms = $rooms->sortBy('id');
            $first = $rooms->first();

            return [
                'id' => $first->id, 'grade' => Str::squish($first->grade), 'section' => Str::squish($first->section ?: $first->name),
                'classroom_ids' => $rooms->pluck('id')->all(), 'subjects' => $rooms->pluck('name')->unique()->implode(', '),
                'teachers' => $rooms->map(fn (Classroom $room): string => $teachers->get($room->teacher_account_id)?->fullName() ?? '')
                    ->filter()->unique()->implode(', '),
                'students_count' => $rooms->sum('students_count'),
            ];
        })->values();
        $selected = isset($filter['classroom']) ? (int) $filter['classroom'] : null;
        abort_if($selected && ! $allClassrooms->contains('id', $selected), 404);
        $selectedSection = $selected ? $sections->first(fn (array $section): bool => in_array($selected, $section['classroom_ids'], true)) : null;
        $selected = $selectedSection['id'] ?? null;
        $classrooms = $selectedSection ? $allClassrooms->whereIn('id', $selectedSection['classroom_ids']) : $allClassrooms;
        $visibleSections = $selectedSection ? collect([$selectedSection]) : $sections;
        $roomIds = $classrooms->pluck('id');
        $students = Student::whereIn('classroom_id', $roomIds);
        $quizResults = $this->quizResults($roomIds);
        $data = ['teacher' => $teacher, 'tab' => $tab, 'tabs' => self::TABS, 'allClassrooms' => $allClassrooms, 'classrooms' => $classrooms,
            'teachers' => $teachers, 'selected' => $selected, 'sections' => $sections, 'visibleSections' => $visibleSections,
            'studentCount' => (clone $students)->count(), 'staffCount' => $staff->count(),
            'totalXp' => (int) (clone $students)->sum('xp'),
            'quizAverage' => DB::query()->fromSub($quizResults, 'results')->avg('percent'),
            'completedLessons' => LessonProgress::whereNotNull('completed_at')->whereIn('student_id', (clone $students)->select('id'))->count(),
            'badgeCount' => StudentAchievement::whereIn('student_id', (clone $students)->select('id'))->count(),
            'updatedAt' => now(config('learning.timezone'))->format('M j, Y · g:i A')];

        if ($tab === 'students') {
            $data['rows'] = $this->progress($roomIds, $classrooms);
        } elseif ($tab === 'leaderboard') {
            $data['rows'] = (clone $students)->select('id', 'classroom_id', 'nickname', 'leaderboard_visible', 'xp', 'level')
                ->selectRaw('RANK() OVER (ORDER BY COALESCE(xp, 0) DESC) AS ranking_position')->orderByRaw('COALESCE(xp, 0) DESC')->orderBy('id')
                ->paginate(25)->withQueryString()->through(fn ($student) => ['name' => $student->rankingName(0), 'rank' => $student->ranking_position,
                    'section' => $this->section($classrooms->firstWhere('id', $student->classroom_id)), 'xp' => (int) $student->xp, 'level' => max(1, (int) $student->level)]);
        } elseif ($tab === 'achievements') {
            $data['rows'] = StudentAchievement::whereIn('student_id', (clone $students)->select('id'))
                ->with('student:id,classroom_id,nickname,leaderboard_visible')->orderByDesc('earned_at')->orderByDesc('id')
                ->paginate(25, ['id', 'student_id', 'badge_code', 'earned_at'])->withQueryString()->through(fn ($award) => [
                    'name' => $award->student->rankingName(0), 'section' => $this->section($classrooms->firstWhere('id', $award->student->classroom_id)),
                    'badge' => config('achievements.'.$award->badge_code.'.name', 'Saved achievement'),
                    'requirement' => config('achievements.'.$award->badge_code.'.requirement', 'Achievement recorded in the learning profile.'), 'earned_at' => $award->earned_at]);
        } elseif (in_array($tab, ['lessons', 'quizzes', 'assignments', 'challenges'], true)) {
            $data += $this->activities($tab, $classrooms, $teachers, $selected);
        }

        return $data;
    }

    private function section(?Classroom $room): string
    {
        return $room ? $room->grade.' · '.($room->section ?: $room->name) : 'Section unavailable';
    }

    private function quizResults(Collection $roomIds): Builder
    {
        return DB::table('quiz_submissions as result')->join('students', 'students.id', '=', 'result.student_id')
            ->join('classrooms', 'classrooms.id', '=', 'students.classroom_id')->join('teacher_quizzes as quiz', 'quiz.id', '=', 'result.teacher_quiz_id')
            ->whereIn('students.classroom_id', $roomIds)->whereNull('students.deleted_at')->where('students.registration_status', 'approved')->whereNull('quiz.deleted_at')
            ->whereColumn('quiz.teacher_account_id', 'classrooms.teacher_account_id')->where('result.total_questions', '>', 0)
            ->whereColumn('result.graded_questions', 'result.total_questions')->where('result.score', '>=', 0)->whereColumn('result.score', '<=', 'result.total_questions')
            ->groupBy('result.student_id', 'result.teacher_quiz_id')
            ->selectRaw('result.student_id, result.teacher_quiz_id, MAX(100.0 * result.score / result.total_questions) AS percent, MAX(result.created_at) AS last_activity');
    }

    private function progress(Collection $roomIds, Collection $classrooms): LengthAwarePaginator
    {
        $page = Student::whereIn('classroom_id', $roomIds)->with('achievements:id,student_id,badge_code,earned_at')->orderBy('nickname')->orderBy('id')
            ->paginate(25, ['id', 'classroom_id', 'nickname', 'leaderboard_visible', 'xp', 'level', 'streak_days'])->withQueryString();
        $ids = $page->getCollection()->pluck('id');
        $lessons = LessonProgress::whereIn('student_id', $ids)->get(['student_id', 'lesson_key', 'completed_at', 'last_opened_at']);
        $quizzes = $this->quizResults($roomIds)->whereIn('result.student_id', $ids)->get();
        $labs = DB::table('lab_submissions as lab')->join('code_workspaces as workspace', 'workspace.id', '=', 'lab.code_workspace_id')
            ->join('students', 'students.id', '=', 'workspace.student_id')->join('classrooms', 'classrooms.id', '=', 'students.classroom_id')
            ->whereIn('workspace.student_id', $ids)->whereColumn('lab.teacher_account_id', 'classrooms.teacher_account_id')
            ->groupBy('workspace.student_id')->selectRaw('workspace.student_id, COUNT(DISTINCT workspace.id) AS submitted, COUNT(DISTINCT CASE WHEN lab.reviewed_at IS NOT NULL AND lab.score IS NOT NULL THEN workspace.id END) AS reviewed, MAX(lab.created_at) AS last_activity')->get()->keyBy('student_id');
        $catalogs = $classrooms->whereIn('id', $page->getCollection()->pluck('classroom_id'))->pluck('teacher_account_id')->unique()
            ->mapWithKeys(fn ($id) => [$id => app(LessonService::class)->catalogForTeacher($id)]);

        return $page->through(function ($student) use ($classrooms, $catalogs, $lessons, $quizzes, $labs) {
            $room = $classrooms->firstWhere('id', $student->classroom_id);
            $catalog = $catalogs[$room->teacher_account_id];
            $history = $lessons->where('student_id', $student->id)->whereIn('lesson_key', array_keys($catalog));
            $completed = $history->whereNotNull('completed_at')->count();
            $results = $quizzes->where('student_id', $student->id);
            $lab = $labs->get($student->id);
            $last = collect([$history->max('last_opened_at'), $results->max('last_activity'), $lab?->last_activity])->filter()->map(fn ($at) => Carbon::parse($at))->sortDesc()->first();

            return ['name' => $student->rankingName(0), 'section' => $this->section($room), 'xp' => (int) $student->xp, 'level' => max(1, (int) $student->level),
                'streak' => (int) $student->streak_days, 'completed' => $completed, 'total' => count($catalog), 'percent' => count($catalog) ? (int) round(100 * $completed / count($catalog)) : 0,
                'quiz_average' => $results->isEmpty() ? null : round($results->avg('percent'), 1), 'quiz_count' => $results->count(),
                'coding_submitted' => (int) ($lab?->submitted ?? 0), 'coding_reviewed' => (int) ($lab?->reviewed ?? 0), 'last_active' => $last,
                'badges' => $student->achievements->map(fn ($award) => ['name' => config('achievements.'.$award->badge_code.'.name', 'Saved achievement'), 'earned_at' => $award->earned_at]),
                'lessons' => $history->map(fn ($record) => ['title' => $catalog[$record->lesson_key]['title'], 'completed' => $record->completed_at !== null])->values()];
        });
    }

    private function activityQuery(string $model, Collection $classrooms): \Illuminate\Database\Eloquent\Builder
    {
        $table = (new $model)->getTable();

        return $model::whereIn('teacher_account_id', $classrooms->pluck('teacher_account_id')->unique())
            ->where(fn ($query) => $query->whereIn('classroom_id', $classrooms->pluck('id'))
                ->orWhereHas('classrooms', fn ($rooms) => $rooms->whereIn('classrooms.id', $classrooms->pluck('id'))->whereColumn('classrooms.teacher_account_id', $table.'.teacher_account_id')))
            ->with(['classrooms' => fn ($rooms) => $rooms->whereIn('classrooms.id', $classrooms->pluck('id'))]);
    }

    private function audience($activity, Collection $classrooms): string
    {
        $ids = $activity->classrooms->pluck('id')->push($activity->classroom_id);

        return $classrooms->where('teacher_account_id', $activity->teacher_account_id)->whereIn('id', $ids)->map(fn ($room) => $this->section($room))->implode(', ');
    }

    private function lessonDefinition(array $definition): array
    {
        return Arr::only($definition, ['title', 'objective', 'topic', 'minutes', 'sections', 'code', 'output']);
    }

    private function activities(string $tab, Collection $classrooms, Collection $teachers, ?int $selected): array
    {
        if ($tab === 'lessons') {
            $rows = TeacherLessonPlan::whereIn('classroom_id', $classrooms->pluck('id'))
                ->whereHas('classroom', fn ($query) => $query->whereColumn('classrooms.teacher_account_id', 'teacher_lesson_plans.teacher_account_id'))
                ->latest()->paginate(12)->withQueryString();
            $definitions = LessonContent::whereIn('teacher_account_id', $rows->getCollection()->pluck('teacher_account_id'))->whereIn('lesson_key', $rows->getCollection()->pluck('lesson_key'))
                ->get(['teacher_account_id', 'lesson_key', 'definition'])->keyBy(fn ($content) => $content->teacher_account_id.':'.$content->lesson_key);
            $rows->through(function ($plan) use ($classrooms, $teachers, $definitions) {
                $definition = $definitions->get($plan->teacher_account_id.':'.$plan->lesson_key)?->definition ?? config('lessons.'.$plan->lesson_key, config('legacy_lessons.'.$plan->lesson_key, []));

                return ['title' => $definition['title'] ?? 'Saved lesson plan', 'teacher' => $teachers->get($plan->teacher_account_id)?->fullName(), 'sections' => $this->section($classrooms->firstWhere('id', $plan->classroom_id)),
                    'status' => $plan->status, 'instructions' => $plan->instructions, 'due_at' => $plan->due_at, 'definition' => $this->lessonDefinition($definition)];
            });
            $contents = LessonContent::whereIn('teacher_account_id', $selected ? $classrooms->pluck('teacher_account_id')->unique() : $teachers->keys())
                ->latest()->paginate(12, ['id', 'teacher_account_id', 'definition', 'status'], 'content_page')->withQueryString()
                ->through(fn ($content) => ['teacher' => $teachers->get($content->teacher_account_id)?->fullName(), 'status' => $content->status, 'definition' => $this->lessonDefinition($content->definition)]);

            return compact('rows', 'contents');
        }
        $model = $tab === 'quizzes' ? TeacherQuiz::class : TeacherAssignment::class;
        $query = $this->activityQuery($model, $classrooms);
        if ($tab !== 'quizzes') {
            $query->where('kind', $tab === 'challenges' ? 'challenge' : 'assignment');
        }
        $rows = $query->latest()->paginate(12)->withQueryString()->through(function ($activity) use ($tab, $teachers, $classrooms) {
            $row = ['title' => $activity->title, 'teacher' => $teachers->get($activity->teacher_account_id)?->fullName(),
                'sections' => $this->audience($activity, $classrooms), 'status' => $activity->status];
            if ($tab === 'quizzes') {
                return $row + ['instructions' => $activity->instructions, 'opens_at' => $activity->opens_at, 'closes_at' => $activity->closes_at,
                    'time_limit' => $activity->untimed ? 'Untimed' : $activity->time_limit.' minutes',
                    'question_time' => $activity->untimed ? null : $activity->question_seconds,
                    'questions' => collect($activity->questions ?? [])->map(fn ($question) => Arr::only($question, ['type', 'prompt', 'options', 'code']))->all()];
            }

            return $row + ['instructions' => $activity->objective, 'due_at' => $activity->due_at, 'term' => $activity->school_term,
                'difficulty' => $activity->difficulty, 'xp' => $activity->xp, 'time_limit' => $activity->time_limit_minutes,
                'sample_output' => $activity->sample_output, 'starter_code' => $activity->starter_code];
        });
        $data = compact('rows');
        if ($tab === 'challenges') {
            $matchRooms = Classroom::whereNull('archived_at')->whereIn('teacher_account_id', $teachers->keys())->get(['id', 'grade', 'section', 'name']);
            $data['matches'] = CodingMatch::whereIn('teacher_account_id', $teachers->keys())
                ->whereHas('participants.student', fn ($query) => $query->whereIn('classroom_id', $classrooms->pluck('id')))
                ->with(['participants' => fn ($query) => $query->select('id', 'coding_match_id', 'student_id', 'response')
                    ->whereHas('student', fn ($query) => $query->whereIn('classroom_id', $matchRooms->pluck('id')))
                    ->with('student:id,classroom_id,nickname,leaderboard_visible')])->latest()->paginate(12, ['id', 'teacher_account_id', 'title', 'instructions', 'sample_output', 'status', 'time_limit_minutes', 'winner_xp', 'deadline_at', 'awarded_at'], 'content_page')->withQueryString()
                ->through(fn ($match) => ['title' => $match->title, 'teacher' => $teachers->get($match->teacher_account_id)?->fullName(), 'instructions' => $match->instructions,
                    'sample_output' => $match->sample_output, 'status' => $match->status, 'time_limit' => $match->time_limit_minutes, 'xp' => $match->winner_xp,
                    'deadline' => $match->deadline_at, 'awarded_at' => $match->awarded_at, 'participants' => $match->participants->map(fn ($participant) => ['name' => $participant->student?->rankingName(0) ?? 'Former student',
                        'section' => $this->section($matchRooms->firstWhere('id', $participant->student?->classroom_id)), 'response' => $participant->response])]);
        }

        return $data;
    }
}
