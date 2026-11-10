<?php

$topics = (require __DIR__.'/cpp_curriculum.php')['lessons'];
$templates = require __DIR__.'/ready_made_activities.php';
$challengeActivities = [];
foreach ($topics as $key => $lesson) {
    $task = $templates[$key]['challenge'];
    $challengeActivities['topic-'.$key] = ['title' => 'Challenge: '.$task['title'], 'objective' => $task['objective'],
        'sample_output' => $task['sample_output'], 'hint' => implode(' ', $task['hints']), 'code' => $task['starter_code']];
}

return [
    'assignment_starter' => "#include <iostream>\n\nint main() {\n    // Write your solution to the assignment here.\n    return 0;\n}\n",
    'activities' => [
        ...(require __DIR__.'/arduino_curriculum.php')['practice'],
        ...$challengeActivities,
        ...array_combine(
            array_map(static fn (string $key): string => 'cpp-'.$key, array_keys(require __DIR__.'/cpp_activity_templates.php')),
            array_map(static fn (array $template): array => [
                'title' => 'Practice: '.$template['assignment']['title'],
                'objective' => $template['assignment']['objective'],
                'sample_output' => $template['assignment']['sample_output'],
                'hint' => implode(' ', $template['assignment']['hints']),
                'code' => $template['assignment']['starter_code'],
            ], require __DIR__.'/cpp_activity_templates.php'),
        ),
        ...(require __DIR__.'/practice_extension.php'),
        ...(require __DIR__.'/challenge_activities.php'),
        'input_total' => ['sample_output' => '7', 'title' => 'Practice: Read and add', 'objective' => 'Read one integer from the user, add 2, and print only the result. Input 5 should produce 7. Also test 0 and another positive integer.', 'hint' => 'Declare an int variable, read it with std::cin >>, then output the expression with std::cout <<. Use valid integer input.', 'code' => "#include <iostream>\n\nint main() {\n    // Read an integer, add 2, and print the result.\n    return 0;\n}\n"],
        'hello' => ['title' => 'Mission 1: Introduce your coder cadet', 'objective' => 'Print your nickname and one learning goal on separate lines. Predict the output before asking your teacher to review.', 'hint' => 'Use std::cout and \\n to start a new line. Put text inside double quotes.', 'code' => "#include <iostream>\n\nint main() {\n    // Print your introduction here.\n    return 0;\n}\n"],
        'variables' => ['sample_output' => '12', 'title' => 'Mission 2: Supplies calculator', 'objective' => 'Create integer variables for 3 teams with 4 devices each. Calculate and print the total number of devices.', 'hint' => 'Declare int variables, multiply with *, then print the result using std::cout. Try changing the number of teams.', 'code' => "#include <iostream>\n\nint main() {\n    int teams = 3;\n    int devicesPerTeam = 4;\n    // Calculate and print the total.\n    return 0;\n}\n"],
        'loops' => ['sample_output' => "5\n4\n3\n2\n1\nLaunch!", 'title' => 'Mission 3: Launch countdown', 'objective' => 'Use a loop to print a countdown from 5 to 1, then print Launch! Explain what stops the loop.', 'hint' => 'A for loop has an initial value, a condition, and an update. Check that the counter moves toward the stopping condition.', 'code' => "#include <iostream>\n\nint main() {\n    // Write your countdown loop here.\n    return 0;\n}\n"],
    ]];
