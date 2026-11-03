<?php

$question = static fn (string $prompt, array $options, int $correct, string $why): array => compact('prompt', 'options', 'correct', 'why');
$task = static function (string $title, string $objective, array $examples, array $hints, ?string $starter = null): array {
    return [
        'title' => $title,
        'objective' => $objective."\nPrint exactly the required output without extra prompts. Explain your algorithm and at least two tests in your Code Lab reflection, then submit for teacher review.",
        'sample_input' => $examples[0][0],
        'sample_output' => $examples[0][1],
        'test_cases' => array_map(static fn (array $example, int $index): array => ['input' => $example[0], 'output' => $example[1], 'hidden' => $index > 0, 'weight' => 1], $examples, array_keys($examples)),
        'hints' => $hints,
        'starter_code' => $starter ?? "#include <iostream>\n\nint main() {\n    // Write and test your solution here.\n    return 0;\n}\n",
    ];
};

return [
    'introduction' => [
        'term' => 'Term1', 'difficulty' => 'Easy',
        'questions' => [
            $question('Which order describes a simple programming workflow?', ['Plan, write, compile, test', 'Test, delete, guess, stop', 'Publish a password, then compile', 'Run before writing any instructions'], 0, 'A plan guides the code; compilation and testing check it.'),
            $question('An algorithm says: start at 2, add 3, then multiply by 2. What is the result?', ['7', '10', '8', '12'], 1, 'The steps give 2 + 3 = 5, then 5 * 2 = 10.'),
            $question('Why use a small test example when learning an algorithm?', ['It replaces all future tests', 'It hides mistakes', 'Its steps can be traced and checked', 'It removes the need for input'], 2, 'A small example makes it easier to check each step against the expected result.'),
            $question('Which action is output in a classroom program?', ['Reading a number', 'Declaring an integer', 'Adding two values internally', 'Displaying the calculated total'], 3, 'Output is information the program sends out, such as a displayed result.'),
            $question('A program compiles successfully. What should you do next?', ['Run suitable tests and compare results', 'Assume every answer is correct', 'Delete the expected output', 'Share your login password'], 0, 'Compilation checks source rules, while tests check behavior.'),
        ],
        'assignment' => $task('My first coding plan', 'Write a C++ program that prints these two lines: Plan the steps and Test the result. No input is required. In your reflection, describe an algorithm for adding two numbers.', [['', "Plan the steps\nTest the result"]], ['Begin in main.', 'Use a separate output statement or a newline for each line.']),
        'challenge' => $task('Three-step mission', 'Write a C++ program that displays the three ordered steps shown in the sample output. No input is required. Explain why input must come before calculation in this plan.', [['', "1. Read two numbers\n2. Add the numbers\n3. Print the total"]], ['Numbers and punctuation are part of the required text.', 'Use newline characters between the steps.']),
    ],
    'output' => [
        'term' => 'Term1', 'difficulty' => 'Easy',
        'questions' => [
            $question('What does std::cout << 2 << 3; display?', ['5', '23', '2 3', 'Nothing'], 1, 'Two insertion operations print the values consecutively without a space.'),
            $question('Which statement prints a double quotation mark around Hi?', ['std::cout << Hi;', 'std::cin >> "Hi";', 'std::cout << "\"Hi\"";', 'std::cout >> "Hi";'], 2, 'Escaped quotation marks inside a string are printed as quotation marks.'),
            $question('What do braces around the statements in main identify?', ['A string literal', 'An input number', 'A header file', 'The body of the function'], 3, 'Braces group the statements that belong to the function.'),
            $question('What does std::cout << 4 + 6; display?', ['10', '46', '4 + 6', '0'], 0, 'The arithmetic expression is evaluated before its value is inserted into the stream.'),
            $question('Which spelling refers to the standard output stream?', ['std::Cout', 'std::cout', 'std::coutt', 'std::cin'], 1, 'C++ names are case sensitive; std::cout is the standard output stream.'),
        ],
        'assignment' => $task('Coder introduction card', 'Use C++ output statements to display the two lines in the sample. No input is required. Preserve spaces, capitalization and punctuation.', [['', "NGF C++ Classroom\nReady to code!"]], ['Put text inside double quotes.', 'Print a newline after the first line.']),
        'challenge' => $task('Console badge', 'Print the three-line badge shown below using std::cout. Do not request input.', [['', "+----------+\n| C++ TEAM |\n+----------+"]], ['Count the hyphens and spaces carefully.', 'Each line must appear on its own output line.']),
    ],
    'variables' => [
        'term' => 'Term1', 'difficulty' => 'Easy',
        'questions' => [
            $question('After int score = 8; score = score + 2;, what is score?', ['8', '2', '10', '82'], 2, 'The expression uses the old value 8, adds 2, and assigns 10.'),
            $question('Which is a valid C++ variable name?', ['8teams', 'team-count', 'int', 'teamCount'], 3, 'teamCount begins with a letter and is not a reserved keyword.'),
            $question('What is the value of 2 + 3 * 4?', ['14', '20', '24', '9'], 0, 'Multiplication is evaluated before addition.'),
            $question('What does const int limit = 5; express?', ['limit must change every loop', 'limit cannot be reassigned', 'limit is a string', 'limit has no value'], 1, 'const prevents reassignment of this variable after initialization.'),
            $question('What is 7.0 / 2?', ['3', '4', '3.5', '1'], 2, 'A floating-point operand makes this floating-point division.'),
        ],
        'assignment' => $task('Devices for every team', 'Declare and initialize integer variables for 4 teams and 6 devices per team. Calculate and print the total number of devices. No input is required. Use meaningful variable names.', [['', '24']], ['Multiply teams by devices per team.', 'Print the calculated variable instead of a quoted answer.']),
        'challenge' => $task('Classroom garden measurements', 'Set integer variables length = 5 and width = 3. Calculate the rectangular garden area and perimeter. Print area on the first line and perimeter on the second. No input is required.', [['', "15\n16"]], ['Area is length * width.', 'Perimeter is 2 * (length + width).']),
    ],
    'input' => [
        'term' => 'Term1', 'difficulty' => 'Easy',
        'questions' => [
            $question('Which statement reads two integers into a and b?', ['std::cout << a << b;', 'std::cin << a << b;', 'a >> b;', 'std::cin >> a >> b;'], 3, 'The extraction operator >> can be chained to read both variables.'),
            $question('For input 4 7, what does cin >> a >> b; cout << a + b; print? Assume declared integers and std names.', ['11', '47', '3', '28'], 0, 'The two read integers are added: 4 + 7 = 11.'),
            $question('With integer input a = 4 and b = 7, what is a * b?', ['11', '28', '47', '3'], 1, 'The multiplication operator gives 4 * 7 = 28.'),
            $question('Which declaration should come before std::cin >> count;?', ['count == 0;', 'std::cout count;', 'int count = 0;', 'input count;'], 2, 'The target variable must be declared with a suitable type before extraction.'),
            $question('The judge expects only 11. Which output matches?', ['Enter numbers: 11', 'Total is 11', '11 apples', '11'], 3, 'Extra labels and prompts do not match an output-only contract.'),
        ],
        'assignment' => $task('Add two scores', 'Read two integers a and b, each from 0 to 100. Print their sum. Input is two whitespace-separated integers, for example 12 8.', [['12 8', '20'], ['0 0', '0'], ['100 25', '125']], ['Declare both input variables.', 'Use std::cin >> a >> b and print their sum.']),
        'challenge' => $task('Supply packs', 'Read the number of packs and the number of pencils per pack, each from 0 to 100. Print the total number of pencils. Input contains two integers in that order.', [['3 8', '24'], ['0 12', '0'], ['5 7', '35']], ['Read both quantities before calculating.', 'Multiplication connects packs to total pencils.']),
    ],
    'operators' => [
        'term' => 'Term1', 'difficulty' => 'Easy',
        'questions' => [
            $question('What is 17 % 5?', ['2', '3', '5', '12'], 0, '17 = 3 * 5 + 2, so the remainder is 2.'),
            $question('What is 20 / 4 + 2 with integers?', ['3', '7', '10', '22'], 1, 'Division happens first: 5 + 2 = 7.'),
            $question('After int x = 3; x += 4;, what is x?', ['3', '4', '7', '12'], 2, 'x += 4 is equivalent here to x = x + 4.'),
            $question('Which expression checks whether n is even?', ['n / 2 == 0', 'n % 2 == 1', 'n = 2', 'n % 2 == 0'], 3, 'An even integer leaves remainder zero when divided by 2.'),
            $question('What does 3 == 4 evaluate to?', ['false', 'true', '7', '1'], 0, 'Equality comparison is false because the operands differ.'),
        ],
        'assignment' => $task('Share supplies fairly', 'Read integers items (0 to 1000) and groups (1 to 100). Print the integer quotient items / groups on the first line and the remainder items % groups on the second. groups is always positive.', [['17 5', "3\n2"], ['0 4', "0\n0"], ['20 4', "5\n0"]], ['Integer division gives complete groups.', 'Remainder gives the items left over.']),
        'challenge' => $task('Minutes into hours', 'Read a non-negative integer totalMinutes up to 10000. Print whole hours on the first line and remaining minutes on the second, using 60 minutes per hour.', [['135', "2\n15"], ['0', "0\n0"], ['60', "1\n0"]], ['Divide by 60 to find whole hours.', 'Use % 60 for the minutes left over.']),
    ],
    'conditions' => [
        'term' => 'Term1', 'difficulty' => 'Mid',
        'questions' => [
            $question('What does !(3 < 5) evaluate to?', ['true', 'false', '3', '5'], 1, '3 < 5 is true, and logical NOT reverses it.'),
            $question('Which condition checks whether age is from 12 through 14 inclusive?', ['age >= 12 || age <= 14', '12 <= age <= 14', 'age >= 12 && age <= 14', 'age > 12 && age < 14'], 2, 'Both inclusive boundary comparisons must be true.'),
            $question('What does if (score >= 75) print for score = 75 if its body prints Pass?', ['Nothing', 'Fail', '75', 'Pass'], 3, 'The inclusive >= comparison includes the boundary value 75.'),
            $question('Which operator compares equality?', ['==', '=', '+=', '!='], 0, '== compares values; = assigns a value.'),
            $question('In an if/else, how many of the two branches execute for one evaluation?', ['Both always', 'Exactly one', 'Neither always', 'Up to three'], 1, 'A true condition selects the if branch; otherwise the else branch is selected.'),
        ],
        'assignment' => $task('Pass checkpoint', 'Read an integer score from 0 to 100. Print Pass if score is at least 75; otherwise print Keep practicing. Include a test at the exact boundary.', [['75', 'Pass'], ['74', 'Keep practicing'], ['100', 'Pass']], ['Use >= to include 75.', 'Only one branch should print.']),
        'challenge' => $task('Number classifier', 'Read an integer from -1000 to 1000. Print Positive, Negative or Zero according to its sign. Use an if / else if / else structure.', [['-4', 'Negative'], ['0', 'Zero'], ['7', 'Positive']], ['Check greater than zero first.', 'Check less than zero next; the remaining case is zero.']),
    ],
    'for-loops' => [
        'term' => 'Term2', 'difficulty' => 'Mid',
        'questions' => [
            $question('How many times does for (int i = 0; i < 4; ++i) run its body?', ['0', '3', '4', '5'], 2, 'The body runs for i = 0, 1, 2, 3.'),
            $question('What is printed by for (int i = 1; i <= 3; ++i) cout << i;?', ['012', '6', '1234', '123'], 3, 'The values 1, 2 and 3 are printed without separators.'),
            $question('Which change makes a for-loop counter decrease by one?', ['--i', '++i', 'i += 2', 'i == 1'], 0, '--i decreases the counter by one.'),
            $question('What is total after starting at 0 and adding integers 1 through 4?', ['4', '10', '6', '24'], 1, '1 + 2 + 3 + 4 = 10.'),
            $question('When is the condition in a for loop checked?', ['Only after all repetitions', 'Only when compiling', 'Before each possible iteration', 'Only after the first iteration'], 2, 'The condition is checked before the first and every subsequent iteration.'),
        ],
        'assignment' => $task('Count the launch steps', 'Read an integer n from 1 to 20. Use a for loop to print the numbers 1 through n, one number per line.', [['3', "1\n2\n3"], ['1', '1'], ['5', "1\n2\n3\n4\n5"]], ['Start the counter at 1.', 'Use <= n to include the last number.']),
        'challenge' => $task('Number staircase total', 'Read an integer n from 0 to 100. Use a for loop to add all integers from 1 through n and print their total. For n = 0, print 0.', [['5', '15'], ['0', '0'], ['1', '1']], ['Initialize total to zero.', 'Add the current counter during each iteration.']),
    ],
    'while-loops' => [
        'term' => 'Term2', 'difficulty' => 'Mid',
        'questions' => [
            $question('What happens if a while condition is false before its first check?', ['The body runs once', 'The body runs forever', 'Compilation fails', 'The body runs zero times'], 3, 'A while loop checks before running its body.'),
            $question('A loop has int x = 3; while (x > 0) { --x; }. What is x afterward?', ['0', '1', '3', '-1'], 0, 'The loop decreases x until the condition x > 0 becomes false at zero.'),
            $question('For input 2 5 -1, what is the sum before sentinel -1?', ['6', '7', '-1', '8'], 1, 'Only 2 and 5 belong in the sum; the sentinel is excluded.'),
            $question('Which is a common cause of an infinite while loop?', ['Initializing a total', 'Using braces', 'Never changing a value needed to make the condition false', 'Reading a sentinel correctly'], 2, 'If the condition stays true and no exit occurs, repetition does not end.'),
            $question('Why can while be suitable for sentinel input?', ['It always runs exactly five times', 'It sorts all inputs automatically', 'It removes input errors', 'The number of values may be unknown beforehand'], 3, 'The sentinel determines when input ends, rather than a fixed count.'),
        ],
        'assignment' => $task('Sentinel savings', 'Read integers from 0 to 100 until -1. The input always ends with -1 and contains at most 20 values before it. Use a while loop to print the sum of the non-sentinel values.', [['4 6 -1', '10'], ['-1', '0'], ['0 3 2 -1', '5']], ['Initialize the sum to zero.', 'Do not add -1.', 'Read the next value so the loop can stop.']),
        'challenge' => $task('Count the deliveries', 'Read non-negative integers until -1, with at most 20 values before the sentinel. Use a while loop to count how many values were entered, including zero values but excluding -1. Print the count.', [['8 0 5 -1', '3'], ['-1', '0'], ['0 -1', '1']], ['Count input entries, not their sum.', 'Test -1 as the first input.']),
    ],
    'functions' => [
        'term' => 'Term2', 'difficulty' => 'Mid',
        'questions' => [
            $question('For int twice(int x) { return x * 2; }, what does twice(6) return?', ['12', '6', '2', '8'], 0, 'The argument 6 becomes x, and 6 * 2 is returned.'),
            $question('Which word sends a computed value back from a function?', ['include', 'return', 'while', 'cin'], 1, 'return supplies the function result to its caller.'),
            $question('In int square(int n), what is n?', ['A header', 'A global constant', 'A parameter', 'An output stream'], 2, 'A parameter receives an argument when the function is called.'),
            $question('Why place a repeated calculation in a function?', ['To prevent testing', 'To hide every error', 'To force the answer to zero', 'To reuse it and test it separately'], 3, 'A function names a task that can be reused and checked on different inputs.'),
            $question('A variable declared inside a function body is normally accessible where?', ['Inside its enclosing scope', 'In every function automatically', 'Only in a header', 'Only in the compiler'], 0, 'Local variables belong to their declared scope.'),
        ],
        'assignment' => $task('Reusable square', 'Write int square(int number) that returns number * number. In main, read an integer from -100 to 100, call square, and print the returned result.', [['5', '25'], ['0', '0'], ['-3', '9']], ['Keep calculation in square.', 'Call square from main using the input value.']),
        'challenge' => $task('Choose the larger value', 'Write int larger(int a, int b) that returns the larger argument. In main, read two integers from -1000 to 1000 and print larger(a, b). Equal arguments return that same value.', [['4 9', '9'], ['-4 -8', '-4'], ['5 5', '5']], ['Compare the two parameters inside larger.', 'Return a value from every path.']),
    ],
    'arrays' => [
        'term' => 'Term3', 'difficulty' => 'Mid',
        'questions' => [
            $question('For int a[3] = {4, 8, 2};, what is a[1]?', ['4', '8', '2', '3'], 1, 'Index 1 refers to the second element.'),
            $question('Which loop condition fits indexes of int scores[4] when i starts at zero?', ['i <= 4', 'i < 3', 'i < 4', 'i > 4'], 2, 'The valid indexes are 0 through 3.'),
            $question('What does a[0] = 9; change?', ['Every element', 'The array length', 'The last element only', 'The first element'], 3, 'Index zero identifies the first element.'),
            $question('How many elements are declared by int values[6];?', ['6', '5', '7', '0'], 0, 'The size specifies six elements.'),
            $question('To find a maximum in a non-empty array that may contain negatives, what is a safe starting maximum?', ['Always 0', 'The first element', 'The array length', 'Always 1'], 1, 'The first element is an actual candidate even if all numbers are negative.'),
        ],
        'assignment' => $task('Five-day reading total', 'Read exactly five integers from 0 to 100 into an array. Use a loop over valid indexes to print their sum.', [['1 2 3 4 5', '15'], ['0 0 0 0 0', '0'], ['10 0 5 0 2', '17']], ['Use an array of length five.', 'Start at index zero and continue while the index is less than five.']),
        'challenge' => $task('Find the strongest signal', 'Read exactly five integers from -100 to 100 into an array. Use a loop to find and print the largest value. All values may be negative.', [['3 9 2 9 1', '9'], ['-8 -2 -5 -3 -9', '-2'], ['0 0 0 0 0', '0']], ['Initialize the maximum with the first array element.', 'Compare each remaining value with the current maximum.']),
    ],
    'strings' => [
        'term' => 'Term3', 'difficulty' => 'Mid',
        'questions' => [
            $question('Which header declares std::string?', ['<cmath>', '<iomanip>', '<string>', '<array>'], 2, 'Include <string> for the standard string type.'),
            $question('For std::string word = "CODE";, what is word.size()?', ['3', '5', '0', '4'], 3, 'There are four characters in CODE.'),
            $question('For std::string word = "cat";, what is word[0]?', ["'c'", "'a'", "'t'", "'0'"], 0, 'String indexing also starts at zero.'),
            $question('What does std::string("Code") + "Quest" produce?', ['Code Quest', 'CodeQuest', 'QuestCode', '9'], 1, 'String addition concatenates the two sequences without inserting a space.'),
            $question('How do the strings "Code" and "code" compare with ==?', ['Always equal', 'They cannot be compared', 'Not equal because case differs', 'Equal after automatic conversion'], 2, 'String comparison is case sensitive.'),
        ],
        'assignment' => $task('Friendly coder greeting', 'Read one non-empty word containing only English letters, at most 20 characters. Print Hello, followed by one space, the word, and an exclamation mark. Use a fictional nickname rather than personal information.', [['Ada', 'Hello, Ada!'], ['Coder', 'Hello, Coder!'], ['Z', 'Hello, Z!']], ['Include <string> and declare std::string.', 'Use cin >> to read the word.']),
        'challenge' => $task('Word-length scanner', 'Read one non-empty word containing only English letters, at most 20 characters. Print the number of characters in the word using std::string.', [['CodeQuest', '9'], ['A', '1'], ['loops', '5']], ['Include <string>.', 'Use size() or length() on the string.']),
    ],
    'debugging' => [
        'term' => 'Term3', 'difficulty' => 'Hard',
        'questions' => [
            $question('Which test best exposes a loop that misses its last value?', ['A random password', 'No input', 'Only repeating the same large case', 'A small boundary case with a traced expected total'], 3, 'A small boundary test makes an off-by-one error visible.'),
            $question('What is the safest first step after a compiler reports many errors?', ['Read the first relevant error and inspect that area', 'Delete the whole file', 'Change every operator', 'Ignore them and submit'], 0, 'One early syntax error can cause later messages; investigate its cause first.'),
            $question('An accumulator is declared int total; and then used in total += 3;. What repair is needed?', ['Rename main', 'Initialize total, such as int total = 0;', 'Replace + with /', 'Print a password'], 1, 'Reading an uninitialized local integer is not a valid starting point for a sum.'),
            $question('Which action provides evidence that a bug fix works?', ['Only changing indentation', 'Removing the failed test', 'Rerunning the failed test and relevant boundary tests', 'Claiming compilation proves all answers'], 2, 'Rechecking the failing case and other cases tests the repaired behavior.'),
            $question('A product starts at 0 and repeatedly multiplies positive numbers. What is the bug?', ['Multiplication cannot use variables', 'The loop must use strings', 'All inputs must be zero', 'The product should start at 1'], 3, 'Zero remains zero after multiplication; one is the multiplication identity.'),
        ],
        'assignment' => $task('Repair the missing last number', 'The starter program should sum integers 1 through n, where input n is from 1 to 100. It currently misses n. Repair the logic and explain the original bug and a boundary test.', [['3', '6'], ['1', '1'], ['5', '15']], ['Trace i for n = 1.', 'Check whether the condition includes the final value.'], "#include <iostream>\nint main() {\n    int n = 0;\n    std::cin >> n;\n    int total = 0;\n    for (int i = 1; i < n; ++i) { total += i; }\n    std::cout << total;\n    return 0;\n}\n"),
        'challenge' => $task('Repair the zero product', 'The starter program should multiply three input integers, each from 0 to 10, but its accumulator has a bug. Repair it so ordinary and zero-containing inputs both work. Explain why your starting value is correct.', [['2 3 4', '24'], ['0 3 4', '0'], ['1 1 1', '1']], ['Consider the identity value for multiplication.', 'Keep the test containing zero; a correct fix must still print zero for it.'], "#include <iostream>\nint main() {\n    int product = 0;\n    for (int i = 0; i < 3; ++i) {\n        int value = 0;\n        std::cin >> value;\n        product *= value;\n    }\n    std::cout << product;\n    return 0;\n}\n"),
    ],
    'mini-project' => [
        'term' => 'Term3', 'difficulty' => 'Hard',
        'questions' => [
            $question('Which step belongs before writing a mini-project?', ['Define inputs, outputs and constraints', 'Remove all tests', 'Publish private student records', 'Guess the final grade'], 0, 'A clear contract guides implementation and testing.'),
            $question('A task reads three counts. Which case tests a mixture of zero and positive values?', ['3 5 7 only', '0 4 0', 'No values', 'A password'], 1, '0 4 0 checks that zeros and a positive count are all handled.'),
            $question('Why choose meaningful names in a project?', ['To eliminate compilation', 'To increase XP automatically', 'To make the program easier to understand and maintain', 'To avoid every test'], 2, 'Names communicate the purpose of data and steps to readers.'),
            $question('Which reflection best supports teacher feedback?', ['It works.', 'Give me points.', 'I copied it.', 'I added three counts and tested 0 0 0, expecting 0.'], 3, 'The reflection describes an algorithm and concrete test evidence.'),
            $question('What should you do when actual output differs from the sample?', ['Trace the calculation and compare with the contract', 'Change the sample to hide the error', 'Ignore the difference', 'Stop testing permanently'], 0, 'Tracing identifies where behavior departs from the specified task.'),
        ],
        'assignment' => $task('Classroom supply budget', 'Read three non-negative integer supply counts, each at most 100. Each supply item costs 5 credits. Calculate the total item count and total cost. Print the count on the first line and cost on the second. Plan your variables and test zero counts.', [['3 5 7', "15\n75"], ['0 0 0', "0\n0"], ['0 4 0', "4\n20"]], ['Add the three counts before calculating cost.', 'Use a named constant for the price of 5 credits.']),
        'challenge' => $task('Classroom inventory keeper', 'Read startingStock, received and used in that order, each an integer from 0 to 1000. The input guarantees used <= startingStock + received. Calculate the stock left after receiving and using items, then print it. Explain your algorithm and test the case where all stock is used.', [['10 5 8', '7'], ['0 0 0', '0'], ['3 2 5', '0']], ['Compute available stock before subtracting used items.', 'The result should never be negative under the stated input constraints.']),
    ],
];
