<?php

declare(strict_types=1);

namespace App\Weather\Services;

use App\Models\CollectionStatus;
use App\Models\ForecastDay;
use App\Weather\Data\ForecastData;
use App\Weather\Data\ObservationData;
use Carbon\CarbonImmutable;

/**
 * Generates realistic fixture data so the dashboard is fully viewable before
 * real Ambient credentials are configured. Everything it produces is clearly
 * synthetic (a fixed device MAC and a smooth diurnal model), and demo mode is
 * guarded against production in the calling command.
 *
 * The generator is deterministic given a reference time so tests and repeated
 * seeds are stable; it takes the "now" instant as an argument rather than
 * reading the clock itself.
 */
class DemoDataGenerator
{
    public const DEMO_MAC = 'DE:M0:00:00:00:01';

    public function __construct(
        private readonly ObservationRepository $observations,
        private readonly DailySummaryService $summaries,
        private readonly ForecastService $forecast,
    ) {}

    /**
     * Seed a rolling window of observations, refresh summaries, and write a
     * synthetic forecast. Returns counts for console output.
     *
     * @return array{observations: int, forecast_days: int}
     */
    public function generate(CarbonImmutable $now, int $hours = 48): array
    {
        $timezone = (string) config('weather.timezone', 'America/New_York');
        $stored = 0;

        // Every 5 minutes across the window, matching the real cadence.
        $steps = (int) ($hours * 60 / 5);

        for ($i = $steps; $i >= 0; $i--) {
            $observedAt = $now->subMinutes($i * 5);
            $data = $this->syntheticObservation($observedAt, $timezone);
            $this->observations->store($data);
            $stored++;
        }

        // Rebuild summaries for the covered days.
        $this->summaries->rebuildRecent((int) ceil($hours / 24) + 1);

        // Build a synthetic 7-day forecast and persist via the real service
        // path by faking the client would be overkill; write rows directly.
        $forecastDays = $this->syntheticForecast($now, $timezone);

        CollectionStatus::recordSuccess(CollectionStatus::SOURCE_AMBIENT);
        CollectionStatus::recordSuccess(CollectionStatus::SOURCE_FORECAST);

        return ['observations' => $stored, 'forecast_days' => $forecastDays];
    }

    /**
     * Model a single observation using smooth daily cycles so trends and
     * sparklines look natural.
     */
    private function syntheticObservation(CarbonImmutable $observedAt, string $timezone): ObservationData
    {
        $local = $observedAt->setTimezone($timezone);
        // Fractional hour of day, 0..24.
        $hour = (int) $local->format('G') + ((int) $local->format('i')) / 60.0;

        // Temperature: warm Georgia summer, peak ~5pm, trough ~6am.
        $phase = ($hour - 15) / 24 * 2 * M_PI;
        $temp = 82.0 + 11.0 * cos($phase);

        // Humidity inversely related to temperature.
        $humidity = (int) round(max(35, min(96, 150 - $temp)));

        // Dew point derived roughly from temp + humidity (Magnus approx).
        $dewPoint = $temp - (9.0 / 25.0) * (100 - $humidity);

        // Solar radiation only during daylight.
        $solar = 0.0;
        if ($hour > 6.5 && $hour < 20.5) {
            $solar = max(0.0, 780.0 * sin(M_PI * ($hour - 6.5) / 14.0));
        }
        $uv = (int) round($solar / 90.0);

        // Wind: light, gusty in the afternoon. Deterministic wobble via sin.
        $windBase = 3.0 + 3.0 * max(0.0, sin(M_PI * ($hour - 8) / 12.0));
        $windSpeed = round($windBase + 1.5 * sin($hour * 1.7), 1);
        $windGust = round($windSpeed + 4.0 + 2.0 * abs(sin($hour)), 1);
        $windDir = (int) round((180 + 40 * sin($hour / 3.0)) + 360) % 360;

        // Pressure: gentle decline across the window (approaching system).
        $pressure = round(30.10 - 0.02 * $hour / 24.0 - 0.05 * cos($phase), 2);

        // Rain: a short shower band in the late afternoon of "today".
        $rainHourly = ($hour > 16 && $hour < 17.5) ? 0.08 : 0.0;

        $payload = [
            'dateutc' => $observedAt->getTimestampMs(),
            'date' => $observedAt->toIso8601String(),
            'tempf' => round($temp, 1),
            'feelsLike' => round($temp + ($temp > 80 ? 3 : 0), 1),
            'dewPoint' => round($dewPoint, 1),
            'humidity' => $humidity,
            'windspeedmph' => $windSpeed,
            'windgustmph' => $windGust,
            'winddir' => $windDir,
            'windspdmph_avg2m' => $windSpeed,
            'winddir_avg2m' => $windDir,
            'windspdmph_avg10m' => round($windSpeed * 0.9, 1),
            'winddir_avg10m' => $windDir,
            'maxdailygust' => round($windGust + 5, 1),
            'baromrelin' => $pressure,
            'baromabsin' => round($pressure - 1.34, 2),
            'hourlyrainin' => $rainHourly,
            'dailyrainin' => round(max(0.0, ($hour - 16) * 0.05), 2),
            '24hourrainin' => 0.12,
            'weeklyrainin' => 0.86,
            'monthlyrainin' => 3.42,
            'eventrainin' => $rainHourly > 0 ? 0.08 : 0.0,
            'uv' => $uv,
            'solarradiation' => round($solar, 1),
            'tempinf' => round(71.5 + 1.5 * cos($phase), 1),
            'humidityin' => 48,
            'battout' => 1,
            'battin' => 1,
            'macAddress' => self::DEMO_MAC,
            'tz' => $timezone,
        ];

        return ObservationData::fromAmbient($payload, self::DEMO_MAC);
    }

