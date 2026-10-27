<?php

namespace App\Weather\Providers;

/** Verified against Ambient's Device Data Specs. Unknown fields are never publishable. */
final class MeasurementRegistry
{
    public static function definitions(): array
    {
        $definitions = [];
        foreach (['tempf' => 'Outdoor temperature', 'tempinf' => 'Indoor temperature', 'feelsLike' => 'Feels like', 'dewPoint' => 'Dew point'] as $field => $label) {
            $definitions[$field] = [$label, 'temperature', '°F', -130, 160];
        }
        foreach (['humidity' => 'Outdoor humidity', 'humidityin' => 'Indoor humidity'] as $field => $label) {
            $definitions[$field] = [$label, 'humidity', '%', 0, 100];
        }
        for ($i = 1; $i <= 10; $i++) {
            $definitions['temp'.$i.'f'] = ['Temperature channel '.$i, 'temperature', '°F', -130, 200];
            $definitions['humidity'.$i] = ['Humidity channel '.$i, 'humidity', '%', 0, 100];
            $definitions['soiltemp'.$i.'f'] = ['Soil temperature '.$i, 'temperature', '°F', -130, 200];
        }
        foreach (['windspeedmph' => 'Wind speed', 'windgustmph' => 'Wind gust', 'maxdailygust' => 'Daily maximum gust'] as $field => $label) {
            $definitions[$field] = [$label, 'speed', 'mph', 0, 300];
        }
        foreach (['baromrelin' => 'Relative pressure', 'baromabsin' => 'Absolute pressure'] as $field => $label) {
            $definitions[$field] = [$label, 'pressure', 'inHg', 0, 40];
        }
        foreach (['dailyrainin' => 'Daily rain', '24hourrainin' => 'Rain in 24 hours', 'weeklyrainin' => 'Weekly rain', 'monthlyrainin' => 'Monthly rain', 'eventrainin' => 'Event rain'] as $field => $label) {
            $definitions[$field] = [$label, 'precipitation', 'in', 0, 10000];
        }
        $definitions['hourlyrainin'] = ['Rain rate', 'rain_rate', 'in/h', 0, 1000];
        $definitions['uv'] = ['UV index', 'uv', 'UV index', 0, 30];
        $definitions['solarradiation'] = ['Solar radiation', 'radiation', 'W/m²', 0, 2500];
        foreach (['pm25' => 'PM2.5', 'pm25_in' => 'Indoor PM2.5', 'pm25_24h' => 'PM2.5 in 24 hours'] as $field => $label) {
            $definitions[$field] = [$label, 'particulate', 'µg/m³', 0, 10000];
        }
        $definitions['co2'] = ['CO₂', 'co2', 'ppm', 0, 100000];

        return $definitions;
    }

    public static function field(string $field): ?array
    {
        $d = self::definitions()[$field] ?? null;

        return $d ? array_combine(['label', 'quantity', 'unit', 'min', 'max'], $d) : null;
    }

    public static function value(string $field, mixed $raw): ?float
    {
        $d = self::field($field);
        if (! $d || ! is_numeric($raw) || is_bool($raw)) {
            return null;
        }
        $value = (float) $raw;

        return is_finite($value) && $value >= $d['min'] && $value <= $d['max'] ? $value : null;
    }
}
