<?php

namespace App\Http\Controllers;

use App\Models\LessonProgress;
use App\Models\Student;
use App\Models\TeacherAccount;
use App\Models\TeacherLessonPlan;
use App\Services\LessonService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class TeacherLessonController extends Controller
{
    private function teacher(): TeacherAccount
    {
        $teacher = TeacherAccount::find(session('teacher_id'));
        abort_unless($teacher, 403);

        return $teacher;
    }

    public function index(LessonService $service)
    {
        if (! TeacherAccount::find(session('teacher_id'))) {
            return redirect()->route('teacher.login');
        }
        $teacher = $this->teacher();

        return response()->view('teacher-lessons', [
            'teacher' => $teacher, 'rooms' => $teacher->classrooms()->withCount('students')->orderBy('grade')->orderBy('section')->get(),
            'catalog' => $service->catalog(),
            'plans' => TeacherLessonPlan::with('classroom')->where('teacher_account_id', $teacher->id)
                ->whereHas('classroom', fn ($query) => $query->where('teacher_account_id', $teacher->id))->latest()->get(),
        ])->header('Cache-Control', 'private, no-store');
    }

    private function details(Request $request): array
    {
        $data = $request->validate([
            'instructions' => ['nullable', 'string', 'max:3000'],
            'due_at' => ['nullable', 'date_format:Y-m-d\TH:i'],
            'status' => ['required', Rule::in(['draft', 'published'])],
        ]);
        $data['due_at'] = ! empty($data['due_at']) ? Carbon::createFromFormat('Y-m-d\TH:i', $data['due_at'], config('learning.timezone'))->utc() : null;

        return $data;
    }

    public function store(Request $request, LessonService $service)
    {
        $teacher = $this->teacher();
        $data = $request->validate([
            'lesson_key' => ['required', Rule::in(array_keys($service->catalog()))],
            'classroom_ids' => ['required', 'array', 'min:1'],
            'classroom_ids.*' => ['required', 'integer', 'distinct', Rule::exists('classrooms', 'id')->where('teacher_account_id', $teacher->id)],
        ]);
        $details = $this->details($request);
        DB::transaction(function () use ($teacher, $data, $details) {
            TeacherAccount::whereKey($teacher->id)->lockForUpdate()->firstOrFail();
            foreach ($data['classroom_ids'] as $id) {
                TeacherLessonPlan::updateOrCreate(['teacher_account_id' => $teacher->id, 'classroom_id' => $id, 'lesson_key' => $data['lesson_key']], $details);
            }
        });

        return redirect()->route('teacher.lessons')->with('lesson_notice', 'Lesson plan saved for the selected sections.');
    }

    private function owns(TeacherLessonPlan $plan, TeacherAccount $teacher): void
    {
        abort_unless($plan->teacher_account_id === $teacher->id && $plan->classroom?->teacher_account_id === $teacher->id, 403);
    }

    public function update(Request $request, TeacherLessonPlan $plan)
    {
        $this->owns($plan, $this->teacher());
        $plan->update($this->details($request));

        return redirect()->route('teacher.lessons')->with('lesson_notice', 'Lesson plan updated.');
    }

    public function destroy(TeacherLessonPlan $plan)
    {
        $this->owns($plan, $this->teacher());
        $plan->delete();

        return redirect()->route('teacher.lessons')->with('lesson_notice', 'Lesson assignment removed. Saved learning progress is kept.');
    }

    public function progress(Request $request, LessonService $service)
    {
        if (! TeacherAccount::find(session('teacher_id'))) {
            return redirect()->route('teacher.login');
        }
        $teacher = $this->teacher();
        $rooms = $teacher->classrooms()->orderBy('grade')->orderBy('section')->get();
        $filter = $request->validate(['classroom' => ['nullable', 'integer'], 'lesson' => ['nullable', Rule::in(array_keys($service->catalog()))], 'search' => ['nullable', 'string', 'max:100']]);
        $selected = ! empty($filter['classroom']) ? (int) $filter['classroom'] : null;
        abort_if($selected && ! $rooms->contains('id', $selected), 403);
        $lessonKey = $filter['lesson'] ?? null;
        $search = trim($filter['search'] ?? '');
        $students = Student::with('classroom')->whereIn('classroom_id', $selected ? [$selected] : $rooms->pluck('id'));
        if ($search !== '') {
            $students->where(fn ($query) => $query->where('nickname', 'like', '%'.$search.'%')->orWhere('student_id', 'like', '%'.$search.'%')->orWhere('first_name', 'like', '%'.$search.'%')->orWhere('surname', 'like', '%'.$search.'%'));
        }
        $students = $students->orderBy('nickname')->orderBy('id')->paginate(25)->withQueryString();
        $records = LessonProgress::whereIn('student_id', $students->getCollection()->pluck('id'))
            ->whereIn('lesson_key', array_keys($service->catalog()))->when($lessonKey, fn ($query) => $query->where('lesson_key', $lessonKey))->orderByDesc('last_opened_at')->get()->groupBy('student_id');

        return response()->view('teacher-lesson-progress', compact('teacher', 'rooms', 'selected', 'lessonKey', 'search', 'students', 'records') + ['catalog' => $service->catalog()])->header('Cache-Control', 'private, no-store');
    }

    public function feedback(Request $request, LessonProgress $progress)
    {
        $teacher = $this->teacher();
        abort_unless($progress->student?->classroom?->teacher_account_id === $teacher->id, 403);
        $data = $request->validate(['teacher_feedback' => ['required', 'string', 'min:5', 'max:3000']]);
        $progress->update([...$data, 'feedback_by_teacher_id' => $teacher->id, 'feedback_at' => now()]);

        return redirect()->route('teacher.lessons.progress',['classroom' => $progress->student->classroom_id, 'lesson' => $progress->lesson_key])->with('lesson_notice','Feedback saved. The student can read it in this lesson.');
    }
}
