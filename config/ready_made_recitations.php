<?php

$banks = [
    'introduction' => <<<'QUESTIONS'
What is the term for an ordered set of steps used to solve a problem? | algorithm / an algorithm
What tool translates C++ source into a program the computer can run? | compiler / a compiler
In the sequence input → process → output, which stage performs calculations? | process / processing
What do we call a mistake in a program that needs fixing? | bug / a bug
What is the name of the activity of finding and fixing program mistakes? | debugging
QUESTIONS,
    'get-started' => <<<'QUESTIONS'
What file extension is commonly used for a C++ source file? Include the dot. | .cpp
Which function is the usual starting point of a C++ console program? | main / main()
After editing source code, what build step must succeed before running the new version? | compilation / compile / compiling
What does IDE stand for? | integrated development environment
Which command in an online editor starts executing a successfully built program: Run or Reset? | Run
QUESTIONS,
    'syntax' => <<<'QUESTIONS'
Which punctuation mark ends most C++ statements? | ; / semicolon / a semicolon
Which pair of symbols encloses a C++ block? | {} / { } / curly braces / braces
Are the identifiers score and Score the same identifier in C++? Answer yes or no. | no
What keyword introduces a function that returns an integer, as in int main()? | int
In return 0;, which numeric value is returned? | 0 / zero
QUESTIONS,
    'output' => <<<'QUESTIONS'
Which standard output object follows std:: when printing to the console? | cout / std::cout
Which insertion operator sends a value to std::cout? | <<
What escape sequence moves output to a new line? Type the backslash too. | \n
What is printed by std::cout << 6 + 4;? | 10
What is printed by std::cout << "6" << "4";? | 64
QUESTIONS,
    'comments' => <<<'QUESTIONS'
Which two characters start a single-line comment in C++? | //
Which two characters open a multi-line C++ comment? | /*
Which two characters close a multi-line C++ comment? | */
Does a normal source-code comment execute as a program instruction? Answer yes or no. | no
In int points = 7; // bonus, what value is assigned to points? | 7
QUESTIONS,
    'variables' => <<<'QUESTIONS'
In int lives = 3;, what is the variable's identifier? | lives
What value does x hold after int x = 4; x = x + 2;? | 6
Which keyword makes a variable's value unchangeable after initialization? | const
May a C++ identifier begin with a digit? Answer yes or no. | no
What value does total hold after int a = 3, b = 5; int total = a + b;? | 8
QUESTIONS,
    'input' => <<<'QUESTIONS'
Which standard input object follows std:: when reading keyboard input? | cin / std::cin
Which extraction operator is used with std::cin? | >>
If the input is 12, what value does age hold after std::cin >> age;? | 12
Which statement reads into count: std::cin >> count; or std::cout << count;? Copy the correct statement. | std::cin >> count; / std::cin>>count;
If input n is 4, what is printed by std::cout << n * 3;? | 12
QUESTIONS,
    'data-types' => <<<'QUESTIONS'
Which basic C++ type stores true or false? | bool
Which basic type is used for a single character such as 'A'? | char
Which type fits a whole-number count: int or double? | int
Which keyword asks the compiler to infer a variable's type from its initializer? | auto
Does 7.0 / 2 produce an integer-only result or a floating-point result? | floating-point / floating point / a floating-point result
QUESTIONS,
    'operators' => <<<'QUESTIONS'
What is the result of integer division 9 / 2 in C++? | 4
What is the result of 9 % 2? | 1
What is the value of x after int x = 3; x += 4;? | 7
Which comparison operator checks equality? | ==
What is the result of 2 + 3 * 4? | 14
QUESTIONS,
    'strings' => <<<'QUESTIONS'
Which standard-library type stores text such as "robot"? | std::string / string
What is the length of the string "CODE"? | 4
What character is at index 0 in std::string word = "Robot";? | R / 'R'
What text results from std::string("Code") + "Quest"? | CodeQuest
Which standard input function can read a whole line including spaces? | std::getline / getline / getline()
QUESTIONS,
    'math' => <<<'QUESTIONS'
What is the result of std::sqrt(81.0)? | 9 / 9.0
What is the result of std::pow(3.0, 2.0)? | 9 / 9.0
Which header provides std::sqrt and std::ceil? Include the angle brackets. | <cmath>
What is the result of std::ceil(4.2)? | 5 / 5.0
What is the result of std::floor(4.8)? | 4 / 4.0
QUESTIONS,
    'booleans' => <<<'QUESTIONS'
What Boolean value results from 8 > 3? | true
What Boolean value results from !true? | false
What does std::cout normally print for true without std::boolalpha? | 1
What Boolean value results from true && false? | false
What Boolean value results from false || true? | true
QUESTIONS,
    'conditions' => <<<'QUESTIONS'
Which keyword begins a conditional branch in C++? | if
Which keyword introduces the alternative branch when the if condition is false? | else
For int score = 80;, is score >= 75 true or false? | true
What is printed by if (3 > 5) { std::cout << "A"; } else { std::cout << "B"; }? | B
What do we call an if statement inside another if statement? | nested if / a nested if / nested condition
QUESTIONS,
    'switch' => <<<'QUESTIONS'
Which keyword starts a multi-case selection in C++? | switch
Which keyword labels a matching value inside a switch? | case
Which keyword commonly ends a case so execution does not continue into the next one? | break
Which switch label handles values that match none of the named cases? | default
In switch (2) { case 1: break; case 2: std::cout << "Go"; break; }, what is printed? | Go
QUESTIONS,
    'while-loops' => <<<'QUESTIONS'
Which loop checks its condition before each repetition: while or do-while? | while
Which loop runs its body at least once: while or do-while? | do-while / do while
How many repetitions occur in int n = 0; while (n < 3) { n++; }? | 3
What is the final value of n after int n = 3; while (n > 0) { n--; }? | 0
What do we call a special input value used to stop a loop, such as -1? | sentinel / sentinel value / a sentinel value
QUESTIONS,
    'for-loops' => <<<'QUESTIONS'
How many repetitions occur in for (int i = 0; i < 4; i++)? | 4
What is the first value of i in for (int i = 5; i >= 1; i--)? | 5
Which update decreases i by one: i++ or i--? | i--
How many times does the inner body run when a 2-iteration loop contains a 3-iteration loop? | 6
In for (int value : values), what collection is being visited? | values
QUESTIONS,
    'break-continue' => <<<'QUESTIONS'
Which keyword exits the nearest enclosing loop immediately? | break
Which keyword skips the remaining statements in the current loop repetition? | continue
If a loop counter reaches 3 and executes break, does the loop normally continue to 4? Answer yes or no. | no
How many values are printed by for (int i = 1; i <= 4; i++) { if (i == 2) continue; std::cout << i; }? | 3
How many values are printed by for (int i = 1; i <= 4; i++) { if (i == 2) break; std::cout << i; }? | 1
QUESTIONS,
    'arrays' => <<<'QUESTIONS'
What is the first valid index of a C++ array? | 0 / zero
What is the last valid index of int marks[5];? | 4
What is the value of a[1] after int a[3] = {4, 7, 9};? | 7
How many elements are in int grid[2][3];? | 6
What is the sum of the elements in int a[3] = {2, 4, 6};? | 12
QUESTIONS,
    'structures' => <<<'QUESTIONS'
Which keyword defines a simple structure in C++? | struct
Which operator accesses an object's member, as in item.quantity? | . / dot / dot operator
In struct Book { int pages; };, what is the member's name? | pages
After struct Box { int size; }; Box b{5};, what is b.size? | 5
Which punctuation mark follows the closing brace of a struct definition? | ; / semicolon
QUESTIONS,
    'enums' => <<<'QUESTIONS'
Which keyword defines an enumeration in C++? | enum
In enum class Stage { Read, Code };, what is the scoped name for Code? | Stage::Code
What is the first default value in enum Color { Red, Green, Blue };? | 0 / zero
For enum Color { Red, Green, Blue };, what integer value does Green have? | 1
Is Stage::Read the same named value as Stage::Code? Answer yes or no. | no
QUESTIONS,
    'references' => <<<'QUESTIONS'
In int& alias = count;, which existing variable does alias refer to? | count
What symbol is used in int& alias to declare a reference? | & / ampersand
What is x after int x = 4; int& r = x; r = 9;? | 9
Do x and its reference r refer to the same object? Answer yes or no. | yes
Which operator obtains an object's memory address when used as &x? | & / address-of / address-of operator
QUESTIONS,
    'pointers' => <<<'QUESTIONS'
What symbol declares a pointer in int* p;? | * / asterisk
What does &x produce: the value of x or its address? | address / its address / the address of x
Which operator dereferences p in the expression *p? | * / asterisk
What is x after int x = 4; int* p = &x; *p = 7;? | 7
Which C++ literal represents a pointer that does not point to an object? | nullptr
QUESTIONS,
    'memory-management' => <<<'QUESTIONS'
Which keyword dynamically allocates one integer in new int(5)? | new
Which keyword releases an object allocated with a matching single-object new? | delete
Which form releases an array created with new int[4]? | delete[] / delete []
What do we call dynamically allocated memory that is no longer reachable and was not released? | memory leak / a memory leak
After deleting an allocation, should a learner dereference its old pointer? Answer yes or no. | no
QUESTIONS,
    'functions' => <<<'QUESTIONS'
What is returned by int doubleIt(int n) { return n * 2; } when called with 6? | 12
Which keyword sends a result back from a function? | return
Which return type indicates that a function returns no value? | void
In square(5), what number is passed to the function? | 5
If a function is defined after main(), what declaration can introduce it before the call? | function prototype / prototype / a function prototype
QUESTIONS,
    'function-parameters' => <<<'QUESTIONS'
In int add(int a, int b), how many parameters are declared? | 2 / two
In add(3, 4), what are 3 and 4 called: parameters or arguments? | arguments
If int supplies(int teams, int perTeam = 4) returns teams * perTeam, what does supplies(3) return? | 12
Using that same supplies function, what does supplies(3, 2) return? | 6
In void change(int& n), is n passed by value or by reference? | by reference / reference
QUESTIONS,
    'arduino-robotics' => <<<'QUESTIONS'
In the robotics cycle sense → decide → act, which stage reads a sensor? | sense / sensing
What is the general name for a component that measures the environment? | sensor / a sensor
What is the general name for a component that produces motion or another physical action? | actuator / an actuator
Which part executes an Arduino sketch: the microcontroller or the breadboard? | microcontroller / the microcontroller
After a robot acts, should it check its sensors again? Answer yes or no. | yes
QUESTIONS,
    'arduino-kit-safety' => <<<'QUESTIONS'
Before changing breadboard wires, should circuit power be on or off? | off / power off
Which unit is used for electrical resistance? | ohm / ohms / Ω
Which component is commonly placed in series with an LED to limit current? | resistor / a resistor
Should a bare wire directly connect 5V to GND? Answer yes or no. | no
Who should inspect an unpowered classroom circuit before the first power-on? | teacher / the teacher / ICT teacher
QUESTIONS,
    'arduino-breadboards' => <<<'QUESTIONS'
What component lets you make temporary circuit connections without soldering? | breadboard / a breadboard
Does an ordinary breadboard generate its own electrical power? Answer yes or no. | no
Do the two sides of a typical breadboard's center gap connect automatically across that gap? Answer yes or no. | no
What type of wire is used to connect breadboard points to Arduino pins? | jumper wire / jumper wires
Before using a long power rail, should you check whether it is split? Answer yes or no. | yes
QUESTIONS,
    'arduino-uno-board' => <<<'QUESTIONS'
How many labeled digital I/O pins D0 through D13 are on an Uno R3? | 14
How many labeled analog inputs A0 through A5 are on an Uno R3? | 6
What pin label represents the circuit's ground reference? | GND
What cable connection is commonly used to upload sketches from a computer to an Uno R3? | USB / USB cable
Which constant names the built-in LED pin without writing its pin number? | LED_BUILTIN
QUESTIONS,
    'arduino-ide-upload' => <<<'QUESTIONS'
What is an Arduino source program commonly called? | sketch / a sketch
What is the usual Arduino sketch file extension? Include the dot. | .ino
Which IDE action checks/builds a sketch without intentionally sending it to the board: Verify or Upload? | Verify
Which function runs once after an Arduino starts or resets? | setup / setup()
Which function repeats after setup() finishes? | loop / loop()
QUESTIONS,
    'arduino-blink' => <<<'QUESTIONS'
Which pin mode is used to drive a normal LED output? | OUTPUT
What Arduino function sets a digital output HIGH or LOW? | digitalWrite / digitalWrite()
How many milliseconds are in one second? | 1000 / 1,000
For the Uno built-in LED, which digital state normally turns it on: HIGH or LOW? | HIGH
How long is delay(500) in seconds? | 0.5 / 0.5 seconds / half a second
QUESTIONS,
    'arduino-sketch-logic' => <<<'QUESTIONS'
What is count after int count = 2; count++;? | 3
What keyword declares a pin number that should not change during the program? | const
Which conditional keyword chooses between two Arduino code paths? | if
What is the Boolean result of 3 <= 3? | true
What punctuation mark normally ends an Arduino C++ statement? | ; / semicolon
QUESTIONS,
    'arduino-buttons' => <<<'QUESTIONS'
Which input mode enables the Uno's internal pull-up resistor? | INPUT_PULLUP
With INPUT_PULLUP and a button wired to GND, is a pressed button normally read as HIGH or LOW? | LOW
Which Arduino function reads a digital input pin? | digitalRead / digitalRead()
What problem describes a switch making several rapid transitions during one press? | bouncing / switch bounce / contact bounce / bounce
Does a button reading need to be interpreted according to its wiring? Answer yes or no. | yes
QUESTIONS,
    'arduino-traffic-lights' => <<<'QUESTIONS'
Which traffic-light color commonly means stop? | red
How many separate digital outputs are needed for three independently controlled single-color LEDs? | 3 / three
How many milliseconds does delay(3000) wait? | 3000 / 3,000
Which function configures a traffic-light pin as OUTPUT? | pinMode / pinMode()
Should three separate LEDs each have appropriate current limiting? Answer yes or no. | yes
QUESTIONS,
    'arduino-analog-input' => <<<'QUESTIONS'
Which function reads an analog input on an Uno R3? | analogRead / analogRead()
What is the lowest normal raw result of analogRead on the Uno R3? | 0 / zero
What is the highest normal 10-bit analogRead result on the Uno R3? | 1023 / 1,023
What board label identifies analog input zero? | A0
Assuming a 5.0-volt reference, what estimated voltage corresponds to raw value 1023? | 5 / 5.0 / 5 V / 5 volts
QUESTIONS,
    'arduino-pwm' => <<<'QUESTIONS'
What does PWM stand for? | pulse width modulation / pulse-width modulation
Which Arduino function sets a PWM duty value on a supported Uno pin? | analogWrite / analogWrite()
What is the maximum usual analogWrite duty argument on an Uno R3? | 255
What duty argument gives zero on-time in ordinary analogWrite use? | 0 / zero
Does Uno PWM create a continuously variable true analog voltage directly at the pin? Answer yes or no. | no
QUESTIONS,
    'arduino-rgb' => <<<'QUESTIONS'
What three color-channel names are represented by RGB? Answer as R, G, B words. | red green blue / red, green, blue / red, green and blue
How many independently controlled channels does an RGB LED have? | 3 / three
For the common-cathode example color(255, 0, 0), which color is requested? | red
For the common-cathode example color(0, 0, 0), are the channels on or off? | off
Should you identify whether an RGB LED is common-anode or common-cathode before wiring it? Answer yes or no. | yes
QUESTIONS,
    'arduino-light-sensor' => <<<'QUESTIONS'
What does LDR stand for? | light dependent resistor / light-dependent resistor
What circuit arrangement converts a resistance change into a measurable voltage? | voltage divider / a voltage divider
In bool dark = lightValue < 400;, is dark true when lightValue is 250? | true
Using the same condition, is dark true when lightValue is 600? | false
Should a light threshold be calibrated for the actual classroom circuit? Answer yes or no. | yes
QUESTIONS,
    'arduino-buzzer' => <<<'QUESTIONS'
Which Arduino function generates a tone on a pin? | tone / tone()
Which function stops an ongoing tone on the specified pin? | noTone / noTone()
What unit measures sound frequency? | hertz / Hz
Which tone has the higher frequency: 1000 Hz or 1500 Hz? | 1500 / 1500 Hz / 1,500 Hz
In tone(8, 1000, 150), what is the duration in milliseconds? | 150
QUESTIONS,
    'arduino-servo' => <<<'QUESTIONS'
Which library is used by the lesson's Servo arm example? | Servo / Servo.h / <Servo.h>
Which Servo method connects the object to a signal pin? | attach / attach()
Which Servo method requests an angle in the lesson example? | write / write()
What angle is requested by arm.write(90)? | 90 / 90 degrees
Should a servo's mechanical movement range be checked before running the program? Answer yes or no. | yes
QUESTIONS,
    'arduino-ultrasonic' => <<<'QUESTIONS'
Which sensor pin sends the ultrasonic trigger command: trigger or echo? | trigger / trig
Which Arduino function measures how long an input pulse stays HIGH? | pulseIn / pulseIn()
In the lesson calculation, why is sound travel time divided by 2: one-way distance or twice the speed? | one-way distance / one way distance / to get one-way distance
What result does pulseIn return when the measurement times out? | 0 / zero
Should a missing echo automatically be treated as a confirmed zero-centimeter obstacle distance? Answer yes or no. | no
QUESTIONS,
    'arduino-temperature' => <<<'QUESTIONS'
Which temperature sensor model is used by the lesson's (volts - 0.5) * 100 formula? | TMP36
What is the estimated Celsius temperature when that TMP36 formula receives volts = 0.75? | 25 / 25 C / 25 degrees Celsius
What is the estimated Celsius temperature when volts = 0.5 in the same formula? | 0 / 0 C / zero
Should you check a temperature sensor's exact pinout before connecting power? Answer yes or no. | yes
Are these classroom sensor estimates suitable as a medical thermometer? Answer yes or no. | no
QUESTIONS,
    'arduino-lcd' => <<<'QUESTIONS'
Which library is used for the parallel character LCD in this lesson? | LiquidCrystal / LiquidCrystal.h / <LiquidCrystal.h>
How many columns are configured by lcd.begin(16, 2)? | 16
How many rows are configured by lcd.begin(16, 2)? | 2
Which LCD method positions the text cursor? | setCursor / setCursor()
In lcd.setCursor(0, 1), which zero-based row index is selected? | 1
QUESTIONS,
    'arduino-keypad' => <<<'QUESTIONS'
How many key positions are on a 4 by 4 matrix keypad? | 16
What collection type stores the lesson's row-pin numbers? | array / an array
In a matrix keypad, does the program scan rows and columns or measure temperature? | rows and columns / scan rows and columns
With a pulled-up column and an active LOW row, what column state indicates a pressed key? | LOW
Can a simple keypad scanner require debounce handling for reliable single presses? Answer yes or no. | yes
QUESTIONS,
    'arduino-joystick' => <<<'QUESTIONS'
How many analog axes does the lesson's joystick read? | 2 / two
What function reads the joystick's A0 and A1 axes? | analogRead / analogRead()
What term describes a center range that ignores small joystick variations? | dead zone / deadzone / a dead zone
In the lesson's if (x < 450), what direction is reported for x = 300? | LEFT
With a pull-up button wired to GND, which digital state means the joystick button is pressed? | LOW
QUESTIONS,
    'arduino-pir' => <<<'QUESTIONS'
What does PIR stand for in a PIR motion sensor? | passive infrared / passive infra-red
Does a PIR module normally need settling time after power-on? Answer yes or no. | yes
In the lesson's bool event = digitalRead(motionPin) == HIGH;, which input state means an event? | HIGH
How many seconds does delay(60000) wait? | 60 / sixty
Does a basic PIR module identify which named student moved? Answer yes or no. | no
QUESTIONS,
    'arduino-motor-driver' => <<<'QUESTIONS'
Should a DC motor be powered directly from an Uno GPIO pin? Answer yes or no. | no
What module is used between the control pins and the motor in this lesson? | motor driver / a motor driver / motor driver module
Which analogWrite duty value disables ordinary PWM drive on enablePin? | 0 / zero
Which unit measures the electrical current that a motor driver must safely handle? | ampere / amperes / amp / amps / A
Should the motor supply and driver ratings be checked before running the motor? Answer yes or no. | yes
QUESTIONS,
    'arduino-millis' => <<<'QUESTIONS'
Which Arduino function reports elapsed milliseconds without pausing the sketch? | millis / millis()
Which type stores the lesson's previous timestamp? | unsigned long
If previous is 1000 and now is 1500, what is now - previous? | 500
In ledOn = !ledOn;, does the Boolean state stay the same or toggle? | toggle / toggles
Which comparison checks whether at least 500 milliseconds have elapsed: >= 500 or < 500? | >= 500 / >=500
QUESTIONS,
    'arduino-robot-functions' => <<<'QUESTIONS'
What do we call a named reusable block such as decideAction()? | function / a function
For the lesson's decideAction helper, what is returned for distanceCm = 10? | NEAR: stop
For the same helper, what is returned for distanceCm = 20? | FAR: ready
For the same helper, what is returned for distanceCm = -1? | INVALID: safe stop
Which distance is the lesson's boundary between near and far decisions? Answer in centimeters. | 15 / 15 cm
QUESTIONS,
    'arduino-capstone' => <<<'QUESTIONS'
In the capstone, what value represents an invalid distance returned by readDistanceCm()? | -1 / -1.0
In bool valid = cm >= 0;, is cm = -1 valid? Answer yes or no. | no
For warn = !valid || cm < 15.0, is warn true when valid is false? | true
For a valid distance of 20 cm, is the capstone's near warning true or false? | false
Before demonstrating a completed robot, should the learner test normal, boundary and invalid readings? Answer yes or no. | yes
QUESTIONS,
];

$questions = [];
foreach ($banks as $key => $bank) {
    foreach (explode("\n", trim($bank)) as $line) {
        [$question, $answers] = explode(' | ', $line, 2);
        $questions[$key][] = ['question' => $question, 'accepted_answers' => explode(' / ', $answers), 'case_sensitive' => false];
    }
}

return $questions;
