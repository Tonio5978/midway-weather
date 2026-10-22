<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Weather\Support\Compass;
use PHPUnit\Framework\TestCase;

final class CompassTest extends TestCase
{
    public function test_abbreviation_maps_cardinal_bearings(): void
    {
        $this->assertSame('N', Compass::abbreviation(0));
        $this->assertSame('N', Compass::abbreviation(360));
        $this->assertSame('E', Compass::abbreviation(90));
        $this->assertSame('S', Compass::abbreviation(180));
        $this->assertSame('W', Compass::abbreviation(270));
    }

    public function test_abbreviation_maps_intercardinal_bearings(): void
    {
        $this->assertSame('NE', Compass::abbreviation(45));
        $this->assertSame('SSW', Compass::abbreviation(200));
        $this->assertNull(Compass::abbreviation(null));
    }

    public function test_full_name_spells_out_the_bearing(): void
    {
        $this->assertSame('north', Compass::fullName(0));
        $this->assertSame('northeast', Compass::fullName(45));
        $this->assertSame('south', Compass::fullName(180));
        $this->assertNull(Compass::fullName(null));
    }
}
