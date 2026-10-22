<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Weather\Support\TemperatureMood;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TemperatureMoodTest extends TestCase
{
    /**
     * @return array<string, array{float|null, string}>
     */
    public static function temperatureBands(): array
    {
        return [
            'missing data uses the neutral baseline' => [null, 'mild'],
            'below frigid boundary' => [24.9, 'frigid'],
            'cold lower boundary' => [25.0, 'cold'],
            'below cool boundary' => [44.9, 'cold'],
            'cool lower boundary' => [45.0, 'cool'],
            'below mild boundary' => [59.9, 'cool'],
            'mild lower boundary' => [60.0, 'mild'],
            'below warm boundary' => [74.9, 'mild'],
            'warm lower boundary' => [75.0, 'warm'],
            'below hot boundary' => [84.9, 'warm'],
            'hot lower boundary' => [85.0, 'hot'],
            'below oppressive boundary' => [94.9, 'hot'],
            'oppressive lower boundary' => [95.0, 'oppressive'],
        ];
    }

    #[DataProvider('temperatureBands')]
    public function test_current_temperature_maps_to_the_expected_mood(?float $temperature, string $expected): void
    {
        $this->assertSame($expected, TemperatureMood::fromTemperature($temperature));
    }

    public function test_preview_values_are_strictly_normalized(): void
    {
        $this->assertSame('cold', TemperatureMood::normalize(' COLD '));
        $this->assertNull(TemperatureMood::normalize('stormy'));
        $this->assertNull(TemperatureMood::normalize(null));
    }

    public function test_documented_integer_boundaries_and_ranges_are_owned_by_temperature_mood(): void
    {
        foreach ([24 => 'frigid', 25 => 'cold', 44 => 'cold', 45 => 'cool', 59 => 'cool', 60 => 'mild', 74 => 'mild', 75 => 'warm', 84 => 'warm', 85 => 'hot', 94 => 'hot', 95 => 'oppressive'] as $temperature => $mood) {
            $this->assertSame($mood, TemperatureMood::fromTemperature((float) $temperature));
        }
        $this->assertSame(['minimum' => 60, 'maximumExclusive' => 75], TemperatureMood::range(TemperatureMood::MILD));
    }
}
