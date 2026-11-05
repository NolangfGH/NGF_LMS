<?php

// Each row contains a question, its correct answer, and three distractors.
return [
    'introduction' => <<<'QUESTIONS'
Which instruction for sorting three cards is precise enough for a computer to follow? | Compare the first two values and swap them if the first is larger. | Move things around until they look right. | Put the nicest card first. | Work faster whenever possible.
A program calculates a class total. Which is its input? | The individual counts entered into it | The total it prints | The final success message | The program's filename
A packing rule says to put four markers in each kit. Which is a valid test of the rule? | Enter 3 kits and check for 12 markers. | Enter 3 kits and expect 7 markers. | Run the program without looking at the result. | Change the file extension and assume it works.
Why trace an algorithm with pencil and paper first? | It lets you verify the steps and expected result on a small case. | It installs the compiler. | It creates a C++ executable. | It makes every input correct.
Two correct algorithms solve the same task. What can still differ between them? | The sequence of steps and the amount of work | Whether the task has a correct result | The value of the same expected result | The definition of the input
A program receives 8 and 5, compares them, and displays 8. Which operation is the processing step? | Comparing the two values | Entering 8 and 5 | Displaying 8 | Naming the program
Which is an example of a clear stopping condition for a classroom inventory algorithm? | Stop after recording the last item on the list. | Stop whenever the list seems long. | Stop before beginning each item. | Stop when the instructions are confusing.
Which use of C++ matches a classroom simulation? | Update a robot's location according to a sequence of commands. | Guarantee the robot always moves safely without any tests. | Make the robot understand any vague instruction. | Repair a broken battery by renaming a file.
QUESTIONS,
    'get-started' => <<<'QUESTIONS'
After editing main.cpp, you see yesterday's output. What should you check first? | Whether you saved and rebuilt the current source | Whether the screen is bright enough | Whether the source has a new name | Whether the browser history is empty
What is a build diagnostic most useful for? | Locating a problem that the compiler detected | Proving the program meets every requirement | Showing what a user typed last week | Choosing the answer to a math problem
Which comparison checks the behavior of a program that prints a greeting? | Compare its actual output with the specified greeting. | Compare the editor color with the console color. | Compare the source size with a classmate's. | Compare the number of files before and after signing in.
Your first build reports several syntax errors. Which repair strategy is sensible? | Inspect the first reported problem, repair it and build again. | Delete every line mentioned by any diagnostic. | Change the expected output to remove the errors. | Run the failed build repeatedly.
Which artifact can you read and edit to change the program's instructions? | The source file | The build timestamp | The output history | The compiler's icon
A build succeeds, but your program prints 14 where the specification says 12. What kind of problem should you investigate? | The calculation or instructions in the program | An inability to compile any source | The brightness of the output panel | An unsupported file name extension
Which practice makes it easier to identify the effect of a repair? | Change one relevant detail and rebuild before the next change. | Edit every statement at once. | Replace the expected answer after every run. | Ignore whether the program rebuilt.
What is the role of the output console in a basic Code Lab exercise? | It shows text produced by the running program. | It permanently rewrites your C++ source. | It approves classroom enrollment. | It translates diagrams into executable code.
QUESTIONS,
    'syntax' => <<<'QUESTIONS'
Which is a valid minimal definition of a console program's entry function? | int main() { return 0; } | main int() { return 0; } | int Main() { return 0; } | int main() [ return 0; ]
What is missing from the end of the statement std::cout << "Ready"? | A semicolon | A colon | A closing parenthesis | A comma
Which syntax correctly includes the standard stream header? | #include <iostream> | include <iostream>; | #include {iostream} | <include> iostream
Which snippet places a return statement inside main's body? | int main() { return 0; } | int main() {} return 0; | int main() ( return 0; ) | int main return 0;
Which expression uses the proper spelling and namespace for standard output? | std::cout | STD::cout | std:Cout | std.cout
Which line displays the literal word done instead of referring to an identifier named done? | std::cout << "done"; | std::cout << done; | std::cout >> "done"; | std::cout "done";
How many output statements are in std::cout << "A"; std::cout << "B";? | 2 | 0 | 1 | 4
What determines the end of the body of main? | Its matching closing brace | The first blank line | The first indentation change | The final double quote
QUESTIONS,
    'output' => <<<'QUESTIONS'
What does std::cout << "Room " << 12; display? | Room 12 | Room12 | 12 Room | Room << 12
What does std::cout << "7 + 2"; display? | 7 + 2 | 9 | 72 | "7 + 2"
What does std::cout << (7 + 2) << 4; display? | 94 | 13 | 9 4 | 724
Which snippet displays X and Y on separate lines? | std::cout << "X\nY"; | std::cout << "X Y"; | std::cout << "X/nY"; | std::cout << "XY";
Which snippet prints 3 followed by a space and then 6? | std::cout << 3 << ' ' << 6; | std::cout << 3 << 6; | std::cout << (3 + 6); | std::cout << "3<<6";
What is displayed by std::cout << "2" << 5;? | 25 | 7 | 10 | 2 5
Which output instruction prints the word STOP and moves the cursor to the next line? | std::cout << "STOP" << '\n'; | std::cout << "STOPn"; | std::cout << "STOP " ; | std::cout << '\n' << "STOP";
What does std::cout << '[' << 4 << ']'; display? | [4] | 4 | [ 4 ] | [4
QUESTIONS,
    'comments' => <<<'QUESTIONS'
What does std::cout << "// note"; display? | // note | Nothing | note | A compiler error
Which comment explains a design decision? | // Use 60 because a question lasts one minute. | // Set limit to 60. | // The next line is a line. | // My real password is here.
In /* old draft */ std::cout << 8;, what is displayed? | 8 | old draft | old draft 8 | Nothing
Why update a comment after changing a program's formula? | An outdated explanation can mislead the next reader. | Comments control the calculation. | Comments change integers into decimals. | The compiler uses comments as input.
Which comment style can cover several lines with one opening marker and one closing marker? | A block comment | A string literal | A single-line comment | An include directive
Where does the comment begin in std::cout << 5; // Print the count? | At the two slashes | At the stream insertion operator | At the digit 5 | At the first colon
What is the best way to record why a test uses zero? | Add a short comment identifying zero as a boundary case. | Print a private password to explain it. | Change the test to avoid the boundary. | Add quotation marks around the entire program.
What happens to text after // on the same source line when the slashes are outside a string? | It is treated as a comment. | It becomes keyboard input. | It is printed automatically. | It is compiled as another statement.
QUESTIONS,
    'variables' => <<<'QUESTIONS'
After int kits = 4; kits = 9;, what is the value of kits? | 9 | 4 | 13 | 0
Which declaration gives both variables an initial value? | int red = 2, blue = 5; | int red, blue = 5; | int red = 2, blue; | int red, blue;
Which is a valid and descriptive name for a count of books? | bookCount | 2books | book count | int
Which line creates an integer whose value cannot be assigned again? | const int seats = 28; | int seats = 28; | int seats; | auto seats = 28;
After int x = 3; int y = x; x = 7;, what is y? | 3 | 7 | 10 | 0
Which declaration best represents a fixed number of days in a week? | const int daysPerWeek = 7; | int d; | std::string d = "maybe"; | int daysPerWeek = 0;
Why initialize a local counter before incrementing it? | Incrementing needs a known starting value. | Initializing makes it a string. | C++ automatically sets every local variable to 1. | It prevents the value from ever changing.
QUESTIONS,
    'input' => <<<'QUESTIONS'
For int a = 0, b = 0; std::cin >> a >> b;, what is b when the input is 9 4? | 4 | 9 | 13 | 94
Which line reads one integer into an already declared variable items? | std::cin >> items; | std::cout >> items; | std::cin << items; | std::cout << items;
What data should a learner supply when testing a program that accepts a fictional student count? | A small made-up number | A classmate's login password | A screenshot of the teacher's gradebook | The real names of every student
For int n = 0; std::cin >> n; std::cout << n * 2;, what is displayed after entering 6? | 12 | 6 | 8 | 62
What does the prompt std::cout << "Enter a count: "; do by itself? | Displays a message asking for input | Reads an integer into memory | Rejects negative counts | Creates a variable called count
What kind of input should you test if an integer program must handle invalid entries? | A word where the program expects a number | Only 1 repeatedly | Only the exact sample | The program's source filename
When the allowed range is 1 through 10, which three tests check the lower boundary? | 0, 1 and 2 | 4, 5 and 6 | 8, 9 and 10 | 10, 11 and 12
QUESTIONS,
    'data-types' => <<<'QUESTIONS'
Which type best represents whether a door is open? | bool | double | char | std::string
Which declaration directly stores one letter as a char? | char grade = 'B'; | char grade = "B"; | bool grade = 'B'; | std::string grade = 'B';
What type does auto temperature = 24.5; deduce? | double | int | bool | char
Which value would int discard the fractional part of when assigned? | 4.75 | 4 | 0 | -2
What does std::cout << false; normally display without boolalpha? | 0 | false | 1 | -1
Which expression uses floating-point division to calculate 2.5? | 5.0 / 2 | 5 / 2 | 5 % 2 | 5 - 2
Which is the best type for a book title that may contain spaces? | std::string | char | int | bool
QUESTIONS,
    'operators' => <<<'QUESTIONS'
What is the result of 19 % 4? | 3 | 4 | 4.75 | 0
After int n = 6; n *= 3;, what is n? | 18 | 9 | 2 | 63
What does 2 + 4 * 3 evaluate to? | 14 | 18 | 20 | 24
Which comparison is true when count is 5? | count != 4 | count < 5 | count > 5 | count == 4
Which expression is true only when both paid and ready are true? | paid && ready | paid || ready | paid != ready | !paid
What is the value of (2 + 4) * 3? | 18 | 14 | 9 | 24
Which operation changes total by subtracting 2 from its current value? | total -= 2; | total == 2; | total = 2; | total += 2;
QUESTIONS,
    'strings' => <<<'QUESTIONS'
For std::string name = "Mira";, what is name[2]? | 'r' | 'M' | 'i' | 'a'
What is the length of std::string("Hi there")? | 8 | 7 | 9 | 2
Which statement appends a space and a word to an existing std::string line? | line += " start"; | line -= " start"; | line == " start"; | line >> " start";
Which call reads a full name including spaces into a std::string name? | std::getline(std::cin, name); | std::cout << name; | name.length(); | std::cin >> name;
What does std::string("C++") + " Lab" produce? | C++ Lab | C++Lab | C++ + Lab | Lab C++
Which escape lets you put a double quote inside a double-quoted string literal? | \" | \' | \n | \t
In a C-style string initialized with "Hi", which character marks the end of the text? | '\0' | '\n' | 'H' | ' '
QUESTIONS,
    'math' => <<<'QUESTIONS'
What does std::sqrt(81.0) return? | 9 | 8 | 27 | 40.5
What does std::pow(3.0, 2.0) return? | 9 | 6 | 5 | 8
What does std::floor(4.99) return? | 4 | 5 | 4.99 | 0
What does std::ceil(-3.8) return? | -3 | -4 | 3 | 4
What does std::max(-6, -2) return? | -2 | -6 | 2 | -8
Which input is within the real-valued square-root domain? | 0.0 | -0.25 | -9.0 | -1.0
Which expression computes the square of an integer n using multiplication? | n * n | n ^ 2 | n + 2 | n % 2
What is the value of std::floor(-1.1)? | -2 | -1 | 1 | 2
Which expression selects the smaller of the values 18 and 24? | std::min(18, 24) | std::max(18, 24) | std::ceil(24.0) | std::sqrt(18.0)
What does std::ceil(9.0) return? | 9 | 10 | 8 | 0
QUESTIONS,
    'booleans' => <<<'QUESTIONS'
What is the value of true && false? | false | true | 2 | An error
What is the value of !(4 < 7)? | false | true | 4 | 7
With bool full = false; bool open = true;, what does open && !full evaluate to? | true | false | 2 | An error
What does std::cout << (3 == 5); normally display without boolalpha? | 0 | 1 | 3 | 5
Which rule is true if at least one of home or away is true? | home || away | home && away | !home && !away | !home && away
Which expression checks that count is between 2 and 8 inclusive? | count >= 2 && count <= 8 | count > 2 && count < 8 | count >= 2 || count <= 8 | count == 2 && count == 8
What does !(true || false) evaluate to? | false | true | 1.5 | An error
What is the value of 8 >= 8? | true | false | 0.8 | 8
With int x = 3;, which expression compares x to 3 without changing x? | x == 3 | x = 3 | x += 3 | x *= 3
Which condition is true only when neither keyA nor keyB is active? | !keyA && !keyB | keyA && keyB | keyA || keyB | !keyA || !keyB
QUESTIONS,
    'conditions' => <<<'QUESTIONS'
If int n = 6;, what is printed by if (n > 6) std::cout << "A"; else std::cout << "B";? | B | A | AB | Nothing
If score is 82, which branch runs in if (score >= 90) A(); else if (score >= 75) B(); else C();? | B only | A only | B and C | C only
What value does (9 < 5 ? 11 : 22) produce? | 22 | 11 | 9 | 5
Where should you check battery > 0 if it matters only after the robot has permission to move? | Inside the branch for permission to move | Before declaring battery | In every unrelated else branch | In a comment only
What is wrong with if (n > 5); { std::cout << n; } when the block should run conditionally? | The semicolon makes the if body an empty statement. | The braces prevent all output. | cout requires parentheses around n. | The comparison must use =.
Which test cases check a discount that starts at exactly 100 pesos? | 99, 100 and 101 | 1, 2 and 3 | 45, 55 and 65 | Only 150
For int n = 3;, what does if (n < 0) std::cout << "N"; else if (n == 0) std::cout << "Z"; else std::cout << "P"; display? | P | N | Z | NZ
Why use else if rather than separate if statements for mutually exclusive score bands? | The chain stops after the first true branch. | It runs every true branch. | It converts scores to strings. | It removes the need for any condition.
Which expression selects 10 for a positive n and 0 otherwise? | n > 0 ? 10 : 0 | n >= 0 ? 0 : 10 | n < 0 ? 10 : 0 | n = 0 ? 10 : 0
What is displayed by int n = 4; if (n > 0) { if (n % 2 == 0) std::cout << "E"; }? | E | Nothing | 4 | 0
QUESTIONS,
    'switch' => <<<'QUESTIONS'
With n equal to 8, which label handles it in a switch that has case 1, case 2 and default? | default | case 1 | case 2 | Both case 1 and case 2
Which statement is normally placed at the end of a switch case to stop fall-through? | break; | continue; | case; | default;
Which value can be used directly as the controlling expression of a basic switch? | An int menu selection | A std::string title | A double price | An entire int array
Which label matches a character option named choice when its value is 'Y'? | case 'Y': | case "Y": | default 'Y': | if 'Y':
What happens when matching case 1 has no break before case 2? | Execution can continue into the case 2 statements. | The switch reevaluates its expression as 2. | The program skips all case 1 statements. | C++ automatically inserts a break.
Why might case 1: appear directly before case 2: with a shared body? | Both options intentionally use the same instructions. | Both options are invalid. | Two variables must be declared. | It makes case 2 unreachable.
Which control structure best describes a choice among exact integer menu codes? | switch | A nested counting loop | A comment block | An array initializer
What can happen in a switch with no matching case and no default? | Execution continues after the switch. | The first case always runs. | The last case always runs. | The program restarts.
Which pair cannot both appear as labels in the same switch? | case 3: and case 3: | case 0: and case 1: | case 4: and default: | case -1: and case 1:
QUESTIONS,
    'while-loops' => <<<'QUESTIONS'
How many times does int x = 8; while (x < 5) { ++x; } enter the body? | 0 | 1 | 3 | 5
What does int x = 3; while (x > 0) { std::cout << x; --x; } display? | 321 | 3210 | 123 | 210
How many times does int n = 10; do { ++n; } while (n < 5); execute its body? | 1 | 0 | 5 | 10
What is the final value of n after int n = 2; while (n < 9) { n += 3; }? | 11 | 8 | 9 | 12
A loop reads integers until -1. Which condition keeps processing ordinary values? | value != -1 | value == -1 | value > -1 && value < -1 | value = -1
Why must a sentinel-controlled input loop read another value in the body? | To give the loop a chance to reach the stop value | To turn every value into the sentinel | To skip the original condition | To print the variable name
What does int n = 2; do { std::cout << n; --n; } while (n > 0); display? | 21 | 210 | 12 | 2
Which step fixes int k = 1; while (k <= 4) { std::cout << k; } to print 1 through 4? | Add ++k; inside the body after printing. | Add ++k; only after the loop. | Set k to 1 every time through the body. | Change k <= 4 to k >= 0.
Which loop is suited to a menu that must display once before testing whether to repeat? | do / while | A while loop whose condition is initially false | A switch with no cases | A for loop with zero iterations
QUESTIONS,
    'for-loops' => <<<'QUESTIONS'
What does for (int i = 1; i <= 3; ++i) std::cout << i; display? | 123 | 012 | 1234 | 321
How many times does the body of for (int i = 0; i < 6; i += 2) run? | 3 | 2 | 4 | 6
Which part of for (int i = 0; i < 4; ++i) runs after each ordinary iteration? | ++i | int i = 0 | The entire declaration of main | return 0
An outer loop runs 3 times and an inner loop runs 2 times each. How many times does the inner body run? | 6 | 5 | 3 | 2
Which loop visits every element of an int array data without explicitly using an index? | for (int value : data) | for (int value = data) | for (int value; data;) | for (int value >> data)
What does for (int i = 5; i >= 3; --i) std::cout << i; display? | 543 | 54 | 345 | 654
Which condition prints the index values 0 through 4 from a loop starting at 0 and increasing by 1? | i < 5 | i <= 5 | i < 4 | i > 5
Why use a reference in for (int& value : data) when updating each array element? | Changes to value affect the element itself. | The reference prevents any change. | It automatically sorts the elements. | It starts the loop at the last element.
What is sum after int sum = 0; for (int i = 2; i <= 4; ++i) sum += i;? | 9 | 6 | 10 | 12
QUESTIONS,
    'break-continue' => <<<'QUESTIONS'
What does for (int i = 1; i < 5; ++i) { if (i == 3) break; std::cout << i; } display? | 12 | 124 | 123 | 1234
What does for (int i = 1; i < 5; ++i) { if (i == 3) continue; std::cout << i; } display? | 124 | 12 | 123 | 1234
What does a break in the inner of two nested loops normally exit? | The inner loop only | Both loops | The program | Only the current if block
In a for loop, what happens immediately after continue skips the remaining body? | The loop's update expression runs, then the condition is checked. | The initializer runs again. | Every loop ends. | The remainder of the body runs as usual.
Which instruction is appropriate after finding the first match in a simple search loop? | break; | continue; | case; | default;
Which instruction skips an invalid item but keeps looking at later items? | continue; | break; | return 0; | delete;
How many numbers are printed by for (int i = 0; i < 6; ++i) { if (i % 2 == 0) continue; std::cout << i; }? | 3 | 0 | 5 | 6
A while loop increments its counter at the very end of its body. Why can a continue before that increment cause trouble? | The counter may stay unchanged and the same iteration may repeat. | It always ends the program. | It increments twice automatically. | It moves the counter into a different variable.
Which values appear with for (int i = 0; i < 6; ++i) { if (i == 4) break; if (i == 1) continue; std::cout << i; }? | 023 | 0123 | 02345 | 04
QUESTIONS,
    'arrays' => <<<'QUESTIONS'
For int scores[] = {7, 11, 9};, what is scores[1]? | 11 | 7 | 9 | 3
What is the largest valid index of int a[5]{};? | 4 | 5 | 0 | 6
How many elements are inferred for int items[] = {2, 4, 8, 16};? | 4 | 3 | 16 | 30
For int a[6]{}; in its own scope, which expression returns the element count? | sizeof(a) / sizeof(a[0]) | sizeof(a[0]) | a[6] | sizeof(a[0]) / sizeof(a)
For int board[2][3] = {{1,2,3},{4,5,6}};, what is board[1][2]? | 6 | 5 | 3 | 2
With int a[] = {2, 4, 6};, what is a[0] + a[2]? | 8 | 6 | 10 | 12
Which loop bound keeps an index i starting at 0 within an array with length 7? | i < 7 | i <= 7 | i > 7 | i != 8
What is the main danger of writing to a[3] when a has exactly three elements? | It is out of bounds and leads to undefined behavior. | The array automatically grows to four elements. | It always changes only the first element. | C++ silently rounds 3 down to 2.
How many values can be stored in int readings[3][4]{};? | 12 | 7 | 9 | 16
QUESTIONS,
    'structures' => <<<'QUESTIONS'
Which C++ feature groups a book's title and page count into one record? | A structure with two members | A single int | An include directive | A while condition
Given struct Book { int pages; }; Book b{120};, which expression reads the page count? | b.pages | Book.pages | b->pages | pages[b]
Given struct Item { int count; double price; }; Item a{3, 4.5};, what is a.count? | 3 | 4.5 | 13.5 | 0
Given struct Point { int x; int y; }; Point p{1,2};, which assignment changes just y to 7? | p.y = 7; | Point.y = 7; | p = 7; | p.x = 7;
Which punctuation normally follows the closing brace of a struct definition? | A semicolon | A colon | A period | A slash
Given struct Point { int x; int y; }; Point points[] = {{1,2},{3,4}};, what is points[1].x? | 3 | 1 | 2 | 4
Given struct Seat { int row; int column; }; Seat a{2,3}; Seat b = a; b.row = 5;, what is a.row? | 2 | 5 | 3 | 7
Why group quantity and label in one InventoryItem structure? | Related values travel together as one record. | The compiler makes the values identical. | It makes array indexes start at 1. | All members must have the same type.
Given struct Entry { int number; }; Entry e{};, what is e.number? | 0 | 1 | -1 | Always an error
Which declaration creates two separate variables of an existing type Player? | Player a{}, b{}; | Player a + b; | struct Player = a, b; | Player a.b;
Given struct Reading { double volts; bool valid; }; Reading r{1.5,true};, which member indicates whether to use the reading? | r.valid | r.volts | Reading.volts | r[1]
QUESTIONS,
    'enums' => <<<'QUESTIONS'
Which declaration defines scoped choices Red and Green? | enum class Light { Red, Green }; | int Light { Red, Green }; | struct Light = Red, Green; | enum class Light(Red, Green);
Given enum class Light { Red, Green };, which expression names its Green choice? | Light::Green | Light.Green | Light->Green | Light[Green]
Given enum class Stage { Start = 2, Play, Done };, what is static_cast<int>(Stage::Done)? | 4 | 2 | 3 | 5
Why prefer named enum values over unexplained menu numbers in a decision? | The names make the allowed choices and intent clearer. | Enum values automatically validate every input. | They let a menu hold any string. | They remove the need to make decisions.
Given enum class Move { Left, Right };, which initializes a Move variable correctly? | Move next = Move::Right; | Move next = "Right"; | Move next = 1; | Move next = Right;
What is a sensible first step before mapping a keyboard number to a menu enum? | Check that the number matches an allowed menu option. | Use any number as a trusted choice. | Treat every number as the last choice. | Store the user's password as the choice.
Given enum class Mode { Off = 0, On = 10 };, what is static_cast<int>(Mode::On)? | 10 | 1 | 0 | -10
Given enum class Day { Mon, Tue };, which comparison checks whether variable d represents Monday? | d == Day::Mon | d = Day::Mon | d == "Mon" | d.Mon == Day
Given enum class Result { Win, Draw, Loss };, which enum member represents a tie? | Result::Draw | Draw::Result | Result.Draw | Result::Win
Given enum class Rank { Bronze = 5, Silver, Gold = 20 };, what is static_cast<int>(Rank::Silver)? | 6 | 5 | 19 | 20
Which distinction is true for an enum class value compared with an int? | It does not implicitly convert to int. | It is always a std::string. | It has no named values. | It can never appear in a switch.
QUESTIONS,
    'references' => <<<'QUESTIONS'
After int score = 4; int& alias = score; alias = 8;, what is score? | 8 | 4 | 12 | 0
Which binds a non-const reference to an existing int named count? | int& ref = count; | int ref = count; | int* ref = &count; | int& ref;
After int x = 2; int& r = x; ++x;, what is r? | 3 | 2 | 0 | 4
Given int x = 2, y = 8; int& r = x; r = y;, which variable does r now refer to? | x | y | Neither variable | Both variables
Given int x = 2; int& r = x;, what does (&r == &x) evaluate to? | true | false | 2 | A compiler error
Which operator obtains the memory address of an existing variable n? | &n | *n | n& | n::
What is the practical difference between int copy = n; and int& alias = n;? | copy holds its own value and alias refers to n. | Both are separate copies. | Both must point to dynamically allocated memory. | Neither can be initialized from n.
Which declaration allows you to read a variable total through a reference without changing it through that reference? | const int& view = total; | int& view = total; | int view = total; | int* view = &total;
After int a = 1; int& b = a; b += 4;, what is a? | 5 | 1 | 4 | 0
When is an ordinary local reference alias bound to its target? | When it is initialized | At every assignment through it | Only when the program ends | The first time it is printed
Given int a = 3, b = 9; int& ref = a; ref = b; b = 2;, what is ref? | 9 | 2 | 3 | 11
QUESTIONS,
    'pointers' => <<<'QUESTIONS'
Given int total = 6;, which declaration stores its address in p? | int* p = &total; | int p = total; | int& p = total; | int* p = total;
Given int n = 12; int* p = &n;, what is *p? | 12 | The address of p | nullptr | An unrelated copy of an address
After int n = 3; int* p = &n; *p = 7;, what is n? | 7 | 3 | 10 | 0
Which value can mark an int pointer as pointing to no object? | nullptr | -1 | "" | false
Which expression tests that p is non-null before considering dereferencing it? | p != nullptr | *p != nullptr | p = nullptr | &p == nullptr
After int a = 3, b = 5; int* p = &a; p = &b;, what does *p read? | 5 | 3 | 8 | 0
Given int n = 6; int* p = &n;, what does p == &n evaluate to? | true | false | 6 | A compiler error
Which instruction changes the integer reached through p without changing the stored address? | *p = 20; | p = nullptr; | p = &other; | ++p;
What is the consequence of dereferencing a null pointer? | Undefined behavior | A guaranteed result of 0 | A guaranteed C++ exception | Automatic allocation of an int
For int a = 7; int* p = &a;, what is the difference between p and &p? | p is a's address and &p is the address of the pointer variable. | Both name the value 7. | Both are the address of a. | &p dereferences the pointer.
For int a = 1, b = 2; int* p = &a; int* q = p; p = &b;, what is *q? | 1 | 2 | 3 | nullptr
QUESTIONS,
    'memory-management' => <<<'QUESTIONS'
Which expression creates a dynamically allocated int initialized to 7? | new int(7) | int(7) | delete 7 | &7
Which statement frees an int obtained with new int(7) through its owning pointer p? | delete p; | delete[] p; | delete *p; | p = nullptr;
Which statement pairs with int* p = new int[4]; when the array is no longer needed? | delete[] p; | delete p; | delete p[4]; | p[0] = 0;
What is a memory leak in a small manual-allocation exercise? | Allocated memory is never released and the program loses its way to free it. | A string prints a newline. | An array is initialized to zero. | A pointer safely points to a local variable.
Which object can be passed to delete p in this exercise? | One allocated with matching scalar new and still owned by p | A local int reached through &x | The first item of an ordinary local array | The address of a string literal
Why should you stop reading through p after delete p;? | The allocated object's lifetime has ended. | p always becomes a reference. | delete always changes the object to 0. | The compiler creates another object in its place.
What does p = nullptr; do to an outstanding allocation when p is the only pointer to it? | Loses access to it without freeing it | Frees the allocation immediately | Runs delete[] automatically | Copies its data to the console
What is the safest ordinary choice for a fixed-size local int array used only inside one function? | Use a local array with automatic lifetime. | Allocate and intentionally never free it. | Delete the address of a local int. | Use a dangling pointer to the array.
What should a learner do with another pointer q that aliases p after the allocation is freed through p? | Stop using q to access the freed allocation. | Dereference q because only p was deleted. | Delete the allocation again through q. | Assume q automatically became null.
Which statement about delete on a null pointer is true? | It has no effect on an allocated object. | It frees all objects. | It automatically allocates new memory. | It always crashes.
Which standard library type is intended to own a dynamically allocated sequence and clean up when it goes out of scope? | std::vector<int> | int* | int& | bool
QUESTIONS,
    'functions' => <<<'QUESTIONS'
Which declaration tells C++ that a function named twice accepts an int and returns an int? | int twice(int value); | twice(int value); | void twice(int value); | int twice;
Given int twice(int x) { return x * 2; }, what is twice(8)? | 16 | 8 | 10 | 64
Which statement calls an already declared function void greet()? | greet(); | void greet(); | greet; | void greet {}
Why declare a function before a call if the full definition is below the call? | The compiler must know the function's interface at the call. | The call runs before main by default. | It copies the function twice into the source. | It prevents the function from ever returning.
Which function return type fits a routine that displays a message and returns no value? | void | int | double | std::string
What does return 4; do inside a function that returns int? | Ends that call and supplies the value 4 to the caller. | Prints 4 to the console automatically. | Calls the function four times. | Declares a new variable named 4.
Given int one() { return 1; }, what is one() + one()? | 2 | 1 | 11 | 0
Given int triple(int n) { return n * 3; }, which call returns 15? | triple(5) | triple(3) | triple(12) | triple(15)
Why put a repeated conversion into a well-named function? | You can reuse and test the same logic from different calls. | It makes the conversion correct for every input without tests. | It requires every variable to be global. | It removes all parameters.
In int add(int a, int b) { return a + b; }, which portion is the function body? | { return a + b; } | int add | (int a, int b) | a, b
QUESTIONS,
    'function-parameters' => <<<'QUESTIONS'
In the call scale(4, 3), what are 4 and 3 called? | Arguments | Return types | Function bodies | Headers
Given int add(int a, int b = 2) { return a + b; }, what is add(5)? | 7 | 5 | 2 | 10
Given int subtract(int a, int b) { return a - b; }, what is subtract(3, 8)? | -5 | 5 | 11 | 24
Given void change(int n) { n = 9; } and int a = 3; change(a);, what is a afterward? | 3 | 9 | 6 | 0
Given void change(int& n) { n = 9; } and int a = 3; change(a);, what is a afterward? | 9 | 3 | 6 | 0
Which parameter can refer to a Book record without copying it and prevent changes through the parameter? | const Book& book | Book book | Book& book | int book
Why pass a separate length to a function declared with an int a[] parameter? | The parameter does not preserve the array's element count. | The function always receives exactly one element. | C++ uses a default length of ten. | Passing a length automatically resizes the caller's array.
Given int cost(int qty, int price = 5) { return qty * price; }, what is cost(3, 8)? | 24 | 15 | 40 | 11
Given void first(int a[]) { a[0] = 9; } and int values[] = {1, 2}; first(values);, what is values[0]? | 9 | 1 | 2 | 0
Given struct Item { int count; }; and void add(Item& x) { ++x.count; }, what happens to Item kit{5}; after add(kit);? | kit.count becomes 6. | kit.count stays 5. | kit.count becomes 0. | kit becomes a pointer.
QUESTIONS,
];
