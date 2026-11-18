<?php

return (static function (): array {
    $source = require __DIR__.'/arduino_questions.php';
    $topics = [
        'easy' => ['robotics', 'kit-safety', 'breadboards', 'uno-board', 'ide-upload', 'blink', 'sketch-logic', 'traffic-lights'],
        'average' => ['buttons', 'analog-input', 'pwm', 'rgb', 'light-sensor', 'buzzer', 'servo', 'ultrasonic', 'temperature'],
        'difficult' => ['lcd', 'keypad', 'joystick', 'pir', 'motor-driver', 'millis', 'robot-functions', 'capstone'],
    ];
    $extra = [
        'easy' => [['robotics', 7], ['kit-safety', 7], ['uno-board', 7], ['blink', 7]],
        'average' => [['analog-input', 7], ['pwm', 7]],
        'difficult' => [['millis', 7], ['robot-functions', 7], ['motor-driver', 7], ['capstone', 7]],
    ];
    $bank = [];
    foreach ($topics as $level => $slugs) {
        $questions = [];
        $selectedRows = [];
        foreach ($slugs as $slug) {
            $selectedRows[] = [$slug, 5];
            $selectedRows[] = [$slug, 6];
        }
        foreach ([...$selectedRows, ...$extra[$level]] as [$slug, $index]) {
            $rows = explode("\n", trim($source[$slug]));
            [$prompt, $answer, $first, $second, $third] = explode(' | ', $rows[$index]);
            $options = [$first, $second, $third];
            $correct = crc32($prompt) % 4;
            array_splice($options, $correct, 0, [$answer]);
            $questions[] = ['type' => 'multiple_choice', 'prompt' => $prompt, 'options' => $options, 'correct' => $correct,
                'why' => 'The correct answer is '.$answer.'. Review the related Arduino lesson and its worked sketch.'];
        }
        $bank[$level] = ['regular' => array_slice($questions, 0, 15), 'tiebreak' => array_slice($questions, 15, 5)];
    }
    $bank['difficult']['regular'][10] = [
        'type' => 'fill_blank', 'prompt' => 'An HC-SR04 echo lasts 580 microseconds. Using distanceCm = duration / 58, the distance is ____ cm.',
        'accepted_answers' => ['10'], 'case_sensitive' => true, 'why' => 'The common classroom approximation duration / 58 converts the echo round-trip time to centimetres: 580 / 58 = 10.',
    ];
    $bank['difficult']['regular'][11] = [
        'type' => 'multiple_choice', 'prompt' => 'What prints? int total=0; for(int i=1;i<=4;i++){total+=i;} Serial.println(total);',
        'options' => ['4', '6', '10', '15'], 'correct' => 2, 'why' => 'The loop adds 1, 2, 3 and 4, giving 10.',
    ];
    $bank['difficult']['regular'][12] = [
        'type' => 'fill_blank', 'prompt' => 'int values[3]={2,4,6}; Serial.println(values[1]); prints ____.',
        'accepted_answers' => ['4'], 'case_sensitive' => true, 'why' => 'Array indices begin at zero, so values[1] is the second element.',
    ];
    $bank['difficult']['tiebreak'][2] = [
        'type' => 'fill_blank', 'prompt' => 'Fill in the equality operator: if(value ____ 500){ Serial.println("At threshold"); }',
        'accepted_answers' => ['=='], 'case_sensitive' => true, 'why' => 'Equality comparison uses ==. A single = assigns a value.',
    ];

    return $bank;
})();
