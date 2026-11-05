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
QUESTIONS,
        'Term 3 · Data and functions' => <<<'QUESTIONS'
multiple_choice | Which C++ construct can group an item's label and quantity into one record? | A struct | A single char | An else statement | A comparison
multiple_choice | Which C++ type definition gives names to a fixed set of menu choices? | enum class | double | char[] | void
multiple_choice | After int count = 2; int& alias = count; alias = 3;, what is count? | 3 | 2 | 5 | 0
multiple_choice | Given int count = 6; int* p = &count;, which expression reads the value 6 through p? | *p | &p | p | p++
multiple_choice | Which statement releases an allocation made by int* p = new int(3);? | delete p; | delete[] p; | p = nullptr; | delete *p;
multiple_choice | Given int twice(int n) { return n * 2; }, what value does twice(4) return? | 8 | 4 | 6 | 16
multiple_choice | In scale(4, 5), what are the values 4 and 5 called? | Arguments | Return types | Headers | Function names
QUESTIONS,
    ],
    'post-test' => [
        'Term 1 · Foundations' => <<<'QUESTIONS'
multiple_choice | A ticket tool should collect a quantity and display the total price. Which algorithm follows that specification? | Read quantity, multiply by unit price, display the result. | Display the quantity before reading it, then stop. | Read quantity and ignore the unit price. | Add one ticket to the total price and display the unit price.
multiple_choice | You fixed main.cpp, but your program still shows the old output. Which step is most useful first? | Save, rebuild and run the updated program. | Rename the executable without rebuilding. | Change the expected output to the old result. | Clear the output without running the program.
multiple_choice | Which edit completes the statement std::cout << "Begin" inside main? | Append a semicolon. | Add a second opening brace. | Replace the closing quote with a colon. | Remove the angle brackets in the header.
multiple_choice | What does std::cout << (6 + 4) << '-' << 3; display? | 10-3 | 6+4-3 | 7 | 103
multiple_choice | What does /* old message */ std::cout << "Now"; display? | Now | old message Now | old message | Nothing
multiple_choice | Which declaration prevents later reassignment of the class capacity? | const int capacity = 32; | int capacity = 32; | auto capacity = 32; | int capacity;
multiple_choice | With int x = 0, y = 0; std::cin >> x >> y;, what is x - y after entering 11 3? | 8 | -8 | 14 | 113
multiple_choice | Which expression calculates a fractional average of 9 and 4? | (9 + 4) / 2.0 | (9 + 4) / 2 | 9 + 4 / 2.0 | (9 + 4) % 2
multiple_choice | After int total = 3; total += 2 * 4;, what is total? | 11 | 20 | 24 | 8
multiple_choice | Given std::string label = "Mars";, what does label[1] contain? | 'a' | 'M' | 'r' | 's'
QUESTIONS,
        'Term 2 · Decisions and collections' => <<<'QUESTIONS'
multiple_choice | Which value does std::floor(-2.3) return? | -3 | -2 | 2 | 3
multiple_choice | Which expression is true when exactly one of the Boolean flags north and south is true? | north != south | north && south | north == south | north || south
multiple_choice | Which expression returns "retry" if points is under 10 and "ready" otherwise? | points < 10 ? "retry" : "ready" | points <= 10 ? "ready" : "retry" | points > 10 ? "retry" : "ready" | points == 10 ? "retry" : "ready"
multiple_choice | In a switch, case 1 prints X and has no break. Case 2 prints Y then breaks. What prints when option 1 is selected? | XY | X | Y | Nothing
multiple_choice | What value remains after int k = 1; while (k < 9) { k += 3; }? | 10 | 7 | 9 | 12
multiple_choice | An outer loop makes three rows. An inner loop prints four symbols for each row. How many symbols print? | 12 | 7 | 9 | 16
multiple_choice | What is printed by for (int i = 0; i < 5; ++i) { if (i == 1) continue; if (i == 4) break; std::cout << i; }? | 023 | 0123 | 0234 | 0
multiple_choice | Given int grid[2][3] = {{2,4,6},{8,10,12}};, what is grid[1][0]? | 8 | 2 | 4 | 12
QUESTIONS,
        'Term 3 · Data and functions' => <<<'QUESTIONS'
multiple_choice | Given struct Seed { int count; }; Seed tray{4};, which statement increases the count by two? | tray.count += 2; | Seed.count += 2; | tray += 2; | count.tray += 2;
multiple_choice | Given enum class Signal { Stop, Ready, Go }; and Signal s, which expression compares s with Go? | s == Signal::Go | s = Signal::Go | s == "Go" | s == Signal.Go
multiple_choice | Given int x = 3, y = 9; int& ref = x; ref = y; y = 2;, what is ref now? | 9 | 3 | 2 | 11
multiple_choice | After int a = 4, b = 6; int* p = &a; p = &b; *p += 3;, what is b? | 9 | 6 | 7 | 4
multiple_choice | A program allocates int* values = new int[3];. Which line releases the array? | delete[] values; | delete values; | values = nullptr; | delete values[0];
multiple_choice | Given int next(int x) { return x + 1; }, what value is returned by next(next(6))? | 8 | 6 | 7 | 13
multiple_choice | Given void addOne(int& n) { ++n; } and int x = 4;, what happens after addOne(x);? | x becomes 5. | x remains 4. | x becomes 1. | The call returns 5.
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
