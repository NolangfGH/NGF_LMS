<?php

return [
    'url' => env('JUDGE0_URL'),
    'token' => env('JUDGE0_AUTH_TOKEN'),
    'language_id' => (int) env('JUDGE0_CPP_LANGUAGE_ID', 54),
    'cpu_seconds' => 2,
    'wall_seconds' => 5,
    'memory_kb' => 128000,
    'output_limit' => 12000,
    'practice_xp' => 30,
    'lesson_xp' => 25,
];
