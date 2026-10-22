<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Weather\Support\Number;
use PHPUnit\Framework\TestCase;

/**
 * Boundary-value normalization for the messy provider payloads. Guards the
 * whole app from string numbers, missing fields, and NaN/INF sentinels.
 */
final class NumberTest extends TestCase
{
    public function test_float_coerces_numeric_strings_and_rejects_non_numbers(): void
    {
        $this->assertSame(12.5, Number::float('12.5'));
        $this->assertSame(12.5, Number::float(12.5));
        $this->assertNull(Number::float(null));
        $this->assertNull(Number::float(''));
        $this->assertNull(Number::float('abc'));
        $this->assertNull(Number::float(true));
        $this->assertNull(Number::float(false));
    }

    public function test_float_rejects_non_finite_values(): void
    {
        // '1e999' is numeric but overflows to INF; must be rejected.
        $this->assertNull(Number::float('1e999'));
    }

    public function test_int_rounds_to_nearest_whole_number(): void
    {
        $this->assertSame(13, Number::int('12.6'));
        $this->assertSame(12, Number::int(12.4));
        $this->assertNull(Number::int(null));
        $this->assertNull(Number::int('nope'));
    }

    public function test_int_in_range_clamps_to_bounds(): void
    {
        $this->assertSame(100, Number::intInRange('150', 0, 100));
        $this->assertSame(0, Number::intInRange('-5', 0, 100));
        $this->assertSame(55, Number::intInRange('55', 0, 100));
        $this->assertNull(Number::intInRange(null, 0, 100));
    }

    public function test_battery_ok_treats_one_as_ok_and_null_as_unknown(): void
    {
        $this->assertTrue(Number::batteryOk(1));
        $this->assertFalse(Number::batteryOk(0));
        $this->assertNull(Number::batteryOk(null));
    }
}
