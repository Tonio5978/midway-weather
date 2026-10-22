<?php

namespace App\Weather\Configuration;

/** The same safe, normalized source view can grow with new collectors. */
final class WeatherSources
{
    public static function definitions(array $model): array
    {
        return [
            'ambient' => ['name' => 'Ambient Weather station', 'cadence' => 'Every minute', 'stale_minutes' => 20, 'payload' => $model['observation']],
            'forecast' => ['name' => 'Open-Meteo forecast', 'cadence' => 'Hourly', 'stale_minutes' => 180, 'payload' => $model['forecast']],
            'pool' => ['name' => 'Pool temperature', 'cadence' => 'Not connected', 'stale_minutes' => 20, 'payload' => null],
        ];
    }
}
