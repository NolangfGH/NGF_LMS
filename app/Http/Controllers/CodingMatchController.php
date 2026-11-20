<?php

namespace App\Http\Controllers;

use App\Models\CodingMatch;
use App\Models\CodingMatchSubmission;
use App\Models\Student;
use App\Models\TeacherAccount;
use App\Services\CodingMatchPresenter;
use App\Services\CodingMatchService;
use App\Services\LearningCourses;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CodingMatchController extends Controller
{
    private function teacher(): TeacherAccount
    {
        return TeacherAccount::find(session('teacher_id')) ?? abort(403, 'Sign in as a teacher.');
    }

    private function student(): Student
    {
        return Student::with('classroom')->find(session('student_id')) ?? abort(401, 'Sign in as a student.');
    }

    private function authorizeTeacher(CodingMatch $match, bool $manage = true): TeacherAccount
    {
        $teacher = $this->teacher();
        abort_unless((int) $match->teacher_account_id === $teacher->id || ! $manage && $match->canBeViewedByTeacher($teacher->id), 404);

        return $teacher;
    }

    private function authorizeStudent(CodingMatch $match): Student
    {
        $student = $this->student();
        abort_unless($match->participants()->where('student_id', $student->id)->exists(), 404);

        return $student;
    }

    public function index(): Response|RedirectResponse
    {
        if (! session('teacher_id')) {
            return redirect()->route('teacher.login');
        }
        $teacher = $this->teacher();

        return response()->view('teacher-matches', [
            'teacher' => $teacher,
            'classrooms' => $teacher->classrooms()->with(['students' => fn ($q) => $q->orderBy('surname')->orderBy('first_name')])->orderBy('grade')->orderBy('section')->get(),
            'codingMatches' => CodingMatch::with('participants')->supervisedBy($teacher->id)->latest('id')->paginate(12),
            'pendingPeerRequests' => CodingMatch::where('origin', 'student')->where('status', 'awaiting_approval')->supervisedBy($teacher->id)->latest('id')->limit(8)->get(),
        ])->header('Cache-Control', 'no-store, private');
    }

    public function store(Request $request, CodingMatchService $service): RedirectResponse
    {
        $teacher = $this->teacher();
        $count = $request->integer('participant_count', 2);
        $data = $request->validate([
            'participant_count' => ['sometimes', 'required', 'integer', Rule::in([2, 10])],
            'course' => ['sometimes', 'required', Rule::in(LearningCourses::keys())],
            'title' => ['required', 'string', 'max:160'], 'instructions' => ['required', 'string', 'min:10', 'max:10000'],
            'sample_output' => ['required', 'string', 'max:10000'], 'time_limit_minutes' => ['required', 'integer', 'between:1,180'],
            'winner_xp' => ['required', 'integer', 'between:0,10000'], 'award_title' => ['required', 'string', 'max:80'],
            'students' => ['required', 'array', 'list', 'size:'.$count], 'students.*' => ['required', 'integer', 'distinct'],
        ]);
        $data += ['participant_count' => 2, 'course' => 'cpp'];
        $data['participant_count'] = $count;
        $match = DB::transaction(function () use ($data, $teacher, $service) {
            $students = Student::with('classroom')->whereIn('id', $data['students'])->whereHas('classroom', fn ($q) => $q->where('teacher_account_id', $teacher->id))->lockForUpdate()->get()->keyBy('id');
            abort_unless($students->count() === $data['participant_count'], 422, 'Choose the required number of different students enrolled in your own sections.');
            $match = CodingMatch::create([...collect($data)->except('students')->all(), 'teacher_account_id' => $teacher->id]);
            foreach ($data['students'] as $slot => $id) {
                $student = $students[$id];
                $match->participants()->create(['student_id' => $student->id, 'slot' => $slot + 1,
                    'student_name' => trim($student->first_name.' '.$student->surname).' ('.$student->nickname.')',
                    'section_label' => $student->classroom->grade.' · '.($student->classroom->section ?: $student->classroom->name),
                    'draft_code' => CodingMatchService::starterCode($data['course']),
                ]);
                $service->notice($match, 'student', $student->id, 'invitation', 'Your teacher invited you to “'.$match->title.'”. Accept or decline in the challenge arena.');
            }

            return $match;
        });

        return redirect()->route('teacher.matches.show', $match)->with('match_notice', 'Challenge assigned. All '.$match->participant_count.' students have an invitation.');
    }

    public function teacherShow(CodingMatch $match, CodingMatchPresenter $presenter): Response|RedirectResponse
    {
        if (! session('teacher_id')) {
            return redirect()->route('teacher.login');
        }
        $teacher = $this->authorizeTeacher($match, false);
        if ($match->origin === 'student' && $match->status === 'awaiting_approval') {
            return redirect()->route('teacher.peer-challenges.review', $match);
        }

        return response()->view('teacher-match', ['teacher' => $teacher, 'match' => $match, 'snapshot' => $presenter->snapshot($match, null, $teacher->id)])->header('Cache-Control', 'no-store, private');
    }

    public function studentShow(CodingMatch $match, CodingMatchPresenter $presenter): Response|RedirectResponse
    {
        if (! session('student_id')) {
            return redirect()->route('student.login');
        }
        $student = $this->authorizeStudent($match);
        if ($match->origin === 'student' && $match->status === 'awaiting_approval') {
            return redirect()->route('student.peer-challenges');
        }

        return response()->view('student-match', ['student' => $student, 'classroom' => $student->classroom,
            'lastSyncedAt' => now(config('learning.timezone'))->format('M j, Y · g:i A'),
            'match' => $match, 'snapshot' => $presenter->snapshot($match, $student->id),
        ])->header('Cache-Control', 'no-store, private');
    }

    public function status(Request $request, CodingMatch $match, CodingMatchPresenter $presenter): JsonResponse
    {
        $studentId = null;
        $teacherId = null;
        if ($request->routeIs('teacher.*')) {
            $teacher = $this->authorizeTeacher($match, false);
            $teacherId = $teacher->id;
        } else {
            $studentId = $this->authorizeStudent($match)->id;
        }

        return response()->json($presenter->snapshot($match, $studentId, $teacherId))->header('Cache-Control', 'no-store, private');
    }

    public function records(Request $request): JsonResponse
    {
        $teacher = $request->routeIs('teacher.*');
        $query = CodingMatch::with('participants');
        if ($teacher) {
            $query->supervisedBy($this->teacher()->id);
        } else {
            $query->whereHas('participants', fn ($q) => $q->where('student_id', $this->student()->id));
        }
        $matches = $query->latest('id')->paginate(12);
        $matches->withPath(route($teacher ? 'teacher.challenges' : 'student.challenges'));

        return response()->json(['html' => view('match-records', ['codingMatches' => $matches, 'matchRole' => $teacher ? 'teacher' : 'student'])->render()])->header('Cache-Control', 'no-store, private');
    }

    public function respond(Request $request, CodingMatch $match, CodingMatchService $service): JsonResponse
    {
        $student = $this->authorizeStudent($match);
        $data = $request->validate(['decision' => ['required', Rule::in(['accepted', 'declined'])]]);
        $service->respond($match, $student, $data['decision']);

        return response()->json(['message' => 'Your response was saved.']);
    }

    public function save(Request $request, CodingMatch $match, CodingMatchService $service): JsonResponse
    {
        $student = $this->authorizeStudent($match);
        $data = $request->validate(['revision' => ['required', 'integer', 'min:0'], 'code' => ['present', 'nullable', 'string', 'max:50000']]);
        $draft = $service->save($match, $student, $data['revision'], $data['code'] ?? '');

        return response()->json(['revision' => $draft->draft_revision, 'saved_at' => $draft->draft_saved_at?->toIso8601String()]);
    }

    public function submit(Request $request, CodingMatch $match, CodingMatchService $service): JsonResponse
    {
        $student = $this->authorizeStudent($match);
        $data = $request->validate(['revision' => ['required', 'integer', 'min:0']]);
        $record = $service->submit($match, $student, $data['revision']);

        return response()->json(['id' => $record->id, 'message' => 'Final code submitted. Your teacher can now verify it.']);
    }

    public function review(Request $request, CodingMatch $match, CodingMatchSubmission $submission, CodingMatchService $service): JsonResponse|RedirectResponse
    {
        $teacher = $this->authorizeTeacher($match);
        abort_unless($submission->participant->coding_match_id === $match->id, 404);
        $data = $request->validate(['verdict' => ['required', Rule::in(['correct', 'revise'])],
            'feedback' => ['required', 'string', 'min:5', 'max:3000'],
            'observed_output' => ['required_if:verdict,correct', 'nullable', 'string', 'max:10000'],
            'verified' => ['required_if:verdict,correct', 'accepted_if:verdict,correct'],
        ]);
        $service->review($match, $submission, $teacher, $data);
        if ($request->expectsJson()) {
            return response()->json(['message' => 'Verification and feedback saved.']);
        }

        return back()->with('match_notice', 'Verification saved. The student can read your feedback.');
    }

    public function download(CodingMatch $match, CodingMatchSubmission $submission): Response
    {
        $this->authorizeTeacher($match, false);
        abort_unless($submission->participant->coding_match_id === $match->id, 404);

        return response($submission->code, 200, ['Content-Type' => 'text/plain; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="submission-'.$submission->id.('.'.pathinfo(LearningCourses::filename($match->course), PATHINFO_EXTENSION)).'"',
            'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'no-store, private']);
    }

    public function award(Request $request, CodingMatch $match, CodingMatchService $service): JsonResponse|RedirectResponse
    {
        $teacher = $this->authorizeTeacher($match);
        $data = $request->validate(['submission_id' => ['required', 'integer'], 'note' => ['required', 'string', 'min:5', 'max:2000'], 'confirmed' => ['accepted']]);
        $service->award($match, $teacher, $data['submission_id'], $data['note']);
        if ($request->expectsJson()) {
            return response()->json(['message' => 'Final winner awarded. XP credited once.']);
        }

        return back()->with('match_notice', 'Winner finalized. XP was awarded once and is included in the leaderboard.');
    }

    public function cancel(Request $request, CodingMatch $match, CodingMatchService $service): JsonResponse|RedirectResponse
    {
        $teacher = $this->authorizeTeacher($match);
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:1000']]);
        $service->cancel($match, $teacher, $data['reason']);
        if ($request->expectsJson()) {
            return response()->json(['message' => 'Challenge closed. Both students have been notified.']);
        }

        return back()->with('match_notice', 'Challenge closed. Saved code and submission history are retained.');
    }
}
