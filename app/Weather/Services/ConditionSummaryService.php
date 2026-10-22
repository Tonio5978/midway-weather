<?php

declare(strict_types=1);

namespace App\Weather\Services;

use App\Models\ForecastDay;
use App\Models\Observation;
use App\Weather\Contracts\ConditionPhraseSource;
use App\Weather\Data\Trend;
use Illuminate\Support\Facades\Cache;

/**
 * Selects a normalized weather feel and a stable, human phrase for the kiosk.
 *
 * Everything remains local and deterministic. A phrase stays fixed while its
 * category is materially unchanged, then rotates after the configured window.
 * Recently used IDs are excluded so the household display does not sound stuck.
 */
final class ConditionSummaryService
{
    private const CURRENT_CACHE_KEY = 'weather.condition-summary.current';

    private const HISTORY_CACHE_KEY = 'weather.condition-summary.history';

    public function __construct(
        private readonly ConditionPhraseSource $phrases,
    ) {}

    /**
     * Preserve the original display contract while accepting today's forecast
     * as optional context for storm and cloud-cover classification.
     *
     * @param  array<string, Trend>  $trends
     */
    public function summarize(
        ?Observation $observation,
        array $trends = [],
        ?ForecastDay $forecast = null,
        bool $isDaytime = true,
    ): string {
        return $this->details($observation, $trends, $forecast, $isDaytime)['message'];
    }

    /**
     * The display and ecosystem consumers share both the phrase and the
     * interpretation that selected it.  Keeping this here prevents clients
     * from reverse-engineering the weather rules from display copy.
     *
     * @param  array<string, Trend>  $trends
     * @return array{category: string, message: string, phrase_id: string|null}
     */
    public function details(
        ?Observation $observation,
        array $trends = [],
        ?ForecastDay $forecast = null,
        bool $isDaytime = true,
    ): array {
        if ($observation === null || $observation->temperature === null) {
            return [
                'category' => 'awaiting_data',
                'message' => 'Awaiting station data',
                'phrase_id' => null,
            ];
        }

        $category = $this->categoryFor($observation, $trends, $forecast, $isDaytime);
        $selected = $this->selectPhrase($category, $isDaytime);

        if ($selected === null) {
            $selected = $this->selectPhrase('fallback', $isDaytime);
        }

        return $selected ?? [
            'category' => 'fallback',
            'message' => 'Weather is happening. Details remain delightfully ordinary.',
            'phrase_id' => null,
        ];
    }

    /**
     * @param  array<string, Trend>  $trends
     */
    public function categoryFor(
        Observation $observation,
        array $trends = [],
        ?ForecastDay $forecast = null,
        bool $isDaytime = true,
    ): string {
        if ($observation->temperature === null) {
            return 'fallback';
        }

        $temperature = (float) $observation->temperature;
        $dewPoint = $observation->dew_point;
        $humidity = $observation->humidity;
        $wind = (float) ($observation->wind_speed_avg_10m ?? $observation->wind_speed ?? 0.0);
        $gust = (float) ($observation->wind_gust ?? 0.0);
        $rain = (float) ($observation->rain_hourly ?? 0.0);
        $uv = (float) ($observation->uv_index ?? 0.0);
        $solar = $observation->solar_radiation;
        $weatherCode = $forecast?->weather_code;

        if ($this->forecastIsStormy($weatherCode) || ($rain >= 0.1 && $gust >= 25.0)) {
            return 'stormy';
        }

        if ($rain >= (float) config('weather.thresholds.heavy_rain', 0.3)) {
            return 'heavy_rain';
        }

        if ($rain > 0.0) {
            return 'rainy';
        }

        if ($temperature >= 95.0 || ($temperature >= 88.0 && $dewPoint !== null && $dewPoint >= 74.0)) {
            return 'oppressive';
        }

        if ($temperature < 25.0) {
            return 'frigid';
        }

        if ($temperature < (float) config('weather.thresholds.cold', 45)) {
            return $wind >= 10.0 ? 'cold_windy' : 'cold_clear';
        }

        if ($wind >= (float) config('weather.thresholds.wind', 20)) {
            return 'windy';
        }

        if ($temperature >= (float) config('weather.thresholds.hot', 85)) {
            $isDry = ($dewPoint !== null && $dewPoint < 60.0)
                || ($dewPoint === null && $humidity !== null && $humidity < 40);

            return $isDry ? 'hot_dry' : 'hot_humid';
        }

        if ($this->isOvercast($weatherCode, $solar, $humidity)) {
            return 'overcast';
        }

        if ($isDaytime && $uv >= 8.0) {
            return 'high_uv';
        }

        if ($temperature >= 75.0) {
            return $wind >= 8.0 ? 'warm_breezy' : 'warm_calm';
        }

        if (($dewPoint !== null && $dewPoint >= 68.0) || ($dewPoint === null && $humidity !== null && $humidity >= (float) config('weather.thresholds.humidity', 80))) {
            return 'muggy';
        }

        if ($wind <= 1.0 && $temperature >= 58.0) {
            return 'dead_calm';
        }

        if ($temperature < 60.0) {
            return $wind >= 8.0 ? 'cool_breezy' : 'cool_calm';
        }

        $pressure = $trends['pressure'] ?? null;
        if ($pressure instanceof Trend && $pressure->direction === Trend::FALLING) {
            return 'falling_pressure';
        }
        if ($pressure instanceof Trend && $pressure->direction === Trend::RISING) {
            return 'rising_pressure';
        }

        if ($this->isPleasant($temperature, $dewPoint, $wind, $weatherCode, $solar)) {
            return 'pleasant';
        }

        if ($isDaytime && $temperature >= 60.0 && $temperature < 75.0 && $this->isClear($weatherCode, $solar)) {
            return 'mild_clear';
        }

        return 'fallback';
    }

