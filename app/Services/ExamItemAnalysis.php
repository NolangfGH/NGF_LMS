<?php

namespace App\Services;

use App\Models\Student;
use App\Models\TeacherQuiz;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ExamItemAnalysis
{
    private const MIN_CORRELATION_SAMPLE = 5;

    /** @param Collection<int, int> $classroomIds */
    public function report(TeacherQuiz $exam, Collection $classroomIds, ?string $requestedVersion): array
    {
        $current = $this->questions($exam->questions ?? []);
        $currentVersion = $this->version($current);
        $versions = [$currentVersion => $this->emptyVersion($current)];
        $excluded = 0;
        $submissions = DB::table('quiz_submissions as submission')
            ->where('submission.teacher_quiz_id', $exam->id)
            ->whereIn('submission.student_id', Student::whereIn('classroom_id', $classroomIds)->select('id'))
            ->whereNotExists(fn ($prior) => $prior->selectRaw('1')->from('quiz_submissions as prior')
                ->whereColumn('prior.teacher_quiz_id', 'submission.teacher_quiz_id')
                ->whereColumn('prior.student_id', 'submission.student_id')
                ->whereColumn('prior.attempt_number', '<', 'submission.attempt_number'))
            ->leftJoin('quiz_attempts as attempt', function (JoinClause $join): void {
                $join->on('attempt.teacher_quiz_id', '=', 'submission.teacher_quiz_id')
                    ->on('attempt.student_id', '=', 'submission.student_id')
                    ->on('attempt.attempt_number', '=', 'submission.attempt_number');
            })
            ->select('submission.id', 'submission.grading_results', 'submission.graded_questions', 'attempt.questions as snapshot')
            ->lazyById(200, 'submission.id', 'id');

        foreach ($submissions as $submission) {
            $questions = $this->questions(json_decode($submission->snapshot ?? 'null', true) ?? []);
            $results = json_decode($submission->grading_results ?? 'null', true);
            if (count($questions) !== (int) $submission->graded_questions || ! $this->matchesResults($questions, $results)) {
                $excluded++;

                continue;
            }
            $version = $this->version($questions);
            $versions[$version] ??= $this->emptyVersion($questions);
            $score = count(array_filter($results, fn (array $result): bool => $result['correct']));
            $versions[$version]['learners']++;
            $versions[$version]['total_correct'] += $score;
            foreach ($questions as $index => $question) {
                $this->addResponse($versions[$version]['items'][$question['key']], $results[$index], $score);
            }
        }

        $versionChoices = collect($versions)->map(fn (array $version): int => $version['learners']);
        $selected = $requestedVersion ?? ($versions[$currentVersion]['learners'] ? $currentVersion : $versionChoices->sortDesc()->keys()->first());
        abort_unless(isset($versions[$selected]), 404);
        $result = $versions[$selected];
        $result['items'] = array_values(array_map($this->summarize(...), $result['items']));
        $result['average'] = $this->percent($result['total_correct'], $result['learners'] * $result['question_count']);

        return $result + [
            'excluded' => $excluded,
            'current_version' => $currentVersion,
            'selected_version' => $selected,
            'versions' => $versionChoices,
            'minimum_correlation_sample' => self::MIN_CORRELATION_SAMPLE,
        ];
    }

    private function questions(array $definitions): array
    {
        $questions = [];
        foreach ($definitions as $definition) {
            if (! is_array($definition) || ! is_string($definition['prompt'] ?? null)) {
                return [];
            }
            $type = $definition['type'] ?? 'identification';
            $options = $type === 'true_false' ? ['True', 'False'] : array_values($definition['options'] ?? []);
            $expected = array_values($definition['correct'] ?? []);
            $identityOptions = $options;
            $identityExpected = $expected;
            sort($identityOptions, SORT_STRING);
            if ($type !== 'ordering') {
                sort($identityExpected, SORT_STRING);
            }
            $key = hash('sha256', json_encode([$type, $definition['prompt'], $identityOptions, $identityExpected], JSON_THROW_ON_ERROR));
            $questions[] = ['key' => $key, 'prompt' => $definition['prompt'], 'type' => $type, 'options' => $options, 'expected' => $expected];
        }

        return $questions;
    }

    private function version(array $questions): string
    {
        $keys = array_column($questions, 'key');
        sort($keys, SORT_STRING);

        return hash('sha256', implode(':', $keys));
    }

    private function matchesResults(array $questions, mixed $results): bool
    {
        if ($questions === [] || ! is_array($results) || count($results) !== count($questions)) {
            return false;
        }
        foreach ($questions as $index => $question) {
            $result = $results[$index] ?? null;
            if (! is_array($result) || ($result['prompt'] ?? null) !== $question['prompt']
                || ($result['expected'] ?? null) !== $question['expected'] || ! is_bool($result['correct'] ?? null)) {
                return false;
            }
        }

        return true;
    }

    private function emptyVersion(array $questions): array
    {
        $items = [];
        foreach ($questions as $index => $question) {
            $key = $question['key'];
            $items[$key] ??= $question + ['numbers' => [], 'correct' => 0, 'wrong' => 0, 'blank' => 0,
                'selections' => array_fill(0, count($question['options']), 0), 'rest_sum' => 0, 'rest_squared_sum' => 0, 'correct_rest_sum' => 0];
            $items[$key]['numbers'][] = $index + 1;
        }

        return ['learners' => 0, 'total_correct' => 0, 'question_count' => count($questions), 'items' => $items];
    }

    private function addResponse(array &$item, array $result, int $score): void
    {
        $correct = (int) $result['correct'];
        $answer = $result['answer'] ?? null;
        $blank = $answer === null || $answer === [] || (is_string($answer) && trim($answer) === '');
        $bucket = $correct ? 'correct' : ($blank ? 'blank' : 'wrong');
        $item[$bucket]++;
        $remainingScore = $score - $correct;
        $item['rest_sum'] += $remainingScore;
        $item['rest_squared_sum'] += $remainingScore ** 2;
        $item['correct_rest_sum'] += $correct * $remainingScore;
        if (in_array($item['type'], ['multiple_choice', 'multiple_answer', 'true_false'], true)) {
            foreach ($item['options'] as $index => $option) {
                if (in_array($option, (array) $answer, true)) {
                    $item['selections'][$index]++;
                }
            }
        }
    }

    private function summarize(array $item): array
    {
        $total = $item['correct'] + $item['wrong'] + $item['blank'];
        $item['total'] = $total;
        $item['percent_correct'] = $this->percent($item['correct'], $total);
        $item['discrimination'] = null;
        if ($total >= self::MIN_CORRELATION_SAMPLE && count($item['numbers']) === 1) {
            $varianceCorrect = $total * $item['correct'] - $item['correct'] ** 2;
            $varianceRest = $total * $item['rest_squared_sum'] - $item['rest_sum'] ** 2;
            if ($varianceCorrect > 0 && $varianceRest > 0) {
                $item['discrimination'] = round(($total * $item['correct_rest_sum'] - $item['correct'] * $item['rest_sum']) / sqrt($varianceCorrect * $varianceRest), 2);
            }
        }

        return $item;
    }

    private function percent(int $count, int $total): ?float
    {
        return $total ? round($count / $total * 100, 1) : null;
    }
}
