<?php

namespace App\Http\Controllers;

use App\Models\TeacherAccount;
use App\Services\ExamSpecification;
use App\Services\LearningCourses;
use App\Services\ReadyMadeActivities;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExamSpecificationController extends Controller
{
    private function data(string $course, string $term, ExamSpecification $specification, ReadyMadeActivities $library): array
    {
        $teacher = TeacherAccount::find(session('teacher_id'));
        abort_unless($teacher?->hasStaffAccess() && ! $teacher->isPrincipal(), 403);
        $exam = $specification->exam($course, $term, $library);
        $saved = DB::table('teacher_exam_specifications')->where('teacher_account_id', $teacher->id)->where('exam_key', $exam['key'])->first();
        $rows = $saved ? json_decode($saved->rows, true, 512, JSON_THROW_ON_ERROR) : $specification->defaults($exam);
        $totals = $specification->totals($rows);

        return compact('teacher', 'exam', 'course', 'term', 'saved', 'rows', 'totals');
    }

    public function edit(string $course, string $term, ExamSpecification $specification, ReadyMadeActivities $library): Response
    {
        return response()->view('teaching-library.tos', $this->data($course, $term, $specification, $library))->header('Cache-Control', 'private, no-store');
    }

    public function save(Request $request, string $course, string $term, ExamSpecification $specification, ReadyMadeActivities $library): RedirectResponse
    {
        $context = $this->data($course, $term, $specification, $library);
        $rules = ['revision' => ['required', 'integer', 'min:0'], 'rows' => ['required', 'array', 'list', 'size:'.count($context['rows'])],
            'rows.*' => ['required', 'array:topic,competency,hours,items'], 'rows.*.topic' => ['required', 'string', 'max:160'],
            'rows.*.competency' => ['required', 'string', 'max:500'], 'rows.*.hours' => ['required', 'numeric', 'between:0,200', 'decimal:0,2'],
            'rows.*.items' => ['required', 'array:'.implode(',', array_keys(ExamSpecification::LEVELS))]];
        foreach (ExamSpecification::LEVELS as $level => $label) {
            $rules['rows.*.items.'.$level] = ['present', 'nullable', 'string', 'max:200'];
        }
        $data = $request->validate($rules);
        $rows = $specification->normalized($data['rows'], count($context['exam']['quiz']['questions']));
        DB::transaction(function () use ($context, $data, $rows): void {
            TeacherAccount::whereKey($context['teacher']->id)->lockForUpdate()->firstOrFail();
            $query = DB::table('teacher_exam_specifications')->where('teacher_account_id', $context['teacher']->id)->where('exam_key', $context['exam']['key']);
            $saved = $query->first();
            abort_if((int) ($saved->revision ?? 0) !== (int) $data['revision'], 409, 'This TOS changed in another tab. Refresh first.');
            $changes = ['rows' => json_encode($rows, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), 'revision' => (int) $data['revision'] + 1, 'updated_at' => now()];
            if ($saved) {
                $query->update($changes);
            } else {
                DB::table('teacher_exam_specifications')->insert($changes + ['teacher_account_id' => $context['teacher']->id, 'exam_key' => $context['exam']['key'], 'created_at' => now()]);
            }
        });

        return redirect()->route('teacher.tos.edit', [$course, $term])->with('tos_notice', 'Table of Specifications saved. Confirm it matches your final examination and actual teaching time.');
    }

    public function download(string $course, string $term, ExamSpecification $specification, ReadyMadeActivities $library): StreamedResponse
    {
        $data = $this->data($course, $term, $specification, $library);
        $safe = static fn (string $value): string => preg_match('/^[\s]*[=+@-]/u', $value) ? "'".$value : $value;

        return response()->streamDownload(function () use ($data, $safe): void {
            $stream = fopen('php://output', 'w');
            fputcsv($stream, ['Table of Specifications', LearningCourses::title($data['course']), $data['term']], ',', '"', '');
            fputcsv($stream, ['Topic', 'Learning competency', 'Teaching hours', 'Time %', ...array_values(ExamSpecification::LEVELS), 'Items', 'Item %'], ',', '"', '');
            foreach ($data['rows'] as $row) {
                $items = array_sum(array_map('count', $row['items']));
                fputcsv($stream, [$safe($row['topic']), $safe($row['competency']), $row['hours'], $data['totals']['hours'] ? round(100 * $row['hours'] / $data['totals']['hours'], 2) : 0,
                    ...array_map(fn ($numbers) => implode(', ', $numbers), $row['items']), $items, round(100 * $items / max(1, $data['totals']['items']), 2)], ',', '"', '');
            }
            fputcsv($stream, ['Total', '', $data['totals']['hours'], $data['totals']['hours'] ? 100 : 0, ...array_values($data['totals']['levels']), $data['totals']['items'], 100], ',', '"', '');
            fclose($stream);
        }, $data['exam']['key'].'-tos.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store']);
    }
}
