<?php

namespace App\Http\Controllers;

use App\Models\BlogPost;
use App\Models\TeacherAccount;
use App\Models\TeacherAssignment;
use App\Models\TeacherQuiz;
use App\Services\ReadyMadeActivities;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class TeachingLibraryController extends Controller
{
    public function index(Request $request, ReadyMadeActivities $library): View|RedirectResponse
    {
        $teacher = TeacherAccount::find(session('teacher_id'));
        if (! $teacher) {
            return redirect()->route('teacher.login');
        }
        abort_if($teacher->isPrincipal(), 403);

        $data = $request->validate(['q' => ['nullable', 'string', 'max:100'], 'term' => ['nullable', Rule::in(['Term1', 'Term2', 'Term3'])]]);
        $query = mb_strtolower(trim($data['q'] ?? ''));
        $catalog = array_filter($library->catalog(), static fn (array $lesson): bool => (empty($data['term']) || $lesson['term'] === $data['term'])
            && ($query === '' || str_contains(mb_strtolower($lesson['title'].' '.$lesson['topic'].' '.implode(' ', $lesson['subtopics'])), $query)));

        return view('teaching-library.index', [
            'pageTitle' => 'Ready-made Library', 'teacher' => $teacher, 'catalog' => $catalog,
            'topicCount' => count($library->catalog()),
            'termExams' => array_filter($library->termExams(), fn (array $exam): bool => empty($data['term']) || $exam['term'] === $data['term']),
        ]);
    }

    public function show(string $key, string $kind, ReadyMadeActivities $library): View|RedirectResponse
    {
        $teacher = TeacherAccount::find(session('teacher_id'));
        if (! $teacher) {
            return redirect()->route('teacher.login');
        }
        abort_if($teacher->isPrincipal(), 403);
        abort_unless(in_array($kind, ['quiz', 'assignment', 'challenge', 'article'], true), 404);
        $lesson = $this->template($library, $key, $kind);

        return view('teaching-library.show', [
            'pageTitle' => 'Review ready-made '.$kind, 'teacher' => $teacher,
            'lesson' => $lesson, 'activity' => $lesson[$kind], 'kind' => $kind,
            'rooms' => $teacher->classrooms()->whereNull('archived_at')->withCount('students')->orderBy('grade')->orderBy('section')->get(),
        ]);
    }

    public function store(Request $request, string $key, string $kind, ReadyMadeActivities $library): RedirectResponse
    {
        $teacher = TeacherAccount::find(session('teacher_id'));
        abort_unless($teacher && ! $teacher->isPrincipal(), 403);
        abort_unless(in_array($kind, ['quiz', 'assignment', 'challenge', 'article'], true), 404);
        $lesson = $this->template($library, $key, $kind);
        if ($kind === 'article') {
            $post = new BlogPost(Arr::only($lesson['article'], ['title', 'category', 'body', 'is_graded', 'response_time_limit_minutes']));
            $post->forceFill(['teacher_account_id' => $teacher->id, 'status' => 'draft', 'published_at' => null])->save();

            return redirect()->route('teacher.blog.edit', $post)
                ->with('library_notice', 'Your private article draft is ready. Review or edit the text, grading and response timer before publishing to students in all your sections. Students have not been notified.');
        }
        $data = $request->validate([
            'classroom_ids' => ['required', 'array', 'min:1', 'max:100'],
            'classroom_ids.*' => ['required', 'integer', 'distinct', Rule::exists('classrooms', 'id')->where('teacher_account_id', $teacher->id)->whereNull('archived_at')],
            'school_term' => [Rule::requiredIf($kind !== 'quiz'), 'nullable', Rule::in(['Term1', 'Term2', 'Term3'])],
        ]);

        $activity = DB::transaction(function () use ($teacher, $data, $lesson, $kind): TeacherQuiz|TeacherAssignment {
            $attributes = Arr::except($lesson[$kind], ['sample_input']);
            if ($kind !== 'quiz') {
                $attributes['school_term'] = $data['school_term'];
            }
            $attributes += ['teacher_account_id' => $teacher->id, 'classroom_id' => $data['classroom_ids'][0], 'status' => 'draft'];
            $model = $kind === 'quiz' ? TeacherQuiz::class : TeacherAssignment::class;
            $activity = $model::create($attributes);
            $activity->classrooms()->sync($data['classroom_ids']);

            return $activity;
        });

        $route = match ($kind) {
            'quiz' => 'teacher.quizzes',
            'challenge' => 'teacher.section-challenges',
            default => 'teacher.assignments',
        };

        return redirect()->route($route, ['edit' => $activity->id])
            ->with('library_notice', 'Your private draft is ready. Review or edit it, set dates, and publish when ready. Students have not been notified.');
    }

    private function template(ReadyMadeActivities $library, string $key, string $kind): array
    {
        if ($kind === 'quiz' && preg_match('/^term[123]-/', $key)) {
            $exam = $library->termExams()[$key] ?? null;
            abort_unless($exam, 404);

            return $exam;
        }

        return $library->lesson($key);
    }
}
