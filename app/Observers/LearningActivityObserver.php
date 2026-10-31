<?php

namespace App\Observers;

use App\Models\BlogComment;
use App\Models\BlogPost;
use App\Models\BlogResponse;
use App\Models\LabSubmission;
use App\Models\LessonProgress;
use App\Models\Student;
use App\Services\LearningNotifications;
use Illuminate\Database\Eloquent\Model;

class LearningActivityObserver
{
    public function created(Model $model): void
    {
        $notices = app(LearningNotifications::class);
        match (true) {
            $model instanceof BlogPost => $notices->publishedArticle($model),
            $model instanceof BlogComment => $notices->blogComment($model),
            $model instanceof BlogResponse => $notices->blogResponse($model),
            $model instanceof LabSubmission => $notices->labSubmitted($model),
            $model instanceof LessonProgress => $notices->lessonCompleted($model),
            default => null,
        };
    }

    public function updated(Model $model): void
    {
        $notices = app(LearningNotifications::class);
        if ($model instanceof Student && $model->wasChanged('classroom_id')) {
            $model->forceFill(['notifications_initialized_at' => null])->saveQuietly();
        }
        if ($model instanceof BlogPost && $model->wasChanged('status') && $model->status === 'published') {
            $notices->publishedArticle($model);
            BlogResponse::where('author_role', 'teacher')->whereHas('comment', fn ($q) => $q->where('blog_post_id', $model->id))
                ->chunkById(100, fn ($rows) => $rows->each(fn ($row) => $notices->blogResponse($row)));
        }
        if (($model instanceof BlogComment || $model instanceof BlogResponse) && $model->wasChanged('graded_at')) {
            $notices->blogGrade($model);
        }
        if ($model instanceof LabSubmission && $model->wasChanged('reviewed_at')) {
            $notices->labFeedback($model);
        }
        if ($model instanceof LessonProgress && $model->wasChanged('completed_at')) {
            $notices->lessonCompleted($model);
        }
    }
}
