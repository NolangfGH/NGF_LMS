<?php

return [
    'introduction' => [
        'title' => 'Think like a programmer: turn a classroom task into an algorithm',
        'category' => 'Learning Tips',
        'introduction' => 'Programming begins with a clear plan, not a long file of code. Imagine counting supplies for your group: first decide what information you need, then describe the calculation, and finally decide how to present the result. An algorithm is an ordered set of steps that someone else can follow. C++ lets you express those steps precisely, but it cannot decide what you meant when a step is missing.',
        'prompts' => [
            'Write a three-step algorithm for adding two classroom supply counts. Label the input, processing and output.',
            'Choose small input values and trace your algorithm. Explain how you would know whether the result is correct.',
        ],
        'teacher_notes' => 'Look for an ordered plan that reads both counts, adds them, and displays their sum. A useful response traces specific values and compares the result with an expected total. If the learner lists only C++ keywords, ask them to explain the problem-solving steps in their own words.',
    ],
    'output' => [
        'title' => 'Make your first C++ message clear: output, spaces and new lines',
        'category' => 'C++ Basics',
        'introduction' => 'A console message is a useful first program because you can see exactly what your instructions produce. The compiler treats capitalization, quotation marks and punctuation carefully. The reader also needs spaces and line breaks. Think of output as a contract: if a task asks for two lines, printing both messages on one line changes the result even when the words are correct.',
        'prompts' => [
            'Explain what std::cout, the << operator, and the newline escape sequence \\n do in the example.',
            'Describe what would change if you removed the newline from the first message. Write the two messages as they would appear.',
        ],
        'teacher_notes' => 'The learner should connect cout with output and << with inserting data into that stream. Removing the newline joins the messages on one line without automatically inserting a space. Check that the learner distinguishes text inside quotes from C++ statements.',
    ],
    'variables' => [
        'title' => 'Give data a meaningful name: variables in a classroom calculation',
        'category' => 'C++ Basics',
        'introduction' => 'A variable connects a value to a name you can use in a calculation. Names such as teams and devicesPerTeam make a program easier to explain than unrelated letters. The type matters too: a whole-number count and a measurement that includes fractions have different needs. Initialize a variable before you read its value, then trace how the calculation uses it.',
        'prompts' => [
            'In the example, identify the input values stored in variables and the calculated result. Explain why int is suitable for these counts.',
            'Change teams to 5 and devicesPerTeam to 3 in your explanation. Predict the new result and describe a situation where double would be a better choice than int.',
        ],
        'teacher_notes' => 'Expect a total of 15 for the changed values, meaningful variable roles, and an explanation that int stores whole-number counts. A good double example involves fractional measurements or results, not just a different variable name.',
    ],
    'input' => [
        'title' => 'Let the user supply the data: read, calculate and display',
        'category' => 'C++ Basics',
        'introduction' => 'A program becomes more useful when it can solve the same kind of problem for different input values. Standard input lets a learner type a value without rewriting the source. Declare a suitable variable, read the value with std::cin, then use it in a calculation. When a coding task specifies exact output, keep extra prompts out of the submitted result unless the task asks for them.',
        'prompts' => [
            'Explain the difference between std::cin >> value and std::cout << value. Why must value be declared before input is read?',
            'Plan a program that reads two non-negative counts and prints their sum. Give one ordinary test and one test containing zero, including the expected outputs.',
        ],
        'teacher_notes' => 'Look for the correct direction of extraction and insertion, a declared variable, and two valid input/output examples. If a response uses cin << or cout >>, guide the student to compare the stream operations rather than only correcting punctuation.',
    ],
    'operators' => [
        'title' => 'What is left over? Understand division, remainder and order',
        'category' => 'Problem Solving',
        'introduction' => 'Suppose a group shares 17 items among 5 teams. Each team can receive 3 whole items, with 2 left over. Integer division and remainder answer those two different questions. Parentheses also help communicate the intended order of a longer calculation. Before running a program, predict a small result so you can compare what C++ does with what the task actually needs.',
        'prompts' => [
            'Use 17 and 5 to explain the difference between / and %. Show how the quotient and remainder fit back into the original number.',
            'Compare 3 + 2 * 4 with (3 + 2) * 4. State both results and explain the order of operations. What divisor must an integer division avoid?',
        ],
        'teacher_notes' => 'The quotient is 3 and remainder is 2, with 17 = 3 * 5 + 2. The expression results are 11 and 20 respectively. Expect the explanation that multiplication precedes addition unless parentheses change the order, and division by zero must be avoided.',
    ],
    'conditions' => [
        'title' => 'Make a fair decision: conditions and boundary tests',
        'category' => 'Problem Solving',
        'introduction' => 'Programs often choose between actions. A condition is a question with a true or false result; an if/else statement uses that result to select a branch. Boundaries deserve special attention. A rule that includes 30 behaves differently from a rule that includes only values greater than 30. Good programmers test a value below the boundary, the boundary itself, and a value above it.',
        'prompts' => [
            'Trace the decision in the lesson example and explain which branch runs. Compare >= with > at the boundary used in that example.',
            'Create a rule that prints Pass for a score of at least 75 and Keep practicing otherwise. State the expected result for scores 74, 75 and 76.',
        ],
        'teacher_notes' => 'For the proposed score rule, 74 prints Keep practicing while 75 and 76 print Pass. The reasoning must include the exact boundary in >=. If a learner uses = for a comparison, ask them to distinguish assignment from comparison.',
    ],
    'for-loops' => [
        'title' => 'Count with confidence: trace a for loop before running it',
        'category' => 'Coding Tips',
        'introduction' => 'A for loop groups three important decisions: where a counter starts, when repetition is allowed, and how the counter changes. Instead of guessing how many times it runs, write a tiny trace table. Record the counter before each condition check and the output produced by that iteration. This makes a missing or extra repetition much easier to find.',
        'prompts' => [
            'Identify the initialization, condition and update in the lesson example. Explain how the loop eventually stops.',
            'Trace for (int i = 1; i <= 3; ++i) when its body prints i on a new line. What output changes if the condition becomes i < 3?',
        ],
        'teacher_notes' => 'The trace for <= prints 1, 2, 3 on separate lines; < prints only 1 and 2. Look for a connection between the update and the condition becoming false. A loop can have the right syntax while still making an off-by-one error.',
    ],
    'while-loops' => [
        'title' => 'Know when to stop: sentinel input and while loops',
        'category' => 'Coding Tips',
        'introduction' => 'Sometimes you do not know how many values will arrive. A sentinel is a special value that means stop, rather than another item to process. A while loop checks its condition before each iteration, so it can correctly process zero items when the sentinel arrives first. Keep the running total initialized, exclude the sentinel, and make sure each repetition can read the next value.',
        'prompts' => [
            'For input 4 6 -1 and sentinel -1, explain which values belong in the sum and give the final total.',
            'What should happen when the first input is -1? Describe how forgetting to read the next input could prevent a loop from stopping.',
        ],
        'teacher_notes' => 'The ordinary total is 10; -1 is excluded. An immediate sentinel gives a total of 0 and no processed values. The explanation should connect reading new input with eventually making the loop condition false.',
    ],
    'functions' => [
        'title' => 'Name a useful task: functions, parameters and returned values',
        'category' => 'Coding Tips',
        'introduction' => 'A function gives a name to a task so the rest of the program can request it without repeating every step. A parameter receives the value supplied by the caller. A return statement sends a result back. Keep a small calculation separate from input and output, and you can test the calculation with several arguments while main remains easy to read.',
        'prompts' => [
            'Identify the function, parameter, argument and returned result in the lesson example. Describe how main uses the result.',
            'Plan int square(int number). Explain what it should return for 5, 0 and -3, and why testing only one positive number is not enough evidence.',
        ],
        'teacher_notes' => 'square returns 25, 0 and 9 for the proposed arguments. Look for the distinction between a parameter in a function declaration and an argument supplied in a call. Calling a function is different from merely writing its definition.',
    ],
    'arrays' => [
        'title' => 'One collection, many values: arrays and safe indexes',
        'category' => 'C++ Basics',
        'introduction' => 'An array stores a fixed collection of values under one name. C++ indexes begin at zero, so a five-element array has valid indexes from 0 to 4. Indexing past the end is not a way to request an empty value; it can make the program behave unpredictably. Trace the index alongside the running total to check that every element is visited exactly once.',
        'prompts' => [
            'For int values[5] = {1, 2, 3, 4, 5};, give the value at index 2 and explain why index 5 is invalid.',
            'Explain how a loop starting at index 0 can sum all five elements safely. State the total and the condition that prevents an out-of-bounds access.',
        ],
        'teacher_notes' => 'Index 2 contains 3, valid indexes are 0 through 4, and the sum is 15. Expect i < 5, not i <= 5, with a zero start and one-step updates. Ask for a trace when the student confuses an element value with its index.',
    ],
    'strings' => [
        'title' => 'Work with words: strings, spaces and readable messages',
        'category' => 'C++ Basics',
        'introduction' => 'Text is data too. std::string holds a sequence of characters, and double quotes mark a string literal in C++. Spaces and punctuation are part of a message, so they must be planned just like a calculation. Reading one word with >> is different from reading a whole line with std::getline. Use fictional nicknames and safe sample text when you test classroom programs.',
        'prompts' => [
            'If name is Sam in the lesson greeting, write the exact output, including spaces and punctuation. Explain the difference between a string literal and a character literal.',
            'A user enters Ada Lovelace. Explain why std::cin >> name reads only the first word and name an input operation that can read the whole line.',
        ],
        'teacher_notes' => 'The greeting is Hello, Sam! A string literal uses double quotes; a character literal such as A uses single quotes. Extraction into a string stops at whitespace, whereas getline can read the whole line. If prior formatted input is discussed, the remaining newline also needs handling.',
    ],
    'debugging' => [
        'title' => 'Be a bug detective: use evidence instead of guessing',
        'category' => 'Problem Solving',
        'introduction' => 'Debugging is a careful investigation. A compiler error can stop a program from building, while a logic error can produce a wrong result even when it runs. Start from a small failing test with a known expected result. Read the first relevant error or trace the calculation, change the cause, then rerun the failed test and useful boundary cases. Explain the evidence behind the fix.',
        'prompts' => [
            'A loop intended to sum 1 through 3 uses i < 3 and produces 3. Explain the error, the expected total, and the condition that repairs it.',
            'Compare this logic error with a missing semicolon. Describe the tests you would run after the loop repair and what each test checks.',
        ],
        'teacher_notes' => 'The loop skips the final 3; i <= 3 gives 6 when starting at 1. A missing required semicolon is a syntax problem that can be reported during compilation. Useful tests include the smallest intended count and the previously failing case, with expected results.',
    ],
    'mini-project' => [
        'title' => 'From idea to evidence: build a classroom supplies mini-project',
        'category' => 'Projects',
        'introduction' => 'A mini-project combines planning, coding, testing and explanation. For the classroom supplies task, agree on the contract first: read three non-negative counts and print only their total. Choose clear variable names or a suitable array, then work from small tests toward the complete solution. A useful submission includes the program and an explanation of the choices and evidence behind it.',
        'prompts' => [
            'Describe an algorithm for reading three supply counts and printing their total. Compare using three named variables with using an array and loop.',
            'Give expected outputs for 3 5 7, 0 0 0 and 0 4 0. Explain why extra text such as Total: can fail an exact-output test when the contract asks for only the number.',
        ],
        'teacher_notes' => 'Expected totals are 15, 0 and 4. Both three variables and an array/loop can meet the contract; assess the explanation rather than requiring one without instruction. Look for zero cases, exact output and a clear plan that includes all three counts.',
    ],
];
