<?php

namespace App\Services;

use App\Models\BlogComment;
use App\Models\BlogPost;
use App\Models\BlogResponse;
use App\Models\CodingMatch;
use App\Models\LabSubmission;
use App\Models\LearningNotification;
use App\Models\LessonProgress;
use App\Models\QuizBeeMatch;
use App\Models\Student;
use App\Models\TeacherAccount;
use App\Models\TeacherAssignment;
use App\Models\TeacherQuiz;
use Illuminate\Support\Facades\DB;

class LearningNotificationTargets
{
    private array $cache = [];

    private function find(string $model, int $id)
    {
        $key = $model.':'.$id;
        if (! array_key_exists($key, $this->cache)) {
            $this->cache[$key] = $model::find($id);
        }

        return $this->cache[$key];
    }

    public function url(LearningNotification $notice, Student|TeacherAccount $actor): ?string
    {
        $teacher = $actor instanceof TeacherAccount;
        $role = $teacher ? 'teacher' : 'student';
        if ($notice->coding_match_id) {
            $match = $this->find(CodingMatch::class, $notice->coding_match_id);
            if (! $match || ($teacher ? $match->teacher_account_id !== $actor->id : ! $match->participants()->where('student_id', $actor->id)->exists())) {
                return null;
            }

            return route($role.'.matches.show', $match);
        }
        $id = (int) $notice->target_id;
        if ($notice->target_type === 'quiz_bee') {
            $match = $this->find(QuizBeeMatch::class, $id);
            if (! $match || ($teacher ? $match->teacher_account_id !== $actor->id : $match->status === 'draft'
                || $actor->classroom?->teacher_account_id !== $match->teacher_account_id
                || ! DB::table('quiz_bee_members')->where('quiz_bee_match_id', $id)->where('student_id', $actor->id)->exists())) {
                return null;
            }

            return route($role.'.quiz-bee.show', $match);
        }
        if ($notice->target_type === 'student_registration') {
            $student = Student::withUnapproved()->with('classroom')->find($id);
            if (! $student) {
                return null;
            }
            if ($teacher) {
                return $student->classroom?->teacher_account_id === $actor->id
                    ? route('teacher.registrations', ['status' => $student->registration_status, 'focus' => $id]).'#registration-'.$id : null;
            }

            return $student->id === $actor->id && $student->isRegistrationApproved() ? route('student.dashboard') : null;
        }
        if (in_array($notice->target_type, ['assignment', 'quiz'], true)) {
            $quiz = $notice->target_type === 'quiz';
            $activity = $this->find($quiz ? TeacherQuiz::class : TeacherAssignment::class, $id);
            if (! $activity) {
                return null;
            }
            if ($teacher) {
                return $activity->teacher_account_id === $actor->id ? route($quiz ? 'teacher.quizzes' : 'teacher.assignments') : null;
            }
            if ($activity->status !== 'published' || $actor->classroom?->teacher_account_id !== $activity->teacher_account_id || ! $activity->isAssignedTo((int) $actor->classroom_id)) {
                return null;
            }

            return route($quiz ? 'student.quiz' : ($activity->kind === 'challenge' ? 'student.challenges' : 'student.assignments')).'#'.($quiz ? 'quiz-' : 'assignment-').$id;
        }
        if (in_array($notice->target_type, ['article', 'blog_comment', 'blog_response'], true)) {
            $comment = null;
            if ($notice->target_type === 'article') {
                $post = $this->find(BlogPost::class, $id);
            } else {
                $entry = $this->find($notice->target_type === 'blog_comment' ? BlogComment::class : BlogResponse::class, $id);
                $comment = $entry instanceof BlogComment ? $entry : $entry?->comment;
                $post = $comment?->post;
            }
            if (! $post) {
                return null;
            }
            if ($teacher) {
                if ($post->teacher_account_id !== $actor->id) {
                    return null;
                }
            } elseif ($post->status !== 'published' || $actor->classroom?->teacher_account_id !== $post->teacher_account_id || ($comment && $comment->student_id !== $actor->id)) {
                return null;
            }
            $args = ['post' => $post->id];
            if ($comment) {
                $args['conversation'] = $comment->id;
            }

            return route($role.'.blog.show', $args).($comment ? '#comment-'.$comment->id : '');
        }
        if ($notice->target_type === 'lab_submission') {
            $submission = $this->find(LabSubmission::class, $id);
            if (! $submission) {
                return null;
            }
            if ($teacher) {
                return $submission->teacher_account_id === $actor->id ? route('teacher.code-lab', ['submission' => $id]).'#submission-'.$id : null;
            }
            if ($submission->workspace?->student_id !== $actor->id || ! $submission->reviewed_at) {
                return null;
            }

            return route('student.progress');
        }
        if ($notice->target_type === 'quiz_submission' && $teacher) {
            $result = DB::table('quiz_submissions')->join('teacher_quizzes', 'teacher_quizzes.id', '=', 'quiz_submissions.teacher_quiz_id')->where('quiz_submissions.id', $id)->where('teacher_quizzes.teacher_account_id', $actor->id)->exists();

            return $result ? route('teacher.quiz.reviews', ['submission' => $id]).'#quiz-submission-'.$id : null;
        }
        if ($notice->target_type === 'lesson_progress' && $teacher) {
            $progress = $this->find(LessonProgress::class, $id);
            $student = $progress?->student;
            if (! $student || $student->classroom?->teacher_account_id !== $actor->id) {
                return null;
            }

            return route('teacher.lessons.progress', ['classroom' => $student->classroom_id, 'lesson' => $progress->lesson_key, 'search' => $student->student_id]);
        }

        return null;
    }
}
