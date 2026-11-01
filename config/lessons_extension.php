<?php

$make = function ($title, $topic, $practice, $objective, $sections, $code, $output, $checks, $mistake) {
    return compact('title', 'topic', 'practice', 'objective', 'sections', 'code', 'output', 'checks', 'mistake') + ['minutes' => 12, 'icon' => 'terminal', 'version' => 1];
};
$check = fn ($prompt, $options, $correct, $why) => compact('prompt', 'options', 'correct', 'why');
$section = fn ($title, $text) => compact('title', 'text');

return [
    'introduction' => $make('Your first steps in programming', 'Introduction', 'greeting', 'Explain what an algorithm and a C++ program do, then trace a simple program.', [
        $section('Think before typing', 'An algorithm is a clear sequence of steps that solves a problem. For a greeting: choose the message, display it, then stop. Write these steps before writing C++.'),
        $section('From source to output', 'You write source code in main.cpp. A compiler checks C++ and translates it into a program. Running the program produces output. A compiler error is feedback, not failure.'),
        $section('Your safe workspace', 'Use Code Lab to save drafts, run school compiler tests and explain your reasoning. Never type passwords or personal information into a program. Keep trying a small change at a time.'),
    ], "#include <iostream>\nint main() {\n    std::cout << \"Hello, CodeQuest!\";\n    return 0;\n}", 'Hello, CodeQuest!', [
        $check('What is an algorithm?', ['A sequence of problem-solving steps', 'A password', 'Only a computer error'], 0, 'Algorithms describe ordered steps before any particular programming language.'),
        $check('What checks C++ source before it runs?', ['A leaderboard', 'A compiler', 'An avatar'], 1, 'The compiler translates source code and reports language errors.'),
        $check('What is a useful first response to an error?', ['Give up', 'Share your password', 'Read the message and check the indicated line'], 2, 'Use the error message as evidence, make one change and test again.'),
        $check('Which file contains the C++ source you write in Code Lab?', ['main.cpp', 'A leaderboard rank', 'A student password'], 0, 'main.cpp contains the source code that the compiler checks and translates.'),
        $check('Which information should you keep out of a practice program?', ['A greeting', 'Your password and private personal information', 'A small test number'], 1, 'Use safe practice examples and keep private information out of programs and submissions.'),
    ], 'Compilation and running are separate steps. Read the first compiler error before changing many lines.'),
    'operators' => $make('Calculate with C++ operators', 'Operators', 'operators', 'Apply arithmetic operators and explain integer division and remainder.', [
        $section('Combine values', '+ adds, - subtracts and * multiplies. Parentheses make your intended order clear: (3 + 2) * 4 is 20.'),
        $section('Integer division', 'With integer operands, 7 / 2 is 3: the fractional part is discarded. 7 % 2 is 1, the remainder. Never divide by zero.'),
        $section('Check your assumptions', 'Predict each result for 7 and 2, then 9 and 4. The task guarantees the second number is positive, so division and remainder have a valid divisor.'),
    ], "#include <iostream>\nint main() {\n    int a = 7, b = 2;\n    std::cout << a + b << std::endl << a - b << std::endl\n              << a * b << std::endl << a / b << std::endl << a % b;\n}", "9\n5\n14\n3\n1", [
        $check('What is 7 / 2 with integers?', ['3.5', '3', '1'], 1, 'Integer division discards the fractional part.'),
        $check('What is 7 % 2?', ['1', '3', '14'], 0, 'The remainder after dividing 7 into groups of 2 is 1.'),
        $check('What is (3 + 2) * 4?', ['11', '14', '20'], 2, 'Parentheses are evaluated before multiplication.'),
        $check('What is 9 - 4?', ['13', '5', '36'], 1, 'The subtraction operator removes 4 from 9, giving 5.'),
        $check('Which divisor must you avoid for integer division?', ['0', '1', '2'], 0, 'Integer division by zero is invalid. Check that the divisor is nonzero before dividing.'),
    ], 'Do not use = when you mean ==. Check the divisor before dividing.'),
    'arrays' => $make('Store a collection in an array', 'Arrays', 'arrays', 'Access valid array indexes and use a loop to total five values.', [
        $section('One name, several values', 'int values[5] = {1,2,3,4,5}; creates five integers. The first is values[0], not values[1].'),
        $section('Stay inside the bounds', 'Valid indexes for five items are 0 to 4. Accessing values[5] is outside the array and can make your program behave unpredictably.'),
        $section('Visit every element', 'Start total at zero. A loop beginning at 0 and continuing while i < 5 adds each element once. Trace the index and total on paper.'),
    ], "#include <iostream>\nint main() {\n    int values[5] = {1,2,3,4,5};\n    int total = 0;\n    for (int i = 0; i < 5; ++i) total += values[i];\n    std::cout << total;\n}", '15', [
        $check('What is the first index?', ['1', '0', '5'], 1, 'C++ arrays use zero-based indexing.'),
        $check('Which index is invalid for five values?', ['0', '4', '5'], 2, 'The last valid index is one less than the size.'),
        $check('What is the sum of 1,2,3,4,5?', ['15', '5', '10'], 0, 'Each of the five elements is added once.'),
        $check('What is values[2] for int values[5] = {1,2,3,4,5};?', ['2', '5', '3'], 2, 'Indexing starts at zero, so index 2 refers to the third element, whose value is 3.'),
        $check('Which condition safely visits all five elements when i starts at 0?', ['i <= 5', 'i < 5', 'i > 5'], 1, 'i < 5 permits indexes 0 through 4 and stops before the invalid index 5.'),
    ], 'Use i < size, not i <= size, when starting an index at zero.'),
    'strings' => $make('Work with text using strings', 'Strings', 'strings', 'Store a word in std::string and combine it with a greeting.', [
        $section('Text needs a suitable type', 'std::string stores text. Include <string> and use double quotes for a string literal, such as "Ada".'),
        $section('Read and display', 'std::cin >> name reads one word. std::getline can read a whole line, including spaces. This practice task deliberately uses one word.'),
        $section('Combine readable output', 'Use cout << "Hello, " << name << "!";. Punctuation and spaces are part of the output. Test more than one name without using private information.'),
    ], "#include <iostream>\n#include <string>\nint main() {\n    std::string name = \"Ada\";\n    std::cout << \"Hello, \" << name << \"!\";\n}", 'Hello, Ada!', [
        $check('Which type stores text?', ['int', 'std::string', 'bool'], 1, 'std::string represents a sequence of characters.'),
        $check('What does cin >> name read?', ['One whitespace-delimited word', 'Every line in a file', 'Only integers'], 0, 'For a string, the extraction operator stops at whitespace.'),
        $check('Which is a string literal?', ["'A'", '42', '"Ada"'], 2, 'Double quotes delimit a string literal; single quotes represent a character.'),
        $check('Which input operation can read a name containing spaces as a whole line?', ['std::getline(std::cin, name)', 'std::cout << name', 'std::cin >> name'], 0, 'getline reads a whole line, including spaces, instead of stopping at the first whitespace.'),
        $check('What prints if name is "Sam" in the greeting example?', ['Hello,Sam', 'Hello, Sam!', 'Sam only'], 1, 'The output joins the literal Hello, including its space, the name Sam, and the final exclamation mark.'),
    ], 'Do not mix up single character quotes and double string quotes.'),
    'debugging' => $make('Find and fix a small bug', 'Debugging', 'debugging', 'Distinguish compiler, runtime and logic errors, and trace a repair.', [
        $section('Three useful error categories', 'A missing semicolon can prevent compilation. An invalid operation can stop a running program. A logic error can produce the wrong answer even when the program runs.'),
        $section('Trace before guessing', 'To sum 1,2,3, begin at 1, continue while i <= 3, and add i each time. Trace totals 1,3,6. If your loop used i < 3, it would miss the last value.'),
        $section('Make the fix testable', 'Write the expected result first. Change one thing, run again, and compare actual and expected output. Explain why the fix addresses the cause.'),
    ], "#include <iostream>\nint main() {\n    int total = 0;\n    for (int i = 1; i <= 3; ++i) total += i;\n    std::cout << total;\n}", '6', [
        $check('A program runs but prints the wrong sum. What might it contain?', ['Only a compiler error', 'A logic error', 'No error'], 1, 'Running successfully does not prove the logic is correct.'),
        $check('Which condition includes i equal to 3?', ['i < 3', 'i == 0', 'i <= 3'], 2, 'Less-than-or-equal includes the upper boundary.'),
        $check('What should you do after a repair?', ['Run normal and boundary tests', 'Remove the explanation', 'Assume all programs are correct'], 0, 'Tests provide evidence that the changed program behaves as intended.'),
        $check('A missing semicolon is most likely reported at which stage?', ['After a leaderboard update', 'Only in a teacher comment', 'During compilation'], 2, 'A missing required semicolon is a syntax error that the compiler can report before the program runs.'),
        $check('What sum would the example produce with i < 3 instead of i <= 3?', ['6', '3', '0'], 1, 'The loop adds 1 and 2 but misses 3, producing 3 instead of 6. This is an off-by-one logic error.'),
    ], 'Read the first error and trace small examples; do not change many unrelated lines at once.'),
    'mini-project' => $make('Build a classroom supplies mini-project', 'Mini-projects', 'mini_project', 'Plan, implement and test a program that totals supplies for three teams.', [
        $section('Define the contract', 'Read three non-negative integer supply counts, then print only their total. For input 3 5 7, print 15. Decide inputs and expected outputs before coding.'),
        $section('Choose a clear solution', 'Use meaningful variable names and initialize the total. You can read three variables or store the counts in an array and use a loop. Explain your choice.'),
        $section('Evaluate the result', 'Test an ordinary case, all zeroes, and one large value. Compare the output, reflect on your algorithm, and submit a saved revision for teacher feedback.'),
    ], "#include <iostream>\nint main() {\n    int first = 3, second = 5, third = 7;\n    std::cout << first + second + third;\n}", '15', [
        $check('For input 3 5 7, what output meets the contract?', ['357', '15', '7'], 1, 'The task asks for the sum of all three counts.'),
        $check('Which test checks an important boundary?', ['Only 3 5 7 again', 'No tests', '0 0 0'], 2, 'All-zero input checks the smallest allowed values.'),
        $check('What is useful in your explanation?', ['Algorithm choice and the tests tried', 'A password', 'Only your nickname'], 0, 'Explain the reasoning and evidence behind your solution.'),
        $check('For input 0 4 0, what should the supplies program print?', ['040', '0', '4'], 2, 'The total is 0 + 4 + 0 = 4; zero counts still belong in the calculation.'),
        $check('Which output matches a contract asking for only the total 15?', ['Total: 15 supplies', '15', 'Enter three counts: 15'], 1, 'Only 15 matches the required output. Extra prompts and labels change the output being checked.'),
    ], 'Match the required output exactly. Extra prompts can cause an output-checking test to fail.'),
];
