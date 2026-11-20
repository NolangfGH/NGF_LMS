<?php

namespace App\Services;

class DepEdSubjectGrading
{
    public const REFERENCE = 'https://www.deped.gov.ph/2026/06/04/june-4-2026-do-015-s-2026-revised-guidelines-on-classroom-assessment-grading-system-and-awards-and-recognition-for-the-k-to-12-basic-education-program/';

    public const TRANSITION = '2026-2027';

    public static function schoolDefined(string $course): bool
    {
        return ! LearningCourses::isAcademic($course) || LearningCourses::isElective($course);
    }

    public static function weights(string $course): array
    {
        return ['WW' => 20, 'PT' => str_starts_with($course, 'mapeh') || ! LearningCourses::isAcademic($course) ? 60 : 50, 'EX' => str_starts_with($course, 'mapeh') || ! LearningCourses::isAcademic($course) ? 20 : 30];
    }

    public function template(string $course, ?array $weights = null): array
    {
        $weights ??= self::weights($course);
        $parts = str_starts_with($course, 'mapeh') ? ['ma' => 'Music and Arts', 'ph' => 'Physical Education and Health'] : ['main' => LearningCourses::title($course)];
        $groups = [];
        $slots = [];
        foreach ($parts as $part => $label) {
            $prefix = $part === 'main' ? '' : $part.'_';
            $groupDefinitions = str_starts_with($course, 've') ? [
                ['wwc', 'Written/Oral · Cognitive', 10, 2, 2, 20], ['wwa', 'Written/Oral · Affective', 10, 1, 1, 20],
                ['ptc', 'Performance · Cognitive', 10, 1, 1, 25], ['pta', 'Performance · Affective', 10, 1, 1, 25], ['ptb', 'Performance · Behavioral', 30, 1, 1, 25],
            ] : [
                ['ww', 'Written/Oral Works', $weights['WW'], 5, 3, 20], ['pt', 'Product/Performance Tasks', $weights['PT'], 3, 2, 25],
            ];
            $groupDefinitions = [...$groupDefinitions, ['st1', 'Summative Test 1', $weights['EX'] * .3, 1, 1, 25], ['st2', 'Summative Test 2', $weights['EX'] * .3, 1, 1, 25], ['te', 'Term Examination', $weights['EX'] * .4, 1, 1, 25]];
            foreach ($groupDefinitions as [$group,$groupLabel,$weight,$count,$active,$max]) {
                $groupKey = $prefix.$group;
                $groups[$groupKey] = ['label' => $groupLabel, 'part' => $part, 'weight' => $weight];
                for ($i = 1; $i <= $count; $i++) {
                    $key = $groupKey.($count > 1 ? $i : '');
                    $slots[$key] = ['label' => $groupLabel.($count > 1 ? ' '.$i : ''), 'group' => $groupKey, 'max' => $i <= $active ? $max : 0];
                }
            }
        }

        return compact('parts', 'groups', 'slots', 'weights');
    }

    public function calculate(array $definition, array $scores, string $year): array
    {
        $groupScores = [];
        $partGrades = [];
        $complete = true;
        foreach ($definition['groups'] as $key => $group) {
            $slots = array_filter($definition['slots'], fn ($slot) => $slot['group'] === $key && $slot['max'] > 0);
            $maximum = array_sum(array_column($slots, 'max'));
            $raw = 0;
            $resolved = $maximum > 0;
            foreach ($slots as $slotKey => $slot) {
                $value = $scores[$slotKey] ?? null;
                if (! is_numeric($value) || $value < 0 || $value > $slot['max']) {
                    $resolved = false;

                    continue;
                }
                $raw += (float) $value;
            }
            $percent = $resolved ? 100 * $raw / $maximum : null;
            $groupScores[$key] = ['raw' => $raw, 'max' => $maximum, 'percent' => $percent !== null ? round($percent, 2) : null, 'weighted' => $percent !== null ? $percent * $group['weight'] / 100 : null];
            if (! $resolved && $group['weight'] > 0) {
                $complete = false;
            }
        }
        foreach ($definition['parts'] as $part => $label) {
            $keys = array_keys(array_filter($definition['groups'], fn ($group) => $group['part'] === $part && $group['weight'] > 0));
            $weighted = array_map(fn ($key) => $groupScores[$key]['weighted'], $keys);
            $initial = in_array(null, $weighted, true) ? null : round(array_sum($weighted), 2);
            $partGrades[$part] = ['initial' => $initial, 'grade' => $initial === null ? null : self::termGrade($initial, $year)];
        }
        $grades = array_column($partGrades, 'grade');
        $grade = $complete && ! in_array(null, $grades, true) ? (int) round(array_sum($grades) / count($grades)) : null;
        $initials = array_column($partGrades, 'initial');
        $initial = $complete && ! in_array(null, $initials, true) ? round(array_sum($initials) / count($initials), 2) : null;

        return ['groups' => $groupScores, 'parts' => $partGrades, 'complete' => $complete, 'initial' => $initial, 'grade' => $grade, 'descriptor' => $grade === null ? 'Pending' : self::descriptor($grade)];
    }

    public static function termGrade(float $initial, string $year): int
    {
        $initial = round(max(0, min(100, $initial)), 2);
        if ($year !== self::TRANSITION) {
            return (int) round($initial);
        }
        $minimums = [100 => 99.50, 99 => 98.32, 98 => 97.14, 97 => 95.96, 96 => 94.78, 95 => 93.60, 94 => 92.42, 93 => 91.24, 92 => 90.06, 91 => 88.88, 90 => 87.70, 89 => 86.52, 88 => 85.34, 87 => 84.16, 86 => 82.98, 85 => 81.80, 84 => 80.62, 83 => 79.44, 82 => 78.26, 81 => 77.08, 80 => 75.90, 79 => 74.72, 78 => 73.54, 77 => 72.36, 76 => 71.18, 75 => 70.00, 74 => 65.34, 73 => 60.67, 72 => 56.01, 71 => 51.34, 70 => 46.67, 69 => 42.01, 68 => 37.34, 67 => 32.68, 66 => 28.01, 65 => 23.35, 64 => 18.68, 63 => 14.01, 62 => 9.35, 61 => 4.68, 60 => 0];
        foreach ($minimums as $grade => $minimum) {
            if ($initial >= $minimum) {
                return $grade;
            }
        }

        return 60;
    }

    public static function descriptor(int $grade): string
    {
        return match (true) {
            $grade >= 90 => 'Advancing', $grade >= 80 => 'Benchmarking', $grade >= 75 => 'Connecting', $grade >= 65 => 'Developing', default => 'Emerging',
        };
    }
}
