<?php

return [
    // Each row contains a response type, prompt, key and choices. Multi-select rows list every choice.
    'pre-test' => [
        'Term 1 · Foundations' => <<<'QUESTIONS'
multiple_choice | A recipe lists the steps to prepare one cup of juice. What part of a program is most like the list of steps? | The algorithm | The output screen | The filename | The keyboard
multiple_choice | Which tool translates C++ source into instructions a computer can run? | A compiler | A calculator | A text color picker | A web bookmark
multiple_choice | Which name identifies the entry function in a basic C++ console program? | main | start | print | Main
multiple_choice | What does std::cout << "6 + 4"; print? | 6 + 4 | 10 | 64 | An empty line
multiple_choice | What is the purpose of // Count the kits when it appears on its own source line? | Explain something to a reader | Add one to a count | Read a number | Print a message
multiple_choice | After int books = 4; books = 6;, what is books? | 6 | 4 | 10 | 0
multiple_choice | Which statement reads a value into a declared variable named age? | std::cin >> age; | std::cout << age; | std::cin << age; | std::cout >> age;
multiple_choice | Which type best represents the temperature 27.5 in a beginner program? | double | bool | char | int
multiple_choice | What does 14 % 5 give? | 4 | 2 | 5 | 9
multiple_choice | What is the length of the C++ string "Sun"? | 3 | 2 | 4 | 5
multiple_choice | A weather tool accepts three temperatures and displays their mean. Which step is processing? | Add the temperatures and divide by three. | Type the temperatures. | Display the answer. | Name the source file.
multiple_choice | What should you inspect first when the compiler reports a missing closing brace? | The reported block and its matching opening brace | The screen brightness | Your account nickname | The console's background color
multiple_choice | Which line makes std::cout available to a C++ console program? | #include <iostream> | #include <cmath> | int main(); | using int;
multiple_choice | What does std::cout << "A" << '\n' << "B"; display? | A and B on separate lines | AB on a single line | A space and nothing else | The letter n between A and B
multiple_choice | Which characters start a C++ comment that can span several source lines? | /* | // | */ | ##
multiple_choice | Which is a valid name for a count of plant trays? | trayCount | tray count | 4trays | const
multiple_choice | After int a = 0, b = 0; std::cin >> a >> b;, what is a if the input is 12 7? | 12 | 7 | 19 | 127
multiple_choice | Which declaration stores a single letter as a char? | char code = 'N'; | char code = "N"; | char code = true; | char code = N;
multiple_choice | What is the value of 3 + 2 * 5? | 13 | 25 | 30 | 10
multiple_choice | Which line reads a full name with spaces into std::string name? | std::getline(std::cin, name); | std::cin >> name; | std::cout << name; | name.length();
QUESTIONS,
        'Term 2 · Decisions and collections' => <<<'QUESTIONS'
multiple_choice | What value should std::sqrt(64.0) give? | 8 | 16 | 32 | 4096
multiple_choice | Which expression is true only when both ready and checked are true? | ready && checked | ready || checked | !ready | !checked
multiple_choice | If n is 4, what is printed by if (n > 4) std::cout << "A"; else std::cout << "B";? | B | A | AB | Nothing
multiple_choice | In a switch statement, which label handles an integer value with no matching case? | default | continue | otherwise | main
multiple_choice | How many times does the body run in int x = 5; while (x < 3) { ++x; }? | 0 | 1 | 2 | 5
multiple_choice | How many times does for (int i = 1; i <= 3; ++i) execute its body? | 3 | 1 | 2 | 4
multiple_choice | What does break usually do in a single loop? | Exits that loop | Starts it again | Skips just one statement and resumes at the next | Doubles the counter
multiple_choice | Given int scores[] = {9, 4, 2};, what is scores[0]? | 9 | 4 | 2 | 0
multiple_choice | What does std::ceil(3.2) return? | 4 | 3 | 2 | 3.2
multiple_choice | Which value does !(7 < 3) produce? | true | false | 7 | 3
multiple_choice | Which condition is true when points is at least 15? | points >= 15 | points > 15 | points < 15 | points != 15
multiple_choice | Which statement keeps a switch case from continuing into the next case? | break; | continue; | case; | default;
multiple_choice | How many times does the body run in int n = 0; do { ++n; } while (n < 0);? | 1 | 0 | 2 | 3
multiple_choice | Which loop prints 0, 1, and 2 in that order? | for (int i = 0; i < 3; ++i) std::cout << i; | for (int i = 0; i <= 3; ++i) std::cout << i; | for (int i = 1; i < 3; ++i) std::cout << i; | for (int i = 2; i < 3; ++i) std::cout << i;
multiple_choice | Which statement skips the rest of the current loop iteration? | continue; | break; | default; | return 0;
multiple_choice | What is the last valid index for int distances[6]{};? | 5 | 6 | 1 | 7
QUESTIONS,
        'Term 3 · Data and functions' => <<<'QUESTIONS'
multiple_choice | Which C++ construct can group an item's label and quantity into one record? | A struct | A single char | An else statement | A comparison
multiple_choice | Which C++ type definition gives names to a fixed set of menu choices? | enum class | double | char[] | void
multiple_choice | After int count = 2; int& alias = count; alias = 3;, what is count? | 3 | 2 | 5 | 0
multiple_choice | Given int count = 6; int* p = &count;, which expression reads the value 6 through p? | *p | &p | p | p++
multiple_choice | Which statement releases an allocation made by int* p = new int(3);? | delete p; | delete[] p; | p = nullptr; | delete *p;
multiple_choice | Given int twice(int n) { return n * 2; }, what value does twice(4) return? | 8 | 4 | 6 | 16
multiple_choice | In scale(4, 5), what are the values 4 and 5 called? | Arguments | Return types | Headers | Function names
multiple_choice | Given struct Tray { int seeds; }; Tray a{6};, which expression reads the number of seeds? | a.seeds | Tray.seeds | a[seeds] | a->seeds
multiple_choice | Given enum class Fan { Off, Low, High };, which expression names the High choice? | Fan::High | Fan.High | Fan->High | High::Fan
multiple_choice | Given int n = 7;, which declaration makes r another name for n? | int& r = n; | int r = n; | int* r = &n; | int r = 0;
multiple_choice | Which expression obtains the address of an existing integer count? | &count | *count | count++ | count + 1
multiple_choice | Which statement releases memory allocated by int* readings = new int[5];? | delete[] readings; | delete readings; | readings = nullptr; | delete readings[5];
multiple_choice | Which return type fits a function that prints a message without returning a value? | void | int | bool | double
multiple_choice | Given int cost(int count, int price = 4) { return count * price; }, what is cost(3)? | 12 | 7 | 3 | 4
QUESTIONS,
    ],
    'post-test' => [
        'Term 1 · Foundations' => <<<'QUESTIONS'
multiple_choice | A bookcart algorithm must display the larger of two entered counts. Which test also checks a tie? | Enter 8 and 8 and expect 8. | Enter 3 and 8 and expect 3. | Enter 8 and 4 and expect 12. | Enter 5 and 9 and expect 5.
multiple_choice | A device accepts three humidity values and shows the highest. Which item describes output? | The highest humidity shown on the display | The three humidity readings | The comparison step | The filename
multiple_choice | A source file now contains a corrected formula. What is the best way to ensure the run uses it? | Save the source, compile it successfully, and run that build. | Run the previous executable twice. | Rename the old executable. | Write the expected value in a comment.
multiple_choice | After a successful build, a conversion program prints 70 for a case expected to give 7. What should you check? | The formula and the values used in the calculation | The login account role | Whether the compiler rejects all integers | Whether the source file needs an image extension
multiple_choice | Which line is a valid declaration of a function named main with no parameters followed by its body? | int main() { return 0; } | int main { return 0; } | main int() { return 0; } | int Main() { return 0; }
multiple_choice | Which of these statements contains a correctly quoted string literal? | std::cout << "Lab 8"; | std::cout << Lab 8; | std::cout << "Lab 8; | std::cout "Lab 8";
multiple_choice | What is displayed by std::cout << 5 << ':' << (8 - 3);? | 5:5 | 5:8-3 | 10 | 55
multiple_choice | Which statement displays the numeric value of 12 minus 4, followed by a newline? | std::cout << (12 - 4) << '\n'; | std::cout << "12 - 4" << '\n'; | std::cout << 12 << 4 << '\n'; | std::cout << '\n' << "12-4";
multiple_choice | What is displayed by std::cout << "Note // OK";? | Note // OK | Note | OK | Nothing
multiple_choice | Which comment explains why a program stops collecting readings at ten? | // The sensor stores only ten readings per trial. | // Ten is ten. | // Start of this line. | // Repeat the text of the next statement.
multiple_choice | After int red = 3, blue = red; red = 11;, what value is stored in blue? | 3 | 11 | 14 | 8
multiple_choice | Which declaration best represents a fixed capacity of 28 seats? | const int seatCapacity = 28; | int seatCapacity; | auto seatCapacity = 28; | int seatCapacity = 0;
multiple_choice | After int a = 0, b = 0; std::cin >> b >> a;, what is a + b when input is 14 6? | 20 | 8 | 14 | 146
multiple_choice | A program expects an integer from 1 through 12. Which input pair tests the upper boundary and the first value outside it? | 12 and 13 | 1 and 2 | 5 and 6 | 10 and 11
multiple_choice | Which declaration deduces double for distance? | auto distance = 12.5; | auto distance = 12; | auto distance = 'd'; | auto distance = false;
multiple_choice | What does std::cout << true; print by default without boolalpha? | 1 | true | 0 | -1
multiple_choice | After int count = 10; count -= 3 * 2;, what is count? | 4 | 14 | 20 | 8
multiple_choice | Which expression computes the remainder left when 23 items are packed in groups of 6? | 23 % 6 | 23 / 6 | 23 * 6 | 6 % 23
multiple_choice | Given std::string word = "Robot";, what is word[3]? | 'o' | 'b' | 'R' | 't'
multiple_choice | What is the length of std::string("Lab ") + "One"? | 7 | 6 | 8 | 3
QUESTIONS,
        'Term 2 · Decisions and collections' => <<<'QUESTIONS'
multiple_choice | A robot needs the real-valued square root of 121. Which value is returned by std::sqrt(121.0)? | 11 | 12.1 | 60.5 | 121
multiple_choice | Which value does std::ceil(-2.1) return? | -2 | -3 | 2 | 3
multiple_choice | Which expression checks that the lab is open and has space, given Boolean flags open and full? | open && !full | open && full | open || full | !open && full
multiple_choice | What does !(4 == 4 && 3 > 5) evaluate to? | true | false | 4 | An error
multiple_choice | For int score = 80;, what prints in if (score > 80) std::cout << "H"; else if (score == 80) std::cout << "M"; else std::cout << "L";? | M | H | L | HM
multiple_choice | Which expression chooses 2 when v is less than zero and 4 otherwise? | v < 0 ? 2 : 4 | v <= 0 ? 2 : 4 | v < 0 ? 4 : 2 | v > 0 ? 2 : 4
multiple_choice | In a switch, case 2 prints A and falls through to case 3, which prints B and breaks. Which text prints for option 2? | AB | A | B | BA
multiple_choice | Which form is valid for a switch that takes a char named option? | case 'Q': | case "Q": | case Q(): | default 'Q':
multiple_choice | What does int n = 5; while (n > 1) { std::cout << n; n -= 2; } display? | 53 | 531 | 35 | 5432
multiple_choice | How many times does int k = 8; do { k -= 3; } while (k > 1); execute its body? | 3 | 2 | 4 | 8
multiple_choice | How many times does for (int i = 2; i <= 10; i += 4) run its body? | 3 | 2 | 4 | 8
multiple_choice | Given int values[] = {3, 2, 5}; int sum = 0; for (int v : values) sum += v;, what is sum? | 10 | 5 | 3 | 0
multiple_choice | What prints from for (int i = 0; i < 5; ++i) { if (i == 2) break; std::cout << i; }? | 01 | 012 | 0134 | 234
multiple_choice | What prints from for (int i = 1; i <= 5; ++i) { if (i % 2 == 0) continue; std::cout << i; }? | 135 | 24 | 12345 | 15
multiple_choice | Given int map[2][3] = {{8,6,4},{3,1,5}};, what is map[1][1]? | 1 | 6 | 3 | 5
multiple_choice | Which loop accesses every index of int a[4]{}; exactly once without going out of bounds? | for (int i = 0; i < 4; ++i) a[i] = i; | for (int i = 0; i <= 4; ++i) a[i] = i; | for (int i = 1; i <= 4; ++i) a[i] = i; | for (int i = 0; i < 3; ++i) a[i] = i;
QUESTIONS,
        'Term 3 · Data and functions' => <<<'QUESTIONS'
multiple_choice | Given struct Pair { int left; int right; }; Pair readings{4,9};, which expression gives 13? | readings.left + readings.right | readings.left * readings.right | readings.right - readings.left | Pair.left + Pair.right
multiple_choice | Given struct Part { int stock; }; Part a{3}; Part b = a; b.stock = 8;, what is a.stock? | 3 | 8 | 11 | 0
multiple_choice | Given enum class Zone { Lab = 5, Hall, Gate };, what is static_cast<int>(Zone::Gate)? | 7 | 5 | 6 | 8
multiple_choice | Which expression checks if variable action of type Mode is equal to Mode::Sleep? | action == Mode::Sleep | action = Mode::Sleep | action == "Sleep" | Mode.action == Sleep
multiple_choice | After int n = 8; int& alias = n; alias -= 2;, what is n? | 6 | 8 | 2 | 10
multiple_choice | Given int a = 4, b = 7; int& r = a; r = b; b = 11;, what is r? | 7 | 4 | 11 | 0
multiple_choice | After int n = 2; int* p = &n; *p *= 4;, what is n? | 8 | 2 | 4 | 6
multiple_choice | Which expression compares two pointers p and q to see if they store the same address? | p == q | *p == *q | p = q | &p == &q
multiple_choice | If int* p = new int[7]; created an array, which expression releases it correctly? | delete[] p; | delete p; | delete p[0]; | p = nullptr;
multiple_choice | After delete p; frees an allocation, q still holds its old address. What should the program do before accessing anything through q? | Give q the address of a live object or stop using it. | Read *q immediately. | Delete the same allocation again through q. | Assume q was set to nullptr by delete p.
multiple_choice | Given int combine(int a, int b) { return a * 2 + b; }, what does combine(3, 4) return? | 10 | 14 | 7 | 24
multiple_choice | Which declaration lets main call a function defined later that takes a double and returns bool? | bool ready(double voltage); | double ready(bool voltage); | ready(double voltage); | bool ready;
multiple_choice | Given int area(int width, int height = 3) { return width * height; }, what does area(4, 5) return? | 20 | 12 | 15 | 9
multiple_choice | Given void boost(int& points) { points += 3; } and int total = 9;, what is total after boost(total);? | 12 | 9 | 3 | 27
QUESTIONS,
    ],
    'reasoning' => [
        'Quantitative reasoning' => <<<'QUESTIONS'
multiple_choice | Which number comes next in 4, 9, 16, 25, 36? | 49 | 42 | 47 | 54
identification | The ratio of blue to red counters is 3 to 5. There are 48 counters. Enter the number of blue counters. | 18
multiple_choice | Two-fifths of 35 seedlings sprouted on Monday. How many had yet to sprout? | 21 | 14 | 28 | 33
multiple_answer | Select every expression equal to 36. | 6 × 6 / 45 − 9 / 72 ÷ 2 | 6 × 6 | 30 + 8 | 45 − 9 | 72 ÷ 2 | 4 × 8
multiple_choice | A bus travels 54 km in 45 minutes at constant speed. How far does it travel in 15 minutes? | 18 km | 12 km | 27 km | 36 km
identification | Four quiz scores have a mean of 12. Three scores are 9, 11 and 15. Enter the fourth score. | 13
multiple_choice | Which list is in increasing order? | 0.58, 3/5, 62%, 0.65 | 3/5, 0.58, 0.65, 62% | 62%, 3/5, 0.58, 0.65 | 0.58, 0.65, 3/5, 62%
multiple_choice | A number goes from 80 to 100. What is the percentage increase? | 25% | 20% | 40% | 125%
true_false | A number divisible by both 4 and 6 must be divisible by 12. | True | False
identification | A bag contains 5 green, 3 blue and 2 red counters. Enter the probability of selecting red as a percentage, using digits only. | 20
QUESTIONS,
        'Logical reasoning' => <<<'QUESTIONS'
multiple_choice | All samples in tray A have labels. Sample K has no label. Which conclusion is required? | K is not in tray A. | K is in tray A. | Every labelled sample is in tray A. | Tray A is empty.
multiple_choice | Mira finished before Carlo. Carlo finished before Lian. Which sequence is possible? | Mira, Carlo, Lian | Lian, Mira, Carlo | Carlo, Lian, Mira | Lian, Carlo, Mira
multiple_choice | Rule: if a lamp is on, its indicator is green. The indicator is green. What can you conclude about the lamp from that rule alone? | It may be on or off. | It must be on. | It must be off. | It was on earlier.
multiple_answer | A code has two different digits, begins with an even digit, and is above 30. Select every possible code listed. | 42 / 64 / 86 | 42 | 22 | 64 | 35 | 86
true_false | If every triangle in a box is blue, then every blue shape in the box must be a triangle. | False | True
multiple_choice | Four lockers in a row are numbered 1 to 4. A is immediately left of B. C is in locker 1 and D is in locker 4. Where is B? | Locker 3 | Locker 1 | Locker 2 | Locker 4
multiple_choice | Each step doubles the previous number and subtracts 1. After 2, 3, 5, 9, which number follows? | 17 | 15 | 18 | 19
multiple_choice | Which example disproves the claim that every multiple of 3 is odd? | 6 | 3 | 9 | 15
multiple_choice | A robot may start only if its battery is charged and its cover is closed. The battery is charged and the cover is open. What should the robot do? | Stay stopped. | Start immediately. | Start because one condition is met. | Ignore the cover condition.
multiple_choice | P is older than Q. R is younger than Q. S is older than P. Who is the oldest? | S | P | Q | R
QUESTIONS,
        'Verbal and relational reasoning' => <<<'QUESTIONS'
multiple_choice | Microscope is to tiny cells as telescope is to which object? | Distant stars | Small labels | Heavy bags | Warm liquids
multiple_choice | Which word is closest in meaning to precise? | Exact | Early | Approximate | Large
multiple_choice | Which pair has the same relationship as seed to plant? | Egg to bird | Leaf to tree | Teacher to lesson | Star to sky
multiple_choice | Which word belongs least with the others? | Estimate | Compare | Contrast | Differentiate
multiple_choice | Which sentence best states a conclusion from “All three trial times were shorter after the track was cleaned”? | The recorded trial times decreased after cleaning. | The next trial will always be faster. | Cleaning was the only possible cause. | Each trial took the same time.
multiple_answer | Which words describe an increase in quantity? | rose / grew | fell | rose | shrank | grew | declined
multiple_choice | Thermometer is to temperature as stopwatch is to what? | Elapsed time | Mass | Distance | Brightness
multiple_choice | What does “revise a claim in light of new evidence” mean? | Update the claim after considering the new evidence. | Remove the evidence to keep the claim. | Keep the claim exactly the same in every case. | Replace the claim with an unrelated fact.
multiple_choice | Which sentence has the clearest instruction? | Add 10 mL of water to each cup. | Add a little to those things. | Use enough for all of them. | Add it when you think the cups are ready.
multiple_choice | A report says there was “insufficient evidence.” What does that mean? | The available evidence was not enough to decide. | There was proof that the claim was false. | There was proof that the claim was true. | Nobody collected any evidence at all.
QUESTIONS,
        'Spatial and pattern reasoning' => <<<'QUESTIONS'
multiple_choice | A marker faces north. Rotate it 90 degrees clockwise, then 180 degrees clockwise. Which direction does it face? | West | East | North | South
multiple_choice | On a coordinate grid, x increases rightward and y upward. Reflect (3, -2) across the vertical axis. What is its image? | (-3, -2) | (3, 2) | (-3, 2) | (-2, 3)
multiple_choice | Start at the center of a square grid. Move 2 squares right, 3 up, then 1 left. Where are you relative to the start? | 1 right and 3 up | 3 right and 1 up | 1 left and 3 up | 2 right and 2 up
identification | A rectangle is made from 4 rows of 6 identical square tiles. Enter the number of tiles on the border, counting each corner once. | 16
multiple_choice | Which shape has exactly one line of symmetry? | A non-equilateral isosceles triangle | A square | A circle | A scalene triangle
multiple_choice | Which letter follows the pattern B, E, I, N, T if the step increases by one each time and wraps after Z? | A | Z | B | Y
multiple_choice | In a cube made from 3 × 3 × 3 small cubes, the outside is painted. How many small cubes have paint on exactly three faces? | 8 | 6 | 12 | 27
true_false | Rotating a square around its center by 90 degrees makes it occupy the same outline. | True | False
multiple_answer | Which directions are perpendicular to a westward arrow? | North / South | North | East | South | West
identification | On a 5 by 5 grid of unit squares, enter the number of 2 by 2 squares whose sides follow the grid lines. | 16
QUESTIONS,
        'Scientific and data reasoning' => <<<'QUESTIONS'
multiple_choice | Water temperatures at 1, 2, 3 and 4 minutes are 20, 24, 31 and 33 °C. During which interval did the temperature increase most? | Minutes 2 to 3 | Minutes 1 to 2 | Minutes 3 to 4 | The intervals were equal.
multiple_choice | Two groups of identical plants get the same light and soil but different amounts of water. What is the independent variable? | Amount of water | Plant height at the end | Type of soil | Amount of light
multiple_choice | A class found that more study minutes tended to accompany higher scores. What does this observation alone establish? | An association in the collected data | Proof that time caused the difference | A guaranteed score for each minute | The best method for every student
multiple_answer | To test the effect of light on one plant species, which conditions should stay the same across groups? | Soil type / Water amount / Plant species | Soil type | Light level | Water amount | Plant species
identification | The times for five runs are 9, 11, 11, 13 and 26 seconds. Enter the median time in seconds. | 11
multiple_choice | Which sample best represents the class's preference for a project? | Randomly select students from the whole class. | Ask only the students who proposed one project. | Ask only the two fastest finishers. | Ask one friend five times.
multiple_choice | A balance shows 15 g with an empty container and 57 g with the container and sand. What is the mass of the sand? | 42 g | 57 g | 72 g | 15 g
true_false | Repeating a measurement can help identify an unusually high or low result. | True | False
multiple_choice | A test finds 8 out of 10 seeds sprout in sunlight and 7 out of 10 in shade. Which next step improves confidence in this comparison? | Repeat with more seeds under controlled conditions. | Remove the seeds that did not sprout. | Report that sunlight always causes germination. | Change both light and water at once.
multiple_choice | Which graph best shows how a robot's battery percentage changed at regular time intervals? | A line graph with time on the horizontal axis | A pie chart with one slice for each time | A diagram of the robot's parts | A tally with no time values
QUESTIONS,
    ],
];
