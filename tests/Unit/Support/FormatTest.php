<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Weather\Support\Format;
use PHPUnit\Framework\TestCase;

/**
 * The server-side formatters that produce the first-paint HTML. These are
 * mirrored 1:1 by the `fmt` object in public/display/app.js; the rules asserted
 * here are the contract both sides must honor (null -> em dash, never a bare 0).
 */
final class FormatTest extends TestCase
{
    public function test_null_values_render_as_an_em_dash(): void
    {
        $this->assertSame('—', Format::temp(null));
        $this->assertSame('—', Format::tempPrecise(null));
        $this->assertSame('—', Format::percent(null));
        $this->assertSame('—', Format::speed(null));
        $this->assertSame('—', Format::pressure(null));
        $this->assertSame('—', Format::rain(null));
        $this->assertSame('—', Format::uv(null));
        $this->assertSame('—', Format::solar(null));
    }

    public function test_temperature_is_rounded_to_a_whole_degree(): void
    {
        $this->assertSame('82°', Format::temp(82.4));
        $this->assertSame('83°', Format::temp(82.6));
        $this->assertSame('0°', Format::temp(0.0));
        $this->assertSame('-4°', Format::temp(-4.2));
    }

    public function test_precise_temperature_keeps_one_decimal(): void
    {
        $this->assertSame('68.4°', Format::tempPrecise(68.42));
        $this->assertSame('70.0°', Format::tempPrecise(70.0));
    }

    public function test_percent_and_speed_round_to_whole_numbers(): void
    {
        $this->assertSame('56%', Format::percent(55.6));
        $this->assertSame('5', Format::speed(4.6));
        $this->assertSame('0', Format::speed(0.0));
    }

    public function test_pressure_and_rain_use_two_decimals(): void
    {
        $this->assertSame('30.00', Format::pressure(30.0));
        $this->assertSame('29.92', Format::pressure(29.921));
        $this->assertSame('0.10"', Format::rain(0.1));
        $this->assertSame('0.00"', Format::rain(0.0));
    }

    public function test_uv_and_solar_round_to_whole_numbers(): void
    {
        $this->assertSame('3', Format::uv(3.2));
        $this->assertSame('210', Format::solar(210.4));
    }

    public function test_delta_is_signed_and_blank_when_null(): void
    {
        $this->assertSame('', Format::delta(null));
        $this->assertSame('+2.4', Format::delta(2.44));
        $this->assertSame('-0.04', Format::delta(-0.04, 2));
    }

    public function test_delta_that_rounds_to_zero_is_unsigned_to_avoid_negative_zero(): void
    {
        // -0.04 at one decimal rounds to 0.0 and must not render as "-0.0".
        $this->assertSame('0.0', Format::delta(-0.04, 1));
        $this->assertSame('0.0', Format::delta(0.0, 1));
        $this->assertSame('0.00', Format::delta(0.001, 2));
    }
}
