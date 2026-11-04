<?php

$legacy = [
    'greeting' => ['difficulty' => 'Easy', 'topic' => 'Output'],
    'variables' => ['difficulty' => 'Easy', 'topic' => 'Variables & operators'],
    'branch' => ['difficulty' => 'Mid', 'topic' => 'Conditions'],
    'loops' => ['difficulty' => 'Mid', 'topic' => 'Loops'],
    'functions' => ['difficulty' => 'Mid', 'topic' => 'Functions'],
    'sentinel' => ['difficulty' => 'Hard', 'topic' => 'Input & sentinel loops'],
];

$templates = require __DIR__.'/ready_made_activities.php';
$topics = (require __DIR__.'/cpp_curriculum.php')['lessons'];
$current = [];
foreach ($topics as $key => $lesson) {
    $current['topic-'.$key] = ['difficulty' => $templates[$key]['difficulty'], 'topic' => $lesson['topic'], 'lesson_key' => $key];
}

return [...$current, ...array_map(static fn (array $challenge): array => $challenge + ['legacy' => true], $legacy)];
