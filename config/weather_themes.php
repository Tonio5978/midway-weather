<?php

// Add named themes here. Stylesheets are local public assets and only add a
// seasonal layer; weather/time/temperature attributes remain authoritative.
return [
    'october-fall' => [
        'name' => 'October / Fall',
        'description' => 'Georgia autumn: deep blue skies, copper dusk and warm gold, shaped by the weather.',
        'stylesheet' => 'display/themes/october-fall.css?v=2',
        'default_range' => ['start' => '10-01', 'end' => '11-30'],
    ],
    'default' => ['name' => 'Weather', 'description' => 'Weather and time of day, just as today.', 'stylesheet' => null],
];
