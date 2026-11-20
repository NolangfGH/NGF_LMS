<?php

use App\Services\CreativeTechnologyCurriculum;

$arduino = (require __DIR__.'/arduino_curriculum.php')['lessons'];

return [
    'ct7' => CreativeTechnologyCurriculum::build('ct7', require __DIR__.'/creative_technology_7.php', $arduino),
    'ct10' => CreativeTechnologyCurriculum::build('ct10', require __DIR__.'/creative_technology_10.php', $arduino),
];
