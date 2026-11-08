<?php

namespace App\Services;

use App\Models\BlogComment;
use App\Models\BlogParticipation;
use App\Models\BlogPost;
use App\Models\BlogResponse;
use App\Models\BlogResponseAttempt;
use App\Models\Student;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class BlogLearningService
{
    public function participation(BlogPost $post, Student $student): BlogParticipation
    {
        return DB::transaction(function () use ($post, $student) {
            Student::withTrashed()->whereKey($student->id)->lockForUpdate()->firstOrFail();
            $row = BlogParticipation::where('blog_post_id', $post->id)->where('student_id', $student->id)->lockForUpdate()->first();
            if ($row) {
                return $row;
            }
            $comments = BlogComment::where('blog_post_id', $post->id)->where('student_id', $student->id)->get();
            $messages = $comments->concat(BlogResponse::whereIn('blog_comment_id', $comments->pluck('id'))->where('author_role', 'student')->get())
                ->sortBy(fn ($item) => [$item->created_at?->format('Y-m-d H:i:s.u'), $item instanceof BlogComment ? 0 : 1, $item->id])->values();
            foreach ($messages as $index => $message) {
                $message->timestamps = false;
                $message->forceFill(['submission_number' => $index + 1])->saveQuietly();
            }

            return BlogParticipation::create(['blog_post_id' => $post->id, 'student_id' => $student->id, 'submission_count' => $messages->count()]);
        });
    }

    public function nextNumber(BlogPost $post, Student $student): int
    {
        $participation = $this->participation($post, $student);
        $participation->increment('submission_count');

        return (int) $participation->submission_count;
    }

    public function active(BlogPost $post, Student $student): ?BlogResponseAttempt
    {
        return BlogResponseAttempt::whereHas('participation', fn ($q) => $q->where('blog_post_id', $post->id)->where('student_id', $student->id))
            ->whereNull('submitted_at')->first();
    }

    public function start(BlogPost $post, Student $student, ?BlogComment $comment): BlogResponseAttempt
    {
        return DB::transaction(function () use ($post, $student, $comment) {
            $part = $this->participation($post, $student);
            $active = $part->attempts()->whereNull('submitted_at')->lockForUpdate()->first();
            if ($active && $active->expires_at->isPast()) {
                $this->finish($active, $part);
                $active = null;
            }
            if ($active) {
                if ($active->blog_comment_id !== $comment?->id) {
                    throw ValidationException::withMessages(['body' => 'Finish the response already in progress for this article first.']);
                }

                return $active;
            }
            $currentPost = $post->fresh();
            if (! $currentPost->response_time_limit_minutes) {
                throw ValidationException::withMessages(['body' => 'This article has no timer. Refresh the page to send your response.']);
            }
            if ($comment && $comment->responses()->where('author_role', 'student')->count() >= config('blog.responses_per_person')) {
                throw ValidationException::withMessages(['body' => 'You have used all responses in this conversation.']);
            }

            return $part->attempts()->create(['blog_comment_id' => $comment?->id, 'started_at' => now(), 'expires_at' => now()->addMinutes($currentPost->response_time_limit_minutes)]);
        });
    }

    public function saveAttempt(BlogResponseAttempt $attempt, Student $student, ?string $body, array $media, bool $submit): BlogResponseAttempt
    {
        $discard = [];
        $result = DB::transaction(function () use ($attempt, $student, $body, $media, $submit, &$discard) {
            Student::whereKey($student->id)->lockForUpdate()->firstOrFail();
            $part = BlogParticipation::whereKey($attempt->blog_participation_id)->lockForUpdate()->firstOrFail();
            abort_unless($part->student_id === $student->id, 403);
            $current = BlogResponseAttempt::whereKey($attempt->id)->lockForUpdate()->firstOrFail();
            if ($current->submitted_at) {
                return $current;
            }
            // A small transport allowance lets the browser send its frozen final draft at zero.
            $accept = now()->lt($current->expires_at) || ($submit && now()->lte($current->expires_at->copy()->addSeconds(3)));
            if ($accept) {
                $current->body = $body;
                foreach (['image', 'audio'] as $kind) {
                    if (! empty($media[$kind.'_path'])) {
                        if ($current->{$kind.'_path'}) {
                            $discard[] = $current->{$kind.'_path'};
                        }
                        $current->{$kind.'_path'} = $media[$kind.'_path'];
                        $current->{$kind.'_mime'} = $media[$kind.'_mime'];
                    } elseif (! empty($media['remove_'.$kind])) {
                        if ($current->{$kind.'_path'}) {
                            $discard[] = $current->{$kind.'_path'};
                        }
                        $current->{$kind.'_path'} = null;
                        $current->{$kind.'_mime'} = null;
                    }
                }
                $current->save();
            }
            if ($submit || now()->gte($current->expires_at->copy()->addSeconds(3))) {
                if (now()->lt($current->expires_at) && ! filled($current->body) && ! $current->image_path && ! $current->audio_path) {
                    throw ValidationException::withMessages(['body' => 'Write a response or attach an image or voice message before sending.']);
                }
                $this->finish($current, $part);
            }

            return $current;
        });
        foreach ($discard as $path) {
            Storage::disk('local')->delete($path);
        }

        return $result;
    }

    private function finish(BlogResponseAttempt $attempt, BlogParticipation $part): void
    {
        if ($attempt->submitted_at) {
            return;
        }
        $number = (int) $part->submission_count + 1;
        $fields = ['body' => $attempt->body, 'image_path' => $attempt->image_path, 'image_mime' => $attempt->image_mime,
            'audio_path' => $attempt->audio_path, 'audio_mime' => $attempt->audio_mime, 'submission_number' => $number, 'auto_submitted' => now()->gte($attempt->expires_at)];
        if ($attempt->blog_comment_id) {
            $comment = BlogComment::whereKey($attempt->blog_comment_id)->lockForUpdate()->firstOrFail();
            if ($comment->responses()->where('author_role', 'student')->count() >= config('blog.responses_per_person')) {
                throw ValidationException::withMessages(['body' => 'The response limit for this conversation has been reached.']);
            }
            $message = new BlogResponse;
            $message->forceFill([...$fields, 'blog_comment_id' => $comment->id, 'author_role' => 'student'])->save();
            $comment->forceFill(['last_response_role' => 'student', 'updated_at' => now()])->save();
            $attempt->submitted_response_id = $message->id;
        } else {
            $message = new BlogComment;
            $message->forceFill([...$fields, 'blog_post_id' => $part->blog_post_id, 'student_id' => $part->student_id])->save();
            $attempt->submitted_comment_id = $message->id;
        }
        $part->submission_count = $number;
        $part->save();
        $attempt->submitted_at = now();
        $attempt->save();
    }

    public function finalizeExpired(?int $postId = null, ?int $studentId = null, ?int $teacherId = null): int
    {
        $ids = BlogResponseAttempt::whereNull('submitted_at')->where('expires_at', '<=', now()->subSeconds(3))
            ->whereHas('participation', function ($q) use ($postId, $studentId, $teacherId) {
                $q->when($postId, fn ($q) => $q->where('blog_post_id', $postId))->when($studentId, fn ($q) => $q->where('student_id', $studentId))
                    ->when($teacherId, fn ($q) => $q->whereHas('post', fn ($q) => $q->where('teacher_account_id', $teacherId)));
            })->pluck('id');
        foreach ($ids as $id) {
            DB::transaction(function () use ($id) {
                $row = BlogResponseAttempt::with('participation')->find($id);
                if (! $row) {
                    return;
                }
                Student::withTrashed()->whereKey($row->participation->student_id)->lockForUpdate()->firstOrFail();
                $part = BlogParticipation::whereKey($row->blog_participation_id)->lockForUpdate()->firstOrFail();
                $current = BlogResponseAttempt::whereKey($id)->lockForUpdate()->firstOrFail();
                if (! $current->submitted_at && now()->gte($current->expires_at)) {
                    $this->finish($current, $part);
                }
            });
        }

        return $ids->count();
    }

    public function grade(BlogPost $post, BlogComment $comment, ?BlogResponse $response, string $assessment, ?string $note): void
    {
        DB::transaction(function () use ($post, $comment, $response, $assessment, $note) {
            $student = Student::withTrashed()->whereKey($comment->student_id)->lockForUpdate()->firstOrFail();
            $part = $this->participation($post, $student);
            $message = $response ? BlogResponse::whereKey($response->id)->lockForUpdate()->firstOrFail() : BlogComment::whereKey($comment->id)->lockForUpdate()->firstOrFail();
            if (! $post->fresh()->is_graded) {
                throw ValidationException::withMessages(['assessment' => 'This article is ungraded.']);
            }
            if ($message->assessment) {
                if ($message->assessment !== $assessment) {
                    throw ValidationException::withMessages(['assessment' => 'This assessment is already final. Add a private response to clarify your feedback.']);
                }

                return;
            }
            $number = $message->submission_number;
            if (! in_array($number, [1, 2], true)) {
                throw ValidationException::withMessages(['assessment' => 'Only the first and second comments of this student can earn XP for this article. Each student has a separate allowance.']);
            }
            if ($number === 2 && ! $part->first_assessment) {
                throw ValidationException::withMessages(['assessment' => 'Assess this student’s first comment before their second comment.']);
            }
            if ($number === 2 && ($part->first_assessment === 'totally_correct' || $part->xp_awarded >= 10)) {
                throw ValidationException::withMessages(['assessment' => 'This student’s first comment already earned the maximum 10 XP for this article. Their second comment cannot earn additional XP.']);
            }
            $points = min(config('blog.assessments.'.$assessment.'.xp'), max(0, 10 - $part->xp_awarded));
            $message->forceFill(['assessment' => $assessment, 'grade_note' => $note, 'xp_credited' => $points, 'graded_at' => now()])->save();
            if ($number === 1) {
                $part->first_assessment = $assessment;
            }
            $part->xp_awarded += $points;
            $part->save();
            if ($points > 0) {
                app(XpLedger::class)->change($student->id, 'blog:'.$post->id.':comment:'.$number, $points,
                    ['type' => 'blog', 'id' => $post->id, 'title' => $post->title, 'reason' => 'Teacher-graded article response',
                        'details' => ['Your response #'.$number.' was graded '.str_replace('_', ' ', $assessment).'. Awarded '.$points.' XP; maximum 10 XP per article.']]);
            }
        });
    }
}
