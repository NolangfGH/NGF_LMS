<?php

$base = [
    'output' => [
        'title' => 'Your first C++ program', 'topic' => 'Program structure & output', 'minutes' => 8, 'practice' => 'greeting', 'icon' => 'terminal', 'version' => 1,
        'objective' => 'Recognize the parts of a C++ program and print a message on a new line.',
        'sections' => [
            ['title' => 'Start with a small program', 'text' => 'A C++ program begins executing in main. The braces hold its statements. Most simple statements end with a semicolon. return 0 ends this example normally.'],
            ['title' => 'Send text to the console', 'text' => 'Include iostream to use standard console streams. std::cout << sends a value to the output stream. Put text inside double quotes. A newline escape sequence moves output to a new line.'],
            ['title' => 'Predict before you run', 'text' => 'Read the statements in order. Predict each printed line, then compile and run the example in your C++ compiler. A spelling or punctuation mistake can prevent compilation.'],
        ],
        'code' => "#include <iostream>\n\nint main() {\n    std::cout << \"Hello, CodeQuest!\\n\";\n    std::cout << \"I am learning C++.\";\n    return 0;\n}",
        'output' => "Hello, CodeQuest!\nI am learning C++.",
        'mistake' => 'Use cout << for output. cout >> is not the output operation. Check the two semicolons after the output statements.',
        'checks' => [
            ['prompt' => 'Where does this C++ program begin executing?', 'options' => ['main', 'iostream', 'cout'], 'correct' => 0, 'why' => 'main is the program entry function in this example.'],
            ['prompt' => 'Which statement prints the word Hello?', 'options' => ['std::cout >> "Hello";', 'std::cout << "Hello";', 'std::cin << "Hello";'], 'correct' => 1, 'why' => 'The insertion operator << sends text to cout.'],
            ['prompt' => 'What normally ends a simple C++ statement?', 'options' => ['A comma', 'A semicolon', 'A quotation mark'], 'correct' => 1, 'why' => 'Simple statements such as the output statements end with a semicolon.'],
            ['prompt' => 'Why does this example include <iostream>?', 'options' => ['To repeat a loop', 'To store a password', 'To use standard console streams such as std::cout'], 'correct' => 2, 'why' => 'The iostream header declares the standard console input and output streams.'],
            ['prompt' => 'What does the newline escape sequence \\n do inside the first message?', 'options' => ['Moves the following output to a new line', 'Stops the compiler', 'Prints the letter n only'], 'correct' => 0, 'why' => 'The newline escape sequence starts a new output line, so the second message appears below the first.'],
        ],
    ],
    'variables' => [
        'title' => 'Variables and arithmetic', 'topic' => 'Data types & operators', 'minutes' => 10, 'practice' => 'variables', 'icon' => 'calculate', 'version' => 1,
        'objective' => 'Store whole-number values in int variables and use them in a calculation.',
        'sections' => [
            ['title' => 'Give a value a useful name', 'text' => 'A variable stores a value. Declare its type, choose a meaningful name, and initialize it before using it. int holds whole-number values; double is useful when a calculation needs fractional values.'],
            ['title' => 'Calculate with expressions', 'text' => 'Use + for addition, - for subtraction, * for multiplication and / for division. Multiplication and division are evaluated before addition and subtraction unless parentheses change the order.'],
            ['title' => 'Watch integer division', 'text' => 'When both operands are integers, division produces an integer result. For example, 7 / 2 gives 3. Use a suitable floating-point operand when a fractional result is needed.'],
        ],
        'code' => "#include <iostream>\n\nint main() {\n    int teams = 3;\n    int devicesPerTeam = 4;\n    int total = teams * devicesPerTeam;\n    std::cout << total;\n    return 0;\n}",
        'output' => '12', 'mistake' => 'Initialize total before printing it. Prefer names such as devicesPerTeam instead of unrelated single letters.',
        'checks' => [
            ['prompt' => 'Which type is suitable for a count of whole devices?', 'options' => ['int', 'void', 'return'], 'correct' => 0, 'why' => 'int represents a whole-number count in this example.'],
            ['prompt' => 'What does 3 * 4 evaluate to?', 'options' => ['7', '12', '34'], 'correct' => 1, 'why' => 'The multiplication operator multiplies the two values.'],
            ['prompt' => 'What is the result of the integer expression 7 / 2?', 'options' => ['3.5', '4', '3'], 'correct' => 2, 'why' => 'Integer division discards the fractional part.'],
            ['prompt' => 'Which declaration initializes a whole-number count to zero?', 'options' => ['int count;', 'int count = 0;', 'count == 0;'], 'correct' => 1, 'why' => 'int count = 0 declares the variable and gives it a known starting value.'],
            ['prompt' => 'Which type is suitable when a result needs fractional values?', 'options' => ['double', 'void', 'return'], 'correct' => 0, 'why' => 'double can represent fractional values, while int is used for whole-number values.'],
        ],
    ],
    'input' => [
        'title' => 'Read and use user input', 'topic' => 'Console input', 'minutes' => 10, 'practice' => 'input_total', 'icon' => 'keyboard', 'version' => 1,
        'objective' => 'Read a number into a declared variable, use it in a calculation, and display the result.',
        'sections' => [
            ['title' => 'Read into a variable', 'text' => 'std::cin >> variable reads formatted input into a variable. Declare the variable first. Input and output use different stream directions: cin >> reads; cout << writes.'],
            ['title' => 'Keep prompts and results clear', 'text' => 'A prompt explains what a user should enter. The worked example below omits prompts so its output is only the calculated answer. Read the value before calculating with it.'],
            ['title' => 'Test more than one value', 'text' => 'The example assumes valid integer input. Try zero and another positive integer. Handling invalid text input needs additional checks; do not assume cin always receives the type of value you expect.'],
        ],
        'code' => "#include <iostream>\n\nint main() {\n    int amount = 0;\n    std::cin >> amount;\n    std::cout << amount + 2;\n    return 0;\n}",
        'output' => '7', 'example_note' => 'If the user enters 5, the program prints 7. The entered input is not an extra output statement.',
        'mistake' => 'cin << amount uses the wrong direction. Declare amount and read it with cin >> amount before using it.',
        'checks' => [
            ['prompt' => 'Which statement reads an integer into amount?', 'options' => ['std::cin >> amount;', 'std::cout >> amount;', 'std::cin << amount;'], 'correct' => 0, 'why' => 'cin uses >> to extract input into the variable.'],
            ['prompt' => 'What prints when amount is read as 5 in this example?', 'options' => ['2', '5', '7'], 'correct' => 2, 'why' => 'The expression amount + 2 evaluates to 7.'],
            ['prompt' => 'What should happen before you calculate with a user-supplied value?', 'options' => ['Print an uninitialized variable', 'Read the value into a declared variable', 'Remove main'], 'correct' => 1, 'why' => 'The program needs a declared variable containing the input value first.'],
            ['prompt' => 'What does the example print when the user enters 0?', 'options' => ['0', 'An error in every case', '2'], 'correct' => 2, 'why' => 'After reading zero, amount + 2 evaluates to 2. Zero is a useful boundary test.'],
            ['prompt' => 'Why does this worked example omit an input prompt?', 'options' => ['So its output contains only the calculated answer', 'Because cin prints the prompt automatically', 'Because programs cannot print prompts'], 'correct' => 0, 'why' => 'The output contract asks for just the result; an extra prompt would change the expected output.'],
        ],
    ],
    'conditions' => [
        'title' => 'Make decisions with if and else', 'topic' => 'Conditions', 'minutes' => 10, 'practice' => 'branch', 'icon' => 'alt_route', 'version' => 1,
        'objective' => 'Choose between two actions using a comparison and an if/else statement.',
        'sections' => [
            ['title' => 'Ask a true-or-false question', 'text' => 'An if condition is evaluated as true or false. Comparisons include ==, !=, <, <=, > and >=. Use == to compare values; = assigns a value.'],
            ['title' => 'Choose one branch', 'text' => 'When the condition is true, the if block runs. Otherwise the else block runs. Use braces to make the intended blocks clear, especially when each contains several statements.'],
            ['title' => 'Test the boundary', 'text' => 'For a rule that starts at 30, test 29, 30 and 31. These values check both branches and the exact boundary. Do not test only a single example.'],
        ],
        'code' => "#include <iostream>\n\nint main() {\n    int temperature = 32;\n    if (temperature >= 30) {\n        std::cout << \"Hot\";\n    } else {\n        std::cout << \"Cool\";\n    }\n    return 0;\n}",
        'output' => 'Hot', 'mistake' => 'temperature = 30 changes the variable. temperature == 30 compares equality, and temperature >= 30 includes the boundary.',
        'checks' => [
            ['prompt' => 'Which expression compares x with 5 for equality?', 'options' => ['x = 5', 'x == 5', 'x + 5'], 'correct' => 1, 'why' => '== compares values; = performs assignment.'],
            ['prompt' => 'What prints when temperature is exactly 30?', 'options' => ['Cool', 'Both messages', 'Hot'], 'correct' => 2, 'why' => '>= includes the boundary value 30.'],
            ['prompt' => 'Which set best checks the boundary of the rule?', 'options' => ['29, 30, 31', '100, 200, 300', '32 only'], 'correct' => 0, 'why' => 'These values test just below, at and above the boundary.'],
            ['prompt' => 'What prints when temperature is 29 in the example?', 'options' => ['Hot', 'Cool', 'Both messages'], 'correct' => 1, 'why' => '29 >= 30 is false, so only the else block prints Cool.'],
            ['prompt' => 'Which comparison means x is not equal to 5?', 'options' => ['x = 5', 'x == 5', 'x != 5'], 'correct' => 2, 'why' => 'The != operator tests whether two values differ.'],
        ],
    ],
    'for-loops' => [
        'title' => 'Repeat with a for loop', 'topic' => 'Loops', 'minutes' => 12, 'practice' => 'loops', 'icon' => 'repeat', 'version' => 1,
        'objective' => 'Use initialization, a condition and an update to print a countdown.',
        'sections' => [
            ['title' => 'Describe the repeating action', 'text' => 'A loop repeats a block. A for loop groups an initial value, a continuation condition and an update. The condition is checked before each execution of the body.'],
            ['title' => 'Move toward the stopping condition', 'text' => 'In a countdown, the counter decreases. The update i-- subtracts one each time. A condition such as i >= 1 includes the last desired value.'],
            ['title' => 'Trace the iterations', 'text' => 'Write down the counter values before each pass. Check the first value, last printed value and first value that fails the condition. This helps find off-by-one mistakes.'],
        ],
        'code' => "#include <iostream>\n\nint main() {\n    for (int i = 5; i >= 1; i--) {\n        std::cout << i << \"\\n\";\n    }\n    std::cout << \"Launch!\";\n    return 0;\n}",
        'output' => "5\n4\n3\n2\n1\nLaunch!", 'mistake' => 'Increasing i in this countdown moves away from the stopping condition. Check i-- and avoid a stray semicolon after the for header.',
        'checks' => [
            ['prompt' => 'What does i-- do?', 'options' => ['Adds one', 'Subtracts one', 'Sets i to zero'], 'correct' => 1, 'why' => 'The decrement operation reduces the counter by one.'],
            ['prompt' => 'How many numbers does the loop print?', 'options' => ['4', '6', '5'], 'correct' => 2, 'why' => 'The counter values are 5, 4, 3, 2 and 1.'],
            ['prompt' => 'Which counter value first makes i >= 1 false?', 'options' => ['0', '1', '5'], 'correct' => 0, 'why' => 'After printing 1, the update produces 0 and the loop stops.'],
            ['prompt' => 'Which number is printed first in the countdown example?', 'options' => ['1', '0', '5'], 'correct' => 2, 'why' => 'Initialization sets i to 5 before the first condition check and loop body.'],
            ['prompt' => 'What happens if the update is removed and i stays 5?', 'options' => ['The loop stops after one pass', 'The condition remains true and the loop keeps repeating', 'The counter decreases automatically'], 'correct' => 1, 'why' => 'Without a change to i, the condition i >= 1 remains true. A loop needs a way to reach its stopping condition.'],
        ],
    ],
    'while-loops' => [
        'title' => 'Stop input with a sentinel', 'topic' => 'Loops & input', 'minutes' => 12, 'practice' => 'sentinel', 'icon' => 'sync', 'version' => 1,
        'objective' => 'Use a while loop to process values until a sentinel appears, without adding the sentinel to the total.',
        'sections' => [
            ['title' => 'Choose a stopping signal', 'text' => 'A sentinel is a special input value that means stop. In this lesson, -1 is the sentinel and is not part of the sum. Choose a sentinel that is outside the normal values for your task.'],
            ['title' => 'Read, check, process, repeat', 'text' => 'Read a value before checking it. While the value is not the sentinel, add it and read the next value. Without the next read, the same value can be processed repeatedly.'],
            ['title' => 'Check the empty case', 'text' => 'Test the sentinel as the first input. With a total initialized to zero, the program should print zero. The example assumes valid integer inputs.'],
        ],
        'code' => "#include <iostream>\n\nint main() {\n    int number = 0;\n    int total = 0;\n    std::cin >> number;\n    while (number != -1) {\n        total += number;\n        std::cin >> number;\n    }\n    std::cout << total;\n    return 0;\n}",
        'output' => '10', 'example_note' => 'For the input sequence 4 6 -1, the program prints 10.',
        'mistake' => 'Do not add -1 to total. Remember to read the next number inside the loop.',
        'checks' => [
            ['prompt' => 'What is the role of -1 in this example?', 'options' => ['A value to add', 'A stopping signal', 'The starting total'], 'correct' => 1, 'why' => 'The sentinel ends input processing and is not added.'],
            ['prompt' => 'What prints if the first input is -1?', 'options' => ['-1', '1', '0'], 'correct' => 2, 'why' => 'The loop body does not run, so total stays zero.'],
            ['prompt' => 'Why read the next number inside the loop?', 'options' => ['To update the value used by the next condition check', 'To add the sentinel', 'To remove the need for a condition'], 'correct' => 0, 'why' => 'The next input lets the loop move on or stop.'],
            ['prompt' => 'What total is printed for inputs 2 3 -1?', 'options' => ['4', '5', '-1'], 'correct' => 1, 'why' => 'Only 2 and 3 are added. The sentinel -1 ends the loop without being added.'],
            ['prompt' => 'When does a while loop check its condition?', 'options' => ['Only after the whole program finishes', 'Only after the first pass', 'Before each pass through the loop body'], 'correct' => 2, 'why' => 'A while loop checks its condition before executing the body, so the body may run zero times.'],
        ],
    ],
    'functions' => [
        'title' => 'Organize code with a function', 'topic' => 'Functions', 'minutes' => 12, 'practice' => 'functions', 'icon' => 'account_tree', 'version' => 1,
        'objective' => 'Define a function with a parameter, return a calculated value, and call it from main.',
        'sections' => [
            ['title' => 'Give one task its own function', 'text' => 'A function groups a task behind a name. In int square(int number), the first int is the return type, square is the name, and number is an integer parameter.'],
            ['title' => 'Return a result to the caller', 'text' => 'return number * number sends a value back to the call. It does not print by itself. main can store the value or send it to cout.'],
            ['title' => 'Test the function separately', 'text' => 'Try square with 5, 0 and -3. Predict 25, 0 and 9. Keep a function declaration visible before the call; this example defines square before main.'],
        ],
        'code' => "#include <iostream>\n\nint square(int number) {\n    return number * number;\n}\n\nint main() {\n    std::cout << square(5);\n    return 0;\n}",
        'output' => '25', 'mistake' => 'Printing a value and returning a value are different operations. A function promising an int result needs to return the intended value.',
        'checks' => [
            ['prompt' => 'What value does square(5) return?', 'options' => ['10', '25', '5'], 'correct' => 1, 'why' => '5 multiplied by itself is 25.'],
            ['prompt' => 'What is number in the square definition?', 'options' => ['A parameter', 'An output stream', 'A loop condition'], 'correct' => 0, 'why' => 'A parameter receives the input supplied by the caller.'],
            ['prompt' => 'Does returning a value automatically print it?', 'options' => ['Yes, always', 'Only if the value is zero', 'No; output needs an output statement'], 'correct' => 2, 'why' => 'Returning passes a value to the caller; cout displays it.'],
            ['prompt' => 'What does the first int in int square(int number) describe?', 'options' => ['The return type', 'The number of calls allowed', 'An output statement'], 'correct' => 0, 'why' => 'The return type says that square returns an integer value to its caller.'],
            ['prompt' => 'What does square(-3) return?', 'options' => ['-9', '9', '0'], 'correct' => 1, 'why' => 'The function multiplies -3 by -3, producing 9. Testing a negative input checks another case.'],
        ],
    ],
];

$extra = require __DIR__.'/lessons_extension.php';
$ordered = [];
foreach (['introduction', 'output', 'variables', 'input', 'operators', 'conditions', 'for-loops', 'while-loops', 'functions', 'arrays', 'strings', 'debugging', 'mini-project'] as $key) {
    $ordered[$key] = $base[$key] ?? $extra[$key];
    $ordered[$key]['version'] = 2;
}

return $ordered;
