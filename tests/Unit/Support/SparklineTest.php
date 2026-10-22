<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Weather\Support\Sparkline;
use PHPUnit\Framework\TestCase;

/**
 * The pre-computed SVG geometry that must be byte-identical between the Blade
 * first paint and the JS live updates. Exact path strings are asserted so any
 * drift in the shared math is caught.
 */
final class SparklineTest extends TestCase
{
    public function test_empty_series_produces_no_geometry(): void
    {
        $spark = Sparkline::build([]);

        $this->assertNull($spark['path']);
        $this->assertNull($spark['area']);
        $this->assertSame('0 0 100 32', $spark['view_box']);
        $this->assertSame(0, $spark['count']);
        $this->assertNull($spark['min']);
        $this->assertNull($spark['max']);
    }

    public function test_single_point_draws_a_flat_two_point_line(): void
    {
        $spark = Sparkline::build([['t' => 'a', 'v' => 42.0]]);

        $this->assertSame(1, $spark['count']);
        $this->assertSame(42.0, $spark['first']);
        $this->assertSame(42.0, $spark['last']);
        $this->assertNotNull($spark['path']);
        // Flat series is centered vertically (HEIGHT / 2 = 16).
        $this->assertSame('M0,16 L100,16', $spark['path']);
    }

    public function test_rising_series_maps_high_values_to_small_y(): void
    {
        // min=10, max=20, range=10. usableHeight = 32 - 6 = 26.
        // point 0 (v=10): y = 3 + (1-0)*26 = 29. point 1 (v=20): y = 3 + 0 = 3.
        $spark = Sparkline::build([
            ['t' => 'a', 'v' => 10.0],
            ['t' => 'b', 'v' => 20.0],
        ]);

        $this->assertSame(2, $spark['count']);
        $this->assertSame(10.0, $spark['first']);
        $this->assertSame(20.0, $spark['last']);
        $this->assertSame(10.0, $spark['min']);
        $this->assertSame(20.0, $spark['max']);
        $this->assertSame('M0,29 L100,3', $spark['path']);
        // Area closes down to the baseline (HEIGHT = 32) and back.
        $this->assertSame('M0,29 L100,3 L100,32 L0,32 Z', $spark['area']);
    }

    public function test_flat_multi_point_series_draws_through_the_center(): void
    {
        $spark = Sparkline::build([
            ['t' => 'a', 'v' => 50.0],
            ['t' => 'b', 'v' => 50.0],
            ['t' => 'c', 'v' => 50.0],
        ]);

        $this->assertSame(3, $spark['count']);
        // range = 0 -> every y at HEIGHT/2 = 16, x steps of 50 across 100 wide.
        $this->assertSame('M0,16 L50,16 L100,16', $spark['path']);
    }
}
