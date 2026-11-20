<?php

namespace App\Services;

class LearningCourses
{
    public const TITLES = ['cpp' => 'C++ Programming', 'arduino-uno' => 'Arduino Uno Robotics', 'ct7' => 'Creative Technology 7', 'ct10' => 'Creative Technology 10'];

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

    public static function environment(string $course): string
    {
        return match ($course) {
            'ct7' => 'design',
            'ct10', 'arduino-uno' => 'arduino-uno',
            default => 'cpp',
        };
    }

    public static function filename(string $course): string
    {
        return match (self::environment($course)) {
            'design' => 'design-notebook.txt',
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
            null => [...config('lessons'), ...config('arduino_curriculum.lessons'), ...config('creative_technology.ct7.lessons'), ...config('creative_technology.ct10.lessons')],
            default => [],
        };
    }

    public static function lessonCourse(string $key): string
    {
        return self::lessons()[$key]['course'] ?? 'cpp';
    }

    public static function pretest(string $course): string
    {
        return match ($course) {
            'arduino-uno' => 'arduino-pre-test',
            'ct7', 'ct10' => $course.'-pre-test',
            default => 'pre-test',
        };
    }

    public static function posttest(string $course): string
    {
        return match ($course) {
            'arduino-uno' => 'arduino-post-test',
            'ct7', 'ct10' => $course.'-post-test',
            default => 'post-test',
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
