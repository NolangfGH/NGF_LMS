<?php

namespace App\Services;

use App\Models\{SecondChanceRequest,TeacherAssignment,TeacherQuiz,CodeWorkspace,QuizAttempt,Student};

class SecondChanceService
{
    public function requestFor(int $studentId, string $type, int $id): ?SecondChanceRequest
    {
        return SecondChanceRequest::where('student_id',$studentId)->where('activity_type',$type)->where('activity_id',$id)->first();
    }
    public function assignmentScheduleClosed(TeacherAssignment $assignment, int $studentId): bool
    {
        $grant=$this->requestFor($studentId,'assignment',$assignment->id);
        return $grant?->status === 'approved' ? !$grant->isActive() : $assignment->isClosed();
    }
    public function quizOpen(TeacherQuiz $quiz, int $studentId): bool
    {
        $grant=$this->requestFor($studentId,'quiz',$quiz->id);
        return $grant?->status === 'approved' ? $grant->isActive() : $quiz->isOpen();
    }
    public function eligible(Student $student, string $type, TeacherAssignment|TeacherQuiz $activity): bool
    {
        if ($type === 'assignment') {
            $workspace=CodeWorkspace::where('student_id',$student->id)->where('teacher_assignment_id',$activity->id)->first();
            return $activity->isClosed() || ($workspace && app(CodeLabTimer::class)->describe($workspace)['expired']);
        }
        $attempt=QuizAttempt::where('student_id',$student->id)->where('teacher_quiz_id',$activity->id)->orderByDesc('attempt_number')->first();
        return ($activity->closes_at && now()->gte($activity->closes_at)) || ($attempt && now()->gte($attempt->expires_at));
    }
    // The caller holds the student lock, serializing activation with review and repeated opens.
    public function activateAssignment(CodeWorkspace $workspace): void
    {
        if (!$workspace->teacher_assignment_id) return;
        $grant=$this->requestFor($workspace->student_id,'assignment',$workspace->teacher_assignment_id);
        if (!$grant?->isActive() || $workspace->second_chance_request_id === $grant->id) return;
        $grant->update(['used_at'=>now(),'prior_timer_started_at'=>$workspace->timer_started_at,'prior_time_limit_seconds'=>$workspace->time_limit_seconds]);
        $workspace->update(['second_chance_request_id'=>$grant->id,'timer_started_at'=>now()->startOfSecond(),'time_limit_seconds'=>$grant->duration_minutes*60,'revision'=>$workspace->revision+1]);
    }
}
