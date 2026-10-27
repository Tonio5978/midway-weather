<?php

namespace App\Models;

/** Separate model identity also isolates Eloquent's guarded-column cache. */
class StationObservation extends Observation
{
    protected $table = 'weather_station_observations';
}
