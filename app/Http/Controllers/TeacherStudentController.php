<?php

namespace App\Http\Controllers;

use App\Models\Classroom;
use App\Models\CodingMatch;
use App\Models\Student;
use App\Models\TeacherAccount;
use App\Services\CodingMatchService;
use App\Services\StudentRegistrationApproval;
use App\Services\StudentRosterService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TeacherStudentController extends Controller
{
    private function teacher(): TeacherAccount
    {
        return TeacherAccount::find(session('teacher_id')) ?? abort(403, 'Sign in with your teacher account.');
    }

    private function owned(int $id, bool $lock = false): Student
    {
        $teacher = $this->teacher();

        return Student::withTrashed()->with('classroom')->withCount('achievements')->whereHas('classroom', fn ($q) => $q->where('teacher_account_id', $teacher->id))
            ->when($lock, fn ($q) => $q->lockForUpdate())->findOrFail($id);
    }

    private function filters(Request $request, TeacherAccount $teacher): array
    {
        $data = $request->validate(['q' => ['nullable', 'string', 'max:100'], 'classroom' => ['nullable', 'integer', Rule::exists('classrooms', 'id')->where('teacher_account_id', $teacher->id)],
            'status' => ['nullable', Rule::in(['active', 'deleted'])], 'progress' => ['nullable', Rule::in(['all', 'support', 'not_started', 'progress', 'confirmed'])],
            'sort' => ['nullable', Rule::in(['name', 'xp', 'newest'])], 'per_page' => ['nullable', Rule::in([25, 50, 100])], 'page' => ['nullable', 'integer', 'min:1']]);

        return ['q' => trim($data['q'] ?? ''), 'classroom' => $data['classroom'] ?? null, 'status' => $data['status'] ?? 'active',
            'progress' => $data['progress'] ?? 'all', 'sort' => $data['sort'] ?? 'name', 'per_page' => (int) ($data['per_page'] ?? 100)];
    }

    public function index(Request $request, StudentRosterService $service)
    {
        if (! session('teacher_id')) {
            return redirect()->route('teacher.login');
        }
        $teacher = $this->teacher();

        return response()->view('teacher-students', ['teacher' => $teacher, ...$service->build($teacher, $this->filters($request, $teacher))])->header('Cache-Control', 'private, no-store');
    }

    public function status(Request $request, StudentRosterService $service)
    {
        $teacher = $this->teacher();
        $data = $service->build($teacher, $this->filters($request, $teacher));

        return response()->json(['updated_at' => $data['updatedAt'], 'fragments' => [
            'metrics' => view('student-roster-metrics', $data)->render(), 'sections' => view('student-roster-sections', $data)->render(),
            'table' => view('student-roster-table', $data)->render(),
        ]])->header('Cache-Control', 'private, no-store');
    }

    public function create()
    {
        if (! session('teacher_id')) {
            return redirect()->route('teacher.login');
        }
        $teacher = $this->teacher();

        return response()->view('teacher-student-form', ['teacher' => $teacher, 'record' => null,
            'classrooms' => $teacher->classrooms()->orderBy('grade')->orderBy('section')->get()])->header('Cache-Control', 'private, no-store');
    }

    public function edit(int $student)
    {
        if (! session('teacher_id')) {
            return redirect()->route('teacher.login');
        }
        $teacher = $this->teacher();
        $record = $this->owned($student);
        abort_if($record->trashed(), 404);

        return response()->view('teacher-student-form', ['teacher' => $teacher, 'record' => $record,
            'classrooms' => $teacher->classrooms()->orderBy('grade')->orderBy('section')->get()])->header('Cache-Control', 'private, no-store');
    }

    public function show(int $student, StudentRosterService $service)
    {
        if (! session('teacher_id')) {
            return redirect()->route('teacher.login');
        }
        $teacher = $this->teacher();
        $record = $this->owned($student);

        return response()->view('teacher-student', ['teacher' => $teacher, 'record' => $record, 'evidence' => $service->evidence($teacher, $record)])->header('Cache-Control', 'private, no-store');
    }

    private function profile(Request $request, TeacherAccount $teacher, ?Student $student = null): array
    {
        if (is_string($request->input('student_id'))) {
            $request->merge(['student_id' => strtoupper(trim($request->input('student_id')))]);
        }

        return $request->validate([
            'first_name' => ['required', 'string', 'max:80'], 'middle_name' => ['nullable', 'string', 'max:80'], 'surname' => ['required', 'string', 'max:80'],
            'nickname' => ['required', 'string', 'min:3', 'max:40'],
            'classroom_id' => ['required', 'integer', Rule::exists('classrooms', 'id')->where('teacher_account_id', $teacher->id)],
            'student_id' => ['required', 'string', 'min:3', 'max:40', Rule::unique('students', 'student_id')->ignore($student?->id)],
            'password' => [$student ? 'nullable' : 'required', 'required_with:password_confirmation', 'string', 'min:8', 'max:128', 'confirmed'],
            ...($student ? ['revision' => ['required', 'integer', 'min:1']] : []),
        ], ['student_id.unique' => 'This Student ID is already reserved by an active or deleted account. Use a different ID or restore the existing account.']);
    }

    private function revision(Student $student, int $version): void
    {
        if ($student->roster_revision !== $version) {
            throw ValidationException::withMessages(['revision' => 'This account was changed in another tab. Reload its current details before making changes.']);
        }
    }

    public function store(Request $request)
    {
        $teacher = $this->teacher();
        $data = $this->profile($request, $teacher);
        $student = DB::transaction(function () use ($data, $teacher) {
            $room = Classroom::where('teacher_account_id', $teacher->id)->lockForUpdate()->findOrFail($data['classroom_id']);

            $student = Student::create([...collect($data)->except('classroom_id')->all(), 'classroom_id' => $room->id, 'grade_level' => $room->grade,
                'section' => $room->section ?: $room->period, 'avatar' => 'Astro Bot', 'xp' => Student::REGISTRATION_XP, 'level' => 1]);
            app(StudentRegistrationApproval::class)->teacherEnrolled($student, $teacher);

            return $student;
        }, 3);

        return redirect()->route('teacher.students.show', $student->id)->with('student_notice', 'Student created with 100 welcome XP. Share the Student ID and password privately; the student can change their password in Settings.');
    }

    public function update(Request $request, int $student)
    {
        $teacher = $this->teacher();
        $record = $this->owned($student);
        abort_if($record->trashed(), 404);
        $data = $this->profile($request, $teacher, $record);
        DB::transaction(function () use ($student, $data, $teacher) {
            $room = Classroom::where('teacher_account_id', $teacher->id)->lockForUpdate()->findOrFail($data['classroom_id']);
            $current = $this->owned($student, true);
            abort_if($current->trashed(), 404);
            $this->revision($current, $data['revision']);
            $changedAccess = $current->student_id !== $data['student_id'] || $current->classroom_id !== $room->id || filled($data['password'] ?? null);
            $fields = collect($data)->except(['revision', 'classroom_id', 'password'])->all();
            if (filled($data['password'] ?? null)) {
                $fields += ['password' => $data['password'], 'password_changed_at' => now()];
            }
            $current->forceFill([...$fields, 'classroom_id' => $room->id, 'grade_level' => $room->grade, 'section' => $room->section ?: $room->period,
                'session_version' => $current->session_version + ($changedAccess ? 1 : 0), 'roster_revision' => $current->roster_revision + 1])->save();
        }, 3);

        return redirect()->route('teacher.students.show', $student)->with('student_notice', 'Student details updated. XP and learning history were retained. Changes to login details or section require the student to sign in again.');
    }

    public function destroy(Request $request, int $student, CodingMatchService $matches)
    {
        $teacher = $this->teacher();
        $this->owned($student);
        $data = $request->validate(['revision' => ['required', 'integer', 'min:1'], 'confirm_student_id' => ['required', 'string'], 'confirmed' => ['accepted']]);
        DB::transaction(function () use ($student, $data, $teacher, $matches) {
            // Match awards lock the match before the student; use that order here too.
            $open = CodingMatch::where('teacher_account_id', $teacher->id)->whereIn('status', ['inviting', 'active'])
                ->whereHas('participants', fn ($q) => $q->where('student_id', $student))->orderBy('id')->lockForUpdate()->get();
            $current = $this->owned($student, true);
            $this->revision($current, $data['revision']);
            if (strtoupper(trim($data['confirm_student_id'])) !== $current->student_id) {
                throw ValidationException::withMessages(['confirm_student_id' => 'Type this student’s current Student ID to confirm deletion.']);
            }
            if (! $current->trashed()) {
                foreach ($open as $match) {
                    $matches->cancel($match, $teacher, 'A participant account was removed from the active roster.');
                }
                $current->forceFill(['session_version' => $current->session_version + 1, 'roster_revision' => $current->roster_revision + 1])->save();
                $current->delete();
            }
        }, 3);

        return redirect()->route('teacher.students')->with('student_notice', 'Student moved to Deleted students. Login is blocked and learning history is retained. Open Deleted students to restore the account.');
    }

    public function restore(Request $request, int $student)
    {
        $this->teacher();
        $this->owned($student);
        $data = $request->validate(['revision' => ['required', 'integer', 'min:1']]);
        DB::transaction(function () use ($student, $data) {
            $current = $this->owned($student, true);
            $this->revision($current, $data['revision']);
            abort_unless($current->trashed(), 422, 'This student is already active.');
            $current->forceFill(['session_version' => $current->session_version + 1, 'roster_revision' => $current->roster_revision + 1]);
            $current->restore();
        }, 3);

        return redirect()->route('teacher.students.show', $student)->with('student_notice', 'Student restored with the same XP, password, and saved learning work. Previous sessions and cancelled coding matches remain closed.');
    }

    public function export(Request $request, StudentRosterService $service)
    {
        $teacher = $this->teacher();
        $filters = $this->filters($request, $teacher);
        $data = $service->build($teacher, $filters, true);
        $rows = $data['students'];

        return response()->streamDownload(function () use ($rows, $data) {
            $file = fopen('php://output', 'w');
            fwrite($file, "\xEF\xBB\xBF");
            fputcsv($file, ['Student ID', 'First name', 'Middle name', 'Surname', 'Nickname', 'Grade', 'Section', 'Status', 'XP', 'Level', 'Lessons completed', 'Quiz average (%)', 'Coding solutions confirmed', 'Joined'], ',', '"', '');
            foreach ($rows as $student) {
                $e = $data['evidence']->get($student->id);
                $values = [$student->student_id, $student->first_name, $student->middle_name, $student->surname, $student->nickname, $student->classroom->grade,
                    $student->classroom->section ?: $student->classroom->name, $student->trashed() ? 'Deleted' : 'Active', $student->xp, $student->level,
                    $e['lessons'] ?? '', $e['quiz_average'] ?? '', $e['confirmed'] ?? '', $student->created_at->toDateString()];
                fputcsv($file, array_map(fn ($value) => preg_match('/^[\s]*[=+@\-]/u', (string) $value) ? "'".$value : (string) $value, $values), ',', '"', '');
            }
            fclose($file);
        }, 'codequest-students.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}
