<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Planet Position Production Bonuses
    |--------------------------------------------------------------------------
    |
    | Per-position multipliers applied to basic income. Positions not listed
    | receive a 1.0 multiplier for all resources (no bonus).
    |
    */
    'position_bonuses' => [
        1  => ['metal' => 1,    'crystal' => 1.4, 'deuterium' => 1],
        2  => ['metal' => 1,    'crystal' => 1.3, 'deuterium' => 1],
        3  => ['metal' => 1,    'crystal' => 1.2, 'deuterium' => 1],
        6  => ['metal' => 1.17, 'crystal' => 1,   'deuterium' => 1],
        7  => ['metal' => 1.23, 'crystal' => 1,   'deuterium' => 1],
        8  => ['metal' => 1.35, 'crystal' => 1,   'deuterium' => 1],
        9  => ['metal' => 1.23, 'crystal' => 1,   'deuterium' => 1],
        10 => ['metal' => 1.17, 'crystal' => 1,   'deuterium' => 1],
    ],
];
