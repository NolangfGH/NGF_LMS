<?php

$test = fn ($input, $output, $hidden = false, $weight = 1) => compact('input', 'output', 'hidden', 'weight');
$topics = (require __DIR__.'/cpp_curriculum.php')['lessons'];
$templates = require __DIR__.'/ready_made_activities.php';
$challengeTests = [];
foreach ($topics as $key => $lesson) {
    $challengeTests['topic-'.$key] = $templates[$key]['challenge']['test_cases'];
}

return [
    ...$challengeTests,
    ...array_combine(
        array_map(static fn (string $key): string => 'cpp-'.$key, array_keys(require __DIR__.'/cpp_activity_templates.php')),
        array_map(static fn (array $template): array => $template['assignment']['test_cases'], require __DIR__.'/cpp_activity_templates.php'),
    ),
    'variables' => [$test('', '12')],
    'input_total' => [$test('5', '7'), $test('0', '2', true), $test('-2', '0', true)],
    'loops' => [$test('', "5\n4\n3\n2\n1\nLaunch!")],
    'greeting' => [$test('', "Hello, CodeQuest!\nI am learning C++.")],
    'branch' => [$test('', 'Hot')],
    'functions' => [$test('', '25')],
    'sentinel' => [$test('4 6 -1', '10'), $test('-1', '0', true), $test('1 2 3 -1', '6', true)],
    'arrays' => [$test('', '15')],
    'strings' => [$test('Ada', 'Hello, Ada!'), $test('Coder', 'Hello, Coder!', true)],
    'operators' => [$test('7 2', "9\n5\n14\n3\n1"), $test('9 4', "13\n5\n36\n2\n1", true)],
    'debugging' => [$test('', '6')],
    'mini_project' => [$test('3 5 7', '15'), $test('0 0 0', '0', true)],
];
