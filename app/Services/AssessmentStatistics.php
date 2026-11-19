<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class AssessmentStatistics
{
    /** @param list<array{score: int, total: int}> $results */
    public static function metrics(array $results): array
    {
        $results = array_values(array_filter($results, static fn (array $row): bool => $row['total'] > 0 && $row['score'] >= 0 && $row['score'] <= $row['total']));
        $scores = array_column($results, 'score');
        sort($scores, SORT_NUMERIC);
        $count = count($scores);
        $frequencies = array_count_values($scores);
        $frequency = $frequencies ? max($frequencies) : 0;
        $modes = $frequency > 1 ? array_keys(array_filter($frequencies, static fn (int $value): bool => $value === $frequency)) : [];
        $totals = array_unique(array_column($results, 'total'));

        return ['count' => $count, 'mean' => $count ? round(array_sum($scores) / $count, 2) : null,
            'median' => $count ? round(($scores[(int) floor(($count - 1) / 2)] + $scores[(int) floor($count / 2)]) / 2, 2) : null,
            'modes' => $modes, 'mode_frequency' => $frequency, 'total' => count($totals) === 1 ? reset($totals) : null,
            'mps' => $count ? round(array_sum(array_map(static fn (array $row): float => $row['score'] / $row['total'] * 100, $results)) / $count, 2) : null];
    }

    /**
     * @param  list<array<string, mixed>>  $questions
     * @param  list<array<string, mixed>>  $results
     */
    public function capture(string $source, int $id, array $questions, array $results, string $skill = ''): void
    {
        if ($questions === [] || count($questions) !== count($results)) {
            throw new \LogicException('Item statistics need a complete grading snapshot.');
        }
        $items = [];
        foreach ($questions as $index => $question) {
            $expected = (array) ($question['correct'] ?? $question['accepted_answers'] ?? []);
            $options = $question['options'] ?? [];
            if (is_int($question['correct'] ?? null)) {
                $expected = [$options[$question['correct']] ?? $question['correct']];
            }
            if (($question['type'] ?? '') !== 'ordering') {
                sort($expected, SORT_STRING);
                sort($options, SORT_STRING);
            }
            $result = $results[$index];
            if (! is_bool($result['correct'] ?? null)) {
                throw new \LogicException('Item correctness must be established before recording statistics.');
            }
            $answer = $result['answer'] ?? null;
            $items[] = ['key' => hash('sha256', json_encode([$question['type'] ?? 'multiple_choice', $question['prompt'], $options, $expected], JSON_THROW_ON_ERROR)),
                'number' => $index + 1, 'prompt' => $question['prompt'], 'skill' => $question['skill'] ?? $skill,
                'correct' => $result['correct'], 'blank' => $answer === null || $answer === [] || $answer === ''];
        }
        $keys = array_column($items, 'key');
        sort($keys, SORT_STRING);
        DB::table('assessment_item_snapshots')->updateOrInsert(['source_type' => $source, 'source_id' => $id], [
            'version' => hash('sha256', implode(':', $keys)), 'question_count' => count($items),
            'score' => count(array_filter($items, static fn (array $item): bool => $item['correct'])),
            'items' => json_encode($items, JSON_THROW_ON_ERROR), 'recorded_at' => now(),
        ]);
    }
}
