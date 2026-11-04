<?php

namespace App\Http\Controllers;

use App\Models\LessonContent;
use App\Models\Student;
use App\Models\TeacherAccount;
use App\Services\LessonService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class LessonContentController extends Controller
{
    private function teacher(): TeacherAccount
    {
        $teacher = TeacherAccount::find(session('teacher_id'));
        abort_unless($teacher, 403);

        return $teacher;
    }

    public function index(LessonService $service)
    {
        $teacher = $this->teacher();

        return response()->view('content.index', ['teacher' => $teacher, 'catalog' => $service->catalogForTeacher($teacher->id),
            'contents' => LessonContent::where('teacher_account_id', $teacher->id)->latest()->get()])->header('Cache-Control', 'private, no-store');
    }

    public function edit(string $key = 'new')
    {
        $teacher = $this->teacher();
        $content = LessonContent::where('teacher_account_id', $teacher->id)->where('lesson_key', $key)->first();
        $definition = $content?->definition ?? app(LessonService::class)->catalogForTeacher($teacher->id)[$key] ?? null;
        abort_unless($key === 'new' || $definition, 404);
        $submittedChecks = old('checks', $definition['checks'] ?? []);
        $checkpointChecks = is_array($submittedChecks) ? array_values($submittedChecks) : [];
        $checkpointChecks = array_pad($checkpointChecks, LessonContent::MIN_CHECKPOINT_QUESTIONS, ['prompt' => '', 'options' => ['', '', ''], 'correct' => 0, 'why' => '']);

        return response()->view('content.form', compact('teacher', 'content', 'definition', 'key', 'checkpointChecks'))->header('Cache-Control', 'private, no-store');
    }

    public function save(Request $request, string $key)
    {
        $teacher = $this->teacher();
        $data = $request->validate([
            'revision' => ['required', 'integer', 'min:0'], 'title' => ['required', 'string', 'max:160'], 'topic' => ['required', 'string', 'max:80'],
            'objective' => ['required', 'string', 'max:1000'], 'practice' => ['required', Rule::in(array_keys(config('code_lab.activities')))],
            'minutes' => ['required', 'integer', 'between:1,60'], 'status' => ['required', 'in:draft,published'],
            'sections' => ['required', 'array', 'min:1', 'max:6'], 'sections.*.title' => ['required', 'string', 'max:100'], 'sections.*.text' => ['required', 'string', 'max:5000'],
            'code' => ['required', 'string', 'max:20000'], 'output' => ['nullable', 'string', 'max:5000'], 'mistake' => ['required', 'string', 'max:1000'],
            'checks' => ['required', 'array', 'list', 'min:'.LessonContent::MIN_CHECKPOINT_QUESTIONS, 'max:'.LessonContent::MAX_CHECKPOINT_QUESTIONS], 'checks.*.prompt' => ['required', 'string', 'max:1000'],
            'checks.*.options' => ['required', 'array', 'list', 'size:3'], 'checks.*.options.*' => ['required', 'string', 'max:500'],
            'checks.*.correct' => ['required', 'integer', 'between:0,2'], 'checks.*.why' => ['required', 'string', 'max:1000'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:max_width=4000,max_height=4000'],
        ], ['checks.min' => 'Each lesson needs at least five checkpoint questions.', 'checks.max' => 'A lesson can contain up to twenty checkpoint questions.']);
        $newPath = $request->file('image')?->store('lesson-resources', 'local');
        try {
            DB::transaction(function () use ($teacher, $key, $data, $newPath, $request) {
                TeacherAccount::whereKey($teacher->id)->lockForUpdate()->firstOrFail();
                $key = $key === 'new' ? 'custom-'.Str::lower(Str::random(12)) : $key;
                $content = LessonContent::where('teacher_account_id', $teacher->id)->where('lesson_key', $key)->lockForUpdate()->first();
                $availableLessons = app(LessonService::class)->catalogForTeacher($teacher->id);
                abort_unless($content || isset($availableLessons[$key]) || str_starts_with($key, 'custom-') && (int) $data['revision'] === 0, 404);
                abort_if(($content?->revision ?? 0) !== (int) $data['revision'], 409, 'This lesson changed in another tab. Refresh before saving.');
                $version = ($content?->revision ?? 0) + 1;
                $definition = collect($data)->except(['revision', 'status', 'image'])->all();
                foreach ($definition['checks'] as &$check) {
                    $check['correct'] = (int) $check['correct'];
                }
                unset($check);
                $definition += ['icon' => 'menu_book', 'version' => $version];
                $attributes = ['definition' => $definition, 'status' => $data['status'], 'revision' => $version];
                if ($newPath) {
                    $attributes += ['image_path' => $newPath, 'image_mime' => $request->file('image')->getMimeType()];
                }
                $oldPath = $content?->image_path;
                LessonContent::updateOrCreate(['teacher_account_id' => $teacher->id, 'lesson_key' => $key], $attributes);
                if ($newPath && $oldPath) {
                    DB::afterCommit(fn () => Storage::disk('local')->delete($oldPath));
                }
            });
        } catch (\Throwable $error) {
            if ($newPath) {
                Storage::disk('local')->delete($newPath);
            }
            throw $error;
        }

        return redirect()->route('teacher.content')->with('lesson_notice', 'Lesson content saved. Publishing makes it available only to your teaching sections.');
    }

    public function image(LessonContent $content)
    {
        $student = Student::with('classroom')->find(session('student_id'));
        $teacher = TeacherAccount::find(session('teacher_id'));
        $allowed = request()->routeIs('teacher.content.image') ? $teacher && $teacher->id === (int) $content->teacher_account_id
            : $student && $content->status === 'published' && (int) $student->classroom?->teacher_account_id === (int) $content->teacher_account_id;
        abort_unless($allowed && $content->image_path && Storage::disk('local')->exists($content->image_path), 404);

        return response()->file(Storage::disk('local')->path($content->image_path), ['Content-Type' => $content->image_mime, 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}
