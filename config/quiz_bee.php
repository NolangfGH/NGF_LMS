<?php

$q = fn (string $prompt, array $options, int $correct, string $why) => compact('prompt', 'options', 'correct', 'why');

return ['questions' => [
    'easy' => [
        'regular' => [
            $q('Where does a C++ program begin executing?', ['main', 'cout', 'include', 'return'], 0, 'main is the entry function.'),
            $q('Which stream displays text on the console?', ['std::cin', 'std::cout', 'int', 'bool'], 1, 'cout is the standard output stream.'),
            $q('Which punctuation normally ends a simple C++ statement?', [':', ',', ';', '.'], 2, 'A semicolon terminates a simple statement.'),
            $q('Which type is suitable for a count of whole books?', ['void', 'return', 'if', 'int'], 3, 'int stores whole-number values.'),
            $q('What does the escape sequence \\n do in a string?', ['Starts a new output line', 'Ends every program', 'Reads a number', 'Declares a variable'], 0, 'The newline escape moves the following output to a new line.'),
        ],
        'tiebreak' => [
            $q('Which statement reads into age?', ['std::cin << age;', 'std::cin >> age;', 'std::cout >> age;', 'age >> std::cout;'], 1, 'cin uses >> to read a value.'),
            $q('Which is a string literal?', ["'A'", '42', '"Hello"', 'true'], 2, 'String literals use double quotes.'),
            $q('Which type represents true or false?', ['int', 'double', 'string', 'bool'], 3, 'bool represents Boolean values.'),
            $q('Which declaration initializes score to zero?', ['int score = 0;', 'score == 0;', 'int = score;', 'score int 0;'], 0, 'This declares and initializes an integer variable.'),
            $q('Which starts a single-line C++ comment?', ['##', '//', '<!--', '**'], 1, 'Text after // on that source line is a comment.'),
        ],
    ],
    'average' => [
        'regular' => [
            $q('What is 7 / 2 when both operands are integers?', ['3.5', '4', '3', '2'], 2, 'Integer division discards the fractional part.'),
            $q('What is 9 % 4?', ['4', '2', '0', '1'], 3, 'The remainder of 9 divided by 4 is 1.'),
            $q('Which condition checks whether n is even?', ['n % 2 == 0', 'n / 2 == 0', 'n = 2', 'n + 2 == 0'], 0, 'An even integer has remainder zero when divided by 2.'),
            $q('How many times does for(int i=0; i<4; i++) execute its body?', ['3', '4', '5', '0'], 1, 'The counter values in the body are 0, 1, 2 and 3.'),
            $q('What does x become after int x=2; x += 3;?', ['2', '3', '5', '6'], 2, 'The compound assignment adds 3 to the previous value 2.'),
        ],
        'tiebreak' => [
            $q('For int a[3]={4,7,9}; what is a[1]?', ['4', '9', '1', '7'], 3, 'Indexes start at zero, so a[1] is the second element.'),
            $q('A function returns n*n. What does it return for n=-3?', ['9', '-9', '6', '-6'], 0, 'Multiplying two negative values produces a positive value.'),
            $q('What is the role of a function parameter?', ['It must print text', 'It receives input supplied to the function', 'It ends the compiler', 'It is always a loop'], 1, 'Parameters give names to values passed into a function.'),
            $q('Which index is outside int a[5]?', ['0', '4', '5', '2'], 2, 'The valid indexes are 0 through 4.'),
            $q('Which input operation reads a whole line including spaces into a string?', ['std::cout << name', 'std::cin >> name', 'name = 0', 'std::getline(std::cin, name)'], 3, 'getline reads a line rather than a single whitespace-delimited word.'),
        ],
    ],
    'difficult' => [
        'regular' => [
            $q('What is total after int total=0; for(int i=1;i<=4;i++) total+=i;?', ['10', '6', '4', '15'], 0, 'The sum is 1+2+3+4 = 10.'),
            $q('How many times does the inner body run? for(int i=0;i<3;i++) for(int j=0;j<2;j++) { /* body */ }', ['5', '6', '3', '2'], 1, 'Each of three outer passes has two inner passes: 3*2 = 6.'),
            $q('What is the value of 2 + 3 * 4 % 5?', ['20', '14', '4', '0'], 2, 'Multiplication and remainder are evaluated before addition: 12%5 = 2, then 2+2 = 4.'),
            $q('A loop totals inputs until -1 and excludes the sentinel. What total comes from 4 0 6 -1?', ['9', '-1', '11', '10'], 3, 'Only 4, 0 and 6 are added.'),
            $q('A program compiles but prints an incorrect total. Which error category best fits?', ['Logic error', 'Always a syntax error', 'Always a missing header', 'No possible error'], 0, 'A logic error can produce incorrect output in a program that runs.'),
        ],
        'tiebreak' => [
            $q('What prints? int x=3; if(x>2 && x<5) std::cout<<"A"; else std::cout<<"B";', ['B', 'A', 'AB', 'Nothing'], 1, 'Both comparisons are true, so the if branch prints A.'),
            $q('Which change stops a loop from missing its final value 3 when i starts at 1 and increases?', ['Use i<2', 'Start i at 4', 'Change i<3 to i<=3', 'Remove the update'], 2, 'The <= comparison includes the upper boundary.'),
            $q('What does return do inside an ordinary function?', ['Always prints a value', 'Restarts main', 'Creates an array', 'Ends that function call and can pass a value to its caller'], 3, 'Returning and displaying output are different operations.'),
            $q('A sentinel loop keeps processing the same non-sentinel input forever. What is a likely cause?', ['It does not read the next value inside the loop', 'Its total starts at zero', 'It uses an int variable', 'Its output has a newline'], 0, 'The condition needs an updated input value to move on or stop.'),
            $q('A task asks for only the number 15. Which output matches exactly?', ['Total: 15', '15', 'Enter value: 15', '15 supplies'], 1, 'Extra prompts or labels change the required output.'),
        ],
    ],
]];
