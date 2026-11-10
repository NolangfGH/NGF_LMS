<?php

namespace Tests;

use App\Models\Student;
use App\Services\CourseAssessments;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    protected function recordCompletedLessonPrerequisites(Student ...$students): void
    {
        $rows = [];
        foreach ($students as $student) {
            foreach ([CourseAssessments::REASONING, CourseAssessments::PRE] as $assessment) {
                $rows[] = ['student_id' => $student->id, 'assessment' => $assessment, 'score' => 0,
                    'total_questions' => 50, 'skill_scores' => '{}', 'completed_at' => now()];
            }
        }
        DB::table('course_assessment_results')->insert($rows);
    }
}
