<?php

namespace App\Services;

use App\Models\LabSubmission;
use App\Models\LessonProgress;
use App\Models\Student;
use App\Models\TeacherAccount;
use App\Models\TeacherAssignment;
use App\Models\TeacherQuiz;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ClassReportService
{
    private function execution(TeacherAccount $teacher, Collection $students, ?Carbon $from, Carbon $until): array
    {
        $runs=\App\Models\CodeRun::where('teacher_account_id',$teacher->id)->whereIn('student_id',$students->pluck('id'))->where('mode','tests')->where('status','completed')
            ->when($from,fn($query)=>$query->where('created_at','>=',$from))->where('created_at','<',$until)->latest()->limit(1000)->get(['results']);
        $passed=0;$total=0;$errors=[];
        foreach($runs as $run)foreach($run->results ?? [] as $result){$total++;if($result['passed'] ?? false)$passed++;elseif(isset($result['status']))$errors[$result['status']]=($errors[$result['status']] ?? 0)+1;}
        $tasks=TeacherAssignment::where('teacher_account_id',$teacher->id)->where('status','published')->whereNotNull('due_at')->where('due_at','<',now()->min($until))
            ->when($from,fn($query)=>$query->where('due_at','>=',$from))->with('classrooms')->get();
        $pairs=DB::table('lab_submissions as submission')->join('code_workspaces as workspace','workspace.id','=','submission.code_workspace_id')
            ->where('submission.teacher_account_id',$teacher->id)->whereIn('workspace.student_id',$students->pluck('id'))->where('submission.created_at','<',$until)
            ->get(['workspace.student_id','workspace.teacher_assignment_id'])->mapWithKeys(fn($row)=>[$row->student_id.':'.$row->teacher_assignment_id=>true]);
        $missing=0;
        foreach($students as $student)foreach($tasks as $task){$targets=$task->classrooms->pluck('id')->push($task->classroom_id);if($targets->contains($student->classroom_id)&&!isset($pairs[$student->id.':'.$task->id]))$missing++;}
        arsort($errors);
        return ['runs'=>$runs->count(),'passed'=>$passed,'total'=>$total,'errors'=>$errors,'missing'=>$missing];
    }
    private function within($at, ?Carbon $from, Carbon $until): bool
    {
        if (! $at) {
            return false;
        }
        $time = Carbon::parse($at, 'UTC');

        return (! $from || $time->gte($from)) && $time->lt($until);
    }

    private function validReview($submission): bool
    {
        if (! $submission->reviewed_at || $submission->score === null) {
            return false;
        }
        foreach (['correctness', 'logic', 'readability', 'explanation'] as $key) {
            $value = $submission->rubric[$key] ?? null;
            if (! is_numeric($value) || (int) $value != $value || $value < 0 || $value > 4) {
                return false;
            }
        }

        return (int) $submission->score === array_sum($submission->rubric);
    }

    public function build(TeacherAccount $teacher, Collection $rooms, ?Carbon $from, Carbon $until): array
    {
        $catalog=app(LessonService::class)->catalogForTeacher($teacher->id);
        // Select only the teacher's current roster and summary evidence, never source code or AI drafts.
        $students = Student::with('classroom')->whereIn('classroom_id', $rooms->pluck('id'))->orderBy('nickname')->orderBy('id')->get();
        $ids = $students->pluck('id');
        $lessons = LessonProgress::whereIn('student_id', $ids)->whereIn('lesson_key', array_keys($catalog))->get();
        $labs = LabSubmission::with('workspace:id,student_id,teacher_assignment_id,activity_key')
            ->where('teacher_account_id', $teacher->id)->whereHas('workspace', fn ($query) => $query->whereIn('student_id', $ids))
            ->get(['id', 'code_workspace_id', 'teacher_account_id', 'revision', 'title', 'rubric', 'score', 'created_at', 'reviewed_at']);
        $quizResults = DB::table('quiz_submissions as result')->join('teacher_quizzes as quiz', 'quiz.id', '=', 'result.teacher_quiz_id')
            ->where('quiz.teacher_account_id', $teacher->id)->whereIn('result.student_id', $ids)
            ->get(['result.id', 'result.student_id', 'result.teacher_quiz_id', 'result.score', 'result.total_questions', 'result.graded_questions', 'result.created_at']);
        $completed = $lessons->filter(fn ($item) => $this->within($item->completed_at, $from, $until));
        $periodQuizzes = $quizResults->filter(fn ($item) => $this->within($item->created_at, $from, $until));
        $graded = $periodQuizzes->filter(fn ($item) => $item->score !== null && $item->total_questions > 0 && (int) $item->graded_questions === (int) $item->total_questions && $item->score >= 0 && $item->score <= $item->total_questions)
            ->groupBy(fn ($item) => $item->student_id.':'.$item->teacher_quiz_id)
            ->map(fn ($attempts) => $attempts->sortByDesc(fn ($item) => $item->score / $item->total_questions)->first());
        $periodLabs = $labs->filter(fn ($item) => $this->within($item->created_at, $from, $until));
        $latestLabs = $labs->filter(fn ($item) => $this->within($item->created_at, null, $until))->sortByDesc('id')->unique('code_workspace_id');
        $reviews = $labs->filter(fn ($item) => $this->validReview($item) && $this->within($item->reviewed_at, null, $until))
            ->sortByDesc(fn ($item) => $item->reviewed_at->format('Y-m-d H:i:s').sprintf('%020d', $item->id))->unique('code_workspace_id')
            ->filter(fn ($item) => $this->within($item->reviewed_at, $from, $until));
        $confirmed = $reviews->filter(fn ($item) => $item->rubric['correctness'] >= 3 && $item->rubric['logic'] >= 3);
        $pending = $latestLabs->filter(fn ($item) => ! $item->reviewed_at && $this->within($item->created_at, $from, $until));
        $rows = $students->map(function ($student) use ($lessons, $completed, $periodQuizzes, $graded, $periodLabs, $reviews, $confirmed, $pending, $from, $until, $catalog) {
            $history = $lessons->where('student_id', $student->id);
            $visibleHistory = $history->filter(fn ($item) => $this->within($item->last_opened_at, $from, $until) || $this->within($item->completed_at, $from, $until));
            $results = $graded->where('student_id', $student->id);
            $quizAverage = $results->isEmpty() ? null : round($results->avg(fn ($item) => 100 * $item->score / $item->total_questions), 1);
            $studentReviews = $reviews->filter(fn ($item) => $item->workspace->student_id === $student->id);
            $studentLabs = $periodLabs->filter(fn ($item) => $item->workspace->student_id === $student->id);
            $strong = $confirmed->filter(fn ($item) => $item->workspace->student_id === $student->id)->count();
            $support = [];
            foreach ($visibleHistory as $record) {
                if ($record->confidence === 'need_help' || filled($record->help_request)) {
                    $support[] = 'Help requested: '.($catalog[$record->lesson_key]['title'] ?? 'Saved lesson');
                }
            }
            if ($quizAverage !== null && $quizAverage < 60) {
                $support[] = 'Quiz average below the 60% coaching threshold';
            }
            if ($studentReviews->contains(fn ($item) => $item->rubric['correctness'] < 2 || $item->rubric['logic'] < 2)) {
                $support[] = 'A reviewed solution needs support with correctness or logic';
            }
            $activity = collect();
            foreach ($history as $record) {
                if ($this->within($record->last_opened_at, $from, $until)) {
                    $activity->push($record->last_opened_at);
                }
                if ($this->within($record->completed_at, $from, $until)) {
                    $activity->push($record->completed_at);
                }
            }
            foreach ($periodQuizzes->where('student_id', $student->id) as $result) {
                $activity->push(Carbon::parse($result->created_at, 'UTC'));
            }
            foreach ($studentLabs as $lab) {
                $activity->push($lab->created_at);
            }
            $last = $activity->sortDesc()->first();
            $status = $support ? 'support' : ($strong ? 'confirmed' : ($last ? 'progress' : 'not_started'));

            return [
                'id' => $student->id, 'nickname' => $student->nickname, 'student_id' => $student->student_id,
                'name'=>trim(implode(' ',array_filter([$student->first_name,$student->middle_name,$student->surname]))),
                'classroom_id' => $student->classroom_id, 'grade' => $student->classroom->grade,
                'section' => $student->classroom->section ?: $student->classroom->name,
                'level' => $student->level, 'xp' => $student->xp,
                'lessons' => $completed->where('student_id', $student->id)->count(), 'lesson_total' => count($catalog),
                'quiz_average' => $quizAverage, 'quiz_count' => $periodQuizzes->where('student_id', $student->id)->unique('teacher_quiz_id')->count(),
                'graded_quizzes' => $results->count(), 'coding_count' => $studentLabs->unique('code_workspace_id')->count(),
                'confirmed' => $strong, 'pending' => $pending->filter(fn ($item) => $item->workspace->student_id === $student->id)->count(),
                'review_average' => $studentReviews->isEmpty() ? null : round($studentReviews->avg('score'), 1),
                'status' => $status, 'support' => $support, 'last_active' => $last,
                'lesson_evidence' => $visibleHistory->map(fn ($record) => [
                    'title' => ($catalog[$record->lesson_key]['title'] ?? 'Saved lesson'), 'complete' => $record->completed_at !== null,
                    'latest' => $record->latest_score, 'best' => $record->best_score, 'help' => $record->help_request,
                    'feedback' => $record->teacher_feedback,
                ])->values(),
            ];
        });
        $topics = collect($catalog)->map(function ($definition, $key) use ($completed, $students) {
            $count = $completed->where('lesson_key', $key)->count();

            return ['title' => $definition['title'], 'completed' => $count, 'total' => $students->count(), 'percent' => $students->isEmpty() ? null : round(100 * $count / $students->count())];
        })->values();
        $events = collect();
        foreach ($completed as $record) {
            $events->push(['at' => $record->completed_at, 'kind' => 'lessons']);
        }
        foreach ($periodLabs->sortBy('id')->unique('code_workspace_id') as $record) {
            $events->push(['at' => $record->created_at, 'kind' => 'coding']);
        }
        foreach ($periodQuizzes->sortBy('id')->unique(fn ($record) => $record->student_id.':'.$record->teacher_quiz_id) as $record) {
            $events->push(['at' => Carbon::parse($record->created_at, 'UTC'), 'kind' => 'quizzes']);
        }
        $opportunities = $students->count() * count($catalog);

        return [
            'execution'=>$this->execution($teacher,$students,$from,$until), 'rows' => $rows, 'topics' => $topics, 'chart' => $this->chart($events, $from, $until),
            'metrics' => [
                'students' => $students->count(), 'completion' => $opportunities ? round(100 * $completed->count() / $opportunities, 1) : null,
                'completed' => $completed->count(), 'opportunities' => $opportunities,
                'quiz_average' => $graded->isEmpty() ? null : round($graded->avg(fn ($item) => 100 * $item->score / $item->total_questions), 1),
                'graded_quizzes' => $graded->count(), 'confirmed' => $confirmed->count(),
                'support' => $rows->where('status', 'support')->count(), 'pending' => $pending->count(),
            ],
            'performance' => $this->performance($teacher, $students, $periodLabs, $reviews, $periodQuizzes, $graded),
        ];
    }

    private function chart(Collection $events, ?Carbon $from, Carbon $until): array
    {
        $end = $until->copy()->timezone(config('learning.timezone'));
        $start = $from?->copy()->timezone(config('learning.timezone')) ?? $end->copy()->subDays(42);
        $days = max(1, (int) $start->diffInDays($end));
        $step = max(1, (int) ceil($days / 6));
        $buckets = [];
        for ($cursor = $start->copy(); $cursor->lt($end); $cursor->addDays($step)) {
            $stop = $cursor->copy()->addDays($step)->min($end);
            $slice = $events->filter(fn ($event) => $event['at']->gte($cursor) && $event['at']->lt($stop));
            $buckets[] = ['label' => $cursor->format('M j').'–'.$stop->copy()->subDay()->format('M j'),
                'lessons' => $slice->where('kind', 'lessons')->count(), 'coding' => $slice->where('kind', 'coding')->count(), 'quizzes' => $slice->where('kind', 'quizzes')->count()];
        }

        return $buckets;
    }

    private function performance(TeacherAccount $teacher, Collection $students, Collection $labs, Collection $reviews, Collection $results, Collection $graded): Collection
    {
        $items = collect();
        foreach (['assignment' => TeacherAssignment::class, 'quiz' => TeacherQuiz::class] as $type => $model) {
            $activities = $model::with('classrooms:id')->where('teacher_account_id', $teacher->id)->where('status', 'published')->get();
            foreach ($activities as $activity) {
                $targets = $activity->classrooms->pluck('id')->push($activity->classroom_id)->unique();
                $eligible = $students->whereIn('classroom_id', $targets)->pluck('id');
                if ($eligible->isEmpty()) {
                    continue;
                }
                if ($type === 'quiz') {
                    $submitted = $results->where('teacher_quiz_id', $activity->id)->whereIn('student_id', $eligible)->unique('student_id')->count();
                    $scores = $graded->where('teacher_quiz_id', $activity->id)->whereIn('student_id', $eligible);
                    $average = $scores->isEmpty() ? null : round($scores->avg(fn ($item) => 100 * $item->score / $item->total_questions), 1);
                } else {
                    $matching = fn ($item) => $item->workspace->teacher_assignment_id === $activity->id && $eligible->contains($item->workspace->student_id);
                    $submitted = $labs->filter($matching)->unique('code_workspace_id')->count();
                    $scores = $reviews->filter($matching);
                    $average = $scores->isEmpty() ? null : round($scores->avg('score') / 16 * 100, 1);
                }
                $items->push(['id' => $activity->id, 'title' => $activity->title, 'type' => $type === 'quiz' ? 'Quiz' : ucfirst($activity->kind),
                    'submitted' => $submitted, 'eligible' => $eligible->count(), 'average' => $average,
                    'deadline' => $type === 'quiz' ? $activity->closes_at : $activity->due_at,
                    'url' => $type === 'quiz' ? route('teacher.quiz.reviews') : route('teacher.code-lab'),
                    'updated' => $activity->updated_at]);
            }
        }

        return $items->sortByDesc('updated')->values();
    }
}
