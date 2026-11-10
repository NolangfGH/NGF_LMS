<?php

namespace App\Services;

use App\Models\BlogComment;
use App\Models\BlogPost;
use App\Models\BlogResponse;
use App\Models\Classroom;
use App\Models\LabSubmission;
use App\Models\LearningNotification;
use App\Models\LessonProgress;
use App\Models\QuizBeeMatch;
use App\Models\Student;
use App\Models\TeacherAccount;
use App\Models\TeacherAssignment;
use App\Models\TeacherLessonPlan;
use App\Models\TeacherQuiz;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LearningNotifications
{
    public function postTestCodeRequested(Student $student, int $requestId): void
    {
        $course = DB::table('course_post_test_attempts')->where('id', $requestId)->value('course') === 'arduino-uno' ? 'Arduino Uno' : 'C++';
        $this->send('teacher', $student->classroom?->teacher_account_id, 'quizzes', 'post-test-code:'.$requestId,
            'Post-test code requested', $student->nickname.' is ready for the '.$course.' certification exam. Open Post-test codes to issue a personal code.',
            'post_test_code', $requestId);
    }

    public function ilawPlanEvaluated(int $teacherId, int $planId, int $revision): void
    {
        $this->send('teacher', $teacherId, 'feedback', 'ilaw-evaluated:'.$planId.':'.$revision, 'ILAW lesson plan evaluated',
            'Your principal checked your lesson plan. Open it to see the review and any coaching comments.', 'ilaw_plan', $planId);
    }

    public function quizBee(QuizBeeMatch $match, string $role, int $recipient, string $event, string $title, string $message): void
    {
        $this->send($role, $recipient, 'challenges', 'quiz-bee:'.$match->id.':'.$event, $title, $message, 'quiz_bee', $match->id);
    }

    public function registrationSubmitted(Student $student): void
    {
        $this->send('teacher', $student->classroom?->teacher_account_id, 'registrations', 'registration-submitted:'.$student->id.':'.$student->registration_revision,
            'Student registration awaiting approval', $student->first_name.' '.$student->surname.' requested to join '.$student->grade_level.' · '.$student->section.'. Verify the student before approving access.', 'student_registration', $student->id);
    }

    public function registrationApproved(Student $student): void
    {
        $this->send('student', $student->id, 'registrations', 'registration-approved:'.$student->id, 'Registration approved',
            'Your class administrator approved your account. Your 100 welcome XP is ready. Start your C++ learning journey.', 'student_registration', $student->id);
    }

    private function row(string $role, int $recipient, string $category, string $event, string $title, string $message, string $type, int $target): array
    {
        return ['coding_match_id' => null, 'student_id' => $role === 'student' ? $recipient : null, 'teacher_account_id' => $role === 'teacher' ? $recipient : null,
            'event_key' => Str::limit($event, 120, ''), 'delivery_key' => hash('sha256', $role.':'.$recipient.':'.$event), 'category' => $category,
            'title' => Str::limit($title, 180), 'message' => Str::limit($message, 500), 'target_type' => $type, 'target_id' => $target, 'created_at' => now()];
    }

    private function send(string $role, ?int $recipient, string $category, string $event, string $title, string $message, string $type, int $target): void
    {
        if (! $recipient) {
            return;
        }
        $exists = $role === 'student' ? Student::whereKey($recipient)->exists() : TeacherAccount::whereKey($recipient)->exists();
        if ($exists) {
            LearningNotification::insertOrIgnore($this->row($role, $recipient, $category, $event, $title, $message, $type, $target));
        }
    }

    private function audience(TeacherAssignment|TeacherQuiz $activity)
    {
        $ids = $activity->classrooms()->pluck('classrooms.id')->push($activity->classroom_id)->filter()->unique();

        return Student::whereIn('classroom_id', Classroom::where('teacher_account_id', $activity->teacher_account_id)->whereIn('id', $ids)->select('id'));
    }

    private function publishTo($students, string $category, string $event, string $title, string $message, string $type, int $target): void
    {
        $students->select('students.id')->chunkById(100, function ($rows) use ($category, $event, $title, $message, $type, $target) {
            LearningNotification::insertOrIgnore($rows->map(fn ($student) => $this->row('student', $student->id, $category, $event, $title, $message, $type, $target))->all());
        });
    }

    public function publishedActivity(TeacherAssignment|TeacherQuiz $activity, ?Student $only = null): void
    {
        if ($activity->status !== 'published' || $activity->trashed()) {
            return;
        }
        $quiz = $activity instanceof TeacherQuiz;
        $category = $quiz ? 'quizzes' : ($activity->kind === 'challenge' ? 'challenges' : 'assignments');
        $label = $quiz ? 'quiz' : ($activity->kind === 'challenge' ? 'coding challenge' : 'assignment');
        $type = $quiz ? 'quiz' : 'assignment';
        $students = $this->audience($activity)->when($only, fn ($q) => $q->whereKey($only->id));
        $message = 'Your teacher published '.$activity->title.'. Open it to check the instructions'.($quiz ? ' and quiz schedule.' : ' and deadline.');
        $this->publishTo($students, $category, $type.':published:'.$activity->id, 'New '.$label, $message, $type, $activity->id);
    }

    public function publishedArticle(BlogPost $post, ?Student $only = null): void
    {
        if ($post->status !== 'published' || $post->trashed()) {
            return;
        }
        $students = Student::whereHas('classroom', fn ($q) => $q->where('teacher_account_id', $post->teacher_account_id))->when($only, fn ($q) => $q->whereKey($only->id));
        $this->publishTo($students, 'articles', 'article:published:'.$post->id, 'New C++ article', 'Your teacher published '.$post->title.'. Read it and share a private insight.', 'article', $post->id);
    }

    public function blogComment(BlogComment $comment): void
    {
        $post = $comment->post;
        $student = $comment->student;
        if (! $post || ! $student) {
            return;
        }
        $this->send('teacher', $post->teacher_account_id, 'articles', 'blog-comment:'.$comment->id, 'New student blog comment',
            $student->nickname.' sent a private response to '.$post->title.'.', 'blog_comment', $comment->id);
    }

    public function blogResponse(BlogResponse $response): void
    {
        $comment = $response->comment;
        $post = $comment?->post;
        if (! $post) {
            return;
        }
        if ($response->author_role === 'teacher' && $post->status === 'published') {
            $this->send('student', $comment->student_id, 'articles', 'blog-response:'.$response->id, 'Your teacher replied',
                'Your teacher added a private response to your conversation about '.$post->title.'.', 'blog_response', $response->id);
        } elseif ($response->author_role === 'student') {
            $this->send('teacher', $post->teacher_account_id, 'articles', 'blog-response:'.$response->id, 'New student blog response',
                ($comment->student?->nickname ?? 'A student').' continued the private conversation about '.$post->title.'.', 'blog_response', $response->id);
        }
    }

    public function blogGrade(BlogComment|BlogResponse $entry): void
    {
        if (! $entry->graded_at) {
            return;
        }
        $comment = $entry instanceof BlogComment ? $entry : $entry->comment;
        $post = $comment?->post;
        if (! $post || $post->status !== 'published') {
            return;
        }
        $type = $entry instanceof BlogComment ? 'blog_comment' : 'blog_response';
        $this->send('student', $comment->student_id, 'feedback', 'blog-grade:'.$type.':'.$entry->id, 'Blog assessment saved',
            'Your teacher assessed your response to '.$post->title.'. Open your private conversation to view the feedback and XP.', $type, $entry->id);
    }

    public function labSubmitted(LabSubmission $submission): void
    {
        $student = $submission->workspace?->student;
        if (! $student) {
            return;
        }
        $category = $submission->workspace?->assignment?->kind === 'challenge' ? 'challenges' : 'assignments';
        $this->send('teacher', $submission->teacher_account_id, $category, 'lab-submitted:'.$submission->id, 'Student code submitted',
            $student->nickname.' submitted '.$submission->title.' (revision '.$submission->revision.') for review.', 'lab_submission', $submission->id);
    }

    public function labFeedback(LabSubmission $submission): void
    {
        if (! $submission->reviewed_at) {
            return;
        }
        $this->send('student', $submission->workspace?->student_id, 'feedback', 'lab-feedback:'.$submission->id, 'Code Lab feedback ready',
            'Your teacher reviewed '.$submission->title.'. Open your learning progress to see your updated review status.', 'lab_submission', $submission->id);
    }

    public function quizSubmitted(int $quizId, int $studentId, int $attempt): void
    {
        $result = DB::table('quiz_submissions')->where('teacher_quiz_id', $quizId)->where('student_id', $studentId)->where('attempt_number', $attempt)->first();
        $quiz = TeacherQuiz::withTrashed()->find($quizId);
        $student = Student::withTrashed()->find($studentId);
        if (! $result || ! $quiz || ! $student) {
            return;
        }
        $this->send('teacher', $quiz->teacher_account_id, 'quizzes', 'quiz-submitted:'.$result->id, 'Student quiz submitted',
            $student->nickname.' finished '.$quiz->title.'. Open the saved quiz answers to review the result.', 'quiz_submission', $result->id);
    }

    public function lessonCompleted(LessonProgress $progress): void
    {
        $student = $progress->student;
        $teacherId = $student?->classroom?->teacher_account_id;
        if (! $teacherId || ! $progress->completed_at) {
            return;
        }
        $assigned = TeacherLessonPlan::where('classroom_id', $student->classroom_id)->where('teacher_account_id', $teacherId)->where('lesson_key', $progress->lesson_key)->where('status', 'published')->exists();
        if (! $assigned) {
            return;
        }
        $title = config('lessons.'.$progress->lesson_key.'.title', 'assigned C++ lesson');
        $this->send('teacher', $teacherId, 'lessons', 'lesson-completed:'.$progress->id, 'Assigned lesson completed',
            $student->nickname.' completed '.$title.'. Open learning progress to review the saved evidence.', 'lesson_progress', $progress->id);
    }

    public function initialize(Student|TeacherAccount $actor): void
    {
        if ($actor->notifications_initialized_at) {
            return;
        }
        DB::transaction(function () use ($actor) {
            $current = $actor->newQuery()->whereKey($actor->id)->lockForUpdate()->firstOrFail();
            if ($current->notifications_initialized_at) {
                return;
            }
            if ($actor instanceof Student) {
                foreach ([TeacherAssignment::class, TeacherQuiz::class] as $model) {
                    $model::forClassroom((int) $actor->classroom_id)->where('status', 'published')->chunkById(100, fn ($rows) => $rows->each(fn ($row) => $this->publishedActivity($row, $actor)));
                }
                BlogPost::where('teacher_account_id', $actor->classroom?->teacher_account_id)->where('status', 'published')->chunkById(100, fn ($rows) => $rows->each(fn ($row) => $this->publishedArticle($row, $actor)));
                BlogResponse::whereHas('comment', fn ($q) => $q->where('student_id', $actor->id))->where('author_role', 'teacher')->chunkById(100, fn ($rows) => $rows->each(fn ($row) => $this->blogResponse($row)));
                LabSubmission::whereHas('workspace', fn ($q) => $q->where('student_id', $actor->id))->whereNotNull('reviewed_at')->chunkById(100, fn ($rows) => $rows->each(fn ($row) => $this->labFeedback($row)));
            } else {
                BlogComment::whereHas('post', fn ($q) => $q->where('teacher_account_id', $actor->id))->chunkById(100, fn ($rows) => $rows->each(fn ($row) => $this->blogComment($row)));
                BlogResponse::where('author_role', 'student')->whereHas('comment.post', fn ($q) => $q->where('teacher_account_id', $actor->id))->chunkById(100, fn ($rows) => $rows->each(fn ($row) => $this->blogResponse($row)));
                LabSubmission::where('teacher_account_id', $actor->id)->chunkById(100, fn ($rows) => $rows->each(fn ($row) => $this->labSubmitted($row)));
                DB::table('quiz_submissions')->whereIn('teacher_quiz_id', TeacherQuiz::withTrashed()->where('teacher_account_id', $actor->id)->select('id'))->orderBy('id')->chunkById(100, function ($rows) {
                    foreach ($rows as $row) {
                        $this->quizSubmitted($row->teacher_quiz_id, $row->student_id, $row->attempt_number);
                    }
                });
                LessonProgress::whereNotNull('completed_at')->whereHas('student.classroom', fn ($q) => $q->where('teacher_account_id', $actor->id))->chunkById(100, fn ($rows) => $rows->each(fn ($row) => $this->lessonCompleted($row)));
            }
            $current->forceFill(['notifications_initialized_at' => now()])->saveQuietly();
        });
    }
}
