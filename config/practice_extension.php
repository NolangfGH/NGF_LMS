<?php

$starter = "#include <iostream>\n#include <string>\n\nint main() {\n    // Implement the learning objective here.\n    return 0;\n}\n";
$mission = fn ($title, $objective, $sample_output, $hint) => compact('title', 'objective', 'sample_output', 'hint') + ['code' => $starter];

return [
    'operators' => $mission('Arithmetic toolkit', 'Read two integers a and b, with b greater than zero. Print a+b, a-b, a*b, integer a/b and a%b on five separate lines. For 7 2, match the sample.', "9\n5\n14\n3\n1", 'Read both integers. Parentheses can make expressions clearer. Print only the requested answers.'),
    'arrays' => $mission('Array explorer', 'Store the values 1, 2, 3, 4, 5 in an array. Use a loop to calculate and print their sum.', '15', 'Initialize total to zero. Valid array indexes are zero to four.'),
    'strings' => $mission('Personal greeting', 'Read one word into std::string and print Hello, followed by a comma and space, the word, then an exclamation mark. For input Ada, print Hello, Ada!', 'Hello, Ada!', 'Include <string>. Use cin >> name for this one-word input.'),
    'debugging' => $mission('Repair the boundary', 'Write a loop that adds every integer from 1 through 3, including 3, and print the total. Explain why the final value must be included.', '6', 'Use i <= 3 when starting i at 1; trace the total after each iteration.'),
    'mini_project' => $mission('Team supplies project', 'Read three non-negative integer supply counts and print only their total. For input 3 5 7, print 15.', '15', 'Plan the inputs and output. Use clear variable names and test 0 0 0 as a boundary case.'),
];
