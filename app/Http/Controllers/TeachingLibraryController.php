<?php

namespace App\Http\Controllers;

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

        $data = $request->validate(['q' => ['nullable', 'string', 'max:100']]);
        $query = mb_strtolower(trim($data['q'] ?? ''));
        $catalog = array_filter($library->catalog(), static fn (array $lesson): bool => $query === '' || str_contains(mb_strtolower($lesson['title'].' '.$lesson['topic']), $query));

        return view('teaching-library.index', ['pageTitle' => 'Ready-made Library', 'teacher' => $teacher, 'catalog' => $catalog, 'topicCount' => count($library->catalog())]);
    }

    public function show(string $key, string $kind, ReadyMadeActivities $library): View|RedirectResponse
    {
        $teacher = TeacherAccount::find(session('teacher_id'));
        if (! $teacher) {
            return redirect()->route('teacher.login');
        }
        abort_if($teacher->isPrincipal(), 403);
        abort_unless(in_array($kind, ['quiz', 'assignment', 'challenge'], true), 404);
        $lesson = $library->lesson($key);

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
        abort_unless(in_array($kind, ['quiz', 'assignment', 'challenge'], true), 404);
        $lesson = $library->lesson($key);
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
}
