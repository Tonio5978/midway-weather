<?php

declare(strict_types=1);

namespace App\Weather\Support;

/**
 * Maps WMO weather interpretation codes (as used by Open-Meteo) to a short
 * human label and a stable icon key. The icon key selects one of the local SVG
 * weather icons; it is intentionally coarse so the icon set stays small.
 *
 * Reference: https://open-meteo.com/en/docs (WMO Weather interpretation codes)
 */
final class WeatherCode
{
    /**
     * @return array{label: string, icon: string}
     */
    public static function describe(?int $code, bool $isDay = true): array
    {
        $icon = self::iconKey($code, $isDay);
        $label = self::label($code);

        return ['label' => $label, 'icon' => $icon];
    }

    public static function label(?int $code): string
    {
        return match ($code) {
            0 => 'Clear',
            1 => 'Mostly Clear',
            2 => 'Partly Cloudy',
            3 => 'Overcast',
            45, 48 => 'Fog',
            51, 53, 55 => 'Drizzle',
            56, 57 => 'Freezing Drizzle',
            61 => 'Light Rain',
            63 => 'Rain',
            65 => 'Heavy Rain',
            66, 67 => 'Freezing Rain',
            71 => 'Light Snow',
            73 => 'Snow',
            75 => 'Heavy Snow',
            77 => 'Snow Grains',
            80 => 'Light Showers',
            81 => 'Showers',
            82 => 'Heavy Showers',
            85, 86 => 'Snow Showers',
            95 => 'Thunderstorm',
            96, 99 => 'Thunderstorm w/ Hail',
            default => 'Unknown',
        };
    }

    /**
     * Coarse icon key. Day/night only affects the clear/partly-cloudy icons.
     */
    public static function iconKey(?int $code, bool $isDay = true): string
    {
        return match ($code) {
            0, 1 => $isDay ? 'clear-day' : 'clear-night',
            2 => $isDay ? 'partly-cloudy-day' : 'partly-cloudy-night',
            3 => 'cloudy',
            45, 48 => 'fog',
            51, 53, 55, 56, 57 => 'drizzle',
            61, 63, 65, 66, 67 => 'rain',
            80, 81, 82 => 'showers',
            71, 73, 75, 77, 85, 86 => 'snow',
            95, 96, 99 => 'thunderstorm',
            default => 'cloudy',
        };
    }

    /**
     * Whether a code represents any form of precipitation (used by summaries).
     */
    public static function isPrecipitation(?int $code): bool
    {
        return $code !== null && $code >= 51;
    }

    public static function isThunderstorm(?int $code): bool
    {
        return in_array($code, [95, 96, 99], true);
    }
}
