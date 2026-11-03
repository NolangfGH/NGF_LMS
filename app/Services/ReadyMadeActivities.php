<?php

namespace App\Services;

class ReadyMadeActivities
{
    /** @return array<string, array<string, mixed>> */
    public function catalog(): array
    {
        $catalog = [];
        foreach (config('lessons') as $key => $lesson) {
            $template = config('ready_made_activities.'.$key);
            if ($template === null) {
                continue;
            }
            $questions = array_map(static fn (array $question): array => [
                'type' => 'multiple_choice',
                'prompt' => $question['prompt'],
                'options' => $question['options'],
                'correct' => [$question['options'][$question['correct']]],
                'explanation' => $question['why'],
            ], [...$lesson['checks'], ...$template['questions']]);
            $catalog[$key] = [
                'key' => $key, 'title' => $lesson['title'], 'topic' => $lesson['topic'],
                'objective' => $lesson['objective'], 'term' => $template['term'],
                'quiz' => [
                    'title' => $lesson['topic'].' — 10-question quiz',
                    'instructions' => 'Choose one answer for each question. Review your lesson examples before starting. Each question has a 60-second limit; your teacher can change the timing before publishing.',
                    'questions' => $questions,
                    'xp' => 0, 'time_limit' => 10, 'untimed' => false, 'question_seconds' => 60,
                ],
            ];
            foreach (['assignment', 'challenge'] as $kind) {
                $catalog[$key][$kind] = $template[$kind] + [
                    'kind' => $kind,
                    'school_term' => $template['term'],
                    'difficulty' => $template['difficulty'],
                    'time_limit_minutes' => $kind === 'challenge' ? 40 : 30,
                    'xp' => $kind === 'challenge' ? 200 : 150,
                    'csta_standard' => null,
                ];
            }
            $article = config('ready_made_articles.'.$key);
            if ($article !== null) {
                $parts = ['Learning goal', $lesson['objective'], $article['introduction']];
                foreach ($lesson['sections'] as $section) {
                    $parts[] = $section['title']."\n".$section['text'];
                }
                $parts[] = "C++ example\n".$lesson['code'];
                $parts[] = "Expected output\n".$lesson['output'];
                $parts[] = "Your private response\n".implode("\n\n", array_map(static fn (string $prompt, int $index): string => ($index + 1).'. '.$prompt, $article['prompts'], array_keys($article['prompts'])));
                $parts[] = 'Explain your reasoning in your own words. You may attach a code screenshot or record a voice response. Your comments and teacher replies stay private between you and your teacher.';
                $catalog[$key]['article'] = [
                    'title' => $article['title'], 'category' => $article['category'],
                    'body' => implode("\n\n", $parts),
                    'is_graded' => false, 'response_time_limit_minutes' => 5,
                    'teacher_notes' => $article['teacher_notes'],
                ];
            }
        }

        return $catalog;
    }

    /** @return array<string, mixed> */
    public function lesson(string $key): array
    {
        $catalog = $this->catalog();
        abort_unless(isset($catalog[$key]), 404);

        return $catalog[$key];
    }
}
