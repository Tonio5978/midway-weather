<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Models\ForecastDay;
use App\Models\Observation;
use App\Weather\Data\Trend;
use App\Weather\Services\ConditionSummaryService;
use App\Weather\Support\ConditionPhraseLibrary;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class ConditionSummaryServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        config([
            'weather.display.phrase_rotation_minutes' => 45,
            'weather.display.phrase_history_size' => 5,
        ]);
    }

    private function service(): ConditionSummaryService
    {
        return app(ConditionSummaryService::class);
    }

    /** @param array<string, mixed> $attributes */
    private function observation(array $attributes): Observation
    {
        return new Observation($attributes);
    }

    public function test_missing_station_data_keeps_the_existing_contract(): void
    {
        $this->assertSame('Awaiting station data', $this->service()->summarize(null));
        $this->assertSame(
            'Awaiting station data',
            $this->service()->summarize($this->observation(['temperature' => null])),
        );
    }

    #[DataProvider('categoryScenarios')]
    public function test_it_selects_normalized_categories(
        string $expected,
        array $attributes,
        ?string $pressureDirection = null,
        ?int $weatherCode = null,
    ): void {
        $trends = $pressureDirection === null ? [] : [
            'pressure' => new Trend($pressureDirection, ucfirst($pressureDirection), 0.05, 'past 3 hours'),
        ];
        $forecast = $weatherCode === null ? null : new ForecastDay(['weather_code' => $weatherCode]);

        $this->assertSame(
            $expected,
            $this->service()->categoryFor($this->observation($attributes), $trends, $forecast),
        );
    }

    /** @return array<string, array{string, array<string, mixed>, string|null, int|null}> */
    public static function categoryScenarios(): array
    {
        return [
            'oppressive' => ['oppressive', ['temperature' => 96, 'dew_point' => 76, 'wind_speed' => 3], null, null],
            'hot humid' => ['hot_humid', ['temperature' => 90, 'dew_point' => 70, 'wind_speed' => 3], null, null],
            'hot dry' => ['hot_dry', ['temperature' => 90, 'dew_point' => 50, 'wind_speed' => 3], null, null],
            'warm breezy' => ['warm_breezy', ['temperature' => 80, 'dew_point' => 60, 'wind_speed' => 10, 'uv_index' => 4, 'solar_radiation' => 500], null, null],
            'warm calm' => ['warm_calm', ['temperature' => 80, 'dew_point' => 60, 'wind_speed' => 3, 'uv_index' => 4, 'solar_radiation' => 500], null, null],
            'mild clear' => ['mild_clear', ['temperature' => 68, 'dew_point' => 30, 'wind_speed' => 3, 'solar_radiation' => 500], null, null],
            'cool breezy' => ['cool_breezy', ['temperature' => 55, 'dew_point' => 42, 'wind_speed' => 10], null, null],
            'cool calm' => ['cool_calm', ['temperature' => 55, 'dew_point' => 42, 'wind_speed' => 3], null, null],
            'cold clear' => ['cold_clear', ['temperature' => 35, 'wind_speed' => 3], null, null],
            'cold windy' => ['cold_windy', ['temperature' => 35, 'wind_speed' => 12], null, null],
            'frigid' => ['frigid', ['temperature' => 15, 'wind_speed' => 3], null, null],
            'rainy' => ['rainy', ['temperature' => 65, 'rain_hourly' => 0.05], null, null],
            'heavy rain' => ['heavy_rain', ['temperature' => 65, 'rain_hourly' => 0.4], null, null],
            'stormy observation' => ['stormy', ['temperature' => 70, 'rain_hourly' => 0.15, 'wind_gust' => 28], null, null],
            'stormy forecast' => ['stormy', ['temperature' => 70, 'rain_hourly' => 0, 'wind_gust' => 8], null, 95],
            'overcast' => ['overcast', ['temperature' => 68, 'humidity' => 85, 'wind_speed' => 3, 'solar_radiation' => 100], null, 3],
            'high uv' => ['high_uv', ['temperature' => 70, 'dew_point' => 35, 'wind_speed' => 3, 'uv_index' => 9, 'solar_radiation' => 500], null, null],
            'falling pressure' => ['falling_pressure', ['temperature' => 68, 'dew_point' => 35, 'wind_speed' => 3, 'solar_radiation' => 100], Trend::FALLING, null],
            'rising pressure' => ['rising_pressure', ['temperature' => 68, 'dew_point' => 35, 'wind_speed' => 3, 'solar_radiation' => 100], Trend::RISING, null],
            'dead calm' => ['dead_calm', ['temperature' => 68, 'dew_point' => 55, 'wind_speed' => 0, 'solar_radiation' => 100], null, null],
            'windy' => ['windy', ['temperature' => 68, 'dew_point' => 50, 'wind_speed' => 22], null, null],
            'muggy' => ['muggy', ['temperature' => 68, 'dew_point' => 70, 'wind_speed' => 3, 'solar_radiation' => 100], null, null],
            'pleasant' => ['pleasant', ['temperature' => 72, 'dew_point' => 55, 'wind_speed' => 5, 'uv_index' => 4, 'solar_radiation' => 500], null, null],
            'fallback' => ['fallback', ['temperature' => 68, 'wind_speed' => 3], null, null],
        ];
    }

    public function test_every_curated_category_has_depth_and_the_library_contains_180_phrases(): void
    {
        $library = app(ConditionPhraseLibrary::class);

        foreach ($library->categories() as $category) {
            $minimum = $category === 'fallback' ? 4 : 8;
            $this->assertGreaterThanOrEqual($minimum, count($library->phrasesFor($category)), $category);
        }

        $this->assertSame(180, $library->count());
    }

    public function test_the_fallback_is_a_curated_local_phrase(): void
    {
        $observation = $this->observation(['temperature' => 68, 'wind_speed' => 3]);
        $summary = $this->service()->summarize($observation);

        $this->assertSame('fallback', $this->service()->categoryFor($observation));
        $this->assertContains($summary, app(ConditionPhraseLibrary::class)->phrasesFor('fallback'));
    }

    public function test_refreshes_stay_stable_then_rotate_without_recent_repetition(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-07-21 12:00:00', 'America/New_York'));
        $observation = $this->observation(['temperature' => 90, 'dew_point' => 70, 'wind_speed' => 3]);

        $first = $this->service()->summarize($observation);
        $this->assertSame($first, $this->service()->summarize($observation));

        $this->travel(46)->minutes();
        $second = $this->service()->summarize($observation);
        $this->assertNotSame($first, $second);

        $this->travel(46)->minutes();
        $third = $this->service()->summarize($observation);
        $this->assertNotSame($first, $third);
        $this->assertNotSame($second, $third);

        $this->travelBack();
    }

    public function test_material_category_changes_reselect_immediately(): void
    {
        $hot = $this->service()->summarize($this->observation([
            'temperature' => 90,
            'dew_point' => 50,
            'wind_speed' => 3,
        ]));
        $rain = $this->service()->summarize($this->observation([
            'temperature' => 70,
            'rain_hourly' => 0.08,
        ]));

        $this->assertNotSame($hot, $rain);
        $this->assertContains($hot, app(ConditionPhraseLibrary::class)->phrasesFor('hot_dry'));
        $this->assertContains($rain, app(ConditionPhraseLibrary::class)->phrasesFor('rainy'));
    }

    public function test_nighttime_selection_never_uses_daylight_specific_copy(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-07-21 21:00:00', 'America/New_York'));
        $observation = $this->observation(['temperature' => 90, 'dew_point' => 50, 'wind_speed' => 3]);

        for ($selection = 0; $selection < 10; $selection++) {
            $details = $this->service()->details($observation, [], null, false);
            $this->assertSame('hot_dry', $details['category']);
            $this->assertContains($details['message'], app(ConditionPhraseLibrary::class)->phrasesFor('hot_dry', false));
            $this->assertDoesNotMatchRegularExpression('/\b(?:sun|sunshine|sunny|bright|shade)\b/i', $details['message']);
            $this->travel(46)->minutes();
        }

        $this->travelBack();
    }

    public function test_nighttime_does_not_select_the_daylight_only_high_uv_category(): void
    {
        $observation = $this->observation(['temperature' => 70, 'dew_point' => 35, 'wind_speed' => 3, 'uv_index' => 9, 'solar_radiation' => 500]);

        $this->assertSame('high_uv', $this->service()->categoryFor($observation));
        $this->assertSame('fallback', $this->service()->categoryFor($observation, [], null, false));
        $this->assertContains(
            $this->service()->summarize($observation, [], null, false),
            app(ConditionPhraseLibrary::class)->phrasesFor('fallback', false),
        );
    }
}
