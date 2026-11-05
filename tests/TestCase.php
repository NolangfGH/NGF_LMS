<?php

namespace Tests;

use App\Models\Student;
use App\Services\CourseAssessments;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    protected function recordCompletedPretest(Student ...$students): void
    {
        DB::table('course_assessment_results')->insert(array_map(static fn (Student $student): array => [
            'student_id' => $student->id, 'assessment' => CourseAssessments::PRE, 'score' => 0,
            'total_questions' => 25, 'skill_scores' => '{}', 'completed_at' => now(),
        ], $students));
    }
}
