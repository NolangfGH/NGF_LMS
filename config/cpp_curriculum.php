<?php

return (static function (): array {
    $legacy = require __DIR__.'/legacy_lessons.php';
    $section = static fn (string $title, string $text): array => compact('title', 'text');
    $question = static fn (string $prompt, array $options, int $correct, string $why): array => compact('prompt', 'options', 'correct', 'why');
    $program = static fn (string $body, string $headers = ''): string => "#include <iostream>\n".$headers."\nint main() {\n".$body."\n    return 0;\n}\n";
    $make = static fn (string $title, string $objective, array $sections, string $code, string $output, array $checks, string $mistake): array => [
        'title' => 'C++ '.$title, 'topic' => $title, 'objective' => $objective, 'sections' => $sections,
        'code' => $code, 'output' => $output, 'checks' => $checks, 'mistake' => $mistake,
        'minutes' => 12, 'icon' => 'terminal', 'version' => 2,
    ];

    $lessons = $legacy;
    $lessons['get-started'] = $make('Get Started', 'Describe the edit, compile and run cycle, then build a small C++ console program.', [
        $section('Choose a safe workspace', 'A source editor lets you write text; a compiler translates C++ into an executable. An IDE brings these tools together. CodeQuest Code Lab saves source and uses the school-configured isolated compiler when available. Use fictional data rather than passwords or private records.'),
        $section('Save, compile, run', 'Save source with a .cpp extension, for example main.cpp. Compile, read diagnostic messages, fix the first error, and compile again. Run the new executable after a successful build. Changing source does not change an executable built earlier.'),
        $section('Test what you meant', 'A successful build does not prove an algorithm is correct. Predict output, run the program, and compare the actual output with your prediction, including spaces and line breaks. Use small normal and boundary examples.'),
    ], $program('    std::cout << "Build complete!";'), 'Build complete!', [
        $question('Which file extension is commonly used for C++ source?', ['.cpp', '.jpg', '.mp3'], 0, 'A .cpp file contains C++ source text.'),
        $question('What translates C++ source into a runnable program?', ['A leaderboard', 'A compiler', 'A nickname'], 1, 'A compiler checks and translates the source.'),
        $question('You change a source file. What should you do before testing the new behavior?', ['Run only the old executable', 'Delete the source', 'Compile the updated source'], 2, 'Rebuilding produces an executable containing the new instructions.'),
        $question('What should follow successful compilation?', ['Run tests and compare output with expected results', 'Assume the algorithm is always correct', 'Share your password'], 0, 'Tests check behavior that compilation alone cannot establish.'),
        $question('What is an IDE?', ['A kind of integer', 'An environment combining programming tools', 'The output of every program'], 1, 'An IDE commonly combines editing, build and debugging tools.'),
    ], 'Do not test an old executable and assume it contains your latest edits. Save and rebuild first.');

    $lessons['syntax'] = $make('Syntax & Statements', 'Identify main, headers, braces and statements, and repair basic punctuation mistakes.', [
        $section('Program structure', '#include <iostream> makes standard stream declarations available. Execution begins in main. Its braces group the function body. std::cout names standard output without importing the entire std namespace.'),
        $section('Statements and punctuation', 'A simple output statement ends with a semicolon. A block uses matching braces. Text literals use double quotes. C++ is case sensitive: Main and main differ. Indentation helps readers, but braces determine a block.'),
        $section('Repair a diagnostic', 'A missing quote or semicolon may produce a diagnostic on the following line. Inspect that line and the preceding line. Make one repair, rebuild and test. Assemble a complete program in the coding task.'),
    ], $program('    std::cout << "Syntax checked";'), 'Syntax checked', [
        $question('Which function is the entry point of this console program?', ['main', 'cout', 'include'], 0, 'Execution begins in main.'),
        $question('What normally terminates a simple output statement?', ['A comma', 'A semicolon', 'A colon'], 1, 'An expression statement ends with a semicolon.'),
        $question('Which pair groups the body of main?', ['< and >', '( and ) only', '{ and }'], 2, 'Braces delimit a block of statements.'),
        $question('Which name is the standard output stream?', ['std::cout', 'std::Cout', 'std::main'], 0, 'Names are case sensitive, so cout needs the correct spelling.'),
        $question('Why include <iostream> here?', ['To create an image', 'To use standard stream declarations', 'To end the program'], 1, 'The header declares facilities such as std::cout.'),
    ], 'A missing semicolon after a statement matters; adding a semicolon after every opening brace is not a repair strategy.');

    $lessons['comments'] = $make('Comments', 'Use single-line and block comments to explain intent without changing program output.', [
        $section('Single-line comments', '// starts a comment ending at the source line end. Explain an assumption, decision or test. A comment is not an output instruction: use cout to display information.'),
        $section('Block comments', '/* and */ delimit a block comment spanning one or more lines. Ordinary block comments do not nest. Keep the closing marker visible so working code is not accidentally commented out.'),
        $section('Explain why', 'Explain why a boundary or formula was chosen rather than repeating each statement. Update comments when the algorithm changes. Never put passwords or private student information into source comments.'),
    ], $program('    // Each group receives four markers.
    int groups = 3;
    std::cout << groups * 4;'), '12', [
        $question('Which characters start a single-line C++ comment?', ['//', '**', '##'], 0, 'Two slashes start a comment lasting to the end of the line.'),
        $question('Which markers delimit a block comment?', ['<!-- and -->', '/* and */', '( and )'], 1, 'C++ block comments start with /* and end with */.'),
        $question('What does // Print 7 display by itself?', ['7', 'Print 7', 'Nothing'], 2, 'A source comment is not an output statement.'),
        $question('Which is the most useful comment?', ['// Stop at 30 because the classroom limit is 30.', '// x is x.', '// secret password: ...'], 0, 'Explaining the reason for a boundary helps a reader understand it.'),
        $question('Can ordinary C++ block comments nest?', ['Always', 'No', 'Only after cout'], 1, 'An inner closing marker ends the block.'),
    ], 'A comment claiming a calculation is correct does not test it. Compare output with expected values.');

    $lessons['data-types'] = $make('Data Types', 'Choose number, Boolean, character and string types, and explain basic auto deduction.', [
        $section('Whole and fractional numbers', 'int holds suitable whole-number values; double can represent fractions. Integer 7 / 2 gives 3, while 7.0 / 2 gives 3.5. Exact type ranges and some sizes depend on the implementation.'),
        $section('Booleans and characters', 'bool stores true or false. char represents one character, written with single quotes, for example char initial = \'N\';. A string literal uses double quotes. Without boolalpha, cout normally displays bool as 1 or 0.'),
        $section('Strings and auto', 'Include <string> for std::string. auto count = 3 deduces int; auto price = 3.5 deduces double. The deduced type is static: auto does not let a variable change its type later.'),
        $section('Real-life type choices', 'A class-size count needs whole numbers, an average may need a fraction, and a ready flag needs true or false. Initialize every value and use a range suitable for the problem. These exercises use small inputs.'),
    ], $program('    int count = 4;
    double average = 7.0 / 2;
    char group = \'B\';
    bool ready = true;
    auto extra = 2;
    std::cout << count + extra << "\\n" << average << "\\n" << group << "\\n" << ready;'), "6\n3.5\nB\n1", [
        $question('Which type suits a small whole-number count?', ['int', 'void', 'return'], 0, 'int suits small integral counts.'),
        $question('Which expression produces 3.5 rather than integer 3?', ['7 / 2', '7.0 / 2', '7 % 2'], 1, 'A double operand makes this floating-point division.'),
        $question('Which literal is a character?', ['"A"', 'true', "'A'"], 2, 'A character literal uses single quotes.'),
        $question('What type does auto count = 3; deduce?', ['int', 'std::string', 'bool'], 0, 'The initializer 3 is an int literal.'),
        $question('Which header provides std::string?', ['<cmath>', '<string>', '<iomanip>'], 1, 'The string header supplies std::string.'),
    ], 'Do not assume all number types have the same range or use integer division when a fraction is required.');

    $lessons['math'] = $make('Math', 'Use selected cmath functions with suitable inputs and predict their results.', [
        $section('Headers and functions', 'Include <cmath> for std::sqrt, std::pow, std::ceil and std::floor. Include <algorithm> for std::min and std::max. sqrt computes a square root; max chooses the larger of two comparable values.'),
        $section('Predict and check domains', 'sqrt(49.0) gives 7 and pow(2.0, 3.0) gives 8. Use nonnegative inputs for real square-root exercises. Do not use ^ for a power: for integers it is bitwise XOR.'),
        $section('Round with intent', 'ceil rounds toward positive infinity; floor rounds toward negative infinity. ceil(2.2) is 3, floor(2.8) is 2, and floor(-2.2) is -3. Choose the function matching the problem.'),
    ], $program('    std::cout << std::sqrt(49.0) << "\\n";
    std::cout << std::pow(2.0, 3.0) << "\\n";
    std::cout << std::ceil(2.2);', "#include <cmath>\n"), "7\n8\n3", [
        $question('Which header declares std::sqrt?', ['<cmath>', '<string>', '<fstream>'], 0, 'sqrt is provided by cmath.'),
        $question('What is std::sqrt(49.0)?', ['14', '7', '98'], 1, 'Seven squared equals 49.'),
        $question('What is std::pow(2.0, 3.0)?', ['5', '6', '8'], 2, 'Two cubed is 2 times 2 times 2.'),
        $question('What is std::ceil(2.2)?', ['3', '2', '0'], 0, 'ceil rounds toward positive infinity.'),
        $question('What does ^ mean for integer operands in C++?', ['Raise to a power', 'Bitwise XOR', 'Square root'], 1, 'Use pow or multiplication for powers, not XOR.'),
    ], 'Check allowed inputs. Negative real square-root inputs are outside these classroom tasks.');

    $lessons['booleans'] = $make('Booleans', 'Store true/false values and build expressions with comparisons and logical operators.', [
        $section('Boolean values', 'A bool represents true or false. Without boolalpha, cout normally prints 1 or 0. A flag such as hasPermission can describe a condition, but actual account permissions must still be enforced by the server.'),
        $section('Boolean expressions', 'score >= 60 produces a Boolean result. == compares, != tests inequality, and = assigns. Put parentheses around a comparison in output, as in cout << (score >= 60);.'),
        $section('Real-life requirements', '&& requires both conditions; || requires at least one; ! reverses a truth value. Explain an age or score rule before writing it, and test just below, at and above its boundary.'),
    ], $program('    int score = 72;
    bool passed = score >= 60;
    std::cout << passed << "\\n" << !passed;'), "1\n0", [
        $question('Which pair contains Boolean literals?', ['true and false', 'yes and no', 'on and off'], 0, 'true and false are the C++ Boolean literals.'),
        $question('What does cout normally print for true without boolalpha?', ['true', '1', '-1'], 1, 'Ordinary numeric output displays true as 1.'),
        $question('Which expression is true when score is 60?', ['score > 60', 'score < 60', 'score >= 60'], 2, '>= includes the boundary.'),
        $question('What is !false?', ['true', 'false', 'A string'], 0, 'Logical NOT reverses the value.'),
        $question('Which operator means both conditions must be true?', ['||', '&&', '='], 1, 'Logical AND requires both conditions.'),
    ], 'Use == for comparison. Group comparisons before inserting their results into cout.');

    $lessons['switch'] = $make('Switch', 'Choose an action with switch, case, break and default, and explain fall-through.', [
        $section('Match a menu choice', 'A switch selects a case from an integral or enumeration value. Case labels are constants. A console menu can read 1, 2 or 3 and choose an action. A standard switch cannot directly use std::string selectors.'),
        $section('Break and default', 'break exits the switch. Without it, execution can continue into following case bodies, called fall-through. default handles a value matching no case.'),
        $section('Test every branch', 'Try each case and an invalid choice for default. Inspect breaks if several actions print. Use if/else for arbitrary ranges such as score >= 60, and switch for a discrete set of choices.'),
    ], $program('    int choice = 2;
    switch (choice) {
        case 1: std::cout << "Read"; break;
        case 2: std::cout << "Code"; break;
        default: std::cout << "Choose 1 or 2";
    }'), 'Code', [
        $question('Which value selects the case in the example?', ['choice', 'cout', 'main'], 0, 'The switch expression is matched to case labels.'),
        $question('What exits a switch?', ['continue', 'break', 'case'], 1, 'break transfers control beyond the switch.'),
        $question('Which branch handles a value matching no case?', ['The first case always', 'main', 'default'], 2, 'default is the optional fallback.'),
        $question('What can happen when a case has no break?', ['Execution falls through into later case bodies', 'The source is deleted', 'Every value becomes a string'], 0, 'Execution continues until a break or the end of the switch.'),
        $question('Which is a suitable standard switch selector?', ['std::string text', 'An integer menu choice', 'A double average'], 1, 'An integral selector is supported.'),
    ], 'A missing break may print several actions. Test default as well as the normal choices.');

    $lessons['break-continue'] = $make('Break & Continue', 'Distinguish stopping a loop from skipping one iteration, and trace the resulting output.', [
        $section('Stop with break', 'break exits the nearest enclosing loop or switch. A search can stop after finding its target. A break in a nested loop exits the inner loop only, not every surrounding loop.'),
        $section('Skip with continue', 'continue skips the rest of the current iteration. A for loop then performs its update and next condition check. A while loop goes to its condition, so a skipped update can stop progress.'),
        $section('Trace control flow', 'List the counter values, skipped values and stopping value. Use break for a justified early exit and continue for a clearly explained skipped case. A simpler loop condition is often preferable to many jumps.'),
    ], $program('    for (int i = 1; i <= 6; i++) {
        if (i == 2) { continue; }
        if (i == 5) { break; }
        std::cout << i << "\\n";
    }'), "1\n3\n4\n", [
        $question('What does break do inside a loop?', ['Exits the nearest loop', 'Restarts the source editor', 'Restarts main'], 0, 'break exits the nearest enclosing loop or switch.'),
        $question('What does continue do?', ['Exits the whole program', 'Skips the rest of the current iteration', 'Deletes the counter'], 1, 'The loop continues with its next control step.'),
        $question('Why is 2 absent from the example output?', ['It is outside the range', 'The compiler hides even numbers', 'continue skips its output'], 2, 'continue is reached when i is 2.'),
        $question('Why is 5 absent from the output?', ['break is reached before output', 'cout cannot print 5', 'i never increases'], 0, 'The loop exits before printing 5.'),
        $question('In a for loop, what follows continue?', ['main restarts', 'The update, then the next condition check', 'The program must end'], 1, 'The for loop still performs its update.'),
    ], 'A while-loop continue that skips the counter update can cause an infinite loop.');

    $lessons['structures'] = $make('Structures', 'Group related values in a struct and access members with the dot operator.', [
        $section('Model a record', 'A struct defines a type with named members. A supply label and quantity belong together. A type describes the shape; each variable of that type is a separate object. Use fictional records in exercises.'),
        $section('Create and access objects', 'End a struct definition with a semicolon. Initialize an object with member values. object.member reads or updates a member. Member types can differ, for example string for a label and int for a count.'),
        $section('Records and arrays', 'An array holds elements of one type; a struct groups named fields that may have different types. An array of structures can represent several items. Passing records to functions is covered in Function Parameters.'),
    ], "#include <iostream>\n#include <string>\n\nstruct Supply { std::string label; int quantity; };\n\nint main() {\n    Supply item{\"Markers\", 4};\n    std::cout << item.label << \"\\n\" << item.quantity;\n    return 0;\n}\n", "Markers\n4", [
        $question('What does a struct group together?', ['Named related members', 'Only identical source files', 'Only comments'], 0, 'A structure represents related fields in one object.'),
        $question('How do you access quantity in object item?', ['quantity[item]', 'item.quantity', 'quantity::item'], 1, 'Use the dot operator for a struct object.'),
        $question('What follows a struct definition\'s closing brace?', ['Nothing in every case', 'Only a comma', 'A semicolon'], 2, 'A struct type definition ends with a semicolon.'),
        $question('Can struct members have different types?', ['Yes', 'No, all must be int', 'Only if all are comments'], 0, 'A record can combine text and numeric fields.'),
        $question('After item.quantity = 7;, what is that member\'s value?', ['4', '7', 'Markers'], 1, 'Assignment updates that object\'s member.'),
    ], 'Distinguish the type Supply from an object such as item, and remember the semicolon after the type definition.');

    $lessons['enums'] = $make('Enums', 'Use named enumeration values for a small choice set and distinguish scoped enums from integers.', [
        $section('Name a set of choices', 'An enum defines choices such as Read, Code and Review. Names communicate intent better than unexplained numeric codes. Unscoped enum values without explicit initializers start at zero and increase by one.'),
        $section('Use enum class', 'A scoped enum uses enum class and qualified names such as Stage::Code. It does not implicitly convert to int. Use a comparison or switch to select display text: cout does not automatically print the enumerator name.'),
        $section('Validate external input', 'Stage::Review clearly expresses an activity state. Reading an integer does not prove it is a valid enum choice. Validate a small menu number and map it to an accepted named value.'),
    ], "#include <iostream>\n\nenum class Stage { Read, Code, Review };\n\nint main() {\n    Stage current = Stage::Code;\n    std::cout << (current == Stage::Code);\n    return 0;\n}\n", '1', [
        $question('Why use named enum values?', ['To make choices clearer', 'To hide every error', 'To store unlimited text automatically'], 0, 'Names make a state meaningful to readers.'),
        $question('Which expression names Code in enum class Stage?', ['Code::Stage', 'Stage::Code', 'Stage.Code()'], 1, 'Scoped enumerators use :: qualification.'),
        $question('In enum Color { Red, Blue };, what is Red\'s default value?', ['1', '-1', '0'], 2, 'Default numbering starts at zero.'),
        $question('Does enum class implicitly convert to int?', ['No', 'Always', 'Only after cout'], 0, 'Scoped enums keep stronger type separation.'),
        $question('What does the worked comparison print?', ['Code', '1', 'Stage'], 1, 'The true comparison prints as 1.'),
    ], 'An enum does not automatically print its label. Choose display text explicitly with a condition or switch.');

    $lessons['references'] = $make('References', 'Create an alias, update the original through it and recognize the address-of operation.', [
        $section('Create an alias', 'int& alias = count; gives another name to the existing count object. Initialize an ordinary reference when declaring it. Updating alias changes count; no separate integer copy is created.'),
        $section('Memory address', '&count obtains the address of count. &alias obtains the address of the same object. Actual address text can change across runs and computers. Test address equality instead of hard-coding an expected numeric address.'),
        $section('Lifetime and const access', 'A reference must refer to a live object. It cannot be reseated by assignment: assigning through it changes the original object. A const reference gives read-only access through that reference.'),
    ], $program('    int count = 4;
    int& alias = count;
    alias += 3;
    std::cout << count << "\\n" << (&alias == &count);'), "7\n1", [
        $question('What is alias in int& alias = count;?', ['Another name for count', 'An independent integer copy', 'A new string'], 0, 'A reference aliases the existing object.'),
        $question('After alias = 8;, what happens to count?', ['It stays unchanged', 'It becomes 8', 'It is deleted'], 1, 'Assigning through a reference updates the object.'),
        $question('Which expression obtains count\'s address?', ['*count', 'count()', '&count'], 2, 'Unary & obtains the address.'),
        $question('Should a test expect the same numeric address on every run?', ['No', 'Yes, addresses never change', 'Only if the nickname matches'], 0, 'Addresses depend on the execution environment.'),
        $question('Can an existing reference be reseated by ordinary assignment?', ['Yes, always', 'No, assignment changes the referred-to object', 'Only with cout'], 1, 'The reference stays bound to its original object.'),
    ], 'Distinguish & in a reference declaration from & used to obtain an address.');

    $lessons['pointers'] = $make('Pointers', 'Store an address, read through a valid pointer and change the pointed-to value safely.', [
        $section('Create pointers', 'int* pointer = &count; stores count\'s address. A pointer is an object that can later store another compatible address. Initialize it to a valid address or nullptr, rather than leaving it indeterminate.'),
        $section('Dereferencing', '*pointer accesses the pointed-to int. The star has different roles in a declaration and an expression. Dereference only when the pointer refers to a live valid object; never dereference nullptr.'),
        $section('Modify pointers or values', '*pointer = 9 changes the pointed-to value. pointer = &other changes the destination. Actual address text is not a portable output expectation, so activities compare values and identity instead.'),
    ], $program('    int count = 4;
    int* pointer = &count;
    *pointer = 9;
    std::cout << count << "\\n" << *pointer;'), "9\n9", [
        $question('What does int* pointer = &count; store in pointer?', ['The address of count', 'The text count', 'The number of source lines'], 0, 'The pointer holds an object address.'),
        $question('What does *pointer access for a valid pointer?', ['The pointer\'s name', 'The pointed-to object', 'The filename'], 1, 'Unary * dereferences a pointer.'),
        $question('After *pointer = 9; in the example, what is count?', ['4', '0', '9'], 2, 'pointer refers to count, so assignment updates count.'),
        $question('Is it safe to dereference nullptr?', ['No', 'Yes, it always gives zero', 'Only for int'], 0, 'nullptr points to no object.'),
        $question('Which operation changes pointer\'s destination?', ['*pointer = 3;', 'pointer = &other;', 'count = 3;'], 1, 'Assigning a new address changes the destination.'),
    ], 'A non-null pointer can still be invalid if the object no longer exists. Check lifetime as well as the address.');

    $lessons['memory-management'] = $make('Memory Management', 'Explain dynamic lifetime, match new with delete and recognize safer ownership choices.', [
        $section('Automatic and dynamic lifetime', 'A local object normally lives until its scope ends. new int(5) creates a dynamic int initialized to 5 and returns a pointer. That object\'s lifetime is not automatically tied to the local pointer\'s scope.'),
        $section('New and delete', 'Use delete for one object created with new and delete[] for an array created with new[]. Release an allocation exactly once and never access it afterwards. Setting one pointer to nullptr does not repair other aliases of the old address.'),
        $section('Prefer simple ownership', 'Learn new and delete to read older code and recognize lifetime errors. Usually prefer automatic objects and standard containers; use unique_ptr for suitable dynamic single ownership. Never try arbitrary addresses or huge allocations.'),
        $section('Review ownership, not just output', 'Use small initialized allocations in these activities. Read before release. Correct output alone does not prove there are no leaks, double deletions or lifetime errors; the teacher must inspect ownership too.'),
    ], $program('    int* value = new int(5);
    std::cout << *value;
    delete value;
    value = nullptr;'), '5', [
        $question('What does new int(5) do when successful?', ['Creates a dynamic int initialized to 5', 'Creates five source files', 'Deletes an int'], 0, 'new creates the object and returns its pointer.'),
        $question('What matches a single-object new allocation?', ['delete[] in every case', 'delete', 'No release is needed'], 1, 'Use delete for a single new object.'),
        $question('What matches new int[3]?', ['delete', 'free in every case', 'delete[]'], 2, 'Match new[] with delete[].'),
        $question('Is reading *value after delete value; valid?', ['No', 'Always', 'Only if old output looked right'], 0, 'The object\'s lifetime has ended.'),
        $question('Which is usually simpler for a fixed local count?', ['An unnecessary dynamic allocation', 'An initialized local int', 'A hard-coded memory address'], 1, 'Use automatic lifetime when dynamic storage is unnecessary.'),
    ], 'Never delete twice, mismatch new[] with delete, or access an object after its lifetime ends.');

    $lessons['function-parameters'] = $make('Function Parameters', 'Use parameters, defaults and returns, and pass references, arrays and structures deliberately.', [
        $section('Parameters, arguments and multiple inputs', 'Parameters name inputs in a declaration. Arguments supply values in a call. For int total(int first, int second), first and second are parameters; total(3, 4) supplies arguments. Match argument order to each parameter\'s purpose.'),
        $section('Default parameters', 'A trailing parameter may have a default: int add(int value, int extra = 2). add(5) uses 2, while add(5, 4) supplies 4. In this pattern, parameters with defaults must follow those without defaults.'),
        $section('Return values', 'A non-void function returns its declared type. The caller can store or print that result. cout displays output; return sends a result back. These are different actions. Return a value on every relevant path.'),
        $section('Pass by reference', 'A value parameter receives a copy. An int& parameter aliases the caller\'s object and can change it. A suitable const reference gives read-only access without making an unnecessary copy.'),
        $section('Pass arrays and structures', 'A raw array parameter such as const int values[] behaves as a pointer parameter; pass an element count too. sizeof(values) inside that function does not recover the caller\'s array length. A struct can be passed by value or reference; a const reference permits read-only access without copying.'),
        $section('Real-life example', 'A supplies function can accept teams and items per team, then return their product. Test zero, one and normal counts. Explain whether a function only computes or intentionally modifies a caller-owned value.'),
    ], "#include <iostream>\n\nint supplies(int teams, int perTeam = 4) {\n    return teams * perTeam;\n}\n\nint main() {\n    std::cout << supplies(3) << \"\\n\" << supplies(3, 2);\n    return 0;\n}\n", "12\n6", [
        $question('In int add(int value), what is value?', ['A parameter', 'An image', 'The caller\'s filename'], 0, 'The declaration names the input parameter.'),
        $question('In add(5), what is 5?', ['The return type', 'An argument', 'A header'], 1, 'The call supplies an argument.'),
        $question('What does supplies(3) return in the worked example?', ['3', '4', '12'], 2, 'The omitted argument uses the default 4.'),
        $question('Which parameter can change the caller\'s int through an alias?', ['int& value', 'int value', 'const int value'], 0, 'A non-const reference parameter can update the object.'),
        $question('What should accompany a raw array parameter?', ['The student\'s password', 'Its valid element count', 'Only the first output line'], 1, 'A raw array parameter does not retain the caller\'s array length.'),
    ], 'A value parameter is a copy. A raw array parameter loses array-bound information; pass its count explicitly.');

    $additional = [
        'variables' => [
            $section('Multiple variables and identifiers', 'Separate declarations are often clearest; a comma can declare several same-type variables. Names may contain letters, digits and underscores, but cannot start with a digit or use a keyword. teamCount and teamcount differ. Use descriptive names.'),
            $section('Constants and real-life values', 'A const int such as const int seatsPerRow = 6; cannot later receive a different value. Use constants for fixed rules and variables for changing quantities. Named quantities make a supplies calculation easier to audit.'),
        ],
        'output' => [
            $section('Print text and numbers', 'Quoted digits are text; unquoted numbers can be calculated. cout << 4 + 6; prints 10, while cout << "4 + 6"; prints the expression as text. Chaining output adds no spaces unless you provide them.'),
            $section('New lines and exact output', 'Use \\n in text or std::endl for a new line. endl also flushes the stream. Match required spaces and line breaks, and omit prompts when a task asks for only the result.'),
        ],
        'operators' => [
            $section('Assignment and comparison', '= assigns, == compares, and += updates using the old value. Comparisons include !=, <, <=, > and >= and produce Boolean results.'),
            $section('Logical operators and precedence', '&& is AND, || is OR and ! is NOT. && and || short-circuit, so a later operand may not be evaluated. Multiplication precedes addition; && precedes ||. Use parentheses for clarity. Write x > 1 && x < 5 rather than the misleading chain 1 < x < 5.'),
        ],
        'strings' => [
            $section('Concatenation, numbers and length', 'Use + with std::string values to combine text. Convert a number with std::to_string before concatenating it. size() and length() count char elements, not necessarily visible characters in arbitrary encoded text.'),
            $section('Access and special characters', 'Indexes start at zero. name[0] is the first character of a nonempty string. Use escapes for newlines, quotes and backslashes. Stay within valid character indexes.'),
            $section('Full-line input and namespaces', 'cin >> name reads one word. std::getline(std::cin, name) reads a line including spaces; after formatted extraction, handle its leftover newline deliberately. std::string is qualified, so using namespace std is unnecessary.'),
            $section('C-style strings', 'A C-style string is a char sequence terminated by a null character. char name[] = "Ada" has four elements including the terminator. Prefer std::string in beginner tasks and observe bounds and termination in older char-array code.'),
        ],
        'conditions' => [
            $section('Else if, nested if and logic', 'An if/else-if chain selects the first true branch. A nested if tests a condition within a selected branch. Use && and || for combined requirements, and braces to show which else belongs to which if.'),
            $section('Short-hand choices and real-life examples', 'condition ? valueIfTrue : valueIfFalse selects a value. score >= 60 ? "Pass" : "Practise" is a short example. Prefer if/else for longer actions. Test below, at and above every temperature or score boundary.'),
        ],
        'while-loops' => [
            $section('Do/while executes before checking', 'while checks before its body and may run zero times. do/while runs the body before checking, so runs at least once. Put a semicolon after the final do/while condition.'),
            $section('Real-life repetition', 'A menu that must display once can use do/while. A sentinel sum can use while and must exclude the sentinel. Make progress toward stopping and test an immediate stop as well as several iterations.'),
        ],
        'for-loops' => [
            $section('Nested loops', 'A nested loop completes its inner loop for every outer iteration. Two outer iterations and three inner iterations execute the inner body six times. Use distinct counters and small bounds when tracing a grid.'),
            $section('The range-based for loop', 'C++ uses a range-based for, not a foreach keyword: for (int value : values) visits elements. A value variable receives a copy; int& value can change the original elements. Use this form when an explicit index is unnecessary.'),
            $section('Real-life counted repetition', 'Counted loops suit a known number of teams, rows or measurements. Trace the first value, last included value and first rejected value. Distinguish i < count from i <= count.'),
        ],
        'arrays' => [
            $section('Arrays and loops', 'A raw array holds a fixed number of same-type elements. Use indexes 0 through count - 1, or a suitable range-based for. Keep its count with it and initialize elements before reading them.'),
            $section('Omit and get array size', 'int values[] = {2, 4, 6}; deduces three elements. For an actual raw array in the same scope, sizeof(values) / sizeof(values[0]) gives the element count. The formula cannot recover an array length from a pointer parameter.'),
            $section('Multidimensional arrays', 'int grid[2][3] has two rows and three columns. Access grid[row][column] within those bounds and use nested loops to visit cells. Label what indexes mean and test first and last valid positions.'),
        ],
        'functions' => [
            $section('Declare, define and call', 'A declaration supplies name, return type and parameter types before a call. A definition supplies the body. Defining a helper does not execute it: call it. Put the definition above main or declare it first.'),
            $section('Small jobs and return types', 'Give a function one clear purpose and a descriptive name. A void function performs an action without a return value; an int function can return a whole-number result. Parameters, defaults, references, arrays and structures follow in the next lesson.'),
        ],
    ];
    foreach ($additional as $key => $sections) {
        $combined = [...$lessons[$key]['sections'], ...$sections];
        if (count($combined) > 6) {
            $introduction = array_shift($combined);
            $combined[0]['text'] = $introduction['text'].' '.$combined[0]['text'];
            $combined[0]['title'] = 'Strings introduction and basic operations';
        }
        $lessons[$key]['sections'] = $combined;
    }

    $topics = [
        'introduction' => ['Introduction', 'Term1', ['C++ Home', 'What is C++?', 'Algorithms', 'Safe classroom practice']],
        'get-started' => ['Get Started', 'Term1', ['Editor and compiler', 'Build and run', 'Test output']],
        'syntax' => ['Syntax & Statements', 'Term1', ['Syntax', 'Statements', 'Code challenge']],
        'output' => ['Output', 'Term1', ['Print text', 'Print numbers', 'New lines', 'Code challenge']],
        'comments' => ['Comments', 'Term1', ['Single-line comments', 'Block comments', 'Explain intent']],
        'variables' => ['Variables', 'Term1', ['Declare variables', 'Multiple variables', 'Identifiers', 'Constants', 'Real-life examples', 'Code challenge']],
        'input' => ['User Input', 'Term1', ['Read input', 'Input and output', 'Test cases']],
        'data-types' => ['Data Types', 'Term1', ['Basic data types', 'Numbers', 'Booleans', 'Characters', 'Strings', 'The auto keyword', 'Real-life example', 'Code challenge']],
        'operators' => ['Operators', 'Term1', ['Arithmetic', 'Assignment', 'Comparison', 'Logical', 'Precedence', 'Code challenge']],
        'strings' => ['Strings', 'Term1', ['Strings intro', 'Concatenation', 'Numbers and strings', 'String length', 'Access strings', 'Special characters', 'User input strings', 'Omitting namespace', 'C-style strings', 'Code challenge']],
        'math' => ['Math', 'Term2', ['Math functions', 'Domains', 'Rounding']],
        'booleans' => ['Booleans', 'Term2', ['Boolean values', 'Boolean expressions', 'Real-life example', 'Code challenge']],
        'conditions' => ['If / Else', 'Term2', ['if', 'else', 'else if', 'Short-hand if / else', 'Nested if', 'Logical operators', 'Real-life examples', 'Code challenge']],
        'switch' => ['Switch', 'Term2', ['switch', 'case and default', 'break and fall-through', 'Code challenge']],
        'while-loops' => ['While Loops', 'Term2', ['while loop', 'do / while loop', 'Real-life examples', 'Code challenge']],
        'for-loops' => ['For Loops', 'Term2', ['for loop', 'Nested loops', 'Range-based for (foreach)', 'Real-life examples', 'Code challenge']],
        'break-continue' => ['Break & Continue', 'Term2', ['break', 'continue', 'Trace control flow']],
        'arrays' => ['Arrays', 'Term2', ['Arrays and loops', 'Omit array size', 'Get array size', 'Real-life example', 'Multidimensional arrays', 'Code challenge']],
        'structures' => ['Structures', 'Term3', ['Define structures', 'Access members', 'Multiple records']],
        'enums' => ['Enums', 'Term3', ['Named values', 'enum class', 'Validated choices']],
        'references' => ['References', 'Term3', ['Create references', 'Memory address']],
        'pointers' => ['Pointers', 'Term3', ['Create pointers', 'Dereferencing', 'Modify pointers']],
        'memory-management' => ['Memory Management', 'Term3', ['Memory management', 'new and delete', 'Ownership and lifetime']],
        'functions' => ['Functions', 'Term3', ['Declare and define', 'Call functions', 'Return and void']],
        'function-parameters' => ['Function Parameters', 'Term3', ['Parameters / arguments', 'Default parameter', 'Multiple parameters', 'Return values', 'Pass by reference', 'Pass arrays', 'Pass structures', 'Real-life example']],
    ];
    $ordered = [];
    foreach ($topics as $key => [$topic, $term, $subtopics]) {
        $ordered[$key] = [...$lessons[$key], 'title' => 'C++ '.$topic, 'topic' => $topic, 'term' => $term, 'subtopics' => $subtopics,
            'practice' => $legacy[$key]['practice'] ?? 'cpp-'.$key, 'version' => 2,
            'reference_url' => 'https://www.w3schools.com/cpp/default.asp'];
    }

    return ['revision' => '2026-11-cpp-topics', 'reference_url' => 'https://www.w3schools.com/cpp/default.asp', 'lessons' => $ordered];
})();
