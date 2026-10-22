<?php

declare(strict_types=1);

namespace Tests\Feature\Http;

use App\Models\CollectionStatus;
use App\Models\ForecastDay;
use App\Models\Observation;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Support\MakesWeatherData;
use Tests\TestCase;

final class DailyForecastTest extends TestCase
{
    use MakesWeatherData;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-07T17:12:00Z'));
        config(['weather.timezone' => 'America/New_York', 'weather.staleness.forecast_stale_hours' => 3]);
        Http::preventStrayRequests();
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    private function horizon(): void
    {
        for ($i = -1; $i < 9; $i++) {
            $this->storeForecastDay(CarbonImmutable::now('America/New_York')->startOfDay()->addDays($i), [
                'temperature_high' => 78 + $i, 'temperature_low' => 53 + $i,
                'weather_code' => $i === 3 ? 82 : 0, 'precipitation_probability' => $i === 3 ? 85 : 0,
                'fetched_at' => '2026-10-07T17:00:00Z',
            ]);
        }
        CollectionStatus::recordSuccess(CollectionStatus::SOURCE_FORECAST);
    }

    public function test_projects_seven_dated_days_from_forecast_without_observations_or_writes(): void
    {
        $this->horizon();
        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = $query->sql;
        });
        $response = $this->withHeader('X-Midway-Request-ID', 'forecast:test')->getJson('/api/v1/forecast/daily')->assertOk()
            ->assertHeader('X-Midway-Request-ID', 'forecast:test')->assertHeader('Cache-Control')
            ->assertJsonPath('meta.request_id', 'forecast:test')->assertJsonPath('meta.generated_at', '2026-10-07T17:12:00+00:00')
            ->assertJsonPath('data.timezone', 'America/New_York')->assertJsonPath('data.availability', 'available')
            ->assertJsonPath('data.period.starts_at', '2026-10-07T00:00:00-04:00')->assertJsonPath('data.period.ends_at', '2026-10-14T00:00:00-04:00')
            ->assertJsonCount(7, 'data.days')->assertJsonPath('data.days.0.date', '2026-10-07')->assertJsonPath('data.days.6.date', '2026-10-13')
            ->assertJsonPath('data.days.0.temperature_high_f', 78)->assertJsonPath('data.days.0.temperature_low_f', 53)
            ->assertJsonPath('data.days.3.condition.label', 'Heavy Showers')->assertJsonPath('data.days.3.condition.icon', 'showers')
            ->assertJsonPath('data.days.3.precipitation_probability_percent', 85)->assertJsonPath('data.days.0.last_known_good', false);
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertCount(0, $response->headers->getCookies());
        $this->assertSame(0, Observation::query()->count());
        $this->assertSame(10, ForecastDay::query()->count());
        $this->assertEmpty(array_filter($queries, fn (string $query): bool => preg_match('/^(insert|update|delete)/i', $query) === 1));
        Http::assertNothingSent();
    }

    public function test_retained_and_stale_rows_keep_their_own_source_timestamps(): void
    {
        $this->horizon();
        ForecastDay::query()->where('date', '2026-10-08')->update(['fetched_at' => '2026-10-07T12:00:00Z']);
        CollectionStatus::recordFailure(CollectionStatus::SOURCE_FORECAST, 'Forecast upstream unavailable.');
        $this->getJson('/api/v1/forecast/daily')->assertOk()->assertJsonPath('data.availability', 'degraded')
            ->assertJsonPath('data.days.0.last_known_good', true)->assertJsonPath('data.days.0.freshness.state', 'fresh')
            ->assertJsonPath('data.days.1.retrieved_at', '2026-10-07T12:00:00+00:00')
            ->assertJsonPath('data.days.1.freshness.fresh_until', '2026-10-07T15:00:00+00:00')->assertJsonPath('data.days.1.freshness.state', 'stale');
    }

    public function test_partial_dates_are_omitted_and_invalid_optional_values_remain_null(): void
    {
        $this->horizon();
        ForecastDay::query()->where('date', '2026-10-08')->delete();
        ForecastDay::query()->where('date', '2026-10-09')->update(['precipitation_probability' => 101, 'weather_code' => 1234, 'temperature_low' => null]);
        ForecastDay::query()->where('date', '2026-10-10')->update(['temperature_low' => 100, 'temperature_high' => 60]);
        ForecastDay::query()->where('date', '2026-10-11')->update(['fetched_at' => '2026-10-08T12:00:00Z']);
        $this->getJson('/api/v1/forecast/daily')->assertOk()->assertJsonPath('data.availability', 'degraded')->assertJsonCount(4, 'data.days')
            ->assertJsonPath('data.days.1.date', '2026-10-09')->assertJsonPath('data.days.1.precipitation_probability_percent', null)
            ->assertJsonPath('data.days.1.condition', null)->assertJsonPath('data.days.1.temperature_low_f', null);
    }

    public function test_missing_forecast_and_unsupported_method_use_standard_errors(): void
    {
        $this->withHeader('X-Midway-Request-ID', 'forecast:empty')->getJson('/api/v1/forecast/daily')->assertStatus(503)
            ->assertJsonPath('error.code', 'forecast_unavailable')->assertJsonPath('meta.request_id', 'forecast:empty')->assertJsonMissingPath('data');
        $this->postJson('/api/v1/forecast/daily')->assertStatus(405)->assertHeader('Allow', 'GET, HEAD')->assertJsonPath('error.code', 'method_not_allowed');
    }

    public function test_midnight_rollover_and_dst_use_weather_local_dates(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-01T04:30:00Z'));
        for ($i = -1; $i < 8; $i++) {
            $this->storeForecastDay(CarbonImmutable::now('America/New_York')->startOfDay()->addDays($i), ['fetched_at' => '2026-11-01T04:00:00Z']);
        }
        CollectionStatus::recordSuccess(CollectionStatus::SOURCE_FORECAST);
        $this->getJson('/api/v1/forecast/daily')->assertOk()->assertJsonCount(7, 'data.days')
            ->assertJsonPath('data.period.starts_at', '2026-11-01T00:00:00-04:00')->assertJsonPath('data.period.ends_at', '2026-11-08T00:00:00-05:00')
            ->assertJsonPath('data.days.0.date', '2026-11-01');
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-02T05:01:00Z'));
        $this->getJson('/api/v1/forecast/daily')->assertOk()->assertJsonPath('data.days.0.date', '2026-11-02')->assertJsonCount(7, 'data.days');
    }
}
