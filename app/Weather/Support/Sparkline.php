<?php

declare(strict_types=1);

namespace App\Weather\Support;

/**
 * Turns a series of numeric values into SVG path data for a tiny trend line.
 *
 * The presenter calls this so the SAME computed geometry feeds both the
 * server-rendered first paint (Blade) and the live JS updates — there is no
 * duplicated charting math on the client, and no charting library.
 *
 * Geometry is emitted in a fixed, unitless viewBox (WIDTH x HEIGHT). The SVG
 * element is then stretched to its container with CSS, so the aspect ratio is
 * driven by layout, not by the data. Values are normalized between the series
 * min and max with a little vertical padding so flat-ish series still show
 * shape and extremes never touch the edges.
 */
final class Sparkline
{
    public const WIDTH = 100;

    public const HEIGHT = 32;

    private const PAD_Y = 3.0;

    /**
     * @param  array<int, array{t: string, v: float}>  $points
     * @return array{
     *     path: string|null,
     *     area: string|null,
     *     view_box: string,
     *     min: float|null,
     *     max: float|null,
     *     first: float|null,
     *     last: float|null,
     *     count: int
     * }
     */
    public static function build(array $points): array
    {
        $values = array_map(static fn (array $p): float => (float) $p['v'], $points);
        $count = count($values);

        $viewBox = sprintf('0 0 %d %d', self::WIDTH, self::HEIGHT);

        if ($count === 0) {
            return [
                'path' => null,
                'area' => null,
                'view_box' => $viewBox,
                'min' => null,
                'max' => null,
                'first' => null,
                'last' => null,
                'count' => 0,
            ];
        }

        $min = min($values);
        $max = max($values);

        $coords = self::coordinates($values, $min, $max);

        // A single point can't draw a line; emit a flat two-point line so the
        // element still renders something sensible.
        if ($count === 1) {
            $y = $coords[0]['y'];
            $path = sprintf('M0,%s L%d,%s', self::fmt($y), self::WIDTH, self::fmt($y));
            $area = sprintf(
                'M0,%s L%d,%s L%d,%d L0,%d Z',
                self::fmt($y),
                self::WIDTH,
                self::fmt($y),
                self::WIDTH,
                self::HEIGHT,
                self::HEIGHT,
            );

            return [
                'path' => $path,
                'area' => $area,
                'view_box' => $viewBox,
                'min' => $min,
                'max' => $max,
                'first' => $values[0],
                'last' => $values[0],
                'count' => 1,
            ];
        }

        $segments = [];
        foreach ($coords as $i => $c) {
            $segments[] = ($i === 0 ? 'M' : 'L').self::fmt($c['x']).','.self::fmt($c['y']);
        }
        $path = implode(' ', $segments);

        // Area = line, then down to the baseline and back to the start.
        $first = $coords[0];
        $lastCoord = $coords[$count - 1];
        $area = $path
            .' L'.self::fmt($lastCoord['x']).','.self::HEIGHT
            .' L'.self::fmt($first['x']).','.self::HEIGHT.' Z';

        return [
            'path' => $path,
            'area' => $area,
            'view_box' => $viewBox,
            'min' => $min,
            'max' => $max,
            'first' => $values[0],
            'last' => $values[$count - 1],
            'count' => $count,
        ];
    }

    /**
     * @param  array<int, float>  $values
     * @return array<int, array{x: float, y: float}>
     */
    private static function coordinates(array $values, float $min, float $max): array
    {
        $count = count($values);
        $range = $max - $min;

        $usableHeight = self::HEIGHT - (self::PAD_Y * 2);
        $stepX = $count > 1 ? self::WIDTH / ($count - 1) : 0.0;

        $coords = [];
        foreach ($values as $i => $value) {
            $x = $i * $stepX;

            if ($range <= 0.0) {
                // Flat series: draw through the vertical center.
                $y = self::HEIGHT / 2;
            } else {
                $ratio = ($value - $min) / $range;
                // SVG y grows downward, so invert: high value -> small y.
                $y = self::PAD_Y + (1 - $ratio) * $usableHeight;
            }

            $coords[] = ['x' => $x, 'y' => $y];
        }

        return $coords;
    }

    private static function fmt(float $n): string
    {
        // Trim to 2 decimals and drop trailing zeros to keep the path compact.
        return rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.');
    }
}
