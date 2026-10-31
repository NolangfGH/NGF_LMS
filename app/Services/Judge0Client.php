<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class Judge0Client
{
    public function configured(): bool
    {
        $url = config('compiler.url');

        return is_string($url) && filled(config('compiler.token')) && preg_match('~^https?://[^\s]+$~i', $url) === 1;
    }

    private function client()
    {
        if (! $this->configured()) {
            throw new RuntimeException('The school compiler service has not been connected yet. Your draft is safe.');
        }

        return Http::baseUrl(rtrim(config('compiler.url'), '/'))->acceptJson()->asJson()->connectTimeout(3)->timeout(12)
            ->withOptions(['allow_redirects' => false])->withHeaders(config('compiler.token') ? ['X-Auth-Token' => config('compiler.token')] : []);
    }

    public function submit(string $code, array $cases): array
    {
        $submissions = array_map(fn ($case) => [
            'source_code' => base64_encode($code), 'stdin' => base64_encode($case['input']),
            'language_id' => config('compiler.language_id'), 'compiler_options' => '-std=c++17 -Wall -Wextra',
            'cpu_time_limit' => config('compiler.cpu_seconds'), 'wall_time_limit' => config('compiler.wall_seconds'),
            'memory_limit' => config('compiler.memory_kb'), 'stack_limit' => 64000,
            'max_processes_and_or_threads' => 16, 'max_file_size' => 64, 'number_of_runs' => 1,
            'enable_network' => false, 'redirect_stderr_to_stdout' => false,
        ], $cases);
        $response = $this->client()->post('/submissions/batch?base64_encoded=true', ['submissions' => $submissions]);
        if (! $response->successful() || ! is_array($response->json()) || count($response->json()) !== count($cases)) {
            throw new RuntimeException('The compiler could not accept this run. Try again shortly.');
        }
        $tokens = array_column($response->json(), 'token');
        if (count($tokens) !== count($cases) || collect($tokens)->contains(fn ($token) => ! is_string($token) || ! preg_match('/^[a-zA-Z0-9-]{10,80}$/', $token))) {
            throw new RuntimeException('The compiler returned an incomplete run. Please try again.');
        }

        return $tokens;
    }

    public function results(array $tokens): array
    {
        $response = $this->client()->get('/submissions/batch', ['tokens' => implode(',', $tokens), 'base64_encoded' => 'true', 'fields' => 'token,stdout,stderr,compile_output,status,time,memory']);
        $items = $response->json('submissions');
        if (! $response->successful() || ! is_array($items)) {
            throw new RuntimeException('The compiler is temporarily unavailable. This run can be checked again.');
        }

        return $items;
    }

    public function health(): array
    {
        $response = $this->client()->get('/languages');
        if (! $response->successful() || ! collect($response->json())->contains(fn ($language) => (int) ($language['id'] ?? 0) === config('compiler.language_id') && str_contains($language['name'] ?? '', 'C++'))) {
            throw new RuntimeException('Set JUDGE0_CPP_LANGUAGE_ID to an available C++ language on this compiler.');
        }

        return ['connected' => true, 'language_id' => config('compiler.language_id')];
    }
}
