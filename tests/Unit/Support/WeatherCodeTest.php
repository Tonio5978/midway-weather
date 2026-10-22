<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Weather\Support\WeatherCode;
use PHPUnit\Framework\TestCase;

final class WeatherCodeTest extends TestCase
{
    public function test_label_maps_known_codes_and_falls_back_to_unknown(): void
    {
        $this->assertSame('Clear', WeatherCode::label(0));
        $this->assertSame('Light Rain', WeatherCode::label(61));
        $this->assertSame('Thunderstorm', WeatherCode::label(95));
        $this->assertSame('Unknown', WeatherCode::label(999));
        $this->assertSame('Unknown', WeatherCode::label(null));
    }

    public function test_icon_key_respects_day_and_night_for_clear_skies(): void
    {
        $this->assertSame('clear-day', WeatherCode::iconKey(0, true));
        $this->assertSame('clear-night', WeatherCode::iconKey(0, false));
        $this->assertSame('partly-cloudy-day', WeatherCode::iconKey(2, true));
        $this->assertSame('partly-cloudy-night', WeatherCode::iconKey(2, false));
    }

    public function test_icon_key_is_coarse_for_precipitation_and_defaults_to_cloudy(): void
    {
        $this->assertSame('cloudy', WeatherCode::iconKey(3));
        $this->assertSame('rain', WeatherCode::iconKey(61));
        $this->assertSame('showers', WeatherCode::iconKey(80));
        $this->assertSame('thunderstorm', WeatherCode::iconKey(95));
        $this->assertSame('snow', WeatherCode::iconKey(73));
        $this->assertSame('cloudy', WeatherCode::iconKey(999));
    }

    public function test_precipitation_and_thunderstorm_predicates(): void
    {
        $this->assertFalse(WeatherCode::isPrecipitation(0));
        $this->assertTrue(WeatherCode::isPrecipitation(61));
        $this->assertFalse(WeatherCode::isPrecipitation(null));

        $this->assertTrue(WeatherCode::isThunderstorm(95));
        $this->assertFalse(WeatherCode::isThunderstorm(3));
    }
}
