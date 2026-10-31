<?php

namespace App\Http\Controllers;

use App\Models\BlogComment;
use App\Models\BlogPost;
use App\Models\BlogResponse;
use App\Models\BlogParticipation;
use App\Services\BlogLearningService;
use App\Services\BlogMedia;
use App\Models\Student;
use App\Models\TeacherAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class BlogController extends Controller
{
    private function isTeacher(Request $request): bool
    {
        return $request->routeIs('teacher.blog*');
    }

    private function actor(Request $request): TeacherAccount|Student|null
    {
        return $this->isTeacher($request)
            ? TeacherAccount::find(session('teacher_id'))
            : Student::with('classroom')->find(session('student_id'));
    }

    private function authorizePost(BlogPost $post, TeacherAccount|Student $actor): void
    {
        if ($actor instanceof TeacherAccount) {
            abort_unless($post->teacher_account_id === $actor->id, 403);
        } else {
            abort_unless($post->status === 'published' && $actor->classroom?->teacher_account_id === $post->teacher_account_id, 403);
        }
    }

    private function pageData(TeacherAccount|Student $actor): array
    {
        $isTeacher = $actor instanceof TeacherAccount;

        return [
            'isTeacher' => $isTeacher, 'teacher' => $isTeacher ? $actor : null,
            'student' => $isTeacher ? null : $actor, 'classroom' => $isTeacher ? null : $actor->classroom,
            'pageTitle' => 'C++ Classroom Blog', 'active' => 'blog',
            'lastSyncedAt' => now(config('learning.timezone'))->format('M j, Y · g:i A'),
            'categories' => config('blog.categories'),
            'blogRoute' => $isTeacher ? 'teacher.blog' : 'student.blog',
        ];
    }

    private function privateView(string $view, array $data)
    {
        return response()->view($view, $data)->header('Cache-Control', 'private, no-store');
    }

    public function index(Request $request)
    {
        $actor = $this->actor($request);
        if (! $actor) {
            return redirect()->route($this->isTeacher($request) ? 'teacher.login' : 'student.login');
        }
        $isTeacher = $actor instanceof TeacherAccount;
        app(BlogLearningService::class)->finalizeExpired(studentId: $isTeacher ? null : $actor->id, teacherId: $isTeacher ? $actor->id : null);
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', Rule::in(config('blog.categories'))],
            'status' => ['nullable', Rule::in(['draft', 'published'])],
        ]);
        $teacherId = $isTeacher ? $actor->id : $actor->classroom?->teacher_account_id;
        $base = BlogPost::where('teacher_account_id', $teacherId);
        if (! $isTeacher) {
            $base->where('status', 'published');
        }
        $publishedCount = (clone $base)->where('status', 'published')->count();
        $draftCount = $isTeacher ? (clone $base)->where('status', 'draft')->count() : 0;
        $posts = (clone $base)->with('teacher')->withCount(['comments' => function ($query) use ($isTeacher, $actor) {
            if (! $isTeacher) {
                $query->where('student_id', $actor->id);
            }
        }])->when(filled($filters['search'] ?? null), function ($query) use ($filters) {
            $query->where(fn ($query) => $query->where('title', 'like', '%'.$filters['search'].'%')->orWhere('body', 'like', '%'.$filters['search'].'%'));
        })->when($filters['category'] ?? null, fn ($query, $category) => $query->where('category', $category))
            ->when($isTeacher && filled($filters['status'] ?? null), fn ($query) => $query->where('status', $filters['status']))
            ->orderByDesc('published_at')->orderByDesc('id')->paginate(9)->withQueryString();
        $waitingCount = $isTeacher ? BlogComment::whereHas('post', fn ($query) => $query->where('teacher_account_id', $actor->id))
            ->where('last_response_role', 'student')
            ->whereHas('responses', fn ($query) => $query->where('author_role', 'teacher'), '<', config('blog.responses_per_person'))->count() : 0;

        return $this->privateView('blog.index', $this->pageData($actor) + compact('posts', 'publishedCount', 'draftCount', 'waitingCount', 'filters'));
    }

    public function create(Request $request)
    {
        $teacher = $this->actor($request);
        if (! $teacher) {
            return redirect()->route('teacher.login');
        }

        return $this->privateView('blog.form', $this->pageData($teacher) + ['post' => new BlogPost(['category' => config('blog.categories')[0], 'status' => 'draft','response_time_limit_minutes'=>5])]);
    }

    public function edit(Request $request, BlogPost $post)
    {
        $teacher = $this->actor($request);
        abort_unless($teacher instanceof TeacherAccount, 401);
        $this->authorizePost($post, $teacher);

        return $this->privateView('blog.form', $this->pageData($teacher) + compact('post'));
    }

    private function imageRules(): array
    {
        return ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:max_width=6000,max_height=6000'];
    }

    private function upload(Request $request, string $field, string $folder): array
    {
        if (! $request->hasFile($field)) {
            return [null, null];
        }
        $file = $request->file($field);
        $path = $file->store('blog/'.$folder, 'local');
        if (! $path) {
            throw ValidationException::withMessages([$field => 'The image could not be saved. Please try again.']);
        }

        return [$path, $file->getMimeType()];
    }

    private function postData(Request $request, ?BlogPost $post = null): array
    {
        $hasImage = $request->hasFile('image') || ($post?->image_path && ! $request->boolean('remove_image'));

        return $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'category' => ['required', Rule::in(config('blog.categories'))],
            'body' => [$hasImage ? 'nullable' : 'required', 'string', 'max:30000'],
            'status' => ['required', Rule::in(['draft', 'published'])],
            'is_graded'=>['sometimes','boolean'],
            'response_time_limit_minutes'=>['nullable','integer','min:1','max:60'],
            'image' => $this->imageRules(), 'remove_image' => ['nullable', 'boolean'],
        ], ['body.required' => 'Write your article or upload a topic image.']);
    }

    public function store(Request $request)
    {
        $teacher = $this->actor($request);
        abort_unless($teacher instanceof TeacherAccount, 401);
        $data = $this->postData($request);
        [$path, $mime] = $this->upload($request, 'image', 'posts');
        try {
            $post = new BlogPost(collect($data)->only(['title', 'category', 'body', 'status','is_graded','response_time_limit_minutes'])->all());
            $post->forceFill(['teacher_account_id' => $teacher->id, 'image_path' => $path, 'image_mime' => $mime, 'published_at' => $data['status'] === 'published' ? now() : null])->save();
        } catch (Throwable $exception) {
            if ($path) {
                Storage::disk('local')->delete($path);
            }
            throw $exception;
        }

        return redirect()->route('teacher.blog.show', $post)->with('blog_notice', $post->status === 'published' ? 'Article published to students across all your sections.' : 'Draft saved. Students cannot see it yet.');
    }

    public function update(Request $request, BlogPost $post)
    {
        $teacher = $this->actor($request);
        abort_unless($teacher instanceof TeacherAccount, 401);
        $this->authorizePost($post, $teacher);
        $data = $this->postData($request, $post);
        [$path, $mime] = $this->upload($request, 'image', 'posts');
        $oldPath = null;
        try {
            DB::transaction(function () use ($post, $data, $path, $mime, $request, &$oldPath) {
                $current = BlogPost::whereKey($post->id)->lockForUpdate()->firstOrFail();
                $current->fill(collect($data)->only(['title', 'category', 'body', 'status','is_graded','response_time_limit_minutes'])->all());
                if ($path || $request->boolean('remove_image')) {
                    $oldPath = $current->image_path;
                    $current->forceFill(['image_path' => $path, 'image_mime' => $mime]);
                }
                $current->published_at = $data['status'] === 'published' ? ($current->published_at ?? now()) : null;
                $current->save();
            });
        } catch (Throwable $exception) {
            if ($path) {
                Storage::disk('local')->delete($path);
            }
            throw $exception;
        }
        if ($oldPath) {
            Storage::disk('local')->delete($oldPath);
        }

        return redirect()->route('teacher.blog.show', $post)->with('blog_notice', 'Article updated. Private student discussions have been kept.');
    }

    public function destroy(Request $request, BlogPost $post)
    {
        $teacher = $this->actor($request);
        abort_unless($teacher instanceof TeacherAccount, 401);
        $this->authorizePost($post, $teacher);
        $post->delete();

        return redirect()->route('teacher.blog')->with('blog_notice', 'Article archived and removed from the blog. Its saved discussions are retained.');
    }

    public function show(Request $request, BlogPost $post)
    {
        $actor = $this->actor($request);
        if (! $actor) {
            return redirect()->route($this->isTeacher($request) ? 'teacher.login' : 'student.login');
        }
        $this->authorizePost($post, $actor);
        $isTeacher = $actor instanceof TeacherAccount;
        $focusData=$request->validate(['conversation'=>['nullable','integer','min:1']]);
        $focusedConversation=$focusData['conversation'] ?? null;
        if ($focusedConversation) {
            abort_unless($post->comments()->whereKey($focusedConversation)->when(!$isTeacher,fn ($q) => $q->where('student_id',$actor->id))->exists(),404);
        }
        $learning = app(BlogLearningService::class);
        $learning->finalizeExpired($post->id,$isTeacher ? null : $actor->id);
        $activeAttempt = null;
        $active = null;
        if (!$isTeacher) {
            $learning->participation($post,$actor);
            $active = $learning->active($post,$actor);
            $activeAttempt = $active ? app(BlogActivityController::class)->state($active) : null;
        }
        $comments = $post->comments()->when(! $isTeacher, fn ($query) => $query->where('student_id', $actor->id))
            ->when($focusedConversation,fn ($query) => $query->whereKey($focusedConversation))
            ->when(!$isTeacher && $active?->blog_comment_id, fn ($query) => $query->orderByRaw('CASE WHEN id = ? THEN 0 ELSE 1 END',[$active->blog_comment_id]))
            ->with('responses')->when($isTeacher, fn ($query) => $query->with('student.classroom'))
            ->orderByDesc('updated_at')->orderByDesc('id')->paginate(15)->withQueryString();
        $post->load('teacher');
        $participations = BlogParticipation::where('blog_post_id',$post->id)->whereIn('student_id',$isTeacher ? $comments->pluck('student_id') : [$actor->id])->get()->keyBy('student_id');

        return $this->privateView($request->boolean('thread') ? 'blog.thread' : 'blog.show', $this->pageData($actor) + compact('post', 'comments','activeAttempt','participations','focusedConversation'));
    }

    public function comment(Request $request, BlogPost $post)
    {
        $student = $this->actor($request);
        abort_unless($student instanceof Student, 401);
        $this->authorizePost($post, $student);
        $learning = app(BlogLearningService::class);
        if ($post->response_time_limit_minutes || $learning->active($post,$student)) {
            throw ValidationException::withMessages(['body'=>'Press Start response and use the timed response box for this article.']);
        }
        $data = $request->validate([
            'body' => [($request->hasFile('image') || $request->hasFile('audio')) ? 'nullable' : 'required', 'string', 'max:5000'],
            'image' => $this->imageRules(),
            'audio' => BlogMedia::audioRules(),
        ], ['body.required' => 'Write a comment or attach an image or voice message.']);
        [$path, $mime] = $this->upload($request, 'image', 'comments');
        $audioPath = $audioMime = null;
        try {
            [$audioPath,$audioMime] = BlogMedia::upload($request,'audio','voice');
            DB::transaction(function () use ($post, $student, $data, $path, $mime, $audioPath, $audioMime, $learning) {
                $number = $learning->nextNumber($post,$student);
                $current = BlogPost::whereKey($post->id)->lockForUpdate()->firstOrFail();
                $this->authorizePost($current, $student);
                $comment = new BlogComment(['body' => $data['body'] ?? null]);
                $comment->forceFill(['blog_post_id' => $post->id, 'student_id' => $student->id, 'image_path' => $path, 'image_mime' => $mime,'audio_path'=>$audioPath,'audio_mime'=>$audioMime,'submission_number'=>$number])->save();
            });
        } catch (Throwable $exception) {
            BlogMedia::discard([$audioPath]);
            if ($path) {
                Storage::disk('local')->delete($path);
            }
            throw $exception;
        }

        return redirect()->route('student.blog.show', $post)->with('blog_notice', 'Your private comment was sent to your teacher. Classmates cannot see it.');
    }

    private function authorizeComment(BlogPost $post, BlogComment $comment, TeacherAccount|Student $actor): void
    {
        $this->authorizePost($post, $actor);
        abort_unless($comment->blog_post_id === $post->id, 404);
        abort_unless($actor instanceof TeacherAccount || $comment->student_id === $actor->id, 403);
    }

    public function respond(Request $request, BlogPost $post, BlogComment $comment)
    {
        $actor = $this->actor($request);
        abort_unless($actor, 401);
        $this->authorizeComment($post, $comment, $actor);
        $role = $actor instanceof TeacherAccount ? 'teacher' : 'student';
        $learning = app(BlogLearningService::class);
        if ($role === 'student' && ($post->response_time_limit_minutes || $learning->active($post,$actor))) {
            throw ValidationException::withMessages(['body'=>'Press Start response and use the timed response box for this article.']);
        }
        $data = $request->validate([
            'body' => [($request->hasFile('image') || $request->hasFile('audio')) ? 'nullable' : 'required', 'string', 'max:5000'],
            'image' => $this->imageRules(),
            'audio' => BlogMedia::audioRules(),
        ], ['body.required' => 'Write a response or attach an image or voice message.']);
        [$path, $mime] = $this->upload($request, 'image', 'responses');
        $audioPath = $audioMime = null;
        try {
            [$audioPath,$audioMime] = BlogMedia::upload($request,'audio','voice');
            DB::transaction(function () use ($comment, $post, $data, $role, $actor, $path, $mime, $audioPath, $audioMime, $learning) {
                $number = $role === 'student' ? $learning->nextNumber($post,$actor) : null;
                $current = BlogComment::whereKey($comment->id)->lockForUpdate()->firstOrFail();
                $current->load('post');
                abort_unless($current->post, 404);
                $this->authorizeComment($current->post, $current, $actor);
                $limit = config('blog.responses_per_person');
                if ($current->responses()->where('author_role', $role)->count() >= $limit) {
                    throw ValidationException::withMessages(['body' => "You have reached your limit of {$limit} responses in this conversation. You can still read the discussion."]);
                }
                $response = new BlogResponse(['body' => $data['body'] ?? null]);
                $response->forceFill(['blog_comment_id' => $current->id, 'author_role' => $role, 'image_path' => $path, 'image_mime' => $mime,'audio_path'=>$audioPath,'audio_mime'=>$audioMime,'submission_number'=>$number])->save();
                $current->last_response_role = $role;
                if ($role === 'teacher') {
                    $current->replied_at = now();
                }
                $current->updated_at = now();
                $current->save();
            });
        } catch (Throwable $exception) {
            BlogMedia::discard([$audioPath]);
            if ($path) {
                Storage::disk('local')->delete($path);
            }
            throw $exception;
        }
        return redirect()->route($role.'.blog.show', $post)->withFragment('comment-'.$comment->id)
            ->with('blog_notice', 'Your response was added to the private conversation. Earlier messages have been kept.');
    }

    private function imageResponse(?string $path, ?string $mime, string|array $folder)
    {
        $allowedPath = $path && collect((array) $folder)->contains(fn ($name) => str_starts_with($path, 'blog/'.$name.'/'));
        abort_unless($allowedPath && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, null, [
            'Content-Type' => $mime, 'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff', 'Cross-Origin-Resource-Policy' => 'same-origin',
        ]);
    }

    public function postImage(Request $request, BlogPost $post)
    {
        $actor = $this->actor($request);
        abort_unless($actor, 401);
        $this->authorizePost($post, $actor);

        return $this->imageResponse($post->image_path, $post->image_mime, 'posts');
    }

    public function commentImage(Request $request, BlogComment $comment, string $kind)
    {
        abort_unless(in_array($kind, ['comment', 'reply'], true), 404);
        $actor = $this->actor($request);
        abort_unless($actor, 401);
        abort_unless($comment->post, 404);
        $this->authorizePost($comment->post, $actor);
        abort_unless($actor instanceof TeacherAccount || $comment->student_id === $actor->id, 403);

        return $kind === 'reply'
            ? $this->imageResponse($comment->reply_image_path, $comment->reply_image_mime, 'replies')
            : $this->imageResponse($comment->image_path, $comment->image_mime, ['comments','drafts']);
    }

    public function responseImage(Request $request, BlogResponse $response)
    {
        $actor = $this->actor($request);
        abort_unless($actor, 401);
        $comment = $response->comment;
        abort_unless($comment?->post, 404);
        $this->authorizeComment($comment->post, $comment, $actor);

        return $this->imageResponse($response->image_path, $response->image_mime, ['responses', 'replies','drafts']);
    }
}
