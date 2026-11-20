<?php

namespace App\Http\Controllers;

use App\Models\CodingMatch;
use App\Models\Student;
use App\Models\TeacherAccount;
use App\Services\LearningCourses;
use App\Services\LessonService;
use App\Services\PeerCodingChallenges;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PeerChallengeController extends Controller
{
    private function student(): Student
    {
        return Student::with('classroom')->find(session('student_id')) ?? abort(401);
    }

    private function teacher(): TeacherAccount
    {
        $teacher = TeacherAccount::find(session('teacher_id'));
        abort_unless($teacher && $teacher->hasStaffAccess() && ! $teacher->isPrincipal(), 403);

        return $teacher;
    }

    private function eligiblePeers(Student $student): Builder
    {
        return Student::with('classroom:id,teacher_account_id,grade')->where('id', '!=', $student->id)->whereHas('classroom', fn (Builder $room): Builder => $room
            ->where('grade', $student->classroom?->grade)->whereIn('teacher_account_id', TeacherAccount::where('staff_access_status', 'active')
            ->whereIn('role', array_diff(array_keys(TeacherAccount::ROLES), ['principal']))->select('id')));
    }

    public function index(Request $request, LessonService $lessons): Response|RedirectResponse
    {
        if (! $request->session()->has('student_id')) {
            return redirect()->route('student.login');
        }
        $student = $this->student();
        abort_unless(filled($student->classroom?->grade), 403, 'Ask your teacher to assign a grade level before requesting a peer match.');
        $filters = $request->validate(['course' => ['nullable', Rule::in(LearningCourses::keys())], 'q' => ['nullable', 'string', 'min:2', 'max:60'], 'opponent' => ['nullable', 'integer', 'min:1']]);
        $course = in_array(old('course'), LearningCourses::keys(), true) ? old('course') : ($filters['course'] ?? 'cpp');
        $eligible = $this->eligiblePeers($student);
        $opponentId = filter_var(old('opponent', $filters['opponent'] ?? null), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null;
        $selectedOpponent = $opponentId ? (clone $eligible)->find($opponentId, ['id', 'nickname', 'leaderboard_visible', 'classroom_id']) : null;
        abort_if(isset($filters['opponent']) && ! $selectedOpponent, 404);
        $search = $filters['q'] ?? '';
        if ($search !== '') {
            if (preg_match('/\ACoder-[A-F0-9]{8}\z/i', $search)) {
                $ids = (clone $eligible)->get(['id', 'nickname', 'leaderboard_visible', 'classroom_id'])
                    ->filter(fn (Student $peer): bool => strtoupper($peer->rankingAlias()) === strtoupper($search))->modelKeys();
                $eligible->whereIn('id', $ids);
            } else {
                $eligible->where('leaderboard_visible', true)->where('nickname', 'like', '%'.$search.'%');
            }
        }
        $directory = $eligible->orderBy('id')->paginate(20, ['id', 'nickname', 'leaderboard_visible', 'classroom_id'], 'peers_page')->withQueryString();
        $path = $lessons->path($student, $course);
        $availableLessons = collect($lessons->catalogForTeacher($student->classroom->teacher_account_id, $course))->filter(function (array $lesson, string $key) use ($path, $selectedOpponent, $student): bool {
            $card = $path['cards']->firstWhere('key', $key);

            return $card && ! $card['locked'] && (! $selectedOpponent || $selectedOpponent->classroom?->teacher_account_id === $student->classroom->teacher_account_id
                || isset(LearningCourses::lessons()[$key]));
        });
        $requests = CodingMatch::with('participants.student')->where('origin', 'student')->whereHas('participants', fn (Builder $query): Builder => $query->where('student_id', $student->id))->latest('id')->paginate(12)->withQueryString();
        $approvals = DB::table('coding_match_approvals')->whereIn('coding_match_id', $requests->pluck('id'))->get()->groupBy('coding_match_id');

        return response()->view('peer-challenges', ['student' => $student, 'classroom' => $student->classroom, 'lastSyncedAt' => now(config('learning.timezone'))->format('M j, Y · g:i A'),
            'course' => $course, 'filters' => $filters, 'directory' => $directory, 'selectedOpponent' => $selectedOpponent, 'availableLessons' => $availableLessons, 'requests' => $requests, 'approvals' => $approvals])->header('Cache-Control', 'private, no-store');
    }

    public function store(Request $request, PeerCodingChallenges $service): RedirectResponse
    {
        $data = $request->validate(['opponent' => ['required', 'integer', 'min:1'], 'course' => ['required', Rule::in(LearningCourses::keys())], 'lesson' => ['required', 'string', 'max:60']]);
        $match = $service->request($this->student(), (int) $data['opponent'], $data['course'], $data['lesson']);

        return redirect()->route('student.peer-challenges', ['course' => $match->course])->with('peer_notice', 'Request sent to your teacher. Coding stays locked until every supervising teacher approves and the opponent accepts.');
    }

    public function withdraw(CodingMatch $match, PeerCodingChallenges $service): RedirectResponse
    {
        $service->withdraw($match, $this->student());

        return redirect()->route('student.peer-challenges', ['course' => $match->course])->with('peer_notice', 'The request was withdrawn or declined. Your teacher has been notified.');
    }

    public function review(CodingMatch $match): Response|RedirectResponse
    {
        if (! session('teacher_id')) {
            return redirect()->route('teacher.login');
        }
        $teacher = $this->teacher();
        abort_unless($match->origin === 'student', 404);
        $approvalRows = DB::table('coding_match_approvals')->where('coding_match_id', $match->id)->get();
        $approval = $approvalRows->firstWhere('teacher_account_id', $teacher->id);
        abort_unless($approval, 404);
        $match->load('participants');
        $isHost = (int) $match->teacher_account_id === $teacher->id;
        $hostApproved = $approvalRows->firstWhere('teacher_account_id', $match->teacher_account_id)?->decision === 'approved';
        $canReview = $match->status === 'awaiting_approval' && $approval->decision === 'pending' && ($isHost || $hostApproved);

        return response()->view('peer-challenge-review', compact('teacher', 'match', 'approvalRows', 'approval', 'isHost', 'hostApproved', 'canReview'))->header('Cache-Control', 'private, no-store');
    }

    public function decide(Request $request, CodingMatch $match, PeerCodingChallenges $service): RedirectResponse
    {
        $teacher = $this->teacher();
        $isHost = (int) $match->teacher_account_id === $teacher->id;
        $hostApproval = $isHost && $request->input('decision') === 'approved';
        $data = $request->validate(['decision' => ['required', Rule::in(['approved', 'rejected'])],
            'reason' => [$request->input('decision') === 'rejected' ? 'required' : 'nullable', 'string', 'min:'.($request->input('decision') === 'rejected' ? 5 : 0), 'max:2000'],
            'title' => [Rule::requiredIf($hostApproval), 'nullable', 'string', 'max:160'],
            'instructions' => [Rule::requiredIf($hostApproval), 'nullable', 'string', 'min:10', 'max:10000'],
            'sample_output' => [Rule::requiredIf($hostApproval), 'nullable', 'string', 'max:10000'],
            'time_limit_minutes' => [Rule::requiredIf($hostApproval), 'nullable', 'integer', 'between:1,180'],
            'winner_xp' => [Rule::requiredIf($hostApproval), 'nullable', 'integer', 'between:0,10000'],
            'award_title' => [Rule::requiredIf($hostApproval), 'nullable', 'string', 'max:80']]);
        $service->review($match, $teacher, $data);
        $match->refresh();

        return $match->status === 'inviting' ? redirect()->route('teacher.matches.show', $match)->with('match_notice', 'Every supervising teacher approved. The opponent can now accept the match.')
            : redirect()->route('teacher.peer-challenges.review', $match)->with('peer_notice', $match->status === 'cancelled' ? 'Request declined. The learners have been notified.' : 'Approval recorded. Waiting for the other supervising teacher.');
    }
}
