<?php

namespace App\Services;

use App\Models\GradingNote;
use App\Models\GradingSheet;
use App\Models\LabSubmission;
use App\Models\Student;
use App\Models\TeacherAssignment;
use App\Models\TeacherQuiz;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class GradingSheetService
{
    public function sources(int $teacherId, int $classroomId, string $term): Collection
    {
        $quizzes = TeacherQuiz::where('teacher_account_id', $teacherId)->where('status', 'published')
            ->forClassroom($classroomId)->orderBy('title')->get(['id', 'title', 'questions'])
            ->filter(fn ($quiz) => count($quiz->questions ?? []) > 0)
            ->map(fn ($quiz) => ['key' => 'quiz:'.$quiz->id, 'title' => 'Quiz · '.$quiz->title, 'max' => count($quiz->questions)]);
        $assignments = TeacherAssignment::where('teacher_account_id', $teacherId)->where('status', 'published')
            ->where(fn ($query) => $query->where('school_term', $term)->orWhereNull('school_term'))
            ->forClassroom($classroomId)->orderBy('title')->get(['id', 'title', 'kind'])
            ->map(fn ($assignment) => ['key' => 'assignment:'.$assignment->id, 'title' => ucfirst($assignment->kind).' · '.$assignment->title, 'max' => 16]);

        return $quizzes->concat($assignments)->values();
    }

    public function build(?GradingSheet $sheet, int $teacherId, int $classroomId): array
    {
        $students = Student::where('classroom_id', $classroomId)->orderBy('surname')->orderBy('first_name')->orderBy('id')->get();
        $assessments = $sheet ? $sheet->assessments()->with('scores')->get() : collect();
        $weights = array_replace(config('grading.weights'), $sheet?->weights ?? []);
        $live = $this->liveScores($assessments, $students->pluck('id'), $teacherId, $classroomId);
        $notes = $sheet ? GradingNote::where('grading_sheet_id', $sheet->id)->whereIn('student_id', $students->pluck('id'))->pluck('body', 'student_id') : collect();
        $rows = $students->map(function ($student) use ($assessments, $weights, $live, $notes) {
            $cells = [];
            foreach ($assessments as $assessment) {
                $manual = $assessment->scores->firstWhere('student_id', $student->id);
                $automatic = $live[$assessment->id][$student->id] ?? null;
                $cells[$assessment->id] = [
                    'score' => $manual ? $manual->score : ($automatic['score'] ?? null),
                    'status' => $manual?->status ?? ($automatic ? 'scored' : 'pending'),
                    'mode' => $manual?->status ?? ($assessment->source_type === 'manual' ? 'pending' : 'source'),
                    'automatic' => $automatic, 'override' => $manual !== null && $assessment->source_type !== 'manual',
                ];
            }
            $totals = $this->calculate($assessments, $cells, $weights);

            return [
                'id' => $student->id, 'name' => trim(implode(' ', array_filter([$student->first_name, $student->middle_name, $student->surname]))),
                'nickname' => $student->nickname, 'student_id' => $student->student_id, 'alias' => $student->rankingAlias(),
                'xp' => (int) $student->xp, 'level' => (int) $student->level,
                'cells' => $cells, 'note' => $notes[$student->id] ?? '',
                ...$totals,
            ];
        });
        $graded = $rows->whereNotNull('average');

        return [
            'assessments' => $assessments, 'rows' => $rows, 'weights' => $weights,
            'metrics' => [
                'students' => $rows->count(), 'average' => $graded->isEmpty() ? null : round($graded->avg('average'), 2),
                'complete' => $rows->where('complete', true)->count(), 'pending' => $rows->sum('pending'),
                'passing' => $graded->filter(fn ($row) => $row['average'] >= ($sheet?->passing_score ?? 75))->count(),
                'scored' => $graded->count(), 'highest' => $graded->max('average'), 'lowest' => $graded->min('average'),
            ],
        ];
    }

    public function calculate(Collection $assessments, array $cells, array $weights): array
    {
        $categories = [];
        $sum = 0;
        $activeWeight = 0;
        $pending = 0;
        $resolved = 0;
        $complete = $assessments->isNotEmpty();
        foreach (config('grading.categories') as $key => $label) {
            $items = $assessments->where('category', $key);
            $earned = 0;
            $maximum = 0;
            foreach ($items as $item) {
                $cell = $cells[$item->id];
                if ($cell['status'] === 'excused') {
                    $resolved++;
                } elseif ($cell['score'] !== null) {
                    $earned += $cell['score'];
                    $maximum += $item->max_score;
                    $resolved++;
                } else {
                    $pending++;
                    $complete = false;
                }
            }
            $percentage = $maximum > 0 ? 100 * $earned / $maximum : null;
            $categories[$key] = ['earned' => $earned, 'maximum' => $maximum, 'percentage' => $percentage === null ? null : round($percentage, 2), 'count' => $items->count()];
            if (($weights[$key] ?? 0) > 0) {
                if ($items->isEmpty()) {
                    $complete = false;
                }
                if ($percentage !== null) {
                    $sum += $percentage * $weights[$key];
                    $activeWeight += $weights[$key];
                }
            }
        }
        $average = $activeWeight > 0 ? round($sum / $activeWeight, 2) : null;

        return ['categories' => $categories, 'average' => $average, 'complete' => $complete && $average !== null,
            'pending' => $pending, 'resolved' => $resolved, 'total' => $assessments->count()];
    }

    private function liveScores(Collection $assessments, Collection $studentIds, int $teacherId, int $classroomId): array
    {
        $scores = [];
        $quizzes = $assessments->where('source_type', 'quiz');
        $allowedQuizzes = TeacherQuiz::withTrashed()->where('teacher_account_id', $teacherId)->forClassroom($classroomId)
            ->whereIn('id', $quizzes->pluck('source_id'))->pluck('id');
        $results = DB::table('quiz_submissions')->whereIn('teacher_quiz_id', $allowedQuizzes)->whereIn('student_id', $studentIds)
            ->whereNotNull('score')->where('total_questions', '>', 0)->whereColumn('graded_questions', 'total_questions')
            ->where('score', '>=', 0)->whereColumn('score', '<=', 'total_questions')
            ->get(['id', 'teacher_quiz_id', 'student_id', 'score', 'total_questions']);
        foreach ($quizzes as $assessment) {
            foreach ($results->where('teacher_quiz_id', $assessment->source_id)->groupBy('student_id') as $studentId => $attempts) {
                $best = $attempts->sortByDesc(fn ($result) => $result->score / $result->total_questions)->first();
                $scores[$assessment->id][$studentId] = [
                    'score' => round($best->score / $best->total_questions * $assessment->max_score, 2),
                    'label' => 'Best fully graded quiz attempt · '.$best->score.'/'.$best->total_questions,
                    'url' => route('teacher.quiz.reviews', ['submission' => $best->id]),
                ];
            }
        }
        $assignments = $assessments->where('source_type', 'assignment');
        $allowedAssignments = TeacherAssignment::withTrashed()->where('teacher_account_id', $teacherId)->forClassroom($classroomId)
            ->whereIn('id', $assignments->pluck('source_id'))->pluck('id');
        $labs = LabSubmission::with('workspace:id,student_id,teacher_assignment_id')
            ->where('teacher_account_id', $teacherId)->whereNotNull('reviewed_at')->whereNotNull('score')
            ->whereHas('workspace', fn ($query) => $query->whereIn('student_id', $studentIds)->whereIn('teacher_assignment_id', $allowedAssignments))
            ->orderByDesc('reviewed_at')->orderByDesc('id')
            ->get(['id', 'code_workspace_id', 'rubric', 'score', 'reviewed_at'])
            ->filter(function ($submission) {
                foreach (['correctness', 'logic', 'readability', 'explanation'] as $key) {
                    $value = $submission->rubric[$key] ?? null;
                    if (! is_numeric($value) || $value < 0 || $value > 4 || (int) $value != $value) {
                        return false;
                    }
                }

                return (int) $submission->score === array_sum($submission->rubric);
            })->unique(fn ($submission) => $submission->workspace->student_id.':'.$submission->workspace->teacher_assignment_id);
        foreach ($assignments as $assessment) {
            foreach ($labs->filter(fn ($submission) => (int) $submission->workspace->teacher_assignment_id === (int) $assessment->source_id) as $submission) {
                $scores[$assessment->id][$submission->workspace->student_id] = [
                    'score' => round($submission->score / 16 * $assessment->max_score, 2),
                    'label' => 'Latest final teacher rubric · '.$submission->score.'/16',
                    'url' => route('teacher.code-lab', ['submission' => $submission->id]),
                ];
            }
        }

        return $scores;
    }
}
