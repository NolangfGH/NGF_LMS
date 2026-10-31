<?php

namespace Tests\Supports;

use App\Models\QuizAttempt;
use App\Models\TeacherQuiz;

trait ExistingLegacyQuizAttempt
{
    // Simulate an attempt started before per-question timing was introduced.
    private function beginLegacyQuiz(TeacherQuiz $quiz)
    {
        $quiz->refresh();
        $deadline = now()->addMinutes($quiz->time_limit);
        if ($quiz->closes_at && $quiz->closes_at->lt($deadline)) $deadline = $quiz->closes_at;
        QuizAttempt::firstOrCreate(['student_id'=>session('student_id'),'teacher_quiz_id'=>$quiz->id],[
            'questions'=>$quiz->questions,'answers'=>[],'started_at'=>now(),'expires_at'=>$deadline,'sequential'=>false,
        ]);
        return $this->post(route('student.quiz.start',$quiz));
    }
}
