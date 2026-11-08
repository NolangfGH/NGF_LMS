<?php

namespace App\Http\Controllers;

use App\Models\Classroom;
use App\Models\CodeRun;
use App\Models\CodeWorkspace;
use App\Models\CodingMatch;
use App\Models\QuizAttempt;
use App\Models\SecondChanceRequest;
use App\Models\Student;
use App\Models\StudentAchievement;
use App\Models\TeacherAccount;
use App\Models\TeacherAssignment;
use App\Models\TeacherQuiz;
use App\Models\User;
use App\Services\AchievementService;
use App\Services\ChallengeService;
use App\Services\CodeExecutionService;
use App\Services\CodeLabTimer;
use App\Services\DashboardService;
use App\Services\HomepageService;
use App\Services\Judge0Client;
use App\Services\LeaderboardPeriods;
use App\Services\LearningNotifications;
use App\Services\LessonService;
use App\Services\QuizAttemptService;
use App\Services\QuizRewards;
use App\Services\StaffAccessService;
use App\Services\StudentSettingsService;
use App\Services\XpLedger;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class HomeController extends Controller
{
    public function index(Request $request, HomepageService $service)
    {
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:100']]);
        $home = $service->snapshot(session('student_id'), session('teacher_id'));
        $search = trim($filters['q'] ?? '');

        return response()->view('home', [
            'home' => $home, 'search' => $search,
            'matchCount' => collect($home['catalog'])->filter(fn ($lesson) => $search === '' || str_contains(mb_strtolower($lesson['title'].' '.$lesson['topic'].' '.$lesson['objective']), mb_strtolower($search)))->count(),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function homeStatus(HomepageService $service)
    {
        $home = $service->snapshot(session('student_id'), session('teacher_id'));
        if (! $home['available']) {
            return response()->json(['available' => false, 'message' => 'Live classroom data is temporarily unavailable. Your last loaded values are kept.'], 503)
                ->header('Cache-Control', 'private, no-store')->header('Retry-After', '15');
        }

        return response()->json([
            'available' => true, 'revision' => $home['revision'], 'updated_at' => $home['updated_at'], 'updated_label' => $home['updated_label'], 'year' => $home['year'],
            'fragments' => [
                'stats' => view('home-stats', compact('home'))->render(),
                'portals' => view('home-portals', compact('home'))->render(),
                'catalog' => view('home-catalog', compact('home'))->render(),
                'example' => view('home-example', compact('home'))->render(),
            ],
        ])->header('Cache-Control', 'private, no-store')->header('X-Content-Type-Options', 'nosniff');
    }

    public function generate(Request $request)
    {
        $request->validate([
            'business_type' => ['required', 'string', 'max:255'],
        ]);

        return 'Generated successfully';
    }

    public function teacherDashboard()
    {
        try {
            $teacher = TeacherAccount::find(session('teacher_id'));
        } catch (Throwable) {
            return redirect()->route('teacher.login')->with('auth_error', 'The teacher database is not available yet. Configure the database and try again.');
        }

        if (! $teacher) {
            return redirect()->route('teacher.login')->with('auth_error', 'Sign in with a registered teacher account to continue.');
        }

        $today = now();
        $registeredUsers = 0;
        $activeToday = 0;
        $databaseAvailable = true;

        try {
            $registeredUsers = User::query()->count();
            $activeToday = User::query()->whereDate('updated_at', $today->toDateString())->count();
        } catch (Throwable) {
            // Keep the dashboard usable before the hosting database is configured.
            $databaseAvailable = false;
        }

        $classroom = $teacher->classrooms()->latest()->first();
        $students = Student::with('classroom')->whereHas('classroom', fn ($query) => $query->where('teacher_account_id', $teacher->id))->orderBy('surname')->orderBy('first_name')->get();
        $registeredUsers = $students->count();
        $activeToday = $students->filter(fn ($student) => $student->created_at->copy()->timezone(config('learning.timezone'))->isSameDay(now(config('learning.timezone'))))->count();

        return view('teacher-dashboard', [
            'teacher' => $teacher,
            'overview' => app(DashboardService::class)->teacher($teacher),
            'teacherName' => $teacher->fullName(),
            'teacherRole' => 'System Admin',
            'classroomName' => $classroom?->name ?? 'No classroom created',
            'classroomLocation' => $classroom ? $classroom->name : 'No classroom created',
            'classroomSection' => $classroom ? $classroom->grade.' · '.$classroom->period : 'Create a classroom to get a join code',
            'classCode' => $classroom?->join_code,
            'registeredUsers' => $registeredUsers,
            'activeToday' => $activeToday,
            'lastSyncedAt' => $today->format('M j, Y · g:i A'),
            'currentDay' => $today->format('l'),
            'hasLiveClassData' => $registeredUsers > 0,
            'databaseAvailable' => $databaseAvailable,
            'students' => $students,
            'studentRoster' => $students->map(function ($student) {
                return [
                    'name' => $student->nickname,
                    'student_id' => $student->student_id,
                    'joined' => $student->created_at?->format('M j, Y'),
                ];
            })->values()->all(),
        ]);
    }

    public function studentDashboard()
    {
        if (! session('student_class_verified') || ! session('student_id')) {
            return redirect()->route('student.register')->with('class_code_required', 'Enter a valid class code before opening the Student Dashboard.');
        }

        $data = $this->studentPageData();
        if ($data instanceof RedirectResponse) {
            return $data;
        }

        return view('student-dashboard', [
            ...$data,
            'overview' => app(DashboardService::class)->student($data['student']),
            'student' => $data['student'],
            'studentAlerts' => app(StudentSettingsService::class)->alerts($data['student']),
            'achievementReport' => $data['achievementReport'],
            'studentName' => $data['student']->nickname,
            'classroomName' => $data['classroom']?->name.' · '.$data['classroom']?->period,
            'lastSyncedAt' => now(config('learning.timezone'))->format('M j, Y · g:i A'),
        ]);
    }

    public function studentRegistration()
    {
        $options = $this->studentEnrollmentOptions();
        $grades = $options->pluck('grade')->unique()->sort(SORT_NATURAL | SORT_FLAG_CASE)->values();

        return response()->view('student-register', ['enrollmentOptions' => $options, 'gradeLevels' => $grades])
            ->header('Cache-Control', 'private, no-store');
    }

    /** @return Collection<int, array{grade: string, section: string}> */
    private function studentEnrollmentOptions(): Collection
    {
        return Classroom::openForStudentRegistration()->distinct()->get(['grade', 'section'])
            ->map(fn (Classroom $room): array => ['grade' => $room->grade, 'section' => trim($room->section)])
            ->unique(fn (array $room): string => $room['grade'].'|'.mb_strtoupper($room['section']))
            ->sort(fn (array $first, array $second): int => strnatcasecmp($first['grade'], $second['grade']) ?: strnatcasecmp($first['section'], $second['section']))
            ->values();
    }

    public function studentRegistrationOptions(): JsonResponse
    {
        return response()->json(['enrollment_options' => $this->studentEnrollmentOptions()])
            ->header('Cache-Control', 'private, no-store');
    }

    public function verifyStudentClassCode(Request $request)
    {
        $request->session()->forget(['student_class_verified', 'classroom_id', 'classroom_name', 'verified_join_code']);
        $data = $request->validate([
            'class_code' => ['required', 'string', 'max:20'],
            'classroom_id' => ['required_without:section', 'nullable', 'integer'],
            'section' => ['required_without:classroom_id', 'nullable', 'string', 'max:80'],
            'grade_level' => ['required', 'string', 'max:50'],
        ]);

        $submittedCode = strtoupper(preg_replace('/\s+/', '', $request->string('class_code')->toString()));
        try {
            $classroom = Classroom::openForStudentRegistration()->whereRaw("REPLACE(UPPER(join_code), ' ', '') = ?", [$submittedCode])->first();
        } catch (Throwable) {
            return response()->json(['message' => 'Classroom service is not available. Please try again after the database is configured.'], 503);
        }

        if (! $classroom || ! $classroom->acceptsStudentRegistration() || $classroom->grade !== $data['grade_level']
            || (isset($data['classroom_id']) && $classroom->id !== (int) $data['classroom_id'])
            || (isset($data['section']) && strcasecmp(trim($classroom->section), trim($data['section'])) !== 0)) {
            return response()->json([
                'message' => 'The class code must match your selected grade level and section. Check the details with your teacher.',
            ], 422);
        }

        session([
            'student_class_verified' => true,
            'verified_join_code' => $classroom->registrationFingerprint(),
            'classroom_id' => $classroom->id,
            'classroom_name' => $classroom->grade.' · '.$classroom->section,
        ]);

        return response()->json(['message' => 'Class verified successfully.', 'classroom' => [
            'id' => $classroom->id, 'name' => $classroom->name, 'grade' => $classroom->grade,
            'section' => $classroom->section,
        ]]);
    }

    public function registerTeacher(Request $request)
    {
        $email = $request->input('email', '');
        $request->merge(['email' => is_string($email) ? strtolower(trim($email)) : $email, 'requested_role' => $request->input('requested_role', 'teacher')]);
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:80'],
            'middle_initial' => ['nullable', 'string', 'max:2', 'regex:/^\p{L}\.?$/u'],
            'last_name' => ['required', 'string', 'max:80'],
            'email' => ['bail', 'required', 'string', 'email', 'max:255', 'unique:teacher_accounts,email'],
            'school' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'min:10', 'confirmed'],
            'requested_role' => ['required', Rule::in(array_keys(TeacherAccount::ROLES))],
        ]);

        $teacher = app(StaffAccessService::class)->register($data);
        session()->regenerate();
        session()->forget(['teacher_id', 'teacher_session_version', 'teacher_authenticated_at', 'pending_staff_registration_id']);
        if (! $teacher->hasStaffAccess()) {
            session(['pending_staff_registration_id' => $teacher->id]);

            return response()->json(['message' => 'Your role request is awaiting System Admin approval.', 'status' => 'pending', 'redirect' => route('staff.registration.status')]);
        }
        session(['teacher_id' => $teacher->id, 'teacher_session_version' => $teacher->session_version, 'teacher_authenticated_at' => now()->toIso8601String()]);

        return response()->json(['message' => 'Teacher account created. Add your teaching sections in My Classes.', 'status' => 'active', 'redirect' => route('teacher.classes')]);
    }

    public function loginTeacher(Request $request)
    {
        $data = $request->validate([
            'identifier' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ]);

        $teacher = TeacherAccount::where('email', strtolower($data['identifier']))->first();

        if (! $teacher || ! Hash::check($data['password'], $teacher->password)) {
            return response()->json(['message' => 'The teacher email or password is incorrect.'], 422);
        }

        session()->regenerate();
        if (! $teacher->hasStaffAccess()) {
            session()->forget(['teacher_id', 'teacher_session_version', 'teacher_authenticated_at']);
            session(['pending_staff_registration_id' => $teacher->id]);

            return response()->json(['message' => 'Check your staff access request.', 'status' => $teacher->staff_access_status, 'redirect' => route('staff.registration.status')]);
        }
        session()->forget(['pending_staff_registration_id', 'parent_id', 'parent_session_version']);
        session(['teacher_id' => $teacher->id, 'teacher_session_version' => $teacher->session_version, 'teacher_authenticated_at' => now()->toIso8601String()]);

        return response()->json(['message' => 'Signed in successfully.', 'redirect' => route($teacher->dashboardRoute())]);
    }

    public function registerStudent(Request $request)
    {
        $request->merge(['student_id' => strtoupper(trim($request->input('student_id', '')))]);
        if (! session('student_class_verified') || ! session('classroom_id')) {
            return response()->json(['message' => 'Verify a valid class code before creating a student account.'], 422);
        }

        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:80'],
            'middle_name' => ['nullable', 'string', 'max:80'],
            'surname' => ['required', 'string', 'max:80'],
            'school_name' => ['nullable', 'string', 'max:180'],
            'ict_teacher_name' => ['nullable', 'string', 'max:180'],
            'grade_level' => ['required', 'string', 'max:50'],
            'classroom_id' => ['required', 'integer'],
            'nickname' => ['required', 'string', 'min:3', 'max:40'],
            'student_id' => ['required', 'string', 'min:3', 'max:40', 'unique:students,student_id'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'avatar' => ['nullable', Rule::in(array_keys(array_filter(config('student_settings.avatars'), fn ($avatar) => $avatar['xp'] === 0 && ! isset($avatar['badge']))))],
        ]);

        $student = DB::transaction(function () use ($data) {
            $classroom = Classroom::whereKey(session('classroom_id'))->lockForUpdate()->first();
            if (! $classroom || ! $classroom->acceptsStudentRegistration() || $classroom->id !== (int) $data['classroom_id'] || $classroom->grade !== $data['grade_level'] || ! hash_equals($classroom->registrationFingerprint(), (string) session('verified_join_code'))) {
                throw ValidationException::withMessages(['class_code' => 'Registration is closed or the join code has changed. Verify the current code for your selected grade and section.']);
            }

            $student = new Student([
                'classroom_id' => session('classroom_id'),
                'first_name' => $data['first_name'],
                'middle_name' => $data['middle_name'] ?? null,
                'surname' => $data['surname'],
                'school_name' => $data['school_name'] ?? null,
                'ict_teacher_name' => $data['ict_teacher_name'] ?? null,
                'grade_level' => $classroom->grade,
                'section' => $classroom->section ?? $classroom->period,
                'nickname' => $data['nickname'],
                'student_id' => strtoupper($data['student_id']),
                'password' => Hash::make($data['password']),
                'avatar' => $data['avatar'] ?? 'Astro Bot',
                'xp' => 0,
                'level' => 1,
            ]);
            $student->forceFill(['registration_status' => 'pending'])->save();
            app(LearningNotifications::class)->registrationSubmitted($student);

            return $student;
        });

        $request->session()->forget(['student_id', 'student_name', 'student_session_version', 'student_class_verified', 'classroom_id', 'classroom_name', 'verified_join_code']);
        session(['pending_registration_id' => $student->id]);
        $request->session()->regenerate();

        return response()->json([
            'message' => 'Registration submitted. Your class administrator must approve it before you can sign in.',
            'status' => 'pending',
            'welcome_xp' => Student::REGISTRATION_XP,
            'xp' => $student->xp,
            'redirect' => route('student.registration.status'),
        ]);
    }

    public function loginStudent(Request $request)
    {
        $data = $request->validate([
            'student_id' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $student = Student::withUnapproved()->where('student_id', strtoupper(trim($data['student_id'])))->first();

        if (! $student || $student->classroom?->archived_at || ! Hash::check($data['password'], $student->password)) {
            return response()->json(['message' => 'The Student ID or password is incorrect.'], 422);
        }

        session()->regenerate();
        if (! $student->isRegistrationApproved()) {
            $request->session()->forget(['student_id', 'student_name', 'student_session_version', 'student_class_verified', 'classroom_id', 'classroom_name', 'verified_join_code']);
            session(['pending_registration_id' => $student->id]);

            return response()->json(['message' => 'Check your registration review status.', 'status' => $student->registration_status, 'redirect' => route('student.registration.status')]);
        }
        $request->session()->forget(['pending_registration_id', 'parent_id', 'parent_session_version']);
        session([
            'student_id' => $student->id,
            'student_session_version' => $student->session_version,
            'student_class_verified' => true,
            'classroom_id' => $student->classroom_id,
            'classroom_name' => $student->classroom->name.' · '.$student->classroom->period,
            'student_name' => $student->nickname,
        ]);

        return response()->json(['message' => 'Signed in successfully.', 'redirect' => route('student.dashboard')]);
    }

    private function studentPageData()
    {
        $student = Student::with('classroom')->find(session('student_id'));

        if (! $student) {
            return redirect()->route('student.login')->with('auth_error', 'Sign in with a student account to continue.');
        }

        app(QuizAttemptService::class)->finalizeExpired($student);
        app(QuizRewards::class)->awardPending($student);
        $student->refresh();
        $achievementReport = app(AchievementService::class)->sync($student);
        $student->refresh();

        $classmates = Student::where('classroom_id', $student->classroom_id)
            ->orderByDesc('xp')->orderBy('nickname')->get();
        $publishedAssignments = TeacherAssignment::forClassroom($student->classroom_id)
            ->where('status', 'published')->latest()->get();

        return [
            'student' => $student,
            'teacherProfile' => $student->classroom?->teacher?->classroomProfile(),
            'achievementReport' => $achievementReport,
            'classroom' => $student->classroom,
            'classmates' => $classmates,
            'publishedAssignments' => $publishedAssignments,
            'secondChanceRequests' => SecondChanceRequest::where('student_id', $student->id)->get()->keyBy(fn ($item) => $item->activity_type.':'.$item->activity_id),
            'lastSyncedAt' => now(config('learning.timezone'))->format('M j, Y · g:i A'),
            'levelProgress' => min(100, max(0, $student->xp % 600) / 6),
        ];
    }

    public function studentLearn()
    {
        $data = $this->studentPageData();
        if ($data instanceof RedirectResponse) {
            return $data;
        }

        return response()->view('learn', [...$data, 'lessonPath' => app(LessonService::class)->path($data['student'])])->header('Cache-Control', 'private, no-store');
    }

    public function studentCodeLab(Request $request)
    {
        $data = $this->studentPageData();
        if ($data instanceof RedirectResponse) {
            return $data;
        }
        $activity = app(CodeLabController::class)->activity($request, $data['student']);
        $workspace = CodeWorkspace::firstOrCreate(['student_id' => $data['student']->id, 'activity_key' => $activity['key']], [
            'teacher_assignment_id' => $activity['assignment']?->id, 'title' => $activity['title'], 'code' => $activity['code'],
        ]);
        $workspace = DB::transaction(function () use ($workspace) {
            Student::whereKey($workspace->student_id)->lockForUpdate()->firstOrFail();
            $current = CodeWorkspace::whereKey($workspace->id)->lockForUpdate()->firstOrFail();
            app(CodeLabTimer::class)->startIfNeeded($current);

            return $current;
        });

        $practiceKeys = collect(app(LessonService::class)->catalog())->pluck('practice')
            ->merge(collect(config('challenges'))->reject(fn (array $challenge): bool => ! empty($challenge['legacy']))->keys())
            ->merge(CodeWorkspace::where('student_id', $data['student']->id)->where('activity_key', 'like', 'practice:%')->pluck('activity_key')->map(fn (string $key): string => substr($key, 9)));
        $practiceActivities = array_intersect_key(config('code_lab.activities'), array_flip($practiceKeys->all()));

        return view('code-lab', [...$data, 'labActivity' => $activity, 'workspace' => $workspace,
            'labSubmissions' => $workspace->submissions()->latest()->get(), 'practiceActivities' => $practiceActivities,
            'compilerConfigured' => app(Judge0Client::class)->configured(),
            'codeRuns' => CodeRun::where('code_workspace_id', $workspace->id)->latest()->limit(10)->get(),
            'visibleTests' => collect(app(CodeExecutionService::class)->cases($workspace))->filter(fn ($case) => empty($case['hidden']))->values(),
            'labClient' => ['revision' => $workspace->revision, 'starter' => $activity['code'], 'saveUrl' => route('student.code-lab.save', $workspace), 'submitUrl' => route('student.code-lab.submit', $workspace), 'statusUrl' => route('student.code-lab.status', $workspace), 'timer' => app(CodeLabTimer::class)->describe($workspace)]]);
    }

    public function studentChallenges()
    {
        $data = $this->studentPageData();
        if ($data instanceof RedirectResponse) {
            return $data;
        }
        $data['challengeCards'] = app(ChallengeService::class)->cards($data['student'], $data['publishedAssignments']->where('kind', 'challenge'));
        $data['assignmentWorkspaces'] = CodeWorkspace::where('student_id', $data['student']->id)->get()->keyBy('teacher_assignment_id');
        $data['codingMatches'] = CodingMatch::with('participants')->whereHas('participants', fn ($q) => $q->where('student_id', $data['student']->id))->latest('id')->paginate(12);

        return view('challenges', $data);
    }

    public function studentAchievements(Request $request, XpLedger $ledger): Response|RedirectResponse
    {
        $data = $this->studentPageData();
        if ($data instanceof RedirectResponse) {
            return $data;
        }
        StudentAchievement::where('student_id', $data['student']->id)->whereNull('seen_at')->update(['seen_at' => now()]);

        return response()->view('achievements', $data + ['xpHistory' => $ledger->history($data['student'], $request), 'xpHistoryRoute' => route('student.achievements')])->header('Cache-Control', 'private, no-store');
    }

    public function studentLeaderboard(Request $request)
    {
        $data = $this->studentPageData();
        if ($data instanceof RedirectResponse) {
            return $data;
        }
        $sections = Classroom::where('teacher_account_id', $data['classroom']->teacher_account_id)
            ->where('grade', $data['classroom']->grade)->orderBy('section')->orderBy('id')->get();
        $members = Student::with('classroom')->whereIn('classroom_id', $sections->pluck('id'))
            ->orderByDesc('xp')->orderBy('nickname')->orderBy('id')->get();
        $period = $request->validate(['period' => ['nullable', 'in:all,week,month']])['period'] ?? 'all';
        $periodPoints = app(LeaderboardPeriods::class)->points($members, $period);
        foreach ($members as $member) {
            $member->xp = (int) ($periodPoints[$member->id] ?? 0);
        }
        $members = $members->sortBy([['xp', 'desc'], ['nickname', 'asc'], ['id', 'asc']])->values();
        $viewerId = $data['student']->id;
        $rank = function ($students) use ($viewerId) {
            $position = 0;
            $previousXp = null;
            $currentRank = 0;

            return $students->map(function ($member) use (&$position, &$previousXp, &$currentRank, $viewerId) {
                $position++;
                if ($previousXp !== $member->xp) {
                    $currentRank = $position;
                }
                $previousXp = $member->xp;

                return ['id' => $member->id, 'nickname' => $member->rankingName($viewerId), 'avatar' => ($member->leaderboard_visible || $member->id === $viewerId) ? $member->avatarAppearance()['emoji'] : '👤', 'xp' => $member->xp, 'level' => $member->level,
                    'section_id' => $member->classroom_id, 'section' => $member->classroom->section ?: $member->classroom->name,
                    'rank' => $currentRank];
            })->values();
        };
        $overall = $rank($members);
        $bySection = $sections->mapWithKeys(fn ($room) => [$room->id => $rank($members->where('classroom_id', $room->id))]);
        $filter = $request->validate(['section' => ['nullable', 'integer', 'min:1']]);
        $selected = ! empty($filter['section']) ? (int) $filter['section'] : null;
        abort_if($selected && ! $sections->contains('id', $selected), 403);

        return response()->view('leaderboard', [...$data,
            'period' => $period, 'teamPoints' => $members->where('classroom_id', $data['classroom']->id)->sum('xp'), 'sections' => $sections, 'overallRanking' => $overall, 'sectionRankings' => $bySection,
            'selectedSection' => $selected, 'rankingRows' => $selected ? $bySection[$selected] : $overall,
            'myOverallRank' => $overall->firstWhere('id', $data['student']->id)['rank'] ?? null,
            'mySectionRank' => $bySection[$data['student']->classroom_id]->firstWhere('id', $data['student']->id)['rank'] ?? null,
        ])->header('Cache-Control', 'private, no-store');
    }

    public function studentProgress()
    {
        $data = $this->studentPageData();
        if ($data instanceof RedirectResponse) {
            return $data;
        }

        return response()->view('progress', [...$data, 'lessonPath' => app(LessonService::class)->path($data['student'])])->header('Cache-Control', 'private, no-store');
    }

    public function studentAssignments()
    {
        $data = $this->studentPageData();
        if ($data instanceof RedirectResponse) {
            return $data;
        }
        $data['publishedAssignments'] = $data['publishedAssignments']->where('kind', 'assignment')->values();
        $data['assignmentWorkspaces'] = CodeWorkspace::where('student_id', $data['student']->id)
            ->whereIn('teacher_assignment_id', $data['publishedAssignments']->pluck('id'))->get()->keyBy('teacher_assignment_id');
        $data['assignmentServerTime'] = now()->getTimestampMs();

        return view('student-assignments', $data);
    }

    public function studentQuiz()
    {
        $data = $this->studentPageData();
        if ($data instanceof RedirectResponse) {
            return $data;
        }
        $data['quizAttempts'] = QuizAttempt::where('student_id', $data['student']->id)->orderBy('attempt_number')->get()->keyBy('teacher_quiz_id');
        $data['quizServerTime'] = now()->getTimestampMs();
        $data['quizzes'] = TeacherQuiz::forClassroom($data['student']->classroom_id)
            ->where('status', 'published')->latest()->get();
        $data['quizResults'] = DB::table('quiz_submissions')
            ->where('student_id', $data['student']->id)->orderBy('attempt_number')->get()->keyBy('teacher_quiz_id');
        $data['quizFinalResults'] = DB::table('quiz_submissions')->where('student_id', $data['student']->id)->where('attempt_number', 2)->get()->keyBy('teacher_quiz_id');

        return response()->view('quiz', $data, 200, ['Cache-Control' => 'private, no-store', 'Permissions-Policy' => 'display-capture=()']);
    }

    public function teacherQuizzes()
    {
        $teacher = TeacherAccount::find(session('teacher_id'));
        if (! $teacher) {
            return redirect()->route('teacher.login');
        }
        $classroom = $teacher->classrooms()->latest()->first();
        $quizzes = TeacherQuiz::with(['classroom', 'classrooms'])->where('teacher_account_id', $teacher->id)->latest()->get();

        return view('teacher-quizzes', [
            'teacher' => $teacher,
            'classroom' => $classroom,
            'classrooms' => $teacher->classrooms()->withCount('students')->orderBy('grade')->orderBy('section')->get(),
            'quizzes' => $quizzes,
            'editableQuizzes' => $quizzes->map(fn ($quiz) => [
                'id' => $quiz->id, 'title' => $quiz->title, 'instructions' => $quiz->instructions,
                'classroom_id' => $quiz->classroom_id, 'xp' => $quiz->xp, 'time_limit' => $quiz->time_limit,
                'untimed' => $quiz->untimed, 'question_seconds' => $quiz->question_seconds,
                'classroom_ids' => $quiz->classrooms->isNotEmpty() ? $quiz->classrooms->pluck('id')->all() : [$quiz->classroom_id],
                'status' => $quiz->status, 'questions' => $quiz->questions,
                'opens_at' => $quiz->opens_at?->timezone(config('learning.timezone'))->format('Y-m-d\\TH:i'),
                'closes_at' => $quiz->closes_at?->timezone(config('learning.timezone'))->format('Y-m-d\\TH:i'),
            ])->values()->all(),
        ]);
    }

    public function storeQuizSection(Request $request)
    {
        $teacher = TeacherAccount::find(session('teacher_id'));
        abort_unless($teacher, 401);
        $data = $request->validate([
            'classroom_id' => ['required', 'integer', Rule::exists('classrooms', 'id')->where('teacher_account_id', $teacher->id)],
            'section' => ['required', 'string', 'max:80'],
        ]);
        $base = $teacher->classrooms()->findOrFail($data['classroom_id']);
        if ($base->section === null) {
            $base->update(['section' => trim($data['section'])]);
        } else {
            $request->validate(['section' => [Rule::unique('classrooms', 'section')->where('teacher_account_id', $teacher->id)]]);
            do {
                $code = strtoupper(Str::random(8));
            } while (Classroom::where('join_code', $code)->exists());
            Classroom::create(['teacher_account_id' => $teacher->id, 'name' => $base->name, 'grade' => $base->grade, 'period' => $base->period, 'section' => trim($data['section']), 'join_code' => $code]);
        }

        return back()->with('section_saved', 'Section saved. Share its join code with students in that section.');
    }

    public function updateTeacherQuiz(Request $request, TeacherQuiz $quiz)
    {
        abort_unless(session('teacher_id') && (int) $quiz->teacher_account_id === (int) session('teacher_id'), 403);

        return $this->storeTeacherQuiz($request, $quiz);
    }

    public function deleteTeacherQuiz(TeacherQuiz $quiz)
    {
        abort_unless(session('teacher_id') && (int) $quiz->teacher_account_id === (int) session('teacher_id'), 403);
        $quiz->delete();

        return response()->json(['message' => 'Quiz deleted. Saved student results have been retained.']);
    }

    public function storeTeacherQuiz(Request $request, ?TeacherQuiz $quiz = null)
    {
        $teacher = TeacherAccount::find(session('teacher_id'));
        $classroom = $teacher?->classrooms()->latest()->first();
        if (! $teacher || ! $classroom) {
            return response()->json(['message' => 'Create a classroom before publishing a quiz.'], 422);
        }

        $data = $request->validate([
            'classroom_id' => ['nullable', 'integer', Rule::exists('classrooms', 'id')->where('teacher_account_id', $teacher->id)],
            'classroom_ids' => ['sometimes', 'required', 'array', 'min:1', 'max:100'],
            'classroom_ids.*' => ['required', 'integer', 'distinct', Rule::exists('classrooms', 'id')->where('teacher_account_id', $teacher->id)],
            'opens_at' => ['nullable', 'date_format:Y-m-d\\TH:i'],
            'closes_at' => ['nullable', 'required_with:opens_at', 'date_format:Y-m-d\\TH:i', 'after:opens_at'],
            'title' => ['required', 'string', 'max:255'],
            'instructions' => ['nullable', 'string', 'max:5000'],
            'questions' => ['required', 'array', 'min:1', 'max:50'],
            'questions.*.type' => ['required', 'in:multiple_choice,identification,true_false,multiple_answer,code_output,fill_code,debugging,ordering'],
            'questions.*.prompt' => ['required', 'string', 'max:2000'],
            'questions.*.options' => ['present', 'array', 'max:8'],
            'questions.*.options.*' => ['required', 'string', 'max:500'],
            'questions.*.correct' => ['required', 'array', 'min:1', 'max:8'],
            'questions.*.correct.*' => ['required', 'string', 'max:500'],
            'status' => ['required', 'in:draft,published'],
            'xp' => ['required', 'integer', 'min:0', 'max:10000'],
            'time_limit' => ['required', 'integer', 'min:1', 'max:180'],
            'untimed' => ['sometimes', 'boolean'], 'question_seconds' => ['sometimes', 'integer', 'between:30,600'],
        ]);

        if (! empty($data['closes_at']) && empty($data['opens_at'])) {
            return response()->json(['message' => 'Choose a start date and time before setting the closing time.'], 422);
        }
        $classroom = isset($data['classroom_id']) ? $teacher->classrooms()->findOrFail($data['classroom_id']) : $classroom;
        $classroomIds = $data['classroom_ids'] ?? [$classroom->id];

        $questions = $data['questions'];
        foreach ($questions as $question) {
            $multiple = $question['type'] === 'multiple_answer';
            if (($multiple && count(array_unique($question['correct'])) < 2) || (! $multiple && $question['type'] !== 'ordering' && count($question['correct']) !== 1)) {
                return response()->json(['message' => 'Select two or more correct options for multiple-answer questions, and one answer for other types.'], 422);
            }
            if (! in_array($question['type'], ['identification', 'code_output', 'fill_code', 'debugging'])) {
                $options = $question['type'] === 'true_false' ? ['True', 'False'] : $question['options'];
                if (count(array_unique($options)) < 2 || array_diff($question['correct'], $options)) {
                    return response()->json(['message' => 'Provide distinct choices and select correct answers from those choices.'], 422);
                }
                if ($question['type'] === 'ordering' && (count($question['correct']) !== count($options) || count(array_unique($question['correct'])) !== count($options))) {
                    return response()->json(['message' => 'For ordering questions, provide every unique step in the correct order.'], 422);
                }
            }
        }
        if (! $questions) {
            return response()->json(['message' => 'Add at least one question.'], 422);
        }

        $quiz ??= new TeacherQuiz;
        DB::transaction(function () use ($quiz, $data, $teacher, $questions, $classroomIds) {
            $quiz->fill([
                'teacher_account_id' => $teacher->id,
                'classroom_id' => $classroomIds[0],
                'title' => $data['title'], 'instructions' => $data['instructions'] ?? null,
                'opens_at' => ! empty($data['opens_at']) ? Carbon::createFromFormat('Y-m-d\\TH:i', $data['opens_at'], config('learning.timezone'))->utc() : null,
                'closes_at' => ! empty($data['closes_at']) ? Carbon::createFromFormat('Y-m-d\\TH:i', $data['closes_at'], config('learning.timezone'))->utc() : null,
                'questions' => $questions, 'status' => $data['status'], 'xp' => $data['xp'], 'time_limit' => $data['time_limit'],
                'untimed' => (bool) ($data['untimed'] ?? false), 'question_seconds' => $data['question_seconds'] ?? 60,
            ]);
            $quiz->save();
            $quiz->classrooms()->sync($classroomIds);
            app(LearningNotifications::class)->publishedActivity($quiz);
        });

        return response()->json(['message' => 'Quiz saved.']);
    }

    public function logoutTeacher(Request $request)
    {
        $request->session()->forget(['teacher_id', 'teacher_session_version', 'teacher_authenticated_at', 'pending_staff_registration_id']);
        $request->session()->regenerateToken();

        return redirect()->route('teacher.login')->with('logged_out', 'You have been signed out safely.');
    }

    public function teacherChallenges(Request $request)
    {
        if ($request->filled('edit')) {
            return $this->teacherAssignments($request, 'challenge');
        }

        return app(CodingMatchController::class)->index();
    }

    public function teacherSectionChallenges(Request $request)
    {
        return $this->teacherAssignments($request, 'challenge');
    }

    public function teacherAssignments(Request $request, string $builderKind = 'assignment')
    {
        $teacher = TeacherAccount::find(session('teacher_id'));
        if (! $teacher) {
            return redirect()->route('teacher.login')->with('auth_error', 'Sign in with a registered teacher account to continue.');
        }

        $classroom = $teacher->classrooms()->latest()->first();
        $classrooms = $teacher->classrooms()->withCount('students')->orderBy('grade')->orderBy('section')->get();
        $query = $request->validate(['edit' => ['nullable', 'integer', 'min:1']]);
        $editingAssignment = ! empty($query['edit'])
            ? TeacherAssignment::with('classrooms')->where('teacher_account_id', $teacher->id)->where('kind', $builderKind)->findOrFail($query['edit'])
            : null;
        $assignmentEditor = $editingAssignment ? [
            'id' => $editingAssignment->id,
            'title' => $editingAssignment->title,
            'school_term' => $editingAssignment->school_term,
            'objective' => $editingAssignment->objective,
            'due_at' => $editingAssignment->due_at?->timezone(config('learning.timezone'))->format('Y-m-d\\TH:i'),
            'difficulty' => $editingAssignment->difficulty,
            'xp' => $editingAssignment->xp,
            'status' => $editingAssignment->status,
            'sample_output' => $editingAssignment->sample_output,
            'starter_code' => $editingAssignment->starter_code,
            'test_cases' => $editingAssignment->test_cases ?? [],
            'hints' => $editingAssignment->hints ?? [],
            'time_limit_minutes' => $editingAssignment->time_limit_minutes,
            'csta_standard' => $editingAssignment->csta_standard,
            'classroom_ids' => $editingAssignment->classrooms->pluck('id')->push($editingAssignment->classroom_id)->unique()->values(),
            'update_url' => route('teacher.assignments.update', $editingAssignment),
        ] : null;

        return view('assignments', [
            'teacher' => $teacher,
            'classroom' => $classroom,
            'studentsCount' => $classrooms->sum('students_count'),
            'classrooms' => $classrooms,
            'editingAssignment' => $editingAssignment,
            'assignmentEditor' => $assignmentEditor,
            'builderKind' => $builderKind,
            'assignmentIndexUrl' => route($builderKind === 'challenge' ? 'teacher.section-challenges' : 'teacher.assignments'),
            'assignments' => TeacherAssignment::with(['classrooms', 'classroom'])->where('teacher_account_id', $teacher->id)->where('kind', $builderKind)->latest()->get(),
        ]);
    }

    public function storeTeacherAssignment(Request $request)
    {
        $teacher = TeacherAccount::find(session('teacher_id'));
        $classroom = $teacher?->classrooms()->latest()->first();
        if (! $teacher || ! $classroom) {
            return response()->json(['message' => 'Create or select a classroom before saving an assignment.'], 422);
        }

        $data = $this->validatedAssignmentData($request, $teacher);
        $assignment = DB::transaction(function () use ($data, $teacher) {
            $assignment = TeacherAssignment::create([
                ...collect($data)->except('classroom_ids')->all(),
                'teacher_account_id' => $teacher->id,
                'classroom_id' => $data['classroom_ids'][0],
            ]);
            $assignment->classrooms()->sync($data['classroom_ids']);
            app(LearningNotifications::class)->publishedActivity($assignment);

            return $assignment;
        });

        $request->session()->flash('assignment_notice', $data['status'] === 'published' ? 'Assignment published to the selected sections.' : 'Assignment draft saved.');

        return response()->json(['message' => 'Assignment saved.', 'id' => $assignment->id]);
    }

    public function updateTeacherAssignment(Request $request, TeacherAssignment $assignment)
    {
        $teacher = TeacherAccount::find(session('teacher_id'));
        abort_unless($teacher && (int) $assignment->teacher_account_id === $teacher->id, 403);
        $data = $this->validatedAssignmentData($request, $teacher, $assignment->kind);
        if (isset($data['kind']) && $data['kind'] !== $assignment->kind) {
            throw ValidationException::withMessages(['kind' => 'Keep the activity in its original assignment or challenge category.']);
        }
        DB::transaction(function () use ($data, $teacher, $assignment) {
            $current = TeacherAssignment::where('teacher_account_id', $teacher->id)->whereKey($assignment->id)->lockForUpdate()->firstOrFail();
            $current->fill([
                ...collect($data)->except('classroom_ids')->all(),
                'classroom_id' => $data['classroom_ids'][0],
            ])->save();
            $current->classrooms()->sync($data['classroom_ids']);
            app(LearningNotifications::class)->publishedActivity($current);
            // Keep saved code and immutable submissions; use the new title for future work.
            CodeWorkspace::where('teacher_assignment_id', $current->id)->update(['title' => $current->title]);
        });
        $request->session()->flash('assignment_notice', 'Assignment updated. Changes are available to the selected sections.');

        return response()->json(['message' => 'Assignment updated.', 'id' => $assignment->id]);
    }

    public function deleteTeacherAssignment(Request $request, TeacherAssignment $assignment)
    {
        $teacher = TeacherAccount::find(session('teacher_id'));
        abort_unless($teacher && (int) $assignment->teacher_account_id === $teacher->id, 403);
        DB::transaction(function () use ($assignment, $teacher) {
            $current = TeacherAssignment::where('teacher_account_id', $teacher->id)->whereKey($assignment->id)->lockForUpdate()->firstOrFail();
            $current->delete();
        });
        $request->session()->flash('assignment_notice', 'Assignment deleted. Saved student work and previously earned XP have been kept.');

        return response()->json(['message' => 'Assignment deleted. Saved student work and previously earned XP have been kept.']);
    }

    private function validatedAssignmentData(Request $request, TeacherAccount $teacher, ?string $existingKind = null): array
    {
        $publishingChallenge = ($existingKind ?? $request->input('kind', 'assignment')) === 'challenge' && $request->input('status') === 'published';
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'kind' => ['nullable', 'in:assignment,challenge'],
            'school_term' => ['required', 'in:Term1,Term2,Term3'],
            'classroom_ids' => ['required', 'array', 'min:1', 'max:100'],
            'classroom_ids.*' => ['required', 'integer', 'distinct', Rule::exists('classrooms', 'id')->where('teacher_account_id', $teacher->id)],
            'objective' => [Rule::requiredIf($publishingChallenge), 'nullable', 'string', 'max:5000'],
            'due_at' => ['nullable', 'date_format:Y-m-d\\TH:i'],
            'difficulty' => ['required', 'in:Easy,Mid,Hard'],
            'xp' => ['required', 'integer', 'min:0', 'max:10000'],
            'status' => ['required', 'in:draft,published'],
            'starter_code' => ['nullable', 'string', 'max:20000'],
            'test_cases' => ['sometimes', 'nullable', 'array', 'max:8'],
            'test_cases.*.input' => ['nullable', 'string', 'max:5000'],
            'test_cases.*.output' => ['present', 'nullable', 'string', 'max:5000'],
            'test_cases.*.hidden' => ['required', 'boolean'],
            'test_cases.*.weight' => ['required', 'integer', 'between:1,100'],
            'hints' => ['sometimes', 'nullable', 'array', 'max:5'],
            'hints.*' => ['nullable', 'string', 'max:1000'],
            'sample_output' => [Rule::requiredIf($publishingChallenge), 'nullable', 'string', 'max:20000'],
            'time_limit_minutes' => ['nullable', 'integer', 'min:1', 'max:240'],
            'csta_standard' => ['nullable', 'string', 'max:100'],
        ]);

        if ($request->boolean('tests_included') && ! array_key_exists('test_cases', $data)) {
            $data['test_cases'] = [];
        }
        if ($request->boolean('tests_included') && ! array_key_exists('hints', $data)) {
            $data['hints'] = [];
        }
        if (array_key_exists('test_cases', $data)) {
            $data['test_cases'] = array_values(array_map(fn ($case) => ['input' => $case['input'] ?? '', 'output' => $case['output'] ?? '', 'hidden' => (bool) $case['hidden'], 'weight' => (int) $case['weight']], $data['test_cases'] ?? []));
        }
        if (array_key_exists('hints', $data)) {
            $data['hints'] = array_values(array_filter($data['hints'] ?? [], fn ($hint) => filled($hint)));
        }
        $data['due_at'] = ! empty($data['due_at'])
            ? Carbon::createFromFormat('Y-m-d\\TH:i', $data['due_at'], config('learning.timezone'))->utc()
            : null;

        return $data;
    }
}
