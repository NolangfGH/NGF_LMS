<?php

namespace App\Services;

class QuizGrader
{
    public function grade(array $questions, array $answers): array
    {
        $score = 0;
        $graded = 0;
        $results = [];

        foreach ($questions as $index => $question) {
            $keys = $question['correct'] ?? [];
            $answer = $answers[$index] ?? null;
            $correct = null;
            if ($keys !== []) {
                $graded++;
                if ($answer === null || $answer === '' || $answer === []) {
                    $correct = false;
                } elseif (in_array($question['type'] ?? 'identification', ['identification','code_output','fill_code','debugging'])) {
                    $normalize = fn (string $value) => ($question['type'] ?? 'identification') === 'identification'
                        ? mb_strtolower(preg_replace('/\s+/u', ' ', trim($value))) : preg_replace('/\s+/u', ' ', trim($value));
                    $correct = in_array($normalize($answer), array_map($normalize, $keys), true);
                } elseif ($question['type'] === 'ordering') {
                    $correct = is_array($answer) && array_values($answer) === array_values($keys);
                } elseif ($question['type'] === 'multiple_answer') {
                    $selected = array_values(array_unique($answer));
                    $expected = array_values(array_unique($keys));
                    sort($selected);
                    sort($expected);
                    $correct = $selected === $expected;
                } else {
                    $correct = in_array($answer, $keys, true);
                }
                $score += $correct ? 1 : 0;
            }
            $results[] = ['prompt' => $question['prompt'], 'answer' => $answer, 'expected' => $keys, 'correct' => $correct];
        }

        return ['score' => $graded ? $score : null, 'graded_questions' => $graded, 'total_questions' => count($questions), 'grading_results' => json_encode($results), 'graded_at' => $graded ? now() : null];
    }
}
