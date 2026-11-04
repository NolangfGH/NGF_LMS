<?php

return (static function (): array {
    $question = static fn (string $prompt, array $options, int $correct, string $why): array => compact('prompt', 'options', 'correct', 'why');
    $task = static function (string $title, string $objective, array $examples, array $hints, string $headers = ''): array {
        return [
            'title' => $title,
            'objective' => $objective."\nPrint exactly the required output without extra prompts. Explain the algorithm and two tests in your reflection. Submit your own code for teacher review.",
            'sample_input' => $examples[0][0], 'sample_output' => $examples[0][1],
            'test_cases' => array_map(static fn (array $example, int $index): array => ['input' => $example[0], 'output' => $example[1], 'hidden' => $index > 0, 'weight' => 1], $examples, array_keys($examples)),
            'hints' => $hints,
            'starter_code' => "#include <iostream>\n".$headers."\nint main() {\n    // Write and test your own solution.\n    return 0;\n}\n",
        ];
    };
    $article = static fn (string $title, string $introduction, array $prompts, string $teacher_notes): array => [
        'title' => $title, 'introduction' => $introduction, 'prompts' => $prompts, 'teacher_notes' => $teacher_notes, 'category' => 'C++ Basics',
    ];

    return [
        'get-started' => [
            'term' => 'Term1', 'difficulty' => 'Easy',
            'questions' => [
                $question('A program still prints an old message after you edit its source. What should you check first?', ['Whether the new source was saved and rebuilt', 'Whether your avatar changed', 'Whether every comment was removed', 'Whether the screen is green'], 0, 'An old executable can still contain the previous instructions.'),
                $question('Which activity is testing rather than compilation?', ['Translating source', 'Comparing actual output with predicted output', 'Saving a .cpp file', 'Opening an editor'], 1, 'Testing checks the program\'s observed behavior.'),
                $question('Which is a sensible beginner response to a compiler error?', ['Change every line at random', 'Delete the assignment', 'Read the first diagnostic and make a focused repair', 'Assume it is a score'], 2, 'A focused change makes the cause and effect easier to understand.'),
                $question('Which statement about an online compiler is correct?', ['It needs your account password inside the code', 'It proves every algorithm correct', 'It replaces all testing', 'It still needs valid source and appropriate test input'], 3, 'A compiler service cannot infer the behavior you intended.'),
                $question('Which information belongs in a practice example?', ['Fictional counts and test names', 'Your password', 'Private medical records', 'Another student\'s credentials'], 0, 'Use safe fictional test data.'),
            ],
            'assignment' => $task('Build-and-run greeting', 'Create, save, compile and run a complete C++ program displaying Build complete! and Ready to test on separate lines. No input is required.', [['', "Build complete!\nReady to test"]], ['Start execution in main.', 'Insert one newline between the messages.']),
            'challenge' => $task('Rebuild checklist', 'Display the three lines Save source, Compile source, Run tests in that order. Include a source comment explaining why rebuilding follows an edit. No input is required.', [['', "Save source\nCompile source\nRun tests"]], ['Use a complete program rather than output fragments.', 'Comments do not display automatically.']),
            'article' => $article('The code you edited is not always the code you ran', 'A learner changes a greeting but the console still shows the old message. The problem may be the build process rather than the output instruction. Saving source, compiling it and running the resulting executable are separate steps. A short checklist can make the process repeatable and reduce confusion.', ['Describe a save, compile and run checklist in your own words.', 'Explain why a program that compiles still needs a test with predicted output.'], 'Look for the distinction between source and executable, rebuilding after edits, and comparing observed behavior with an expectation. Do not award correctness merely for listing tool names.'),
        ],
        'syntax' => [
            'term' => 'Term1', 'difficulty' => 'Easy',
            'questions' => [
                $question('Which is a complete valid output statement?', ['std::cout << "Ready";', 'std::cout >> "Ready";', 'std::cin << "Ready";', 'std::cout << Ready'], 0, 'cout uses <<, the literal needs quotes, and the statement needs a semicolon.'),
                $question('Which directive makes iostream declarations available?', ['import main;', '#include <iostream>', 'using iostream()', 'return iostream;'], 1, 'The include directive names the header.'),
                $question('Where should the output statements in a simple program be placed?', ['Outside every function body', 'Inside the include filename', 'Inside main\'s braces', 'Inside a quoted comment only'], 2, 'Statements execute in the function body.'),
                $question('Which spelling is the expected entry function name?', ['Main', 'MAIN', 'start', 'main'], 3, 'The lowercase spelling main is required for this entry function.'),
                $question('A diagnostic points to the line after an output statement. What is useful to inspect?', ['The preceding line for a missing quote or semicolon', 'Only the student\'s nickname', 'The browser wallpaper', 'The leaderboard order'], 0, 'A missing delimiter can affect how the following line is parsed.'),
            ],
            'assignment' => $task('Assemble a complete program', 'Create a valid C++ program with iostream, main, matching braces and an output statement. Display Syntax checked exactly. No input is required.', [['', 'Syntax checked']], ['Use std::cout << with a quoted literal.', 'Check the semicolon and both braces.']),
            'challenge' => $task('Two correct statements', 'Create a complete program displaying Statement 1 and Statement 2 on separate lines. Use two output statements and correct punctuation. No input is required.', [['', "Statement 1\nStatement 2"]], ['Put both statements inside main.', 'Use a newline after the first message.']),
            'article' => $article('Punctuation is part of your program\'s meaning', 'Braces, quotation marks and semicolons help the compiler identify what belongs together. Indentation makes those relationships visible to people, but it cannot replace the required punctuation. A good debugging habit is to inspect the first diagnostic and the nearby source before making one careful change.', ['Explain the different jobs of braces and semicolons in a small program.', 'Write a short example of a punctuation error and describe how you would repair and test it.'], 'Look for a valid distinction between a block and a statement, plus a focused repair. Check the learner\'s proposed source rather than accepting an untested claim.'),
        ],
        'comments' => [
            'term' => 'Term1', 'difficulty' => 'Easy',
            'questions' => [
                $question('What prints from std::cout << 5; // add 2 later ?', ['5', '7', 'add 2 later', 'Nothing'], 0, 'The comment changes neither the expression nor the output.'),
                $question('When does a // comment finish?', ['At the next opening brace only', 'At the end of the source line', 'At the end of every file only', 'At the first space'], 1, 'A line comment lasts through its source line.'),
                $question('Which text is a valid block comment?', ['// note */', '<!-- note -->', '/* note */', '# note #'], 2, 'Both block delimiters are present.'),
                $question('What should happen to a misleading comment after changing an algorithm?', ['Keep it forever', 'Treat it as executable input', 'Print it as proof', 'Update or remove it so it matches the code'], 3, 'Stale comments can mislead future readers.'),
                $question('What is unsafe to put in a source comment?', ['A real account password', 'A boundary-test explanation', 'A formula\'s purpose', 'A note about units'], 0, 'Source and submissions may be shared with authorized reviewers, so credentials do not belong there.'),
            ],
            'assignment' => $task('Comment a supplies calculation', 'Declare 3 groups and 4 markers per group, calculate their product, and print 12. Add a line comment explaining what the quantities represent. No input is required.', [['', '12']], ['The comment should explain intent.', 'Use actual arithmetic, not only a hard-coded answer.']),
            'challenge' => $task('Readable build checklist', 'Print Read, Code and Test on three lines. Include both a single-line comment and a block comment explaining the program. No input is required.', [['', "Read\nCode\nTest"]], ['Only output statements should produce text.', 'Keep both block-comment markers visible.']),
            'article' => $article('A useful comment explains a decision', 'Imagine reading a calculation several weeks after you wrote it. A comment saying multiply the numbers repeats what the code already shows. A comment explaining why every team receives four markers preserves the reason behind the formula. Good comments make assumptions visible and must change when those assumptions change.', ['Write one useful comment for a classroom supplies calculation.', 'Explain why comments alone cannot prove that a program is correct.'], 'Look for an explanation of purpose, units or a boundary. Encourage a test with expected and actual output; comments themselves are not executable evidence.'),
        ],
        'data-types' => [
            'term' => 'Term1', 'difficulty' => 'Easy',
            'questions' => [
                $question('What is the integer result of 9 / 2?', ['4', '4.5', '5', '1'], 0, 'Integer division discards the fractional part.'),
                $question('Which declaration stores true or false?', ['char ready = "yes";', 'bool ready = true;', 'void ready = 1;', 'return ready;'], 1, 'bool is the Boolean type.'),
                $question('Which declaration initializes a single character?', ['char letter = "B";', 'string letter = B;', "char letter = 'B';", 'int letter = "B";'], 2, 'A character literal is written with single quotes.'),
                $question('What type does auto ratio = 2.5; deduce?', ['int', 'bool', 'char', 'double'], 3, 'An unsuffixed fractional literal such as 2.5 has type double.'),
                $question('Can auto make a variable change type every time it is assigned?', ['No; the deduced type stays fixed', 'Yes, it is dynamically typed', 'Only after an if statement', 'Only in Code Lab'], 0, 'auto deduces a static type from the initializer.'),
            ],
            'assignment' => $task('Decimal cost', 'Read a quantity (integer 0 to 100) and a unit price (nonnegative number up to 100). Use int and double appropriately. Print the product with exactly two digits after the decimal point.', [['3 2.50', '7.50'], ['0 4.25', '0.00'], ['4 1.75', '7.00']], ['Include iomanip and use std::fixed with std::setprecision(2).', 'Keep the unit price in a fractional type.'], "#include <iomanip>\n"),
            'challenge' => $task('Typed status card', 'Read a small integer count, a single uppercase character, and a Boolean input represented by 0 or 1. Print twice the count, then the character, then the Boolean as 0 or 1, each on its own line.', [['4 B 1', "8\nB\n1"], ['0 A 0', "0\nA\n0"], ['7 C 1', "14\nC\n1"]], ['Declare one int, one char and one bool.', 'Do not enable boolalpha for this output contract.']),
            'article' => $article('Choosing a type is choosing what a value means', 'A count, an average, an initial and a readiness flag describe different kinds of information. Choosing a suitable type helps communicate that meaning. Integer arithmetic can discard fractions, and auto infers a type rather than allowing it to change later. Explain both the type and the test values when reviewing a calculation.', ['Choose types for a team count, a fractional average, a first initial and a ready flag, and justify them.', 'Explain why 7 / 2 and 7.0 / 2 give different results.'], 'Expect int, a suitable floating-point type, char and bool, plus recognition of integer versus floating-point division. Accept justified alternatives within the task\'s ranges.'),
        ],
        'math' => [
            'term' => 'Term2', 'difficulty' => 'Mid',
            'questions' => [
                $question('What is std::floor(3.9)?', ['3', '4', '0', '3.9'], 0, 'floor rounds toward negative infinity.'),
                $question('What is std::floor(-2.2)?', ['-2', '-3', '2', '3'], 1, 'The lower integer toward negative infinity is -3.'),
                $question('Which input is within the real square-root domain for these tasks?', ['-9.0', '-1.0', '0.0', '-0.5'], 2, 'Zero and positive numbers are valid real square-root inputs.'),
                $question('Which header provides std::min and std::max?', ['<string>', '<fstream>', '<iomanip>', '<algorithm>'], 3, 'These generic selection algorithms are declared in algorithm.'),
                $question('What is std::max(4, 9)?', ['9', '4', '13', '5'], 0, 'max selects the larger value.'),
            ],
            'assignment' => $task('Square-root station', 'Read a perfect-square integer from 0 through 10000. Use std::sqrt and print its nonnegative square root without a label.', [['81', '9'], ['0', '0'], ['144', '12']], ['Include cmath.', 'The input guarantee avoids fractional square-root output.'], "#include <cmath>\n"),
            'challenge' => $task('How many six-seat rows?', 'Read an integer student count from 0 through 120. Each row has 6 seats. Use std::ceil with floating-point division to calculate the minimum number of rows and print it.', [['13', '3'], ['0', '0'], ['12', '2']], ['Divide by 6.0 before calling ceil.', 'Test zero and an exact multiple of six.'], "#include <cmath>\n"),
            'article' => $article('Rounding should follow the classroom problem', 'Thirteen students need three six-seat rows, even though division produces a little more than two. Another task might require rounding down instead. A mathematical function is useful only when its behavior matches the rule. State the rule first, then test zero, an exact division and a value just beyond that boundary.', ['Explain why ceil is appropriate for allocating six-seat rows.', 'Predict the row counts for 0, 12 and 13 students and justify each.'], 'Expect 0, 2 and 3 rows with floating-point division before ceil. Probe why integer division before rounding loses the needed fractional information.'),
        ],
        'booleans' => [
            'term' => 'Term2', 'difficulty' => 'Easy',
            'questions' => [
                $question('What is true && false?', ['false', 'true', '2', 'A string'], 0, 'AND requires both conditions to be true.'),
                $question('What is false || true?', ['false', 'true', '-1', 'An address'], 1, 'OR is true when at least one operand is true.'),
                $question('Which expression compares a and b for inequality?', ['a = b', 'a == b', 'a != b', 'a + b'], 2, '!= tests whether values differ.'),
                $question('What does std::cout << std::boolalpha << true; display?', ['1', '0', 'yes', 'true'], 3, 'boolalpha selects textual Boolean output.'),
                $question('For a minimum score of 60, which inputs best check the boundary?', ['59, 60, 61', '90 only', '1, 2, 3', '100 only'], 0, 'These values cover just below, at and above the threshold.'),
            ],
            'assignment' => $task('Pass flag', 'Read an integer score from 0 through 100. Store whether it is at least 60 in a bool. Print 1 when true and 0 when false.', [['60', '1'], ['59', '0'], ['100', '1']], ['Compare with >=, not =.', 'Print the bool without boolalpha.']),
            'challenge' => $task('Both conditions required', 'Read an integer score from 0 through 100 and a ready flag represented by 0 or 1. Print 1 only when the score is at least 60 AND the ready flag is true; otherwise print 0.', [['60 1', '1'], ['59 1', '0'], ['80 0', '0']], ['Use && to combine the requirements.', 'Check a case where each requirement fails separately.']),
            'article' => $article('Translate a rule into a true-or-false question', 'A classroom rule may require both a completed task and a readiness confirmation. Logical AND captures both, while OR would permit only one. Before writing a condition, describe what should happen when each requirement is missing. Small truth tables reveal mistakes that a single happy-path example can hide.', ['Explain the difference between AND and OR using two classroom requirements.', 'Give one true case and two distinct false cases for score >= 60 AND ready.'], 'Look for both requirements in the true case and independent failures in false cases. Do not confuse assignment with equality or AND with OR.'),
        ],
        'switch' => [
            'term' => 'Term2', 'difficulty' => 'Mid',
            'questions' => [
                $question('Which is a valid case label for an integer switch?', ['case 2:', 'case choice > 2:', 'case "two":', 'case 2.5:'], 0, 'An integer constant is a valid label for an integer selector.'),
                $question('What does choice = 1 print in the worked example?', ['Code', 'Read', 'Both', 'Nothing'], 1, 'Case 1 prints Read and then breaks.'),
                $question('What does choice = 7 print in the worked example?', ['Read', 'Code', 'Choose 1 or 2', '7'], 2, 'No case matches, so default executes.'),
                $question('Which situation is usually clearer with if/else than switch?', ['A two-value enum', 'Menu choices 1, 2 and 3', 'Single-character commands', 'A comparison such as temperature >= 30'], 3, 'An arbitrary range condition fits if/else.'),
                $question('What does break in a switch nested inside a loop exit?', ['The switch only', 'Every surrounding loop', 'The entire operating system', 'The source editor'], 0, 'break exits the nearest enclosing loop or switch.'),
            ],
            'assignment' => $task('Learning menu', 'Read an integer menu choice. Use switch: 1 prints Read, 2 prints Code, 3 prints Review, and every other integer prints Unknown. Print exactly one word.', [['2', 'Code'], ['1', 'Read'], ['3', 'Review'], ['9', 'Unknown']], ['Include breaks after the numbered cases.', 'Use default for other numbers.']),
            'challenge' => $task('Command dispatcher', 'Read one uppercase character. Use switch so R prints Read lesson, C prints Open Code Lab, Q prints Take quiz, and every other character prints Unknown command.', [['C', 'Open Code Lab'], ['R', 'Read lesson'], ['Q', 'Take quiz'], ['X', 'Unknown command']], ['Use single-quoted character case labels.', 'Check that only one branch prints.']),
            'article' => $article('A menu is a set of explicit choices', 'A small classroom console menu is a good place to use switch. Each accepted command has a clearly named action, and default explains what happens to an unsupported command. Missing breaks can make a single selection execute several actions. Test every supported choice and at least one unsupported value.', ['Describe the role of default in a learning menu.', 'Explain what missing break statements can do, and propose a test exposing the problem.'], 'Look for fallback handling and an accurate account of fall-through. A strong test selects an early case and checks that later actions do not appear.'),
        ],
        'break-continue' => [
            'term' => 'Term2', 'difficulty' => 'Mid',
            'questions' => [
                $question('In nested loops, which loop does an inner break normally exit?', ['The nearest inner loop', 'All loops', 'main in every case', 'No loop'], 0, 'break affects the nearest enclosing loop or switch.'),
                $question('Which situation is suitable for continue?', ['End the entire program', 'Skip a disallowed value and examine the next value', 'Free dynamic memory automatically', 'Declare a string type'], 1, 'continue skips the remaining work for one iteration.'),
                $question('What risk appears if while-loop continue skips its only counter update?', ['It guarantees a perfect score', 'The loop always runs once', 'The loop may never reach its stopping condition', 'The compiler adds the update'], 2, 'The state may stop progressing.'),
                $question('What values print in the worked example?', ['1 2 3 4 5 6', '2 5', '1 3 4 5', '1 3 4'], 3, 'Two is skipped; five exits the loop before printing.'),
                $question('Which choice best makes control flow understandable?', ['Use a clear condition and explain each early exit', 'Add jumps randomly', 'Hide every update', 'Avoid tracing'], 0, 'Explicit conditions and a trace help readers verify behavior.'),
            ],
            'assignment' => $task('Skip multiples of three', 'Read an integer n from 0 through 20. Use a loop and continue to skip multiples of 3. Sum the remaining integers from 1 through n inclusive and print the sum.', [['7', '19'], ['0', '0'], ['3', '3']], ['Test divisibility with i % 3 == 0.', 'Initialize the sum to zero.']),
            'challenge' => $task('Stop before a negative value', 'Read up to five integers. Stop immediately when the first negative value is read. Skip zeros using continue, add preceding positive values, and print their total. Each input value is between -100 and 100.', [['3 0 4 -1 9', '7'], ['-1 2 3 4 5', '0'], ['1 2 3 4 5', '15']], ['Read inside a loop of at most five iterations.', 'Check the negative stopping case before adding a value.']),
            'article' => $article('Skipping one item is different from stopping the search', 'Suppose a program reads classroom measurements. A zero may mean there is nothing to add, while a negative sentinel means the data has ended. continue and break express these different decisions. A trace should show which values were added, skipped and never processed.', ['Explain why zero and a negative sentinel need different actions in the sample task.', 'Trace 3, 0, 4, -1, 9 and explain why the total is 7.'], 'Expect zero to skip one iteration and the negative sentinel to end processing before 9. Watch for adding the sentinel or failing to update a while-loop counter.'),
        ],
        'structures' => [
            'term' => 'Term3', 'difficulty' => 'Mid',
            'questions' => [
                $question('Which is a suitable pair of members for one supply record?', ['A text label and integer quantity', 'Two unrelated passwords', 'Only a source filename', 'A comment without data'], 0, 'Members should describe related aspects of one entity.'),
                $question('Which declaration creates an object of type Supply?', ['struct = Supply;', 'Supply item;', 'item Supply(); always', 'new type item;'], 1, 'The type name followed by the variable name declares an object.'),
                $question('Do two distinct Supply objects have to share the same quantity?', ['Always', 'Only if they are local', 'No, each object has its own member values', 'Only if string is included'], 2, 'Each instance stores its own state.'),
                $question('Which syntax initializes label and quantity for the example type?', ['Supply item("Markers" = 4);', 'Supply = item;', 'item[Markers][4];', 'Supply item{"Markers", 4};'], 3, 'Brace initialization supplies the members in declaration order.'),
                $question('How is a structure different from an ordinary homogeneous array?', ['It has named members that may use different types', 'It can never contain an int', 'It is always a comment', 'It has no objects'], 0, 'A structure models related named fields rather than only indexed same-type elements.'),
            ],
            'assignment' => $task('Supply record', 'Define struct Supply with a std::string label and an int quantity. Read one word and a quantity from 0 through 100. Store both in a Supply object and print the label and quantity on separate lines.', [['Markers 4', "Markers\n4"], ['Books 0', "Books\n0"], ['Cards 12', "Cards\n12"]], ['Declare the type before main.', 'Use the dot operator to read and print members.'], "#include <string>\n"),
            'challenge' => $task('Two-record cost', 'Define a structure with integer quantity and unitCost members. Read those two fields for each of two objects. Each value is from 0 through 100. Calculate the combined cost using the stored members and print only the total.', [['3 4 2 5', '22'], ['0 4 0 8', '0'], ['1 7 3 2', '13']], ['Each object represents a separate supply item.', 'Add quantity * unitCost from both objects.']),
            'article' => $article('A record keeps related information together', 'A label and quantity describe one supply item. A structure lets a program keep that relationship explicit instead of scattering unrelated variables through a file. Separate objects represent separate items, while meaningful member names explain how each value should be used.', ['Design a small Supply structure and explain each member\'s type.', 'Explain how two objects of the same structure type can hold different values.'], 'Look for a coherent record, appropriate types and correct member access. Distinguish a type definition from an actual object and check that multiple objects are independent.'),
        ],
        'enums' => [
            'term' => 'Term3', 'difficulty' => 'Mid',
            'questions' => [
                $question('For enum Color { Red, Blue, Green };, what is Green by default?', ['2', '3', '1', '-1'], 0, 'The values begin 0, 1, 2.'),
                $question('For enum Rank { First = 5, Next };, what is Next?', ['0', '6', '5', '10'], 1, 'The following implicit value increases the preceding value by one.'),
                $question('Which declaration defines a scoped enumeration?', ['enum = Stage;', 'class enum Stage()', 'enum class Stage { Read, Code };', 'int enum = 2;'], 2, 'enum class defines scoped enumerators.'),
                $question('What should happen before mapping a user number to an accepted menu state?', ['Trust every number', 'Delete default handling', 'Print a secret', 'Validate the supported choices'], 3, 'External input may be outside the defined menu choices.'),
                $question('Which statement about displaying enum values is correct?', ['Map a value to display text; names do not print themselves', 'cout always prints the enumerator name', 'Enums can only print in images', 'A source comment prints the name'], 0, 'Choose meaningful text explicitly.'),
            ],
            'assignment' => $task('Named learning stage', 'Define enum class Stage with Read, Code and Review. Read an integer: map 1 to Read, 2 to Code and 3 to Review. Print that stage name. For any other integer, print Unknown without treating it as a valid stage.', [['2', 'Code'], ['1', 'Read'], ['3', 'Review'], ['8', 'Unknown']], ['Validate the numeric menu before selecting a named value.', 'Use Stage::Code-style qualification.']),
            'challenge' => $task('Named access levels', 'Define enum class Access with Visitor, Member and Captain. Read 0, 1 or 2 and map it to the corresponding named value. Print 0 for Visitor, 10 for Member and 20 for Captain. Print Invalid for any other input. This is a fictional console exercise, not a real authorization system.', [['2', '20'], ['0', '0'], ['1', '10'], ['5', 'Invalid']], ['Keep input validation separate from enum selection.', 'Select the display value with switch or clear comparisons.']),
            'article' => $article('Named states explain what a number cannot', 'The number 2 could mean a score, a count or a menu command. A name such as Stage::Code explains the intended state. Enumerations make a small set of choices easier to read, but they do not automatically validate external input or display their own labels.', ['Explain how an enum can make a small learning menu clearer.', 'Describe what should happen to an unsupported input such as 99.'], 'Expect named supported choices, validation and explicit fallback behavior. Distinguish enum class from freely interchangeable integer values.'),
        ],
        'references' => [
            'term' => 'Term3', 'difficulty' => 'Mid',
            'questions' => [
                $question('After int a = 3; int& r = a; r += 2;, what is a?', ['5', '3', '2', '0'], 0, 'r aliases a, so adding two updates a.'),
                $question('Which ordinary reference declaration is initialized correctly?', ['int& r;', 'int& r = a;', 'int r& a;', 'reference int = a;'], 1, 'The reference is bound to an existing compatible object.'),
                $question('For a valid reference r to a, what is &r == &a?', ['Always false', 'A filename', 'true', 'A syntax error in every case'], 2, 'Both expressions obtain the same referred-to object\'s address.'),
                $question('What does const int& r = a prevent through r?', ['Reading a', 'Declaring any integer', 'Compiling main', 'Changing the referred-to int through r'], 3, 'The const reference provides read-only access through that alias.'),
                $question('Which is a safe lifetime rule for references?', ['The referred-to object must still exist', 'References make every temporary live forever', 'A reference repairs deleted storage', 'Lifetime never matters'], 0, 'Using a reference after its object\'s lifetime ends is invalid.'),
            ],
            'assignment' => $task('Update through an alias', 'Read an integer n from -100 through 100. Bind an int reference to it, add 5 through that reference, and print the original variable\'s updated value.', [['7', '12'], ['0', '5'], ['-8', '-3']], ['Initialize the reference when declaring it.', 'The output must come from the original variable.']),
            'challenge' => $task('Swap with aliases', 'Read two integers from -100 through 100. Bind a reference to each variable. Swap their values using the references and a temporary variable. Print the two original variables in their new order separated by one space.', [['4 9', '9 4'], ['0 0', '0 0'], ['-2 5', '5 -2']], ['Save one old value before overwriting it.', 'Assigning through a reference changes the original object.']),
            'article' => $article('Two names can refer to one value', 'An alias is useful when a function or another part of a program should work with an existing object. It is different from a copy: changing the alias changes the original value. A reference also depends on the original object remaining alive. An address comparison can demonstrate identity without expecting a fixed machine address.', ['Trace an integer changed through a reference and give the final original value.', 'Explain the difference between copying a value and creating an alias.'], 'Look for shared identity rather than two independent integers. Accept a trace such as a = 3, r aliases a, r += 2, a = 5. Address text itself is not a portable answer.'),
        ],
        'pointers' => [
            'term' => 'Term3', 'difficulty' => 'Hard',
            'questions' => [
                $question('Which declaration initializes a pointer to integer count?', ['int* p = &count;', 'int p = *count;', 'pointer p = count;', 'int& p = nullptr;'], 0, 'The pointer stores the address of a compatible live int.'),
                $question('What does p = &other; change?', ['The type of all integers', 'The object address stored in p', 'The source filename', 'The value of every object'], 1, 'The pointer is redirected to other.'),
                $question('What is a suitable initial value for a pointer with no destination yet?', ['An arbitrary address', 'An uninitialized value', 'nullptr', 'The word secret'], 2, 'nullptr represents no pointed-to object; do not dereference it.'),
                $question('Can a non-null pointer still be invalid?', ['No', 'Only if cout is missing', 'Only for strings', 'Yes, for example after the object\'s lifetime ends'], 3, 'Non-null does not prove valid lifetime or storage.'),
                $question('Which assignment changes the pointed-to integer?', ['*p = 6;', 'p = &other;', 'p = nullptr;', 'int* q = p;'], 0, 'Dereferencing selects the pointee for assignment.'),
            ],
            'assignment' => $task('Double through a pointer', 'Read an integer from -100 through 100. Store its address in a pointer, double the value through the pointer, and print the original variable.', [['7', '14'], ['0', '0'], ['-4', '-8']], ['Use & to obtain the variable\'s address.', 'Use * to access the pointed-to integer.']),
            'challenge' => $task('Redirect a valid pointer', 'Read integers a and b from -100 through 100. Point to a and print its value. Redirect the same pointer to b, add a to the pointed-to value, and print the updated b on a new line.', [['4 7', "4\n11"], ['0 3', "0\n3"], ['-2 5', "-2\n3"]], ['Changing p and changing *p have different effects.', 'Both local integers stay alive during the operations.']),
            'article' => $article('An address and a value are different pieces of information', 'A pointer stores where an object is located; dereferencing accesses that object. Changing the address stored in a pointer selects a different destination, while assigning through the pointer changes a value. These operations are useful only when the destination is valid and its lifetime has not ended.', ['Explain p = &other and *p = 6 using different words for their effects.', 'Give two reasons why dereferencing a pointer might be unsafe.'], 'Expect redirection versus pointee assignment, and risks such as nullptr, uninitialized pointers or ended object lifetimes. Do not treat a non-null address as proof of safety.'),
        ],
        'memory-management' => [
            'term' => 'Term3', 'difficulty' => 'Hard',
            'questions' => [
                $question('What is a memory leak in this lesson\'s ownership context?', ['Losing access to an allocation without releasing it', 'Printing a newline', 'Calling main', 'Using a local initialized int'], 0, 'The owned allocation remains unreleased when its address is lost.'),
                $question('What is double deletion?', ['Declaring two local variables', 'Releasing the same dynamic allocation twice', 'Printing two integers', 'Using two comments'], 1, 'An allocation must not be deleted more than once.'),
                $question('After deleting an object, setting one pointer to nullptr does what?', ['Repairs every alias', 'Creates a new object', 'Clears that pointer only', 'Makes old dereferences safe'], 2, 'Other aliases can still contain invalid old addresses.'),
                $question('Which is a safer usual choice for a small fixed local count?', ['An arbitrary address', 'A huge new array', 'An unreleased pointer', 'An ordinary local variable'], 3, 'Use the simplest lifetime and ownership that meets the task.'),
                $question('Which smart pointer represents exclusive single-object ownership?', ['std::unique_ptr', 'std::string', 'std::cout', 'std::ceil'], 0, 'unique_ptr manages exclusive ownership and releases its owned object automatically.'),
            ],
            'assignment' => $task('Allocate, use, release', 'Read a small integer n from -100 through 100. Allocate one int initialized to n using new. Increase the dynamic value by 1, print it, then release it exactly once with delete. Do not access it after release.', [['4', '5'], ['0', '1'], ['-3', '-2']], ['Keep ownership in one clearly named pointer.', 'Print before delete; set your pointer to nullptr afterwards.']),
            'challenge' => $task('Three-element dynamic total', 'Allocate an int array of exactly three elements using new[]. Read three integers from -100 through 100 into it. Calculate and print the sum, then release the array exactly once with delete[]. Explain why a standard container would normally be preferable.', [['3 4 5', '12'], ['0 0 0', '0'], ['-3 2 8', '7']], ['Only indexes 0, 1 and 2 are valid.', 'Match the array allocation with delete[], not delete.']),
            'article' => $article('Correct output does not prove correct ownership', 'A program can print a plausible answer and still leak an allocation or use a released object. Learning dynamic storage requires a second kind of trace: who owns each object, when it is created, and when its lifetime ends. For everyday code, simpler automatic objects, standard containers and appropriate smart pointers avoid many manual ownership mistakes.', ['Describe the lifetime of a single int created with new and released with delete.', 'Explain why correct output alone cannot rule out a leak or use-after-release.'], 'Expect one matching release, no access after release, and recognition of double deletion or lost ownership. Remind learners that nullptr clears only that pointer, not other aliases.'),
        ],
        'function-parameters' => [
            'term' => 'Term3', 'difficulty' => 'Hard',
            'questions' => [
                $question('What does supplies(3, 2) return in the worked example?', ['6', '12', '5', '2'], 0, 'The explicit second argument replaces the default and 3 times 2 is 6.'),
                $question('Changing an int value parameter normally changes what?', ['The caller\'s original int', 'The function\'s local copy', 'Every global variable', 'The source header'], 1, 'An ordinary int parameter receives a copy.'),
                $question('Which declaration gives a valid trailing default parameter?', ['int add(int a = 2, int b);', 'int add(default a, b);', 'int add(int a, int b = 2);', 'int add(int = default);'], 2, 'In this simple declaration, the defaulted parameter follows the required one.'),
                $question('Why pass a length with const int values[]?', ['It is needed only for comments', 'It changes all values to strings', 'It prints the array automatically', 'The parameter behaves as a pointer and lacks the original array bound'], 3, 'The function needs the valid element count to iterate safely.'),
                $question('Which parameter provides read-only access to an existing Record without copying it?', ['const Record& record', 'Record record in all cases', 'int record', 'void record'], 0, 'A const reference avoids a copy and does not permit mutation through the parameter.'),
            ],
            'assignment' => $task('Supplies with a default', 'Define int supplies(int teams, int perTeam = 4). Read teams and perTeam, each from 0 through 20. Print supplies(teams), then supplies(teams, perTeam), on separate lines. The function must return the product rather than printing internally.', [['3 2', "12\n6"], ['0 5', "0\n0"], ['5 3', "20\n15"]], ['Put the function definition above main or declare it first.', 'The first call deliberately omits the second argument.']),
            'challenge' => $task('Record, array and reference helpers', 'Define struct Record containing int values[3]. Read three integers from -100 through 100. Define int sum(const int values[], int length), and int score(const Record& record) that calls sum for its three values. Define void addBonus(int& total, int bonus = 2), call it with the default bonus on the computed score, and print only the final total.', [['3 4 5', '14'], ['0 0 0', '2'], ['-2 1 6', '7']], ['Pass the valid length 3 into the raw-array helper.', 'Use a const reference for the record and a mutable reference only for total.']),
            'article' => $article('A function contract explains inputs and effects', 'A useful function contract says what values enter, what result returns and whether anything owned by the caller changes. A value parameter is a copy; a reference can alias the caller\'s object. Defaults simplify a call, while an array count prevents a helper from guessing how many elements exist. Read-only structure references can avoid copying without allowing mutation.', ['Compare a value parameter, a mutable reference and a const reference.', 'Explain why a raw-array helper should receive a length and why cout is different from return.'], 'Expect accurate copying and aliasing behavior, a valid element count and distinction between returning a value and displaying it. Ask for a small traced call rather than only definitions.'),
        ],
    ];
})();
