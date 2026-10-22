<?php

declare(strict_types=1);

namespace App\Weather\Exceptions;

use RuntimeException;

/**
 * Thrown when a weather API request fails or returns an unusable response.
 *
 * The message is intended to be short and safe to surface in logs and, after
 * sanitisation by the collection layer, on the display. It must never contain
 * credentials, full request URLs, or raw provider payloads.
 */
class WeatherApiException extends RuntimeException
{
    public static function forSource(string $source, string $reason): self
    {
        return new self(sprintf('%s request failed: %s', $source, $reason));
    }
}
