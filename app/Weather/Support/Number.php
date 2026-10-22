<?php

declare(strict_types=1);

namespace App\Weather\Support;

/**
 * Small value-normalisation helpers used at the API boundary.
 *
 * Weather providers are inconsistent: numbers may arrive as strings, missing
 * fields may be absent or null, and out-of-range sentinels occasionally leak
 * through. These helpers coerce values predictably and return null whenever a
 * value is missing or not a real number, so downstream code can rely on clean
 * nullable floats/ints.
 */
final class Number
{
    /**
     * Coerce a raw value to a float, or null when absent/non-numeric.
     */
    public static function float(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_bool($value)) {
            return null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        $float = (float) $value;

        // Guard against NaN / INF sneaking in from odd payloads.
        if (! is_finite($float)) {
            return null;
        }

        return $float;
    }

    /**
     * Coerce a raw value to an int, or null when absent/non-numeric.
     */
    public static function int(mixed $value): ?int
    {
        $float = self::float($value);

        return $float === null ? null : (int) round($float);
    }

    /**
     * Coerce a raw value to an int clamped to an inclusive range, or null.
     */
    public static function intInRange(mixed $value, int $min, int $max): ?int
    {
        $int = self::int($value);

        if ($int === null) {
            return null;
        }

        return max($min, min($max, $int));
    }

    /**
     * Interpret an Ambient battery flag (1 = OK, 0 = Low) as a boolean.
     * Returns null when the field is absent so "unknown" is distinguishable
     * from "low".
     */
    public static function batteryOk(mixed $value): ?bool
    {
        $int = self::int($value);

        return $int === null ? null : $int === 1;
    }
}
