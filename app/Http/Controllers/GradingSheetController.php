<?php

namespace App\Http\Controllers;

use App\Models\GradingAssessment;
use App\Models\GradingNote;
use App\Models\GradingScore;
use App\Models\GradingSheet;
use App\Models\Student;
use App\Models\TeacherAccount;
use App\Services\GradingSheetService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class GradingSheetController extends Controller
{
    private function teacher(): TeacherAccount
    {
        $teacher = TeacherAccount::find(session('teacher_id'));
        abort_unless($teacher, 403);

        return $teacher;
    }

    private function context(Request $request): array
    {
        $teacher = $this->teacher();
        $rooms = $teacher->classrooms()->withCount('students')->orderBy('grade')->orderBy('section')->get();
        $filters = $request->validate([
            'classroom' => ['nullable', 'integer', 'min:1'], 'term' => ['nullable', Rule::in(array_keys(config('grading.terms')))],
            'school_year' => ['nullable', 'string', 'regex:/^20\d{2}-20\d{2}$/'],
            'search' => ['nullable', 'string', 'max:100'], 'student' => ['nullable', 'integer', 'min:1'],
            'print' => ['nullable', 'boolean'], 'mask' => ['nullable', 'boolean'],
        ]);
        $room = isset($filters['classroom']) ? $rooms->firstWhere('id', (int) $filters['classroom']) : $rooms->first();
        abort_if(isset($filters['classroom']) && ! $room, 403);
        $year = (int) now(config('learning.timezone'))->format('Y');
        $schoolYear = $filters['school_year'] ?? $year.'-'.($year + 1);
        if ((int) substr($schoolYear, 5) !== (int) substr($schoolYear, 0, 4) + 1) {
            throw ValidationException::withMessages(['school_year' => 'Use consecutive years, such as 2026-2027.']);
        }
        $term = $filters['term'] ?? 'Term1';
        $sheet = $room ? GradingSheet::where('teacher_account_id', $teacher->id)->where('classroom_id', $room->id)
            ->where('school_year', $schoolYear)->where('term', $term)->first() : null;
        $studentId = isset($filters['student']) ? (int) $filters['student'] : null;
        if ($studentId) {
            abort_unless($room && Student::where('classroom_id', $room->id)->whereKey($studentId)->exists(), 404);
        }
        $query = array_filter(['classroom' => $room?->id, 'school_year' => $schoolYear, 'term' => $term,
            'search' => trim($filters['search'] ?? ''), 'student' => $studentId, 'mask' => $request->boolean('mask') ? 1 : null], fn ($value) => $value !== null && $value !== '');

        return compact('teacher', 'rooms', 'room', 'schoolYear', 'term', 'sheet', 'studentId', 'query');
    }

    private function data(Request $request, GradingSheetService $service): array
    {
        $data = $this->context($request);
        extract($data);
        $book = $service->build($sheet, $teacher->id, $room?->id ?? 0);
        $search = mb_strtolower($query['search'] ?? '');
        $rows = $book['rows']->filter(fn ($row) => $search === '' || str_contains(mb_strtolower($row['name'].' '.$row['nickname'].' '.$row['student_id']), $search));
        $selectedStudent = $studentId ? $book['rows']->firstWhere('id', $studentId) : null;
        $trajectory = [];
        foreach (config('grading.terms') as $key => $label) {
            $other = $room ? GradingSheet::where('teacher_account_id', $teacher->id)->where('classroom_id', $room->id)->where('school_year', $schoolYear)->where('term', $key)->first() : null;
            $termBook = $key === $term ? $book : ($other ? $service->build($other, $teacher->id, $room->id) : null);
            $trajectory[$key] = ['label' => $label, 'average' => $termBook['metrics']['average'] ?? null,
                'student' => $studentId ? ($termBook['rows'] ?? collect())->firstWhere('id', $studentId) : null];
        }

        return $data + compact('book', 'rows', 'selectedStudent', 'trajectory') + [
            'sources' => $room ? $service->sources($teacher->id, $room->id, $term) : collect(),
            'generatedAt' => now(config('learning.timezone')), 'pageTitle' => 'Grading Sheet',
        ];
    }

    public function index(Request $request, GradingSheetService $service)
    {
        if (! TeacherAccount::find(session('teacher_id'))) {
            return redirect()->route('teacher.login');
        }

        return response()->view('grading.index', $this->data($request, $service))->header('Cache-Control', 'private, no-store');
    }

    public function status(Request $request, GradingSheetService $service)
    {
        $data = $this->data($request, $service);

        return response()->json(['html' => view('grading.workspace', $data)->render(), 'revision' => $data['sheet']?->revision])
            ->header('Cache-Control', 'private, no-store');
    }

    public function store(Request $request)
    {
        $data = $this->context($request);
        abort_unless($data['room'], 422, 'Create a teaching section in Settings first.');
        $sheet = DB::transaction(function () use ($data) {
            TeacherAccount::whereKey($data['teacher']->id)->lockForUpdate()->firstOrFail();

            return GradingSheet::firstOrCreate(['classroom_id' => $data['room']->id, 'school_year' => $data['schoolYear'], 'term' => $data['term']],
                ['teacher_account_id' => $data['teacher']->id, 'weights' => config('grading.weights')]);
        });

        return $this->back($sheet, 'Grading sheet is ready. Confirm your weights, then add or link assessments.');
    }

    private function change(Request $request, GradingSheet $sheet, callable $callback)
    {
        $teacher = $this->teacher();
        abort_unless((int) $sheet->teacher_account_id === $teacher->id && $teacher->classrooms()->whereKey($sheet->classroom_id)->exists(), 404);
        $revision = $request->validate(['revision' => ['required', 'integer', 'min:1']])['revision'];

        return DB::transaction(function () use ($sheet, $revision, $callback) {
            $current = GradingSheet::whereKey($sheet->id)->lockForUpdate()->firstOrFail();
            abort_if($current->revision !== (int) $revision, 409, 'This sheet changed in another tab. Refresh before saving to avoid overwriting newer grades.');
            $result = $callback($current);
            $current->increment('revision');

            return $result;
        });
    }

    private function back(GradingSheet $sheet, string $message, ?int $studentId = null)
    {
        return redirect()->route('teacher.grading', ['classroom' => $sheet->classroom_id, 'school_year' => $sheet->school_year, 'term' => $sheet->term, 'student' => $studentId])
            ->with('grading_notice', $message);
    }

    public function weights(Request $request, GradingSheet $sheet)
    {
        $this->change($request, $sheet, function ($sheet) use ($request) {
            if(is_array($request->input('weights'))) $request->merge(['weights'=>['CH'=>0,...$request->input('weights')]]);
            $rules = ['weights' => ['required', 'array:WW,PT,CP,CH,QA'], 'passing_score' => ['required', 'numeric', 'between:0,100', 'decimal:0,2']];
            foreach (array_keys(config('grading.categories')) as $key) {
                $rules['weights.'.$key] = ['required', 'integer', 'between:0,100'];
            }
            $data = $request->validate($rules);
            if (array_sum($data['weights']) !== 100) {
                throw ValidationException::withMessages(['weights' => 'Category weights must add up to exactly 100%.']);
            }
            $sheet->update(['weights' => array_map('intval', $data['weights']), 'passing_score' => $data['passing_score']]);
        });

        return $this->back($sheet, 'Weights and proficiency threshold saved.');
    }

    public function assessment(Request $request, GradingSheet $sheet, GradingSheetService $service, ?GradingAssessment $assessment = null)
    {
        $this->change($request, $sheet, function ($sheet) use ($request, $service, $assessment) {
            abort_if($assessment && (int) $assessment->grading_sheet_id !== $sheet->id, 404);
            $data = $request->validate([
                'title' => ['required', 'string', 'max:180'], 'category' => ['required', Rule::in(array_keys(config('grading.categories')))],
                'max_score' => ['required', 'numeric', 'between:0.01,100000', 'decimal:0,2'],
                'due_date' => ['nullable', 'date_format:Y-m-d'], 'competency' => ['nullable', 'string', 'max:200'],
                'instructions' => ['nullable', 'string', 'max:3000'],
            ]);
            if ($assessment) {
                if ($assessment->scores()->where('score', '>', $data['max_score'])->exists()) {
                    throw ValidationException::withMessages(['max_score' => 'A saved score exceeds this maximum. Correct the score before lowering the maximum.']);
                }
                $assessment->update($data);
            } else {
                if ($sheet->assessments()->count() >= 100) {
                    throw ValidationException::withMessages(['title' => 'A sheet supports up to 100 assessments. Use a separate term or school year.']);
                }
                $source = $request->validate(['source' => ['nullable', 'string', 'max:50']])['source'] ?? '';
                $data['source_type'] = 'manual';
                if ($source !== '') {
                    if (! $service->sources($sheet->teacher_account_id, $sheet->classroom_id, $sheet->term)->contains('key', $source)) {
                        throw ValidationException::withMessages(['source' => 'Choose a published quiz or coding task assigned to this section and term.']);
                    }
                    [$type, $id] = explode(':', $source);
                    if ($sheet->assessments()->where('source_type', $type)->where('source_id', $id)->exists()) {
                        throw ValidationException::withMessages(['source' => 'This activity is already linked to the sheet.']);
                    }
                    $data += ['source_id' => (int) $id];
                    $data['source_type'] = $type;
                }
                $sheet->assessments()->create($data);
            }
        });

        return $this->back($sheet, $assessment ? 'Assessment updated.' : 'Assessment added. Linked results update automatically.');
    }

    public function destroyAssessment(Request $request, GradingSheet $sheet, GradingAssessment $assessment)
    {
        $this->change($request, $sheet, function ($sheet) use ($request, $assessment) {
            abort_unless((int) $assessment->grading_sheet_id === $sheet->id, 404);
            $request->validate(['confirm_delete' => ['accepted']]);
            $assessment->delete();
        });

        return $this->back($sheet, 'Assessment removed from this sheet. Original quiz and Code Lab records are preserved.');
    }

    private function saveCell(GradingSheet $sheet, GradingAssessment $assessment, int $studentId, array $input): void
    {
        $data = Validator::make($input, ['status' => ['required', Rule::in(['scored', 'excused', 'pending', 'source'])],
            'score' => ['nullable', 'numeric', 'between:0,'.$assessment->max_score, 'decimal:0,2']])->validate();
        if ($data['status'] === 'source' && $assessment->source_type === 'manual') {
            throw ValidationException::withMessages(['score' => 'This assessment has no linked source. Enter a manual score or leave it pending.']);
        }
        $key = ['grading_assessment_id' => $assessment->id, 'student_id' => $studentId];
        if (in_array($data['status'], ['pending', 'source']) || ($data['status'] === 'scored' && ($data['score'] ?? null) === null)) {
            if ($assessment->source_type !== 'manual' && $data['status'] !== 'source') {
                throw ValidationException::withMessages(['score' => 'Choose Use linked result to clear an override, or enter a manual score.']);
            }
            GradingScore::where($key)->delete();
        } else {
            GradingScore::updateOrCreate($key, ['status' => $data['status'], 'score' => $data['status'] === 'excused' ? null : $data['score'], 'scored_by' => $sheet->teacher_account_id]);
        }
    }

    public function scores(Request $request, GradingSheet $sheet, Student $student)
    {
        $this->change($request, $sheet, function ($sheet) use ($request, $student) {
            abort_unless((int) $student->classroom_id === (int) $sheet->classroom_id, 404);
            $data = $request->validate(['scores' => ['nullable', 'array', 'max:100'], 'note' => ['nullable', 'string', 'max:3000']]);
            $assessments = $sheet->assessments()->get()->keyBy('id');
            foreach ($data['scores'] ?? [] as $id => $input) {
                if (! isset($assessments[$id]) || ! is_array($input)) {
                    throw ValidationException::withMessages(['scores' => 'An assessment does not belong to this sheet.']);
                }
                $this->saveCell($sheet, $assessments[$id], $student->id, $input);
            }
            if (array_key_exists('note', $data)) {
                GradingNote::updateOrCreate(['grading_sheet_id' => $sheet->id, 'student_id' => $student->id], ['body' => $data['note']]);
            }
        });

        return $this->back($sheet, 'Scores and private teacher note saved.', $student->id);
    }

    public function import(Request $request, GradingSheet $sheet)
    {
        $this->change($request, $sheet, function ($sheet) use ($request) {
            $request->validate(['file' => ['required', 'file', 'max:2048']]);
            $handle = fopen($request->file('file')->getRealPath(), 'rb');
            try {
                $header = fgetcsv($handle, null, ',', '"', '');
                if ($header) {
                    $header[0] = ltrim($header[0], "\xEF\xBB\xBF");
                }
                if ($header !== ['student_id', 'assessment_id', 'score', 'status']) {
                    throw ValidationException::withMessages(['file' => 'Use the score CSV template: student_id,assessment_id,score,status.']);
                }
                $students = Student::where('classroom_id', $sheet->classroom_id)->get()->keyBy('student_id');
                $assessments = $sheet->assessments()->get()->keyBy('id');
                $seen = [];
                $line = 1;
                while (($row = fgetcsv($handle, null, ',', '"', '')) !== false) {
                    $line++;
                    if ($row === [null]) {
                        continue;
                    }
                    if ($line > 2001 || count($row) !== 4) {
                        throw ValidationException::withMessages(['file' => 'Invalid CSV row '.$line.'. Import up to 2,000 score rows at a time.']);
                    }
                    [$studentCode, $id, $score, $status] = array_map('trim', $row);
                    // Our template prefixes spreadsheet-sensitive identifiers with an apostrophe.
                    $studentCode = ltrim($studentCode, "'");
                    $student = $students[$studentCode] ?? null;
                    $assessment = ctype_digit($id) ? ($assessments[(int) $id] ?? null) : null;
                    $key = $studentCode.':'.$id;
                    if (! $student || ! $assessment || isset($seen[$key])) {
                        throw ValidationException::withMessages(['file' => 'Row '.$line.' has an unknown student/assessment or a duplicate score. No rows were imported.']);
                    }
                    $seen[$key] = true;
                    try {
                        $this->saveCell($sheet, $assessment, $student->id, ['score' => $score === '' ? null : $score, 'status' => $status]);
                    } catch (ValidationException $error) {
                        throw ValidationException::withMessages(['file' => 'Row '.$line.': '.collect($error->errors())->flatten()->implode(' ')]);
                    }
                }
                if ($seen === []) {
                    throw ValidationException::withMessages(['file' => 'The CSV contains no score rows.']);
                }
            } finally {
                fclose($handle);
            }
        });

        return $this->back($sheet, 'CSV scores imported successfully.');
    }

    public function export(Request $request, GradingSheetService $service)
    {
        $data = $this->data($request, $service);
        $request->validate(['format' => ['nullable', Rule::in(['summary', 'scores'])]]);
        $template = $request->input('format') === 'scores';
        $filename = 'grading-'.$data['schoolYear'].'-'.$data['term'].($template ? '-scores' : '-summary').'.csv';

        return response()->streamDownload(function () use ($data, $template) {
            $out = fopen('php://output', 'wb');
            fwrite($out, "\xEF\xBB\xBF");
            $write = function (array $row) use ($out) {
                $row = array_map(fn ($value) => is_string($value) && preg_match('/^[\s]*[=+@\-\t\r]/u', $value) ? "'".$value : $value, $row);
                fputcsv($out, $row, ',', '"', '');
            };
            if ($template) {
                $write(['student_id', 'assessment_id', 'score', 'status']);
                foreach ($data['rows'] as $row) {
                    foreach ($data['book']['assessments'] as $assessment) {
                        $cell = $row['cells'][$assessment->id];
                        $write(["'".$row['student_id'], $assessment->id, $cell['mode'] === 'source' ? '' : $cell['score'], $cell['mode'] === 'scored' && $cell['score'] === null ? 'pending' : $cell['mode']]);
                    }
                }
            } else {
                $write(['School year', 'Term', 'Section', 'Student ID', 'Coder Cadet', ...array_keys(config('grading.categories')), 'Weighted percentage', 'Status', 'Recorded / total', 'XP (separate from grade)']);
                foreach ($data['rows'] as $row) {
                    $masked = (bool) ($data['query']['mask'] ?? false);
                    $write([$data['schoolYear'], $data['term'], $data['room']?->section ?: $data['room']?->name,
                        $masked ? '' : "'".$row['student_id'], $masked ? $row['alias'] : $row['name'],
                        ...array_map(fn ($category) => $category['percentage'], $row['categories']), $row['average'],
                        $row['complete'] ? 'Complete' : ($row['average'] === null ? 'No scores' : 'Provisional'), $row['resolved'].'/'.$row['total'], $row['xp']]);
                }
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store']);
    }
}
