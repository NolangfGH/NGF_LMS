<?php

namespace App\Services;

use App\Models\LabSubmission;
use App\Models\LessonProgress;
use App\Models\Student;
use App\Models\StudentAchievement;
use App\Models\TeacherAssignment;
use App\Models\TeacherQuiz;

class StudentSettingsService
{
    public function avatars(Student $student): array
    {
        $badges = StudentAchievement::where('student_id', $student->id)->pluck('badge_code');
        $cards = [];
        foreach (config('student_settings.avatars') as $key => $definition) {
            $unlocked = $student->xp >= $definition['xp'] && (! isset($definition['badge']) || $badges->contains($definition['badge']));
            $cards[] = ['key' => $key, ...$definition, 'unlocked' => $unlocked, 'equipped' => $student->avatar === $key,
                'requirement' => isset($definition['badge']) ? 'C++ Problem Solver badge' : ($definition['xp'] ? number_format($definition['xp']).' total XP · Level '.(intdiv($definition['xp'], 600) + 1) : 'Available from the start')];
        }

        return $cards;
    }

    public function alerts(Student $student): array
    {
        $teacherId = $student->classroom?->teacher_account_id;
        $now = now();
        $soon = $now->copy()->addDay();

        return [
            'assignment_deadlines' => TeacherAssignment::forClassroom($student->classroom_id)->where('teacher_account_id', $teacherId)->where('status', 'published')->where('due_at', '>', $now)->where('due_at', '<=', $soon)->count(),
            'quiz_deadlines' => TeacherQuiz::forClassroom($student->classroom_id)->where('teacher_account_id', $teacherId)->where('status', 'published')->where('closes_at', '>', $now)->where('closes_at', '<=', $soon)->count(),
            'feedback' => LabSubmission::whereHas('workspace', fn ($query) => $query->where('student_id', $student->id))->whereNotNull('reviewed_at')->whereNotNull('feedback')->count()
                + LessonProgress::where('student_id', $student->id)->whereNotNull('teacher_feedback')->count(),
            'badges' => StudentAchievement::where('student_id', $student->id)->count(),
        ];
    }
}
