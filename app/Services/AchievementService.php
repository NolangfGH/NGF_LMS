<?php

namespace App\Services;

use App\Models\CodeRun;
use App\Models\LabSubmission;
use App\Models\LessonProgress;
use App\Models\Student;
use App\Models\StudentAchievement;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AchievementService
{
    public function catalog(): array
    {
        return config('achievements');
    }

    private function event(string $kind, int $id, string $title, $at, string $detail): array
    {
        return ['kind' => $kind, 'id' => $id, 'title' => $title, 'at' => Carbon::parse($at, 'UTC')->toIso8601String(), 'detail' => $detail];
    }

    private function events(Student $student): array
    {
        $events = array_fill_keys(['submitted_activities', 'quizzes_completed', 'perfect_quizzes', 'clear_code', 'strong_solutions', 'clear_explanations', 'improvements', 'active_days', 'passed_programs', 'bugs_fixed', 'loop_programs', 'challenge_solutions', 'coding_streak'], []);
        $quizRows = DB::table('quiz_submissions as result')->leftJoin('teacher_quizzes as quiz', 'quiz.id', '=', 'result.teacher_quiz_id')
            ->where('result.student_id', $student->id)->orderBy('result.id')
            ->get(['result.id', 'result.teacher_quiz_id', 'result.score', 'result.total_questions', 'result.graded_questions', 'result.created_at', 'result.graded_at', 'quiz.title']);
        foreach ($quizRows as $row) {
            if ($row->total_questions < 1) {
                continue;
            }
            $event = $this->event('quiz', (int) $row->teacher_quiz_id, $row->title ?? 'Saved quiz', $row->created_at, 'Completed quiz'.($row->score !== null ? ': '.$row->score.' / '.$row->total_questions : ' · awaiting grading'));
            $events['quizzes_completed'][$row->teacher_quiz_id] ??= $event;
            $events['active_days'][Carbon::parse($row->created_at, 'UTC')->timezone(config('learning.timezone'))->toDateString()] ??= $event;
            if ($row->score !== null && (int) $row->score === (int) $row->total_questions && (int) $row->graded_questions === (int) $row->total_questions) {
                $events['perfect_quizzes'][$row->teacher_quiz_id] ??= $this->event('quiz', (int) $row->teacher_quiz_id, $event['title'], $row->graded_at ?? $row->created_at, 'All '.$row->total_questions.' questions correct');
            }
        }
        $labs = LabSubmission::whereHas('workspace', fn ($query) => $query->where('student_id', $student->id))
            ->orderBy('id')->get(['id', 'code_workspace_id', 'title', 'revision', 'rubric', 'score', 'created_at', 'reviewed_at']);
        $reviewed = [];
        foreach ($labs as $lab) {
            $event = $this->event('lab', $lab->id, $lab->title, $lab->created_at, 'Code and explanation submitted · revision '.$lab->revision);
            $events['submitted_activities'][$lab->code_workspace_id] ??= $event;
            $events['active_days'][$lab->created_at->copy()->timezone(config('learning.timezone'))->toDateString()] ??= $event;
            if (! $lab->reviewed_at || $lab->score === null || ! $this->validRubric($lab->rubric)) {
                continue;
            }
            $reviewed[] = $lab;
            $review = $this->event('lab', $lab->id, $lab->title, $lab->reviewed_at, 'Final teacher review · revision '.$lab->revision);
            if ($lab->rubric['readability'] >= 3) {
                $events['clear_code'][$lab->code_workspace_id] ??= [...$review, 'detail' => 'Teacher readability: '.$lab->rubric['readability'].' / 4'];
            }
            if ($lab->rubric['explanation'] >= 3) {
                $events['clear_explanations'][$lab->code_workspace_id] ??= [...$review, 'detail' => 'Teacher explanation: '.$lab->rubric['explanation'].' / 4'];
            }
            if ($lab->rubric['correctness'] >= 3 && $lab->rubric['logic'] >= 3) {
                $events['strong_solutions'][$lab->code_workspace_id] ??= [...$review, 'detail' => 'Teacher correctness '.$lab->rubric['correctness'].' / 4 · logic '.$lab->rubric['logic'].' / 4'];
            }
            if ($lab->rubric['correctness'] >= 3 && $lab->rubric['logic'] >= 3 && $lab->workspace?->assignment?->kind === 'challenge') {
                $events['challenge_solutions'][$lab->code_workspace_id] ??= $review;
            }
        }
        foreach (collect($reviewed)->groupBy('code_workspace_id') as $revisions) {
            foreach ($revisions as $later) {
                foreach ($revisions as $earlier) {
                    if ($later->code_workspace_id !== $earlier->code_workspace_id || $later->revision <= $earlier->revision) {
                        continue;
                    }
                    if ($later->created_at->lt($earlier->reviewed_at) || $later->score <= $earlier->score) {
                        continue;
                    }
                    $events['improvements'][$later->code_workspace_id] ??= $this->event('lab', $later->id, $later->title, $later->reviewed_at, 'After teacher feedback: '.$earlier->score.' → '.$later->score.' / 16 points');
                }
            }
        }
        foreach (LessonProgress::where('student_id', $student->id)->whereNotNull('completed_at')->get() as $lesson) {
            $title = config('lessons.'.$lesson->lesson_key.'.title', config('legacy_lessons.'.$lesson->lesson_key.'.title', 'Saved C++ lesson'));
            $event = $this->event('lesson', $lesson->id, $title, $lesson->completed_at, 'Lesson checkpoint completed with a reflection');
            $events['active_days'][$lesson->completed_at->copy()->timezone(config('learning.timezone'))->toDateString()] ??= $event;
        }
        $runs = CodeRun::with('workspace:id,activity_key')->where('student_id', $student->id)->where('mode', 'tests')->where('status', 'completed')->orderBy('id')->get(['id', 'code_workspace_id', 'revision', 'earned', 'possible', 'created_at']);
        $failures = [];
        foreach ($runs as $run) {
            if ($run->possible < 1) {
                continue;
            }
            $event = $this->event('run', $run->id, 'Executed C++ tests', $run->created_at, 'Revision '.$run->revision.' · '.$run->earned.'/'.$run->possible.' test points');
            $events['active_days'][$run->created_at->copy()->timezone(config('learning.timezone'))->toDateString()] ??= $event;
            if ($run->earned !== $run->possible) {
                $failures[$run->code_workspace_id] = $run->revision;

                continue;
            }
            $events['passed_programs'][$run->code_workspace_id] ??= $event;
            if (isset($failures[$run->code_workspace_id]) && $run->revision > $failures[$run->code_workspace_id]) {
                $events['bugs_fixed'][$run->code_workspace_id] ??= $event;
            }
            if (in_array($run->workspace?->activity_key, ['practice:loops', 'practice:sentinel', 'practice:topic-for-loops', 'practice:topic-while-loops'])) {
                $events['loop_programs'][$run->code_workspace_id] ??= $event;
            }
        }
        $dates = array_keys($events['active_days']);
        sort($dates);
        $sequence = [];
        $best = [];
        $previous = null;
        foreach ($dates as $date) {
            if ($previous !== null && Carbon::parse($previous)->addDay()->toDateString() !== $date) {
                $sequence = [];
            }$sequence[] = $date;
            if (count($sequence) > count($best)) {
                $best = $sequence;
            }$previous = $date;
        }
        $today = now(config('learning.timezone'))->toDateString();
        $yesterday = now(config('learning.timezone'))->subDay()->toDateString();
        $streak = in_array($previous, [$today, $yesterday], true) ? count($sequence) : 0;
        DB::table('students')->where('id', $student->id)->update(['streak_days' => $streak, 'best_streak' => max((int) $student->best_streak, count($best))]);
        $student->streak_days = $streak;
        $student->best_streak = max((int) $student->best_streak, count($best));
        foreach ($best as $date) {
            $events['coding_streak'][] = $events['active_days'][$date];
        }
        foreach ($events as &$items) {
            $items = array_values($items);
            usort($items, fn ($a, $b) => strcmp($a['at'], $b['at']));
        }

        return [$events, $labs->whereNull('reviewed_at')->count(), collect($reviewed)->pluck('code_workspace_id')->unique()->count()];
    }

    private function validRubric(?array $rubric): bool
    {
        if (! $rubric) {
            return false;
        }
        foreach (['correctness', 'logic', 'readability', 'explanation'] as $key) {
            $value = $rubric[$key] ?? null;
            if ((! is_int($value) && ! (is_string($value) && ctype_digit($value))) || $value < 0 || $value > 4) {
                return false;
            }
        }

        return true;
    }

    public function sync(Student $student): array
    {
        return DB::transaction(function () use ($student) {
            $account = Student::whereKey($student->id)->lockForUpdate()->firstOrFail();
            [$events,$awaitingReview,$reviewedActivities] = $this->events($account);
            $awards = StudentAchievement::where('student_id', $account->id)->get()->keyBy('badge_code');
            $cards = [];
            foreach ($this->catalog() as $code => $definition) {
                $items = $events[$definition['metric']];
                if (! $awards->has($code) && count($items) >= $definition['target']) {
                    $evidence = array_slice($items, 0, $definition['target']);
                    $award = StudentAchievement::firstOrCreate(['student_id' => $account->id, 'badge_code' => $code], ['earned_at' => end($evidence)['at'], 'evidence' => $evidence]);
                    $awards->put($code, $award);
                }
                $award = $awards->get($code);
                $current = $award ? max($definition['target'], count($items)) : count($items);
                $cards[] = [...$definition, 'code' => $code, 'current' => $current, 'progress' => min(100, (int) floor($current / $definition['target'] * 100)), 'earned' => $award !== null, 'earned_at' => $award?->earned_at, 'evidence' => $award?->evidence ?? [], 'new' => $award && ! $award->seen_at];
            }
            $cards = collect($cards);
            $earned = $cards->where('earned', true)->count();
            if ($account->badges_count !== $earned) {
                DB::table('students')->where('id', $account->id)->update(['badges_count' => $earned]);
            }
            $student->badges_count = $earned;
            $locked = $cards->where('earned', false);
            $focus = $locked->firstWhere('code', $account->achievement_goal) ?? $locked->sortByDesc('progress')->first();

            return ['cards' => $cards, 'earned' => $earned, 'total' => $cards->count(), 'completion' => (int) round($earned / $cards->count() * 100), 'focus' => $focus, 'new_count' => $cards->where('new', true)->count(), 'metrics' => array_map('count', $events), 'awaiting_review' => $awaitingReview, 'reviewed_activities' => $reviewedActivities];
        });
    }
}
