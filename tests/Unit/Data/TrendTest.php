<?php

declare(strict_types=1);

namespace Tests\Unit\Data;

use App\Weather\Data\Trend;
use PHPUnit\Framework\TestCase;

final class TrendTest extends TestCase
{
    public function test_unknown_trend_has_no_delta_and_a_dash_label(): void
    {
        $trend = Trend::unknown('past hour');

        $this->assertSame(Trend::UNKNOWN, $trend->direction);
        $this->assertSame('—', $trend->label);
        $this->assertNull($trend->delta);
        $this->assertSame('past hour', $trend->comparison);
    }

    public function test_to_array_rounds_the_delta_to_two_decimals(): void
    {
        $trend = new Trend(Trend::RISING, 'Warmer', 2.456, 'past hour');

        $this->assertSame([
            'direction' => 'rising',
            'label' => 'Warmer',
            'delta' => 2.46,
            'comparison' => 'past hour',
        ], $trend->toArray());
    }

    public function test_to_array_preserves_null_delta(): void
    {
        $this->assertNull(Trend::unknown()->toArray()['delta']);
    }
}
