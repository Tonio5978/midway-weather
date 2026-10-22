<?php

declare(strict_types=1);

namespace App\Weather\Support;

/**
 * A compact, presentation-neutral interpretation of the current atmosphere.
 *
 * This is part of the Midway Weather contract so every ecosystem display can
 * use the same precedence: an active station rain reading outranks a daily
 * forecast label, while weather codes retain storm and snow meaning.
 */
final class AtmosphereModifier
{
    /** @return array{kind: string, severity: float} */
    public static function fromCurrent(?int $weatherCode, ?float $rainRate): array
    {
        $rate = max(0.0, (float) ($rainRate ?? 0.0));

        $base = match (true) {
            in_array($weatherCode, [96, 99], true) => ['severe-storm', 1.0],
            $weatherCode === 95 => ['thunderstorm', 0.9],
            in_array($weatherCode, [75, 86], true) => ['snow', 0.8],
            in_array($weatherCode, [65, 67, 82], true) => ['heavy-rain', 0.8],
            in_array($weatherCode, [73, 77], true) => ['snow', 0.65],
            in_array($weatherCode, [63, 66, 81], true) => ['rain', 0.65],
            in_array($weatherCode, [71, 85], true) => ['snow', 0.5],
            in_array($weatherCode, [51, 53, 55, 56, 57, 61, 80], true) => ['light-rain', 0.5],
            in_array($weatherCode, [45, 48], true) => ['fog', 0.5],
            $weatherCode === 3 => ['overcast', 0.45],
            in_array($weatherCode, [1, 2], true) => ['partly-cloudy', 0.2],
            default => ['clear', 0.0],
        };

        // Ambient's live in-hour rain is the strongest evidence that the scene
        // is wet, even if Open-Meteo's daily classification remains clear.
        if ($rate <= 0 || str_contains($base[0], 'storm') || $base[0] === 'snow') {
            return ['kind' => $base[0], 'severity' => $base[1]];
        }

        if ($rate >= 0.30) return ['kind' => 'heavy-rain', 'severity' => max($base[1], 0.8)];
        if ($rate >= 0.08) return ['kind' => 'rain', 'severity' => max($base[1], 0.65)];

        return ['kind' => 'light-rain', 'severity' => max($base[1], 0.5)];
    }
}
