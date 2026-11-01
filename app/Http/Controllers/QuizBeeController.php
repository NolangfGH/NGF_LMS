<?php

namespace App\Http\Controllers;

use App\Models\QuizBeeMatch;
use App\Models\Student;
use App\Models\TeacherAccount;
use App\Services\LearningNotifications;
use App\Services\QuizBeePresenter;
use App\Services\QuizBeeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class QuizBeeController extends Controller
{
    private function teacher(): TeacherAccount
    {
        return TeacherAccount::find(session('teacher_id')) ?? abort(403);
    }

    private function student(): Student
    {
        return Student::with('classroom')->find(session('student_id')) ?? abort(401);
    }

    private function authorizeMatch(QuizBeeMatch $bee, QuizBeeService $service): ?Student
    {
        if (request()->routeIs('teacher.*')) {
            abort_unless($bee->teacher_account_id === $this->teacher()->id, 404);

            return null;
        }
        $student = $this->student();
        abort_unless($bee->status !== 'draft' && $student->classroom?->teacher_account_id === $bee->teacher_account_id && $service->teamFor($bee, $student->id), 404);

        return $student;
    }

    public function index()
    {
        $teacherRole = request()->routeIs('teacher.*');
        if (! session($teacherRole ? 'teacher_id' : 'student_id')) {
            return redirect()->route($teacherRole ? 'teacher.login' : 'student.login');
        }
        $actor = $teacherRole ? $this->teacher() : $this->student();
        $matches = QuizBeeMatch::when($teacherRole, fn ($query) => $query->where('teacher_account_id', $actor->id),
            fn ($query) => $query->where('status', '!=', 'draft')->where('teacher_account_id', $actor->classroom?->teacher_account_id)
                ->whereIn('id', DB::table('quiz_bee_members')->where('student_id', $actor->id)->select('quiz_bee_match_id')))->latest()->paginate(20);

        return response()->view('quiz-bee.index', ['teacher' => $teacherRole ? $actor : null, 'student' => $teacherRole ? null : $actor,
            'classroom' => $teacherRole ? null : $actor->classroom, 'teacherRole' => $teacherRole, 'matches' => $matches,
            'lastSyncedAt' => now(config('learning.timezone'))->format('M j, Y · g:i A')])->header('Cache-Control', 'private, no-store');
    }

    public function edit(?QuizBeeMatch $bee = null)
    {
        $teacher = $this->teacher();
        abort_if($bee && ($bee->teacher_account_id !== $teacher->id || $bee->status !== 'draft'), 404);
        $students = Student::with('classroom')->whereHas('classroom', fn ($query) => $query->where('teacher_account_id', $teacher->id))->orderBy('surname')->orderBy('first_name')->get();
        $teams = $bee ? app(QuizBeeService::class)->teams($bee)->map(fn ($team) => ['name' => $team->name, 'captain_id' => $team->captain_id,
            'members' => DB::table('quiz_bee_members')->where('quiz_bee_team_id', $team->id)->pluck('student_id')->reject(fn ($id) => (int) $id === (int) $team->captain_id)->values()->all()])->all() : [['name' => '', 'captain_id' => '', 'members' => []], ['name' => '', 'captain_id' => '', 'members' => []]];

        return response()->view('quiz-bee.form', compact('teacher', 'bee', 'students', 'teams'))->header('Cache-Control', 'private, no-store');
    }

    public function save(Request $request, ?QuizBeeMatch $bee = null)
    {
        $teacher = $this->teacher();
        $questions = $request->input('questions');
        if (is_array($questions)) {
            foreach ($questions as &$level) {
                if (! is_array($level)) {
                    continue;
                }
                foreach ($level as &$bank) {
                    if (! is_array($bank)) {
                        continue;
                    }
                    foreach ($bank as &$question) {
                        if (is_array($question) && is_string($question['accepted_answers'] ?? null)) {
                            $question['accepted_answers'] = array_values(array_filter(array_map('trim', preg_split('/\R/u', $question['accepted_answers']) ?: []), fn ($answer) => $answer !== ''));
                        }
                    }
                    unset($question);
                }
                unset($bank);
            }
            unset($level);
            $request->merge(['questions' => $questions]);
        }
        $teams = $request->input('teams');
        if (is_array($teams)) {
            foreach ($teams as &$team) {
                if (! is_array($team)) {
                    continue;
                }
                $members = is_array($team['members'] ?? null) ? array_values(array_filter($team['members'], fn ($id) => $id !== '' && $id !== null)) : [];
                if (! in_array((string) ($team['captain_id'] ?? ''), array_map('strval', $members), true)) {
                    array_unshift($members, $team['captain_id'] ?? null);
                }
                $team['members'] = $members;
            }
            unset($team);
            $request->merge(['teams' => $teams]);
        }
        $rules = ['title' => ['required', 'string', 'max:160'], 'category' => ['required', Rule::in(['individual', 'group'])],
            'status' => ['required', Rule::in(['draft', 'lobby'])], 'teams' => ['required', 'array', 'list', 'min:2', 'max:40'],
            'teams.*.name' => ['nullable', 'string', 'max:80'], 'teams.*.captain_id' => ['required', 'integer', 'distinct'],
            'teams.*.members' => ['required', 'array', 'list', 'min:1', 'max:5'], 'teams.*.members.*' => ['required', 'integer', 'distinct'],
            'questions' => ['required', 'array:easy,average,difficult']];
        foreach (QuizBeeMatch::LEVELS as $level) {
            $rules['questions.'.$level] = ['required', 'array:regular,tiebreak'];
            foreach (['regular', 'tiebreak'] as $kind) {
                $prefix = 'questions.'.$level.'.'.$kind;
                $rules[$prefix] = ['required', 'array', 'list', $kind === 'regular' ? 'min:1' : 'size:5', 'max:15'];
                $rules[$prefix.'.*.prompt'] = ['required', 'string', 'max:1000'];
                $rules[$prefix.'.*.type'] = ['sometimes', Rule::in($level === 'difficult' ? ['multiple_choice', 'fill_blank'] : ['multiple_choice'])];
                $rules[$prefix.'.*.options'] = ['exclude_if:'.$prefix.'.*.type,fill_blank', 'required', 'array', 'list', 'size:4'];
                $rules[$prefix.'.*.options.*'] = ['exclude_if:'.$prefix.'.*.type,fill_blank', 'required', 'string', 'max:500'];
                $rules[$prefix.'.*.correct'] = ['exclude_if:'.$prefix.'.*.type,fill_blank', 'required', 'integer', 'between:0,3'];
                $rules[$prefix.'.*.accepted_answers'] = ['exclude_unless:'.$prefix.'.*.type,fill_blank', 'required', 'array', 'list', 'min:1', 'max:10'];
                $rules[$prefix.'.*.accepted_answers.*'] = ['exclude_unless:'.$prefix.'.*.type,fill_blank', 'required', 'string', 'max:500'];
                $rules[$prefix.'.*.case_sensitive'] = ['exclude_unless:'.$prefix.'.*.type,fill_blank', 'sometimes', 'boolean'];
                $rules[$prefix.'.*.why'] = ['nullable', 'string', 'max:1000'];
            }
        }
        $data = $request->validate($rules);
        $bee = DB::transaction(function () use ($teacher, $data, $bee) {
            TeacherAccount::whereKey($teacher->id)->lockForUpdate()->firstOrFail();
            if ($bee) {
                $bee = QuizBeeMatch::whereKey($bee->id)->lockForUpdate()->firstOrFail();
                abort_unless($bee->teacher_account_id === $teacher->id && $bee->status === 'draft', 409, 'Only your unpublished drafts can be edited.');
            }
            $ids = collect($data['teams'])->pluck('members')->flatten()->map(fn ($id) => (int) $id);
            $students = Student::whereIn('id', $ids)->whereHas('classroom', fn ($query) => $query->where('teacher_account_id', $teacher->id))->get()->keyBy('id');
            if ($ids->unique()->count() !== $ids->count() || $students->count() !== $ids->count()) {
                throw ValidationException::withMessages(['teams' => 'Each approved student may belong to only one team and must be enrolled in your sections.']);
            }
            foreach ($data['teams'] as $team) {
                if ($data['category'] === 'individual' && count($team['members']) !== 1) {
                    throw ValidationException::withMessages(['teams' => 'Individual entries must contain exactly one student.']);
                }
            }
            $questions = $data['questions'];
            foreach ($questions as &$level) {
                foreach ($level as &$bank) {
                    foreach ($bank as &$question) {
                        $question['type'] ??= 'multiple_choice';
                        if ($question['type'] === 'fill_blank') {
                            $question['accepted_answers'] = array_values(array_unique(array_map('trim', $question['accepted_answers'])));
                            $question['case_sensitive'] = (bool) ($question['case_sensitive'] ?? true);
                        } elseif (count(array_unique(array_map(fn ($option) => mb_strtolower(trim($option)), $question['options']))) !== 4) {
                            throw ValidationException::withMessages(['questions' => 'Each question needs four different answer choices.']);
                        } else {
                            $question['correct'] = (int) $question['correct'];
                        }
                    }
                    unset($question);
                }
                unset($bank);
            }
            unset($level);
            $attributes = ['teacher_account_id' => $teacher->id, 'title' => $data['title'], 'category' => $data['category'], 'status' => $data['status'], 'questions' => $questions];
            if ($bee) {
                $bee->update($attributes);
                DB::table('quiz_bee_teams')->where('quiz_bee_match_id', $bee->id)->delete();
            } else {
                $bee = QuizBeeMatch::create($attributes);
            }
            foreach ($data['teams'] as $index => $team) {
                $captain = $students[$team['captain_id']];
                $teamId = DB::table('quiz_bee_teams')->insertGetId(['quiz_bee_match_id' => $bee->id, 'name' => $data['category'] === 'individual' ? $captain->nickname : (($team['name'] ?? '') ?: 'Team '.($index + 1)), 'captain_id' => $captain->id]);
                foreach ($team['members'] as $id) {
                    DB::table('quiz_bee_members')->insert(['quiz_bee_match_id' => $bee->id, 'quiz_bee_team_id' => $teamId, 'student_id' => $id]);
                    if ($bee->status === 'lobby') {
                        app(LearningNotifications::class)->quizBee($bee, 'student', (int) $id, 'invitation', 'Quiz Bee invitation', 'Your teacher invited you to '.$bee->title.'. Your captain marks the team ready and submits answers.');
                    }
                }
            }

            return $bee;
        }, 5);

        return redirect()->route('teacher.quiz-bee.show', $bee)->with('bee_notice', $bee->status === 'draft' ? 'Draft saved. Review and publish invitations when ready.' : 'Invitations sent to every assigned student.');
    }

    public function show(QuizBeeMatch $bee, QuizBeeService $service, QuizBeePresenter $presenter)
    {
        if (! session(request()->routeIs('teacher.*') ? 'teacher_id' : 'student_id')) {
            return redirect()->route(request()->routeIs('teacher.*') ? 'teacher.login' : 'student.login');
        }
        $student = $this->authorizeMatch($bee, $service);

        return response()->view('quiz-bee.show', ['teacherRole' => ! $student, 'teacher' => $student ? null : $this->teacher(), 'student' => $student,
            'classroom' => $student?->classroom, 'bee' => $bee, 'snapshot' => $presenter->snapshot($bee, $student),
            'lastSyncedAt' => now(config('learning.timezone'))->format('M j, Y · g:i A')])->header('Cache-Control', 'private, no-store');
    }

    public function status(QuizBeeMatch $bee, QuizBeeService $service, QuizBeePresenter $presenter)
    {
        $student = $this->authorizeMatch($bee, $service);

        return response()->json($presenter->snapshot($bee, $student))->header('Cache-Control', 'private, no-store');
    }

    public function action(Request $request, QuizBeeMatch $bee, QuizBeeService $service)
    {
        $student = $this->authorizeMatch($bee, $service);
        $data = $request->validate(['action' => ['required', Rule::in($student ? ['ready', 'answer'] : ['start', 'next', 'cancel'])],
            'run_id' => ['required_if:action,answer,next', 'nullable', 'integer'], 'answer' => ['required_if:action,answer', 'nullable', function ($attribute, $value, $fail) {
                if ((! is_string($value) && ! is_int($value)) || mb_strlen((string) $value) > 500) {
                    $fail('The answer must be text or a choice, up to 500 characters.');
                }
            }]]);
        match ($data['action']) {
            'ready' => $service->ready($bee, $student),
            'answer' => $service->answer($bee, $student, (int) $data['run_id'], $data['answer']),
            'start' => $service->start($bee),
            'next' => $service->next($bee, (int) $data['run_id']),
            'cancel' => DB::transaction(function () use ($bee) {
                $current = QuizBeeMatch::whereKey($bee->id)->lockForUpdate()->firstOrFail();
                abort_unless(in_array($current->status, ['draft', 'lobby', 'running'], true), 409);
                $current->update(['status' => 'cancelled']);
            }),
        };

        return response()->json(['message' => 'Match updated.']);
    }
}
