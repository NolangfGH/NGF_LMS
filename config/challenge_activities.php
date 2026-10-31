<?php

$starter="#include <iostream>\n\nint main() {\n    // Write your solution here.\n    return 0;\n}\n";

return [
    'greeting'=>['title'=>'Hello, CodeQuest!','objective'=>'Use two output statements to print the two lines in the sample output.','sample_output'=>"Hello, CodeQuest!\nI am learning C++.",'hint'=>'Use std::cout << and a newline between the messages. Text belongs inside double quotes.','code'=>$starter],
    'branch'=>['title'=>'Temperature decision','objective'=>'Set an integer temperature to 32. Use an if/else statement to print Hot when the value is at least 30, otherwise print Cool. Test 29 and 30 as well.','sample_output'=>'Hot','hint'=>'Compare temperature with 30 using >=. Only the matching branch should print a message.','code'=>$starter],
    'functions'=>['title'=>'Build a reusable function','objective'=>'Write an integer function square that returns a number multiplied by itself. Call it from main with the value 5 and print the returned result.','sample_output'=>'25','hint'=>'Keep the calculation in square. Return its value, then display it from main. Try 0 and a negative input as extra tests.','code'=>$starter],
    'sentinel'=>['title'=>'Sentinel sum','objective'=>'Read integers with cin until the sentinel -1 is entered. Add the values before -1 and print the total. For the input sequence 4 6 -1, produce the sample output. Test -1 as the first input as well.','sample_output'=>'10','hint'=>'Initialize the total to zero and avoid adding the sentinel. Check that the loop reads the next value and can stop.','code'=>$starter],
];
