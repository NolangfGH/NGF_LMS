<?php

namespace App\Http\Controllers;

use App\Models\Classroom;
use App\Models\RecitationAttempt;
use App\Models\RecitationAward;
use App\Models\RecitationDraft;
use App\Models\RecitationRound;
use App\Models\Student;
use App\Models\TeacherAccount;
use App\Services\RecitationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class RecitationController extends Controller
{
    private function teacher(): TeacherAccount
    {
        $teacher = TeacherAccount::find(session('teacher_id'));
        abort_unless($teacher?->hasStaffAccess() && ! $teacher->isPrincipal(), 403);

        return $teacher;
    }

    private function student(): Student
    {
        return Student::with('classroom')->find(session('student_id')) ?? abort(401);
    }

    private function owned(RecitationRound $round): TeacherAccount
    {
        $teacher = $this->teacher();
        abort_unless($round->teacher_account_id === $teacher->id && Classroom::whereKey($round->classroom_id)->where('teacher_account_id', $teacher->id)->exists(), 403);

        return $teacher;
    }

    public function index(): Response
    {
        $teacher = $this->teacher();
        $rooms = $teacher->classrooms()->orderBy('grade')->orderBy('section')->get();
        $rounds = RecitationRound::where('teacher_account_id', $teacher->id)->latest('id')->paginate(20);

        return response()->view('recitation-teacher', compact('teacher', 'rooms', 'rounds'))->header('Cache-Control', 'private, no-store');
    }

    private function validated(Request $request, TeacherAccount $teacher): array
    {
        $data = $request->validate(['classroom_id' => ['required', 'integer', Rule::exists('classrooms', 'id')->where('teacher_account_id', $teacher->id)->whereNull('archived_at')], 'title' => ['required', 'string', 'max:160'], 'term' => ['required', Rule::in(['Term1', 'Term2', 'Term3'])], 'school_year' => ['sometimes', 'required', 'string', 'regex:/^20\d{2}-20\d{2}$/'], 'question' => ['required', 'string', 'max:5000'], 'accepted_answers' => ['required', 'string', 'max:20000'], 'case_sensitive' => ['required', 'boolean'], 'status' => ['required', Rule::in(['draft', 'open'])]]);
        if (isset($data['school_year']) && (int) substr($data['school_year'], 5) !== (int) substr($data['school_year'], 0, 4) + 1) {
            throw ValidationException::withMessages(['school_year' => 'Use consecutive school years, such as 2026-2027.']);
        }
        $answers = array_values(array_unique(array_filter(array_map('trim', preg_split('/\R/u', $data['accepted_answers'])), fn ($answer) => $answer !== '')));
        if (count($answers) < 1 || count($answers) > 20) {
            throw ValidationException::withMessages(['accepted_answers' => 'Provide one to twenty accepted answers, one per line.']);
        }
        if (collect($answers)->contains(fn (string $answer): bool => mb_strlen($answer) > 5000)) {
            throw ValidationException::withMessages(['accepted_answers' => 'Each accepted answer must fit the student editor’s 5,000-character limit.']);
        }
        $data['accepted_answers'] = $answers;
        $data['teacher_account_id'] = $teacher->id;

        return $data;
    }

    public function store(Request $request): Response
    {
        $round = RecitationRound::create($this->validated($request, $this->teacher()));

        return redirect()->route('teacher.recitations.show', $round);
    }

    public function update(Request $request, RecitationRound $round): Response
    {
        $teacher = $this->owned($round);
        $data = $this->validated($request, $teacher);
        DB::transaction(function () use ($round, $data): void {
            $current = RecitationRound::whereKey($round->id)->lockForUpdate()->firstOrFail();
            abort_if(RecitationDraft::where('recitation_round_id', $current->id)->exists() || RecitationAttempt::where('recitation_round_id', $current->id)->exists(), 409, 'Students have begun responding. Create a new round to change the question or answer key.');
            $current->update([...$data, 'revision' => $current->revision + 1]);
        });

        return back()->with('portal_notice', 'Recitation updated.');
    }

    public function action(Request $request, RecitationRound $round): Response
    {
        $this->owned($round);
        $data = $request->validate(['status' => ['required', Rule::in(['open', 'closed'])]]);
        DB::transaction(function () use ($round, $data): void {
            $current = RecitationRound::whereKey($round->id)->lockForUpdate()->firstOrFail();
            abort_unless(Classroom::whereKey($current->classroom_id)->whereNull('archived_at')->exists(), 409);
            abort_if($current->status === 'completed', 409, 'All five places have been awarded. Create a new round.');
            $current->update(['status' => $data['status'], 'revision' => $current->revision + 1]);
        });

        return back();
    }

    public function show(RecitationRound $round): Response
    {
        $teacher = $this->owned($round);
        $rooms = $teacher->classrooms()->get();
        $canEdit = ! RecitationDraft::where('recitation_round_id', $round->id)->exists() && ! RecitationAttempt::where('recitation_round_id', $round->id)->exists();

        return response()->view('recitation-monitor', ['teacher' => $teacher, 'round' => $round, 'rooms' => $rooms, 'canEdit' => $canEdit, 'state' => $this->teacherState($round)])->header('Cache-Control', 'private, no-store');
    }

    private function teacherState(RecitationRound $round): array
    {
        $students = Student::where('classroom_id', $round->classroom_id)->get(['id', 'first_name', 'surname', 'nickname']);
        $drafts = RecitationDraft::where('recitation_round_id', $round->id)->whereIn('student_id', $students->pluck('id'))->get()->keyBy('student_id');
        $awards = RecitationAward::where('recitation_round_id', $round->id)->orderBy('position')->get();
        $names = Student::whereIn('id', $awards->pluck('student_id'))->pluck('nickname', 'id');
        $answers = RecitationAttempt::whereIn('id', $awards->pluck('recitation_attempt_id'))->pluck('answer', 'id');
        $standings = app(RecitationService::class)->termStandings($round, $students->pluck('id'));

        return ['revision' => $round->revision.':'.(int) $standings->max('last_award_id'), 'status' => $round->status, 'winners' => $awards->map(fn ($award) => ['student_id' => $award->student_id, 'name' => $names->get($award->student_id, 'Former class member'), 'position' => $award->position, 'score' => $award->score, 'xp' => $award->xp, 'answer' => $answers->get($award->recitation_attempt_id), 'at' => $award->created_at->toIso8601String()])->all(),
            'drafts' => $students->map(fn ($student) => ['id' => $student->id, 'name' => trim($student->first_name.' '.$student->surname).' ('.$student->nickname.')', 'answer' => $drafts->get($student->id)?->answer ?? '', 'revision' => $drafts->get($student->id)?->revision ?? 0,
                'term_score' => (int) ($standings->get($student->id)?->points ?? 0), 'term_correct' => (int) ($standings->get($student->id)?->correct_answers ?? 0),
                'term_limit_reached' => (int) ($standings->get($student->id)?->correct_answers ?? 0) >= RecitationService::TERM_LIMIT || (int) ($standings->get($student->id)?->points ?? 0) >= 20])->all()];
    }

    public function status(Request $request, RecitationRound $round): Response
    {
        $this->owned($round);
        $state = $this->teacherState($round);

        return response()->json($request->query('revision') === $state['revision'] ? ['unchanged' => true] : $state)->header('Cache-Control', 'private, no-store');
    }

    public function studentIndex(): Response
    {
        $student = $this->student();
        $rounds = RecitationRound::where('classroom_id', $student->classroom_id)->where('teacher_account_id', $student->classroom->teacher_account_id)->where('status', '!=', 'draft')->latest('id')->paginate(20);
        $awards = RecitationAward::where('student_id', $student->id)->get()->keyBy('recitation_round_id');
        $termTotals = app(RecitationService::class)->studentTermTotals($student);

        return response()->view('recitation-student-list', [...compact('student', 'rounds', 'awards', 'termTotals'), 'classroom' => $student->classroom, 'lastSyncedAt' => now('Asia/Singapore')->format('M j, Y g:i A')])->header('Cache-Control', 'private, no-store');
    }

    private function studentRound(RecitationRound $round): Student
    {
        $student = $this->student();
        abort_unless($student->classroom_id === $round->classroom_id && $student->classroom->teacher_account_id === $round->teacher_account_id && $round->status !== 'draft', 403);

        return $student;
    }

    public function studentShow(RecitationRound $round): Response
    {
        $student = $this->studentRound($round);
        $draft = RecitationDraft::where('recitation_round_id', $round->id)->where('student_id', $student->id)->first();
        $award = RecitationAward::where('recitation_round_id', $round->id)->where('student_id', $student->id)->first();
        $termProgress = app(RecitationService::class)->termProgress($round, $student);

        return response()->view('recitation-student', [...compact('student', 'round', 'draft', 'award', 'termProgress'), 'classroom' => $student->classroom, 'lastSyncedAt' => now('Asia/Singapore')->format('M j, Y g:i A')])->header('Cache-Control', 'private, no-store');
    }

    public function studentStatus(RecitationRound $round): Response
    {
        $student = $this->studentRound($round);

        $award = RecitationAward::where('recitation_round_id', $round->id)->where('student_id', $student->id)->first(['position', 'score', 'xp']);

        return response()->json(['status' => $round->status, 'places_taken' => RecitationAward::where('recitation_round_id', $round->id)->count(), 'xp' => $student->xp, 'level' => $student->level, 'award' => $award, 'term_progress' => app(RecitationService::class)->termProgress($round, $student)])->header('Cache-Control', 'private, no-store');
    }

    public function save(Request $request, RecitationRound $round, RecitationService $service): Response
    {
        $student = $this->studentRound($round);
        $data = $request->validate(['answer' => ['nullable', 'string', 'max:5000'], 'revision' => ['required', 'integer', 'min:0']]);
        $draft = $service->save($round, $student, $data['answer'] ?? '', $data['revision']);

        return response()->json(['revision' => $draft->revision]);
    }

    public function submit(Request $request, RecitationRound $round, RecitationService $service): Response
    {
        $student = $this->studentRound($round);
        $data = $request->validate(['answer' => ['required', 'string', 'max:5000'], 'request_key' => ['required', 'uuid']]);

        return response()->json($service->submit($round, $student, $data['answer'], $data['request_key']));
    }
}
