<?php

namespace App\Services;

class LearningCourses
{
    public const TECHNOLOGY = ['cpp' => 'C++ Programming', 'arduino-uno' => 'Arduino Uno Robotics', 'ct7' => 'Creative Technology 7', 'ct10' => 'Creative Technology 10'];

    public const TITLES = [
        ...self::TECHNOLOGY,
        'math7' => 'Mathematics 7', 'science7' => 'Science 7', 'english7' => 'English 7', 'ap7' => 'Araling Panlipunan 7', 've7' => 'Values Education (ESP) 7', 'filipino7' => 'Filipino 7', 'mapeh7' => 'MAPEH 7', 'research7' => 'Research 7', 'advmath7' => 'Advanced Mathematics 7', 'stem7' => 'Science Enrichment 7',
        'math8' => 'Mathematics 8', 'science8' => 'Science 8', 'english8' => 'English 8', 'ap8' => 'Araling Panlipunan 8', 've8' => 'Values Education (ESP) 8', 'filipino8' => 'Filipino 8', 'mapeh8' => 'MAPEH 8', 'research8' => 'Research 8', 'advmath8' => 'Advanced Mathematics 8', 'stem8' => 'Science Enrichment 8',
        'math9' => 'Mathematics 9', 'science9' => 'Science 9', 'english9' => 'English 9', 'ap9' => 'Araling Panlipunan 9', 've9' => 'Values Education (ESP) 9', 'filipino9' => 'Filipino 9', 'mapeh9' => 'MAPEH 9', 'research9' => 'Research 9', 'advmath9' => 'Advanced Mathematics 9', 'stem9' => 'Science Enrichment 9',
        'math10' => 'Mathematics 10', 'science10' => 'Science 10', 'english10' => 'English 10', 'ap10' => 'Araling Panlipunan 10', 've10' => 'Values Education (ESP) 10', 'filipino10' => 'Filipino 10', 'mapeh10' => 'MAPEH 10', 'research10' => 'Research 10', 'advmath10' => 'Advanced Mathematics 10', 'stem10' => 'Science Enrichment 10',
    ];

    public static function keys(): array
    {
        return array_keys(self::TITLES);
    }

    public static function title(string $course): string
    {
        return self::TITLES[$course] ?? self::TITLES['cpp'];
    }

    public static function recommendedForGrade(?string $grade): string
    {
        return match ($grade) {
            'Grade 7' => 'ct7',
            'Grade 9' => 'arduino-uno',
            'Grade 10' => 'ct10',
            default => 'cpp',
        };
    }

    public static function isCreative(string $course): bool
    {
        return in_array($course, ['ct7', 'ct10'], true);
    }

    public static function isAcademic(string $course): bool
    {
        return isset(self::TITLES[$course]) && ! isset(self::TECHNOLOGY[$course]);
    }

    public static function isElective(string $course): bool
    {
        return self::isAcademic($course) && preg_match('/^(research|advmath|stem)/', $course) === 1;
    }

    public static function grade(string $course): string
    {
        return match ($course) {
            'cpp' => 'Grade 8', 'arduino-uno' => 'Grade 9', 'ct7' => 'Grade 7', 'ct10' => 'Grade 10',
            default => 'Grade '.(preg_match('/(7|8|9|10)$/', $course, $matches) ? $matches[1] : '8'),
        };
    }

    public static function groups(): array
    {
        $groups = [];
        foreach (self::TITLES as $course => $title) {
            $groups[self::grade($course)][$course] = $title;
        }
        ksort($groups, SORT_NATURAL);

        return $groups;
    }

    public static function environment(string $course): string
    {
        return match ($course) {
            'ct7' => 'design',
            'ct10', 'arduino-uno' => 'arduino-uno',
            default => self::isAcademic($course) ? 'academic' : 'cpp',
        };
    }

    public static function filename(string $course): string
    {
        return match (self::environment($course)) {
            'design' => 'design-notebook.txt',
            'academic' => 'study-notebook.txt',
            'arduino-uno' => 'sketch.ino',
            default => 'main.cpp',
        };
    }

    public static function lessons(?string $course = null): array
    {
        return match ($course) {
            'cpp' => config('lessons'),
            'arduino-uno' => config('arduino_curriculum.lessons'),
            'ct7', 'ct10' => config('creative_technology.'.$course.'.lessons'),
            null => array_merge(...array_map(self::lessons(...), self::keys())),
            default => self::isAcademic($course) ? AcademicCurriculum::pack($course)['lessons'] : [],
        };
    }

    public static function lessonCourse(string $key): string
    {
        $prefix = explode('-', $key, 2)[0];
        if (self::isAcademic($prefix) && isset(self::lessons($prefix)[$key])) {
            return $prefix;
        }

        return str_starts_with($key, 'arduino-') ? 'arduino-uno' : (self::isCreative($prefix) ? $prefix : 'cpp');
    }

    public static function pretest(string $course): string
    {
        return match ($course) {
            'arduino-uno' => 'arduino-pre-test',
            'ct7', 'ct10' => $course.'-pre-test',
            default => self::isAcademic($course) ? $course.'-pre-test' : 'pre-test',
        };
    }

    public static function posttest(string $course): string
    {
        return match ($course) {
            'arduino-uno' => 'arduino-post-test',
            'ct7', 'ct10' => $course.'-post-test',
            default => self::isAcademic($course) ? $course.'-post-test' : 'post-test',
        };
    }

    public static function assessmentCourse(string $assessment): ?string
    {
        foreach (self::keys() as $course) {
            if (in_array($assessment, [self::pretest($course), self::posttest($course)], true)) {
                return $course;
            }
        }

        return null;
    }
}
