<?php

namespace App\Http\Controllers;

use App\Models\CodingMatch;
use App\Models\CodingMatchSubmission;
use App\Models\Student;
use App\Models\TeacherAccount;
use App\Services\CodingMatchPresenter;
use App\Services\CodingMatchService;
use Illuminate\Http\Request;
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

    private function authorizeTeacher(CodingMatch $match): TeacherAccount
    {
        $teacher = $this->teacher();
        abort_unless($match->teacher_account_id === $teacher->id, 404);

        return $teacher;
    }

    private function authorizeStudent(CodingMatch $match): Student
    {
        $student = $this->student();
        abort_unless($match->participants()->where('student_id', $student->id)->exists(), 404);

        return $student;
    }

    public function index()
    {
        if (! session('teacher_id')) {
            return redirect()->route('teacher.login');
        }
        $teacher = $this->teacher();

        return response()->view('teacher-matches', [
            'teacher' => $teacher,
            'classrooms' => $teacher->classrooms()->with(['students' => fn ($q) => $q->orderBy('surname')->orderBy('first_name')])->orderBy('grade')->orderBy('section')->get(),
            'codingMatches' => CodingMatch::with('participants')->where('teacher_account_id', $teacher->id)->latest('id')->paginate(12),
        ])->header('Cache-Control', 'no-store, private');
    }

    public function store(Request $request, CodingMatchService $service)
    {
        $teacher = $this->teacher();
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'], 'instructions' => ['required', 'string', 'min:10', 'max:10000'],
            'sample_output' => ['required', 'string', 'max:10000'], 'time_limit_minutes' => ['required', 'integer', 'between:1,180'],
            'winner_xp' => ['required', 'integer', 'between:0,10000'], 'award_title' => ['required', 'string', 'max:80'],
            'students' => ['required', 'array', 'list', 'size:2'], 'students.*' => ['required', 'integer', 'distinct'],
        ]);
        $match = DB::transaction(function () use ($data, $teacher, $service) {
            $students = Student::with('classroom')->whereIn('id', $data['students'])->whereHas('classroom', fn ($q) => $q->where('teacher_account_id', $teacher->id))->lockForUpdate()->get()->keyBy('id');
            abort_unless($students->count() === 2, 422, 'Choose two different students enrolled in your own sections.');
            $match = CodingMatch::create([...collect($data)->except('students')->all(), 'teacher_account_id' => $teacher->id]);
            foreach ($data['students'] as $slot => $id) {
                $student = $students[$id];
                $match->participants()->create(['student_id' => $student->id, 'slot' => $slot + 1,
                    'student_name' => trim($student->first_name.' '.$student->surname).' ('.$student->nickname.')',
                    'section_label' => $student->classroom->grade.' · '.($student->classroom->section ?: $student->classroom->name),
                    'draft_code' => "#include <iostream>\n\nint main() {\n    // Write your solution here.\n    return 0;\n}\n",
                ]);
                $service->notice($match, 'student', $student->id, 'invitation', 'Your teacher invited you to “'.$match->title.'”. Accept or decline in the challenge arena.');
            }

            return $match;
        });

        return redirect()->route('teacher.matches.show', $match)->with('match_notice', 'Challenge assigned. Both students have an invitation.');
    }

    public function teacherShow(CodingMatch $match, CodingMatchPresenter $presenter)
    {
        if (! session('teacher_id')) {
            return redirect()->route('teacher.login');
        }
        $teacher = $this->authorizeTeacher($match);

        return response()->view('teacher-match', ['teacher' => $teacher, 'match' => $match, 'snapshot' => $presenter->snapshot($match)])->header('Cache-Control', 'no-store, private');
    }

    public function studentShow(CodingMatch $match, CodingMatchPresenter $presenter)
    {
        if (! session('student_id')) {
            return redirect()->route('student.login');
        }
        $student = $this->authorizeStudent($match);

        return response()->view('student-match', ['student' => $student, 'classroom' => $student->classroom,
            'lastSyncedAt' => now(config('learning.timezone'))->format('M j, Y · g:i A'),
            'match' => $match, 'snapshot' => $presenter->snapshot($match, $student->id),
        ])->header('Cache-Control', 'no-store, private');
    }

    public function status(Request $request, CodingMatch $match, CodingMatchPresenter $presenter)
    {
        $studentId = null;
        if ($request->routeIs('teacher.*')) {
            $this->authorizeTeacher($match);
        } else {
            $studentId = $this->authorizeStudent($match)->id;
        }

        return response()->json($presenter->snapshot($match, $studentId))->header('Cache-Control', 'no-store, private');
    }

    public function records(Request $request)
    {
        $teacher = $request->routeIs('teacher.*');
        $query = CodingMatch::with('participants');
        if ($teacher) {
            $query->where('teacher_account_id', $this->teacher()->id);
        } else {
            $query->whereHas('participants', fn ($q) => $q->where('student_id', $this->student()->id));
        }
        $matches = $query->latest('id')->paginate(12);
        $matches->withPath(route($teacher ? 'teacher.challenges' : 'student.challenges'));

        return response()->json(['html' => view('match-records', ['codingMatches' => $matches, 'matchRole' => $teacher ? 'teacher' : 'student'])->render()])->header('Cache-Control', 'no-store, private');
    }

    public function respond(Request $request, CodingMatch $match, CodingMatchService $service)
    {
        $student = $this->authorizeStudent($match);
        $data = $request->validate(['decision' => ['required', Rule::in(['accepted', 'declined'])]]);
        $service->respond($match, $student, $data['decision']);

        return response()->json(['message' => 'Your response was saved.']);
    }

    public function save(Request $request, CodingMatch $match, CodingMatchService $service)
    {
        $student = $this->authorizeStudent($match);
        $data = $request->validate(['revision' => ['required', 'integer', 'min:0'], 'code' => ['present', 'nullable', 'string', 'max:50000']]);
        $draft = $service->save($match, $student, $data['revision'], $data['code'] ?? '');

        return response()->json(['revision' => $draft->draft_revision, 'saved_at' => $draft->draft_saved_at?->toIso8601String()]);
    }

    public function submit(Request $request, CodingMatch $match, CodingMatchService $service)
    {
        $student = $this->authorizeStudent($match);
        $data = $request->validate(['revision' => ['required', 'integer', 'min:0']]);
        $record = $service->submit($match, $student, $data['revision']);

        return response()->json(['id' => $record->id, 'message' => 'Saved revision submitted for teacher verification.']);
    }

    public function review(Request $request, CodingMatch $match, CodingMatchSubmission $submission, CodingMatchService $service)
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

    public function download(CodingMatch $match, CodingMatchSubmission $submission)
    {
        $this->authorizeTeacher($match);
        abort_unless($submission->participant->coding_match_id === $match->id, 404);

        return response($submission->code, 200, ['Content-Type' => 'text/plain; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="submission-'.$submission->id.'.cpp"',
            'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'no-store, private']);
    }

    public function award(Request $request, CodingMatch $match, CodingMatchService $service)
    {
        $teacher = $this->authorizeTeacher($match);
        $data = $request->validate(['submission_id' => ['required', 'integer'], 'note' => ['required', 'string', 'min:5', 'max:2000'], 'confirmed' => ['accepted']]);
        $service->award($match, $teacher, $data['submission_id'], $data['note']);
        if ($request->expectsJson()) {
            return response()->json(['message' => 'Final winner awarded. XP credited once.']);
        }

        return back()->with('match_notice', 'Winner finalized. XP was awarded once and is included in the leaderboard.');
    }

    public function cancel(Request $request, CodingMatch $match, CodingMatchService $service)
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
