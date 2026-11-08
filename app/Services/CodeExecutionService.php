<?php

namespace App\Services;

use App\Models\CodeRun;
use App\Models\CodeWorkspace;
use App\Models\Student;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CodeExecutionService
{
    public function cases(CodeWorkspace $workspace): array
    {
        return $workspace->assignment?->test_cases ?? config('compiler_tests.'.str_replace('practice:', '', $workspace->activity_key), []);
    }

    public function begin(CodeWorkspace $workspace, array $data): CodeRun
    {
        if (! app(Judge0Client::class)->configured()) {
            throw ValidationException::withMessages(['compiler' => 'Your school compiler has not been connected yet. Saving and teacher review remain available.']);
        }
        $cases = $data['mode'] === 'tests' ? $this->cases($workspace) : [['input' => $data['stdin'] ?? '', 'output' => null, 'hidden' => false, 'weight' => 0]];
        if (! $cases) {
            throw ValidationException::withMessages(['tests' => 'No automatic tests have been supplied for this activity. Use Run C++ with your own input.']);
        }
        $new = false;
        $run = DB::transaction(function () use ($workspace, $data, $cases, &$new) {
            Student::whereKey($workspace->student_id)->lockForUpdate()->firstOrFail();
            $current = CodeWorkspace::whereKey($workspace->id)->lockForUpdate()->firstOrFail();
            app(CodeLabTimer::class)->enforce($current);
            if ((int) $data['revision'] !== (int) $current->revision) {
                abort(409, 'Save the current code before running it.');
            }
            $existing = CodeRun::where('student_id', $current->student_id)->where('request_key', $data['request_key'])->first();
            if ($existing) {
                abort_unless((int) $existing->code_workspace_id === $current->id && (int) $existing->revision === $current->revision, 409);

                return $existing;
            }
            if (CodeRun::where('student_id', $current->student_id)->whereIn('status', ['preparing', 'running'])->where('created_at', '>', now()->subSeconds(90))->exists()) {
                throw ValidationException::withMessages(['run' => 'Wait for your current program run to finish before starting another.']);
            }
            $new = true;

            return CodeRun::create(['code_workspace_id' => $current->id, 'student_id' => $current->student_id,
                'teacher_account_id' => $current->assignment?->teacher_account_id ?? $current->student->classroom->teacher_account_id,
                'revision' => $current->revision, 'request_key' => $data['request_key'], 'mode' => $data['mode'], 'code' => $current->code,
                'cases' => $cases, 'possible' => array_sum(array_column($cases, 'weight'))]);
        });
        if ($new) {
            try {
                $tokens = app(Judge0Client::class)->submit($run->code, $run->cases);
                $run->update(['tokens' => $tokens, 'status' => 'running']);
            } catch (\Throwable $error) {
                $run->update(['status' => 'unavailable', 'error' => 'The compiler is unavailable or declined this run. Your saved code is unchanged. Try again after checking the school compiler connection.']);
            }
        }

        return $run;
    }

    public function refresh(CodeRun $run): CodeRun
    {
        if (! in_array($run->status, ['preparing', 'running'])) {
            return $run;
        }
        if ($run->created_at->lt(now()->subSeconds(90))) {
            $run->update(['status' => 'unavailable', 'error' => 'The school compiler did not finish in time. Please retry this run.']);

            return $run;
        }
        if (! $run->tokens || ($run->last_polled_at && $run->last_polled_at->gt(now()->subSeconds(2)))) {
            return $run;
        }
        $run->update(['last_polled_at' => now()]);
        try {
            $items = app(Judge0Client::class)->results($run->tokens);
        } catch (\Throwable $error) {
            return $run;
        }
        $byToken = collect($items)->keyBy('token');
        $results = [];
        foreach ($run->tokens as $index => $token) {
            $item = $byToken->get($token);
            if (! $item || ! isset($item['status']['id']) || (int) $item['status']['id'] < 3) {
                return $run;
            }
            $case = $run->cases[$index];
            $decode = function ($value) {
                $decoded = base64_decode($value ?? '', true);

                return mb_convert_encoding(substr($decoded === false ? '' : $decoded, 0, config('compiler.output_limit')), 'UTF-8', 'UTF-8');
            };
            $stdout = $decode($item['stdout'] ?? null);
            $id = (int) $item['status']['id'];
            $status = $id === 3 ? 'ran' : ($id === 5 ? 'timeout' : ($id === 6 ? 'compile_error' : ($id >= 7 && $id <= 12 ? 'runtime_error' : 'unavailable')));
            $passed = $run->mode === 'tests' ? ($id === 3 && $this->normalize($stdout) === $this->normalize($case['output'])) : null;
            if ($passed !== null && $id === 3) {
                $status = $passed ? 'passed' : 'wrong_answer';
            }
            $results[] = ['status' => $status, 'passed' => $passed, 'stdout' => $stdout, 'stderr' => $decode($item['stderr'] ?? null),
                'compile_output' => $decode($item['compile_output'] ?? null), 'seconds' => $item['time'] ?? null, 'memory_kb' => $item['memory'] ?? null,
                'earned' => $passed ? (int) $case['weight'] : 0];
        }
        DB::transaction(function () use ($run, $results) {
            $current = CodeRun::whereKey($run->id)->lockForUpdate()->firstOrFail();
            if ($current->status !== 'running') {
                return;
            }
            $infrastructureFailure = collect($results)->contains('status', 'unavailable');
            $current->update(['results' => $results, 'earned' => array_sum(array_column($results, 'earned')), 'status' => $infrastructureFailure ? 'unavailable' : 'completed']);
        });
        $run->refresh();
        if ($run->status === 'completed' && $run->mode === 'tests' && $run->possible > 0 && $run->earned === $run->possible && ! $run->workspace->teacher_assignment_id) {
            app(LearningRewards::class)->award($run->student_id, 'practice:'.$run->workspace->activity_key, config('compiler.practice_xp'), ['type' => 'practice', 'id' => $run->workspace->id, 'title' => $run->workspace->title, 'reason' => 'Code Lab practice tests passed', 'details' => ['All practice tests passed. One reward per practice activity.']]);
            app(AchievementService::class)->sync(Student::findOrFail($run->student_id));
        }

        return $run;
    }

    private function normalize(string $text): string
    {
        return rtrim(str_replace(["\r\n", "\r"], "\n", $text));
    }

    public function present(CodeRun $run, bool $teacher = false): array
    {
        $items = [];
        foreach ($run->cases as $index => $case) {
            $hidden = (bool) ($case['hidden'] ?? false);
            $result = $run->results[$index] ?? null;
            $item = ['label' => ($hidden ? 'Hidden test ' : 'Test ').($index + 1), 'hidden' => $hidden,
                'status' => $result['status'] ?? 'waiting', 'weight' => $case['weight'], 'earned' => $result['earned'] ?? 0,
                'feedback' => $result ? $this->feedback($result['status']) : 'Waiting for the compiler.'];
            if (! $hidden || $teacher) {
                $item += ['input' => $case['input'], 'expected' => $case['output'], 'stdout' => $result['stdout'] ?? '',
                    'diagnostics' => ($result['compile_output'] ?? '') ?: ($result['stderr'] ?? ''), 'seconds' => $result['seconds'] ?? null, 'memory_kb' => $result['memory_kb'] ?? null];
            }
            $items[] = $item;
        }

        return ['id' => $run->id, 'revision' => $run->revision, 'mode' => $run->mode, 'status' => $run->status, 'earned' => $run->earned,
            'possible' => $run->possible, 'percentage' => $run->possible ? round(100 * $run->earned / $run->possible, 1) : null,
            'message' => $run->error, 'cases' => $items, 'created_at' => $run->created_at->toIso8601String(),
            'status_url' => route('student.code-runs.show', $run)];
    }

    private function feedback(string $status): string
    {
        return match ($status) {
            'passed' => 'This test passed. Try explaining why your algorithm works.',
            'wrong_answer' => 'The output does not match yet. Check your calculations, spacing and boundary values.',
            'compile_error' => 'C++ could not compile. Check semicolons, declarations and << / >> operators. Read the compiler details for the line number.',
            'timeout' => 'The program ran too long. Check that loops stop and that you supplied all required input.',
            'runtime_error' => 'The program stopped while running. Check array indexes, division by zero and initialized variables.',
            'ran' => 'Program finished. Compare the output with your prediction and the task objective.',
            default => 'The compiler could not complete this test. This is not an academic score.',
        };
    }
}
