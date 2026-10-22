<?php

declare(strict_types=1);

namespace App\Weather\Support;

/**
 * Presentation formatters shared by the server-rendered first paint.
 *
 * These are mirrored 1:1 by the `fmt` object in public/display/app.js so the
 * initial HTML and the live JS updates format values identically (no visible
 * shift when the first poll lands). Keep the two in sync when changing rules.
 *
 * Every formatter treats null as "no data" and renders an em dash so the
 * display never shows a bare "0" or a blank where a sensor is simply missing.
 */
final class Format
{
    public const DASH = '—';

    /**
     * Whole-degree temperature with a degree symbol, e.g. 82°. No unit letter —
     * the whole display is Fahrenheit and the big number must stay uncluttered.
     */
    public static function temp(int|float|null $value): string
    {
        if ($value === null) {
            return self::DASH;
        }

        return round((float) $value).'°';
    }

    /**
     * Temperature to one decimal (used where precision reads well, e.g. dew).
     */
    public static function tempPrecise(int|float|null $value): string
    {
        if ($value === null) {
            return self::DASH;
        }

        return number_format((float) $value, 1).'°';
    }

    public static function percent(int|float|null $value): string
    {
        if ($value === null) {
            return self::DASH;
        }

        return round((float) $value).'%';
    }

    /**
     * Wind/speed value in mph, no decimals (calm winds read fine as whole mph).
     */
    public static function speed(int|float|null $value): string
    {
        if ($value === null) {
            return self::DASH;
        }

        return (string) round((float) $value);
    }

    public static function pressure(int|float|null $value): string
    {
        if ($value === null) {
            return self::DASH;
        }

        return number_format((float) $value, 2);
    }

    /**
     * Rainfall / precipitation depth in inches to two decimals.
     */
    public static function rain(int|float|null $value): string
    {
        if ($value === null) {
            return self::DASH;
        }

        return number_format((float) $value, 2).'"';
    }

    /**
     * UV index as a whole number.
     */
    public static function uv(int|float|null $value): string
    {
        if ($value === null) {
            return self::DASH;
        }

        return (string) round((float) $value);
    }

    /**
     * Solar irradiance in W/m^2, whole number.
     */
    public static function solar(int|float|null $value): string
    {
        if ($value === null) {
            return self::DASH;
        }

        return (string) round((float) $value);
    }

    /**
     * A signed delta for trend chips, e.g. +2.4 / -0.04, at the given decimals.
     */
    public static function delta(int|float|null $value, int $decimals = 1): string
    {
        if ($value === null) {
            return '';
        }

        $rounded = round((float) $value, $decimals);

        // Treat a rounded-to-zero delta as unsigned to avoid "-0.0".
        if ($rounded == 0.0) {
            return number_format(0, $decimals);
        }

        $sign = $rounded > 0 ? '+' : '';

        return $sign.number_format($rounded, $decimals);
    }
}
