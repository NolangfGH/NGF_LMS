<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Models\TeacherAccount;
use App\Services\DepEdSubjectGrading;
use App\Services\LearningCourses;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SubjectGradingController extends Controller
{
    private function teacher(): TeacherAccount
    {
        $teacher = TeacherAccount::find(session('teacher_id'));
        abort_unless($teacher?->hasStaffAccess() && ! $teacher->isPrincipal(), 403);

        return $teacher;
    }

    private function context(Request $request): array
    {
        $teacher = $this->teacher();
        $rooms = $teacher->classrooms()->whereNull('archived_at')->orderBy('grade')->orderBy('section')->get();
        $data = $request->validate(['classroom' => ['nullable', 'integer', 'min:1'], 'course' => ['nullable', Rule::in(LearningCourses::keys())],
            'school_year' => ['nullable', 'string', 'regex:/^20\d{2}-20\d{2}$/'], 'term' => ['nullable', Rule::in(['Term1', 'Term2', 'Term3'])]]);
        $room = isset($data['classroom']) ? $rooms->firstWhere('id', (int) $data['classroom']) : $rooms->first();
        abort_if(isset($data['classroom']) && ! $room, 404);
        $course = $data['course'] ?? LearningCourses::recommendedForGrade($room?->grade);
        $year = $data['school_year'] ?? DepEdSubjectGrading::TRANSITION;
        $first = (int) substr($year, 0, 4);
        if ($first < 2026 || (int) substr($year, 5, 4) !== $first + 1) {
            throw ValidationException::withMessages(['school_year' => 'Use a consecutive school year starting in 2026 or later. Older records remain in the existing gradebook.']);
        }
        $term = $data['term'] ?? 'Term1';
        $query = ['classroom' => $room?->id, 'course' => $course, 'school_year' => $year, 'term' => $term];
        $sheet = $room ? DB::table('subject_gradebooks')->where('teacher_account_id', $teacher->id)->where('classroom_id', $room->id)->where('course', $course)->where('school_year', $year)->where('term', $term)->first() : null;

        return compact('teacher', 'rooms', 'room', 'course', 'year', 'term', 'query', 'sheet');
    }

    private function decode(object $sheet): array
    {
        return [json_decode($sheet->definition, true, 512, JSON_THROW_ON_ERROR), json_decode($sheet->entries, true, 512, JSON_THROW_ON_ERROR)];
    }

    public function index(Request $request, DepEdSubjectGrading $grading): Response
    {
        $data = $this->context($request);
        [$definition,$entries] = $data['sheet'] ? $this->decode($data['sheet']) : [$grading->template($data['course']), []];
        $students = $data['room'] ? Student::where('classroom_id', $data['room']->id)->orderBy('surname')->orderBy('first_name')->paginate(20)->withQueryString() : null;
        $results = [];
        foreach ($students ?? [] as $student) {
            $results[$student->id] = $grading->calculate($definition, $entries[$student->id]['scores'] ?? [], $data['year']);
        }

        return response()->view('grading.subject-index', $data + compact('definition', 'entries', 'students', 'results'))->header('Cache-Control', 'private, no-store');
    }

    public function create(Request $request, DepEdSubjectGrading $grading): RedirectResponse
    {
        $data = $this->context($request);
        abort_unless($data['room'], 422);
        DB::transaction(function () use ($data, $grading): void {
            TeacherAccount::whereKey($data['teacher']->id)->lockForUpdate()->firstOrFail();
            $lookup = DB::table('subject_gradebooks')->where('classroom_id', $data['room']->id)->where('course', $data['course'])->where('school_year', $data['year'])->where('term', $data['term']);
            $existing = $lookup->first();
            abort_if($existing && (int) $existing->teacher_account_id !== $data['teacher']->id, 409, 'This class record belongs to its previous teacher. Ask the school administrator to review the assignment before creating a replacement.');
            if (! $existing) {
                DB::table('subject_gradebooks')->insert(['teacher_account_id' => $data['teacher']->id, 'classroom_id' => $data['room']->id, 'course' => $data['course'], 'school_year' => $data['year'], 'term' => $data['term'], 'definition' => json_encode($grading->template($data['course']), JSON_THROW_ON_ERROR), 'entries' => '{}', 'revision' => 1, 'created_at' => now(), 'updated_at' => now()]);
            }
        });

        return redirect()->route('teacher.subject-grading', $data['query'])->with('grading_notice', 'The editable subject gradebook is ready. Review the assessment titles and highest scores before entering results.');
    }

    private function editRecord(Request $request, int $sheet, callable $change): RedirectResponse
    {
        $teacher = $this->teacher();
        $revision = $request->validate(['revision' => ['required', 'integer', 'min:1']])['revision'];
        $record = DB::transaction(function () use ($teacher, $revision, $sheet, $change): object {
            $query = DB::table('subject_gradebooks')->where('id', $sheet)->where('teacher_account_id', $teacher->id)->whereIn('classroom_id', $teacher->classrooms()->whereNull('archived_at')->select('id'));
            $record = $query->lockForUpdate()->first();
            abort_unless($record, 404);
            abort_if((int) $record->revision !== (int) $revision, 409, 'This gradebook changed. Refresh before making another edit.');
            [$definition,$entries] = $this->decode($record);
            [$definition,$entries] = $change($record, $definition, $entries);
            $query->update(['definition' => json_encode($definition, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), 'entries' => json_encode($entries, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), 'revision' => $record->revision + 1, 'updated_at' => now()]);

            return $record;
        });

        return redirect()->route('teacher.subject-grading', ['classroom' => $record->classroom_id, 'course' => $record->course, 'school_year' => $record->school_year, 'term' => $record->term, 'page' => $request->integer('page', 1)])->with('grading_notice', 'Subject gradebook saved.');
    }

    public function settings(Request $request, int $sheet, DepEdSubjectGrading $grading): RedirectResponse
    {
        return $this->editRecord($request, $sheet, function ($record, $definition, $entries) use ($request, $grading): array {
            $rules = ['slots' => ['required', 'array:'.implode(',', array_keys($definition['slots'])), 'size:'.count($definition['slots'])]];
            foreach ($definition['slots'] as $key => $slot) {
                $rules['slots.'.$key] = ['required', 'array:label,max'];
                $rules['slots.'.$key.'.label'] = ['required', 'string', 'max:120'];
                $rules['slots.'.$key.'.max'] = ['required', 'numeric', 'between:0,10000', 'decimal:0,2'];
            }
            if (DepEdSubjectGrading::schoolDefined($record->course)) {
                $rules['weights'] = ['required', 'array:WW,PT,EX'];
                foreach (['WW', 'PT', 'EX'] as $group) {
                    $rules['weights.'.$group] = ['required', 'integer', 'between:1,98'];
                }
            }
            $data = $request->validate($rules);
            $weights = isset($data['weights']) ? array_map('intval', $data['weights']) : $definition['weights'];
            if (array_sum($weights) !== 100) {
                throw ValidationException::withMessages(['weights' => 'Component weights must total exactly 100%.']);
            }
            $updated = $grading->template($record->course, $weights);
            foreach ($data['slots'] as $key => $slot) {
                $maximum = (float) $slot['max'];
                foreach ($entries as $entry) {
                    $score = $entry['scores'][$key] ?? null;
                    if ($score !== null && ($maximum <= 0 || $score > $maximum)) {
                        throw ValidationException::withMessages(['slots.'.$key.'.max' => 'Clear or correct the existing scores before reducing or deactivating this assessment.']);
                    }
                }
                $updated['slots'][$key] = array_replace($updated['slots'][$key], ['label' => $slot['label'], 'max' => $maximum]);
            }
            foreach ($updated['groups'] as $key => $group) {
                if ($group['weight'] > 0 && array_sum(array_column(array_filter($updated['slots'], fn ($slot) => $slot['group'] === $key), 'max')) <= 0) {
                    throw ValidationException::withMessages(['slots' => 'Every weighted component must have an active assessment.']);
                }
            }
            if ($updated !== $definition) {
                foreach ($entries as &$entry) {
                    $entry['reviewed'] = false;
                }
                unset($entry);
            }

            return [$updated, $entries];
        });
    }

    public function scores(Request $request, int $sheet, Student $student, DepEdSubjectGrading $grading): RedirectResponse
    {
        return $this->editRecord($request, $sheet, function ($record, $definition, $entries) use ($request, $student, $grading): array {
            abort_unless((int) $student->classroom_id === (int) $record->classroom_id, 404);
            $active = array_filter($definition['slots'], fn ($slot) => $slot['max'] > 0);
            $rules = ['scores' => ['required', 'array:'.implode(',', array_keys($active))], 'remark' => ['nullable', 'string', 'max:500'], 'reviewed' => ['nullable', 'boolean']];
            foreach ($active as $key => $slot) {
                $rules['scores.'.$key] = ['present', 'nullable', 'numeric', 'between:0,'.$slot['max'], 'decimal:0,2'];
            }
            $data = $request->validate($rules);
            $scores = array_map(fn ($score) => $score === null ? null : (float) $score, $data['scores']);
            $grade = $grading->calculate($definition, $scores, $record->school_year);
            if ($request->boolean('reviewed') && ! $grade['complete']) {
                throw ValidationException::withMessages(['scores' => 'Complete all active scores before marking the record reviewed.']);
            }
            $entries[$student->id] = ['scores' => $scores, 'remark' => $data['remark'] ?? '', 'reviewed' => $request->boolean('reviewed')];

            return [$definition, $entries];
        });
    }

    public function card(Request $request, DepEdSubjectGrading $grading): Response
    {
        $data = $this->context($request);
        $studentId = $request->validate(['student' => ['nullable', 'integer', 'min:1']])['student'] ?? null;
        $student = $studentId ? Student::whereKey($studentId)->where('classroom_id', $data['room']?->id)->firstOrFail() : null;
        $terms = [];
        foreach (['Term1', 'Term2', 'Term3'] as $term) {
            $sheet = $data['room'] ? DB::table('subject_gradebooks')->where('teacher_account_id', $data['teacher']->id)->where('classroom_id', $data['room']->id)->where('course', $data['course'])->where('school_year', $data['year'])->where('term', $term)->first() : null;
            [$definition,$entries] = $sheet ? $this->decode($sheet) : [$grading->template($data['course']), []];
            $entry = $entries[$student?->id] ?? [];
            $result = $grading->calculate($definition, $entry['scores'] ?? [], $data['year']);
            $terms[$term] = ['grade' => $result['grade'], 'descriptor' => $result['descriptor'], 'parts' => $result['parts'], 'remark' => $entry['remark'] ?? '', 'reviewed' => ($entry['reviewed'] ?? false) && $result['complete']];
        }
        $final = collect($terms)->every(fn ($term) => $term['reviewed'] && $term['grade'] !== null) ? (int) round(collect($terms)->avg('grade')) : null;

        return response()->view('grading.subject-card', $data + compact('student', 'terms', 'final'))->header('Cache-Control', 'private, no-store');
    }

    public function export(Request $request, DepEdSubjectGrading $grading): StreamedResponse
    {
        $data = $this->context($request);
        [$definition,$entries] = $data['sheet'] ? $this->decode($data['sheet']) : [$grading->template($data['course']), []];
        $slots = array_filter($definition['slots'], fn ($slot) => $slot['max'] > 0);
        $students = $data['room'] ? Student::where('classroom_id', $data['room']->id)->orderBy('surname')->orderBy('first_name')->get() : collect();
        $safe = static fn ($value) => is_string($value) && preg_match('/^[\s]*[=+@-]/u', $value) ? "'".$value : $value;

        return response()->streamDownload(function () use ($data, $definition, $entries, $students, $slots, $safe, $grading): void {
            $stream = fopen('php://output', 'w');
            fputcsv($stream, ['Subject class record', LearningCourses::title($data['course']), $data['year'], $data['term']], ',', '"', '');
            fputcsv($stream, ['Student ID', 'Learner', ...array_map(fn ($slot) => $safe(($definition['groups'][$slot['group']]['part'] === 'ma' ? 'Music & Arts · ' : ($definition['groups'][$slot['group']]['part'] === 'ph' ? 'PE & Health · ' : '')).$slot['label'].' / '.$slot['max']), array_values($slots)), 'Initial grade', 'Term grade', 'Descriptor', 'Teacher reviewed', 'Remarks'], ',', '"', '');
            foreach ($students as $student) {
                $entry = $entries[$student->id] ?? [];
                $result = $grading->calculate($definition, $entry['scores'] ?? [], $data['year']);
                fputcsv($stream, array_map($safe, [$student->student_id, trim(implode(' ', array_filter([$student->first_name, $student->middle_name, $student->surname]))) ?: $student->nickname, ...array_map(fn ($key) => $entry['scores'][$key] ?? null,array_keys($slots)), $result['initial'], $result['grade'], $result['descriptor'], ($entry['reviewed'] ?? false) && $result['complete'] ? 'Yes' : 'No', $entry['remark'] ?? '']), ',', '"', '');
            }
            fclose($stream);
        }, $data['course'].'-'.$data['term'].'-class-record.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store']);
    }
}
