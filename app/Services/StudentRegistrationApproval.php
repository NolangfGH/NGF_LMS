<?php

namespace App\Services;

use App\Models\Classroom;
use App\Models\LearningNotification;
use App\Models\Student;
use App\Models\TeacherAccount;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class StudentRegistrationApproval
{
    public function owned(TeacherAccount $teacher): Builder
    {
        return Student::withUnapproved()->whereHas('classroom', fn (Builder $query) => $query->where('teacher_account_id', $teacher->id));
    }

    public function review(TeacherAccount $teacher, int $studentId, string $decision, int $revision, ?string $note): Student
    {
        $request = $this->owned($teacher)->findOrFail($studentId);

        return DB::transaction(function () use ($teacher, $studentId, $decision, $revision, $note, $request): Student {
            $room = Classroom::where('teacher_account_id', $teacher->id)->lockForUpdate()->findOrFail($request->classroom_id);
            $student = $this->owned($teacher)->where('classroom_id', $room->id)->lockForUpdate()->findOrFail($studentId);
            abort_if($student->registration_revision !== $revision, 409, 'This request was reviewed in another tab. Refresh before reviewing it again.');
            $prior = $student->registration_status;
            abort_unless(($prior === 'pending' && in_array($decision, ['approved', 'rejected'], true)) || ($prior === 'rejected' && $decision === 'pending'), 409, 'This registration is no longer awaiting this decision.');
            abort_if($decision === 'approved' && $room->archived_at, 422, 'Restore this classroom before approving its registrations.');

            $student->forceFill([
                'registration_status' => $decision,
                'registration_reviewed_by' => $teacher->id,
                'registration_reviewed_at' => now(),
                'registration_review_note' => $note,
                'registration_revision' => $student->registration_revision + 1,
                'session_version' => $student->session_version + 1,
            ])->save();
            $this->audit($student, $teacher, $prior, $decision, $note);

            if ($decision === 'approved') {
                app(LearningRewards::class)->award($student->id, 'registration:approved', Student::REGISTRATION_XP, ['type' => 'registration', 'id' => $student->id, 'title' => 'Student registration', 'reason' => 'Registration approved', 'details' => ['Initial 100 XP credited once when the class teacher approved registration.']]);
                $student->refresh();
                app(LearningNotifications::class)->registrationApproved($student);
            }
            LearningNotification::where('teacher_account_id', $teacher->id)->where('target_type', 'student_registration')->where('target_id', $student->id)->whereNull('read_at')->update(['read_at' => now()]);
            if ($decision === 'pending') {
                app(LearningNotifications::class)->registrationSubmitted($student);
            }

            return $student;
        }, 3);
    }

    public function teacherEnrolled(Student $student, TeacherAccount $teacher): void
    {
        $student->forceFill(['registration_reviewed_by' => $teacher->id, 'registration_reviewed_at' => now()])->save();
        $this->audit($student, $teacher, null, 'approved', 'Added directly by the class administrator.');
    }

    private function audit(Student $student, TeacherAccount $teacher, ?string $prior, string $decision, ?string $note): void
    {
        DB::table('student_registration_reviews')->insert([
            'student_id' => $student->id, 'teacher_account_id' => $teacher->id, 'classroom_id' => $student->classroom_id,
            'from_status' => $prior, 'decision' => $decision, 'note' => $note, 'created_at' => now(),
        ]);
    }
}
