<?php

declare(strict_types=1);

namespace App\Weather\Support;

/**
 * Maps the current outdoor temperature to a presentation-only accent mood.
 *
 * This intentionally knows nothing about forecasts, feels-like readings, or
 * weather conditions. It is a small V1.5 seam that can later grow into a more
 * complete weather mood system without coupling that work to the presenter.
 */
final class TemperatureMood
{
    public const FRIGID = 'frigid';

    public const COLD = 'cold';

    public const COOL = 'cool';

    public const MILD = 'mild';

    public const WARM = 'warm';

    public const HOT = 'hot';

    public const OPPRESSIVE = 'oppressive';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return [
            self::FRIGID,
            self::COLD,
            self::COOL,
            self::MILD,
            self::WARM,
            self::HOT,
            self::OPPRESSIVE,
        ];
    }

    public static function fromTemperature(?float $temperature): string
    {
        if ($temperature === null) {
            return self::MILD;
        }

        return match (true) {
            $temperature < 25 => self::FRIGID,
            $temperature < 45 => self::COLD,
            $temperature < 60 => self::COOL,
            $temperature < 75 => self::MILD,
            $temperature < 85 => self::WARM,
            $temperature < 95 => self::HOT,
            default => self::OPPRESSIVE,
        };
    }

    public static function normalize(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = strtolower(trim($value));

        return in_array($value, self::values(), true) ? $value : null;
    }

    /** @return array{minimum: int|null, maximumExclusive: int|null} */
    public static function range(string $mood): array
    {
        return match ($mood) {
            self::FRIGID => ['minimum' => null, 'maximumExclusive' => 25],
            self::COLD => ['minimum' => 25, 'maximumExclusive' => 45],
            self::COOL => ['minimum' => 45, 'maximumExclusive' => 60],
            self::MILD => ['minimum' => 60, 'maximumExclusive' => 75],
            self::WARM => ['minimum' => 75, 'maximumExclusive' => 85],
            self::HOT => ['minimum' => 85, 'maximumExclusive' => 95],
            self::OPPRESSIVE => ['minimum' => 95, 'maximumExclusive' => null],
        };
    }
}
