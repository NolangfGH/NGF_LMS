<?php

namespace App\Http\Controllers;

use App\Models\CodeRun;
use App\Models\CodeWorkspace;
use App\Models\Student;
use App\Services\CodeExecutionService;
use App\Services\SecondChanceService;
use Illuminate\Http\Request;

class CodeExecutionController extends Controller
{
    public function store(Request $request, CodeWorkspace $workspace, CodeExecutionService $service)
    {
        $student = Student::with('classroom')->find(session('student_id'));
        abort_unless($student && (int) $workspace->student_id === $student->id, 403);
        if ($workspace->teacher_assignment_id) {
            $assignment = $workspace->assignment;
            abort_unless($assignment && $assignment->status === 'published' && (int) $assignment->teacher_account_id === (int) $student->classroom->teacher_account_id && $assignment->isAssignedTo($student->classroom_id), 403);
            abort_if(app(SecondChanceService::class)->assignmentScheduleClosed($assignment, $student->id), 403, 'This assignment is closed.');
        }
        $data = $request->validate(['revision' => ['required', 'integer', 'min:1'], 'mode' => ['required', 'in:run,tests'],
            'stdin' => ['nullable', 'string', 'max:5000'], 'request_key' => ['required', 'uuid']]);
        $run = $service->begin($workspace, $data);

        return response()->json($service->present($run), 202)->header('Cache-Control', 'private, no-store');
    }

    public function show(CodeRun $run, CodeExecutionService $service)
    {
        $student = Student::find(session('student_id'));
        abort_unless($student && (int) $run->student_id === $student->id, 403);

        return response()->json($service->present($service->refresh($run)))->header('Cache-Control', 'private, no-store');
    }
}
