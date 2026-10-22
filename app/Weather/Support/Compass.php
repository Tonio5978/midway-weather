<?php

declare(strict_types=1);

namespace App\Weather\Support;

/**
 * Compass bearing helpers shared by the presenter and summaries.
 */
final class Compass
{
    /**
     * Convert a bearing in degrees to a 16-point abbreviation (N, NNE, ...).
     */
    public static function abbreviation(?int $degrees): ?string
    {
        if ($degrees === null) {
            return null;
        }

        $points = [
            'N', 'NNE', 'NE', 'ENE', 'E', 'ESE', 'SE', 'SSE',
            'S', 'SSW', 'SW', 'WSW', 'W', 'WNW', 'NW', 'NNW',
        ];

        $index = (int) round((($degrees % 360) / 22.5)) % 16;

        return $points[$index];
    }

    /**
     * Full spoken name for a bearing (used in accessible labels).
     */
    public static function fullName(?int $degrees): ?string
    {
        $abbr = self::abbreviation($degrees);

        return $abbr === null ? null : match ($abbr) {
            'N' => 'north',
            'NNE' => 'north-northeast',
            'NE' => 'northeast',
            'ENE' => 'east-northeast',
            'E' => 'east',
            'ESE' => 'east-southeast',
            'SE' => 'southeast',
            'SSE' => 'south-southeast',
            'S' => 'south',
            'SSW' => 'south-southwest',
            'SW' => 'southwest',
            'WSW' => 'west-southwest',
            'W' => 'west',
            'WNW' => 'west-northwest',
            'NW' => 'northwest',
            'NNW' => 'north-northwest',
            default => $abbr,
        };
    }
}
