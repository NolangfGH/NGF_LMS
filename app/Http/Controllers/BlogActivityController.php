<?php

namespace App\Http\Controllers;

use App\Models\BlogComment;
use App\Models\BlogPost;
use App\Models\BlogResponse;
use App\Models\BlogResponseAttempt;
use App\Models\Student;
use App\Models\TeacherAccount;
use App\Services\BlogLearningService;
use App\Services\BlogMedia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Throwable;

class BlogActivityController extends Controller
{
    private function student(BlogPost $post): Student
    {
        $student = Student::with('classroom')->find(session('student_id'));
        abort_unless($student, 401);
        abort_unless($post->status === 'published' && $student->classroom?->teacher_account_id === $post->teacher_account_id, 403);

        return $student;
    }

    private function attempt(BlogPost $post, BlogResponseAttempt $attempt): Student
    {
        $student = $this->student($post);
        abort_unless($attempt->participation?->student_id === $student->id && $attempt->participation->blog_post_id === $post->id, 403);

        return $student;
    }

    public function state(BlogResponseAttempt $attempt): array
    {
        $postId = $attempt->participation->blog_post_id;
        $args = ['post' => $postId, 'attempt' => $attempt->id];
        $target = $attempt->submitted_comment_id ?? $attempt->blog_comment_id;

        return ['attempt_id' => $attempt->id, 'comment_id' => $attempt->blog_comment_id, 'body' => $attempt->body ?? '',
            'expires_at' => $attempt->expires_at->toIso8601String(), 'server_now' => now()->toIso8601String(),
            'duration_seconds' => $attempt->expires_at->timestamp - $attempt->started_at->timestamp, 'finished' => (bool) $attempt->submitted_at,
            'image_url' => $attempt->image_path ? route('student.blog.timer.media', [...$args, 'kind' => 'image']) : null,
            'audio_url' => $attempt->audio_path ? route('student.blog.timer.media', [...$args, 'kind' => 'audio']) : null,
            'save_url' => route('student.blog.timer.draft', $args), 'submit_url' => route('student.blog.timer.submit', $args),
            'redirect' => route('student.blog.show', $postId).($target ? '#comment-'.$target : '')];
    }

    public function start(Request $request, BlogPost $post, BlogLearningService $service)
    {
        $student = $this->student($post);
        $data = $request->validate(['comment_id' => ['nullable', 'integer']]);
        $comment = ! empty($data['comment_id']) ? BlogComment::findOrFail($data['comment_id']) : null;
        if ($comment) {
            abort_unless($comment->blog_post_id === $post->id && $comment->student_id === $student->id, 403);
        }

        return response()->json($this->state($service->start($post, $student, $comment)))->header('Cache-Control', 'private, no-store');
    }

    public function save(Request $request, BlogPost $post, BlogResponseAttempt $attempt, BlogLearningService $service)
    {
        $student = $this->attempt($post, $attempt);
        $data = $request->validate(['body' => ['nullable', 'string', 'max:5000'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:max_width=6000,max_height=6000'],
            'audio' => BlogMedia::audioRules(), 'remove_audio' => ['nullable', 'boolean']]);
        $media = [];
        try {
            $media['remove_audio'] = $request->boolean('remove_audio');
            [$media['image_path'],$media['image_mime']] = BlogMedia::upload($request, 'image', 'drafts');
            [$media['audio_path'],$media['audio_mime']] = BlogMedia::upload($request, 'audio', 'voice');
            $result = $service->saveAttempt($attempt, $student, $data['body'] ?? null, $media, $request->routeIs('student.blog.timer.submit'));
        } catch (Throwable $e) {
            BlogMedia::discard([$media['image_path'] ?? null, $media['audio_path'] ?? null]);
            throw $e;
        }
        BlogMedia::discard(array_diff(array_filter([$media['image_path'], $media['audio_path']]), array_filter([$result->image_path, $result->audio_path])));

        return response()->json($this->state($result))->header('Cache-Control', 'private, no-store');
    }

    public function draftMedia(BlogPost $post, BlogResponseAttempt $attempt, string $kind)
    {
        $this->attempt($post, $attempt);
        abort_unless(in_array($kind, ['image', 'audio'], true), 404);

        return $this->media($attempt->{$kind.'_path'}, $attempt->{$kind.'_mime'}, $kind === 'audio' ? ['voice'] : ['drafts']);
    }

    public function grade(Request $request, BlogPost $post, BlogComment $comment, BlogLearningService $service, ?BlogResponse $response = null)
    {
        $teacher = TeacherAccount::find(session('teacher_id'));
        abort_unless($teacher, 401);
        abort_unless($post->teacher_account_id === $teacher->id, 403);
        abort_unless($comment->blog_post_id === $post->id, 404);
        if ($response) {
            abort_unless($response->blog_comment_id === $comment->id && $response->author_role === 'student', 403);
        }
        $data = $request->validate(['assessment' => ['required', Rule::in(array_keys(config('blog.assessments')))], 'grade_note' => ['nullable', 'string', 'max:1500']]);
        $service->grade($post, $comment, $response, $data['assessment'], $data['grade_note'] ?? null);

        return redirect()->route('teacher.blog.show', $post)->withFragment('comment-'.$comment->id)->with('blog_notice', 'Assessment saved. Eligible XP has been credited to the student’s leaderboard total.');
    }

    private function media(?string $path, ?string $mime, array $folders)
    {
        abort_unless($path && collect($folders)->contains(fn ($folder) => str_starts_with($path, 'blog/'.$folder.'/')) && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, null, ['Content-Type' => $mime, 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff', 'Cross-Origin-Resource-Policy' => 'same-origin']);
    }

    public function voice(Request $request, string $kind, int $message)
    {
        abort_unless(in_array($kind, ['comment', 'response'], true), 404);
        $entry = $kind === 'comment' ? BlogComment::findOrFail($message) : BlogResponse::findOrFail($message);
        $comment = $kind === 'comment' ? $entry : $entry->comment;
        abort_unless($comment?->post, 404);
        $post = $comment->post;
        if ($request->routeIs('teacher.blog.*')) {
            $teacher = TeacherAccount::find(session('teacher_id'));
            abort_unless($teacher, 401);
            abort_unless($teacher->id === $post->teacher_account_id, 403);
        } else {
            $student = $this->student($post);
            abort_unless($student->id === $comment->student_id,403);
        }

        return $this->media($entry->audio_path,$entry->audio_mime,['voice']);
    }
}