    /**
     * Build and persist a plausible 7-day forecast via ForecastData
     * normalization, so it exercises the same path as real data.
     */
    private function syntheticForecast(CarbonImmutable $now, string $timezone): int
    {
        $today = CarbonImmutable::now($timezone)->startOfDay();

        $codes = [95, 3, 61, 80, 2, 1, 0];
        $highs = [91, 88, 83, 85, 90, 92, 93];
        $lows = [74, 71, 72, 73, 72, 74, 75];
        $pop = [65, 40, 80, 55, 20, 10, 5];
        $precip = [0.42, 0.10, 1.10, 0.35, 0.02, 0.0, 0.0];
        $windMax = [12, 9, 15, 13, 8, 7, 6];
        $gustMax = [24, 18, 30, 26, 15, 13, 12];

        $times = [];
        $daily = [
            'weather_code' => [], 'temperature_2m_max' => [], 'temperature_2m_min' => [],
            'precipitation_probability_max' => [], 'precipitation_sum' => [],
            'wind_speed_10m_max' => [], 'wind_gusts_10m_max' => [],
            'sunrise' => [], 'sunset' => [],
        ];

        for ($i = 0; $i < 7; $i++) {
            $date = $today->addDays($i);
            $times[] = $date->toDateString();
            $daily['weather_code'][] = $codes[$i];
            $daily['temperature_2m_max'][] = $highs[$i];
            $daily['temperature_2m_min'][] = $lows[$i];
            $daily['precipitation_probability_max'][] = $pop[$i];
            $daily['precipitation_sum'][] = $precip[$i];
            $daily['wind_speed_10m_max'][] = $windMax[$i];
            $daily['wind_gusts_10m_max'][] = $gustMax[$i];
            $daily['sunrise'][] = $date->copy()->setTime(6, 43)->format('Y-m-d\TH:i');
            $daily['sunset'][] = $date->copy()->setTime(20, 48)->format('Y-m-d\TH:i');
        }

        $forecast = ForecastData::fromOpenMeteo([
            'timezone' => $timezone,
            'daily' => ['time' => $times] + $daily,
        ], $now);

        foreach ($forecast->days as $day) {
            ForecastDay::query()->updateOrCreate(
                ['date' => $day->date->toDateString()],
                $day->toAttributes(),
            );
        }

        return count($forecast->days);
    }
}
