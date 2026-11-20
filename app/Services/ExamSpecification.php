<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

class ExamSpecification
{
    public const LEVELS = ['remember' => 'Remember', 'understand' => 'Understand', 'apply' => 'Apply', 'analyze' => 'Analyze', 'evaluate' => 'Evaluate', 'create' => 'Create'];

    public static function item(array $lesson, string $level): array
    {
        return ['topic' => $lesson['topic'], 'competency' => $lesson['objective'], 'level' => $level];
    }

    public static function suggestLevel(string $prompt): string
    {
        return match (true) {
            preg_match('/\b(?:best approach|best alternative|justify|evaluate|most appropriate)\b/i', $prompt) === 1 => 'evaluate',
            preg_match('/\b(?:error|debug|incorrect|compare|difference|why does|fault|misleading)\b/i', $prompt) === 1 => 'analyze',
            preg_match('/\b(?:output|calculate|compute|trace|predict|value|result|convert|determine|how many)\b/i', $prompt) === 1 => 'apply',
            preg_match('/\b(?:why|explain|purpose|meaning|describes|how)\b/i', $prompt) === 1 => 'understand',
            default => 'remember',
        };
    }

    public function exam(string $course, string $term, ReadyMadeActivities $library): array
    {
        abort_unless(in_array($course, LearningCourses::keys(), true) && in_array($term, ['Term1', 'Term2', 'Term3'], true), 404);
        $key = ($course === 'cpp' ? '' : ($course === 'arduino-uno' ? 'arduino' : $course).'-').strtolower($term).'-term-exam';

        return $library->termExams($course)[$key] ?? abort(404);
    }

    public function defaults(array $exam): array
    {
        $rows = [];
        foreach ($exam['item_specs'] as $index => $item) {
            $topic = $item['topic'];
            $rows[$topic] ??= ['topic' => $topic, 'competency' => $item['competency'], 'hours' => 2, 'items' => array_fill_keys(array_keys(self::LEVELS), [])];
            $rows[$topic]['items'][$item['level']][] = $index + 1;
        }

        return array_values($rows);
    }

    public function normalized(array $rows, int $questionCount): array
    {
        $seen = [];
        foreach ($rows as $index => &$row) {
            foreach (self::LEVELS as $level => $label) {
                $value = trim($row['items'][$level] ?? '');
                $numbers = $value === '' ? [] : preg_split('/[\s,]+/', $value, -1, PREG_SPLIT_NO_EMPTY);
                if (collect($numbers)->contains(fn ($id) => ! ctype_digit($id) || (int) $id < 1 || (int) $id > $questionCount || strlen($id) > 3)) {
                    throw ValidationException::withMessages(['rows.'.$index.'.items.'.$level => 'Enter item numbers between 1 and '.$questionCount.', separated by commas.']);
                }
                $row['items'][$level] = array_map('intval', $numbers);
                sort($row['items'][$level]);
                $seen = [...$seen, ...$row['items'][$level]];
            }
            $row['hours'] = (float) $row['hours'];
        }
        unset($row);
        sort($seen);
        if ($seen !== range(1, $questionCount)) {
            throw ValidationException::withMessages(['rows' => 'Every item number from 1 to '.$questionCount.' must be assigned exactly once. Check missing or duplicate numbers.']);
        }

        return $rows;
    }

    public function totals(array $rows): array
    {
        $levels = array_fill_keys(array_keys(self::LEVELS), 0);
        $hours = 0;
        foreach ($rows as $row) {
            $hours += (float) $row['hours'];
            foreach ($levels as $level => $count) {
                $levels[$level] += count($row['items'][$level]);
            }
        }

        return ['hours' => $hours, 'levels' => $levels, 'items' => array_sum($levels)];
    }
}