    /** @return array{category: string, message: string, phrase_id: string|null}|null */
    private function selectPhrase(string $category, bool $isDaytime): ?array
    {
        $rotationMinutes = max(5, (int) config('weather.display.phrase_rotation_minutes', 45));
        $historySize = max(1, (int) config('weather.display.phrase_history_size', 5));
        $now = now()->timestamp;

        $preview = (bool) config('weather.admin_preview', false);
        $current = $preview ? null : Cache::get(self::CURRENT_CACHE_KEY);
        if (is_array($current)
            && ($current['category'] ?? null) === $category
            && ($current['is_daytime'] ?? null) === $isDaytime
            && is_int($current['selected_at'] ?? null)
            && $current['selected_at'] > $now - ($rotationMinutes * 60)) {
            $currentPhrases = $this->phrases->phrasesFor($category, $isDaytime);
            $currentId = $current['id'] ?? null;

            if (is_string($currentId) && isset($currentPhrases[$currentId])) {
                return ['category' => $category, 'message' => $currentPhrases[$currentId], 'phrase_id' => $currentId];
            }
        }

        $options = $this->phrases->phrasesFor($category, $isDaytime);
        if ($options === []) {
            return null;
        }

        $history = $preview ? [] : Cache::get(self::HISTORY_CACHE_KEY, []);
        $history = is_array($history) ? array_values(array_filter($history, 'is_string')) : [];
        $available = array_diff_key($options, array_flip($history));
        if ($available === []) {
            $available = $options;
        }

        $ids = array_keys($available);
        $slot = intdiv($now, $rotationMinutes * 60);
        $seed = sprintf('%s|%d|%s', $category, $slot, (string) ($current['id'] ?? 'first'));
        $index = (int) (sprintf('%u', crc32($seed)) % count($ids));
        $selectedId = $ids[$index];

        $history[] = $selectedId;
        $history = array_slice(array_values(array_unique($history)), -$historySize);

        $ttl = now()->addDays(2);
        if (! $preview) {
            Cache::put(self::CURRENT_CACHE_KEY, [
                'category' => $category,
                'id' => $selectedId,
                'selected_at' => $now,
                'is_daytime' => $isDaytime,
            ], $ttl);
            Cache::put(self::HISTORY_CACHE_KEY, $history, $ttl);
        }

        return ['category' => $category, 'message' => $available[$selectedId], 'phrase_id' => $selectedId];
    }

    private function forecastIsStormy(?int $weatherCode): bool
    {
        return $weatherCode !== null && in_array($weatherCode, [95, 96, 99], true);
    }

    private function isOvercast(?int $weatherCode, ?float $solar, ?int $humidity): bool
    {
        $cloudyForecast = $weatherCode !== null && in_array($weatherCode, [2, 3, 45, 48], true);
        $darkAndHumid = $solar !== null && $solar < 140.0 && $humidity !== null && $humidity >= 75;

        return $darkAndHumid || ($cloudyForecast && ($solar === null || $solar < 220.0));
    }

    private function isClear(?int $weatherCode, ?float $solar): bool
    {
        return ($weatherCode !== null && in_array($weatherCode, [0, 1], true))
            || ($solar !== null && $solar >= 250.0);
    }

    private function isPleasant(
        float $temperature,
        ?float $dewPoint,
        float $wind,
        ?int $weatherCode,
        ?float $solar,
    ): bool {
        $comfortableMoisture = $dewPoint === null || ($dewPoint >= 42.0 && $dewPoint <= 64.0);

        return $temperature >= 62.0
            && $temperature <= 78.0
            && $wind > 1.0
            && $wind < 10.0
            && $comfortableMoisture
            && $this->isClear($weatherCode, $solar);
    }
}
