<?php

declare(strict_types=1);

namespace Tests\Feature\Http;

use App\Models\CollectionStatus;
use App\Models\DailySummary;
use App\Models\ForecastDay;
use App\Models\Observation;
use App\Support\CanonicalResponse;
use App\Support\ReleaseIdentity;
use App\Weather\Services\CanonicalSummary;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;
use Tests\Support\MakesWeatherData;
use Tests\TestCase;

final class CanonicalWeatherTest extends TestCase
{
    use MakesWeatherData;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-31T16:00:00Z'));
        config(['weather.timezone' => 'America/New_York', 'weather.staleness.fresh_minutes' => 10, 'weather.staleness.stale_minutes' => 20, 'weather.staleness.forecast_stale_hours' => 3, 'weather.retention_days' => 10]);
        Http::preventStrayRequests();
        Http::fake();
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    private function observation(string $time = '2026-08-31T15:58:00Z', array $attributes = []): Observation
    {
        $source = CarbonImmutable::parse($time);

        return $this->storeObservation($source->startOfMinute(), array_replace(['raw' => ['dateutc' => $source->getTimestampMs()]], $attributes));
    }

    private function healthySources(): void
    {
        foreach (['ambient', 'forecast'] as $source) {
            CollectionStatus::query()->create(['source' => $source, 'last_success_at' => '2026-08-31 15:59:00', 'consecutive_failures' => 0]);
        }
    }

    private function forecast(array $attributes = []): ForecastDay
    {
        return $this->storeForecastDay(CarbonImmutable::now('America/New_York')->startOfDay(), array_replace(['fetched_at' => CarbonImmutable::now('UTC')->subHour()], $attributes));
    }

    private function conform(TestResponse $response, string $kind = 'weather'): TestResponse
    {
        $file = tempnam(sys_get_temp_dir(), 'weather-conformance-');
        try {
            file_put_contents($file, $response->getContent());
            $process = new Process(['node', base_path('scripts/validate-contracts.mjs'), $kind, $file, (string) $response->status()], base_path());
            $process->run();
            $this->assertTrue($process->isSuccessful(), $process->getOutput().$process->getErrorOutput());
        } finally {
            unlink($file);
        }

        return $response;
    }

    public function test_real_projection_is_public_read_only_and_preserves_source_semantics(): void
    {
        $this->observation(attributes: ['temperature' => 78.5, 'solar_radiation' => 42, 'rain_hourly' => 0.12]);
        $this->observation('2026-08-31T12:00:00Z', ['temperature' => 65]);
        $this->observation('2026-08-31T14:00:00Z', ['temperature' => 84]);
        $this->forecast(['temperature_high' => 92, 'temperature_low' => 70, 'weather_code' => 2]);
        $this->healthySources();
        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = $query->sql;
        });
        $response = $this->conform($this->withHeader('X-Midway-Request-ID', 'weather.test:1')->getJson('/api/v2/summary/current'))->assertOk();
        $response->assertJsonPath('meta.request_id', 'weather.test:1')
            ->assertJsonPath('data.availability', 'available')->assertJsonPath('data.observation.temperature_f', 78.5)
            ->assertJsonPath('data.observation.observed_at', '2026-08-31T15:58:00+00:00')
            ->assertJsonPath('data.observation.retrieved_at', null)->assertJsonPath('data.observation.last_known_good', false)
            ->assertJsonPath('data.observed_today.temperature_high_f', 84)->assertJsonPath('data.observed_today.temperature_low_f', 65)
            ->assertJsonPath('data.observed_today.as_of_at', '2026-08-31T15:58:00+00:00')
            ->assertJsonPath('data.forecast_today.temperature_high_f', 92)->assertJsonPath('data.forecast_today.condition', 'partly_cloudy')
            ->assertJsonPath('data.interpretation.atmosphere', 'rain')->assertJsonPath('data.interpretation.summary', null);
        $this->assertSame([], $response->headers->getCookies());
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertSame('weather.test:1', $response->headers->get('X-Midway-Request-ID'));
        foreach ($queries as $sql) {
            $this->assertMatchesRegularExpression('/^select\b/i', $sql, $sql);
        }
        Http::assertNothingSent();
    }

    public static function ages(): array
    {
        return [[2, 'fresh'], [10, 'fresh'], [11, 'delayed'], [20, 'delayed'], [21, 'stale'], [60, 'stale']];
    }

    #[DataProvider('ages')]
    public function test_freshness_does_not_imply_availability_or_repoll_time(int $minutes, string $state): void
    {
        $this->observation(CarbonImmutable::now('UTC')->subMinutes($minutes)->toIso8601String());
        $this->forecast();
        $this->healthySources();
        $this->conform($this->getJson('/api/v2/summary/current'))->assertOk()
            ->assertJsonPath('data.availability', 'available')->assertJsonPath('data.observation.freshness.state', $state)
            ->assertJsonPath('data.observed_today.freshness.state', $state)->assertJsonPath('data.observation.last_known_good', false);
    }

    public function test_recent_failed_refresh_is_degraded_without_rewriting_deadlines(): void
    {
        $this->observation();
        $this->forecast();
        $this->healthySources();
        foreach (['ambient', 'forecast'] as $source) {
            CollectionStatus::query()->where('source', $source)->update(['consecutive_failures' => 1, 'last_failure_at' => '2026-08-31 16:00:00']);
        }
        $response = $this->conform($this->getJson('/api/v2/summary/current'))->assertOk();
        $response->assertJsonPath('data.availability', 'degraded')->assertJsonPath('data.observation.last_known_good', true)
            ->assertJsonPath('data.forecast_today.last_known_good', true)->assertJsonPath('data.observation.freshness.state', 'fresh')
            ->assertJsonPath('data.observation.freshness.fresh_until', '2026-08-31T16:08:00+00:00');
        CarbonImmutable::setTestNow('2026-08-31T16:19:00Z');
        $this->conform($this->getJson('/api/v2/summary/current'))->assertJsonPath('data.observation.freshness.state', 'stale')
            ->assertJsonPath('data.observation.observed_at', $response->json('data.observation.observed_at'));
    }

    public function test_stale_selected_forecast_cannot_be_refreshed_by_another_row_or_observation(): void
    {
        $this->observation();
        $this->healthySources();
        $this->forecast(['fetched_at' => '2026-08-31 11:00:00']);
        $this->storeForecastDay(CarbonImmutable::now('America/New_York')->addDay(), ['fetched_at' => '2026-08-31 15:59:00']);
        $this->conform($this->getJson('/api/v2/summary/current'))->assertOk()
            ->assertJsonPath('data.observation.freshness.state', 'fresh')->assertJsonPath('data.forecast_today.freshness.state', 'stale')
            ->assertJsonPath('data.forecast_today.retrieved_at', '2026-08-31T11:00:00+00:00')
            ->assertJsonPath('data.interpretation.freshness.state', 'stale')->assertJsonPath('data.availability', 'available');
    }

    public function test_missing_optional_data_and_operational_evidence_are_honest(): void
    {
        $this->observation(attributes: ['solar_radiation' => -1, 'rain_hourly' => null]);
        $this->conform($this->getJson('/api/v2/summary/current'))->assertOk()
            ->assertJsonPath('data.availability', 'degraded')->assertJsonPath('data.forecast_today', null)
            ->assertJsonPath('data.interpretation', null)->assertJsonPath('data.observation.solar_radiation_w_m2', null)
            ->assertJsonPath('data.observation.last_known_good', false);
    }

    public static function invalidObservations(): array
    {
        return [[['raw' => null]], [['raw' => ['date' => 'yesterday']]], [['raw' => ['date' => '2026-02-30T15:58:00Z']]],
            [['raw' => ['date' => '2026-08-31T15:58:00']]], [['raw' => ['dateutc' => 0]]], [['temperature' => null]], [['temperature' => -500]], [['temperature' => 'not-a-number']]];
    }

    #[DataProvider('invalidObservations')]
    public function test_unusable_observations_return_canonical_503(array $attributes): void
    {
        $this->observation(attributes: $attributes);
        $response = $this->conform($this->getJson('/api/v2/summary/current'))->assertStatus(503);
        $response->assertJsonPath('error.code', 'weather_data_unavailable')->assertJsonPath('error.details.availability', 'unavailable')->assertJsonMissingPath('data');
    }

    public function test_source_offset_is_accepted_and_precision_is_retained(): void
    {
        $this->observation('2026-08-31T15:58:45Z', ['raw' => ['date' => '2026-08-31T11:58:45-04:00']]);
        $this->conform($this->getJson('/api/v2/summary/current'))->assertOk()->assertJsonPath('data.observation.observed_at', '2026-08-31T15:58:00+00:00');
    }

    public function test_future_or_synthetic_newest_rows_use_valid_retained_fallback_and_exclude_bad_extrema(): void
    {
        $this->observation(attributes: ['temperature' => 75]);
        $this->observation('2026-08-31T15:59:00Z', ['raw' => null, 'temperature' => 120]);
        $this->observation('2026-08-31T16:01:00Z', ['temperature' => 130]);
        $this->forecast();
        $this->healthySources();
        $this->conform($this->getJson('/api/v2/summary/current'))->assertOk()
            ->assertJsonPath('data.availability', 'degraded')->assertJsonPath('data.observation.temperature_f', 75)
            ->assertJsonPath('data.observation.last_known_good', false)->assertJsonPath('data.observed_today.temperature_high_f', 75);
    }

    public function test_retention_policy_and_no_observations_return_unavailable(): void
    {
        $this->conform($this->getJson('/api/v2/summary/current'))->assertStatus(503);
        $this->observation('2026-08-01T12:00:00Z');
        $this->conform($this->getJson('/api/v2/summary/current'))->assertStatus(503);
    }

    public static function invalidForecasts(): array
    {
        return [[['temperature_high' => null, 'temperature_low' => null, 'weather_code' => 999]],
            [['temperature_high' => 10, 'temperature_low' => 20]], [['fetched_at' => '2026-08-31 17:00:00']], [['fetched_at' => 'invalid']]];
    }

    #[DataProvider('invalidForecasts')]
    public function test_unusable_forecast_is_partial_success(array $attributes): void
    {
        $this->observation();
        $this->healthySources();
        $forecast = $this->forecast();
        DB::table('forecast_days')->where('id', $forecast->id)->update($attributes);
        $this->conform($this->getJson('/api/v2/summary/current'))->assertOk()->assertJsonPath('data.forecast_today', null)->assertJsonPath('data.availability', 'degraded');
    }

    public static function classifications(): array
    {
        return [[96, 0, 'thunderstorm_with_hail'], [99, 1, 'thunderstorm_with_hail'], [95, 1, 'thunderstorm'], [75, 1, 'snow'], [3, 0.4, 'heavy_rain'], [0, 0.01, 'light_rain'], [999, 0, null]];
    }

    #[DataProvider('classifications')]
    public function test_weather_classification_reuses_precedence_without_provider_or_visual_labels(int $code, float $rain, ?string $expected): void
    {
        $this->observation(attributes: ['rain_hourly' => $rain]);
        $this->forecast(['weather_code' => $code]);
        $response = $this->conform($this->getJson('/api/v2/summary/current'))->assertOk();
        $this->assertSame($expected, $response->json('data.interpretation.atmosphere'));
    }

    public static function days(): array
    {
        return [['2026-03-08T16:00:00Z', 23], ['2026-11-01T16:00:00Z', 25]];
    }

    #[DataProvider('days')]
    public function test_daily_windows_follow_local_dst(string $instant, int $hours): void
    {
        CarbonImmutable::setTestNow($instant);
        $this->observation(CarbonImmutable::now('UTC')->subMinutes(2)->toIso8601String());
        $this->forecast();
        $response = $this->conform($this->getJson('/api/v2/summary/current'))->assertOk();
        $period = $response->json('data.observed_today.period');
        $this->assertSame($hours * 3600, strtotime($period['ends_at']) - strtotime($period['starts_at']));
    }

    public function test_rollover_does_not_relabel_yesterday_or_the_cached_daily_aggregate(): void
    {
        CarbonImmutable::setTestNow('2026-09-01T04:01:00Z');
        $this->observation('2026-09-01T03:59:00Z');
        $this->storeForecastDay(CarbonImmutable::parse('2026-08-31'), ['fetched_at' => '2026-08-31 23:00:00']);
        DailySummary::query()->create(['date' => '2026-09-01', 'temperature_high' => 150, 'temperature_low' => -50]);
        $this->conform($this->getJson('/api/v2/summary/current'))->assertOk()->assertJsonPath('data.observed_today', null)->assertJsonPath('data.forecast_today', null);
    }

    public function test_errors_and_request_ids_are_canonical_without_debug_leakage(): void
    {
        $this->app->bind(CanonicalSummary::class, fn () => new class extends CanonicalSummary
        {
            public function project(CarbonImmutable $now): ?array
            {
                throw new \RuntimeException('private-provider-credential');
            }
        });
        config(['app.debug' => true]);
        $response = $this->conform($this->withHeader('X-Midway-Request-ID', 'bad id')->getJson('/api/v2/summary/current'))->assertStatus(500);
        $response->assertJsonPath('error.code', 'internal_error')->assertDontSee('private-provider-credential');
        $this->assertNotSame('bad id', $response->json('meta.request_id'));
        $this->conform($this->postJson('/api/v2/summary/current'))->assertStatus(405)->assertJsonPath('error.code', 'method_not_allowed')->assertHeader('Allow', 'GET, HEAD');
    }

    public function test_operational_contracts_distinguish_store_health_from_missing_weather(): void
    {
        $this->conform($this->getJson('/api/v1/health'), 'health')->assertOk()->assertJsonPath('data.availability', 'degraded');
        $this->conform($this->getJson('/api/v1/version'), 'version')->assertOk()->assertJsonPath('data.contracts.contracts-weather', '1.0.0');
        foreach (CanonicalResponse::CONTRACTS as $name => $version) {
            $lock = json_decode(file_get_contents(base_path("contracts/$name.lock.json")), true);
            $this->assertSame($version, $lock['version']);
        }
        DB::shouldReceive('select')->once()->andThrow(new \RuntimeException('private-database-path'));
        $this->conform($this->getJson('/api/v1/health'), 'health')->assertStatus(503)->assertJsonPath('data.availability', 'unavailable')->assertDontSee('private-database-path');
    }

    public function test_configured_deadlines_and_inclusive_second_boundaries(): void
    {
        config(['weather.staleness.fresh_minutes' => 5, 'weather.staleness.stale_minutes' => 7, 'weather.staleness.forecast_stale_hours' => 0]);
        $this->observation('2026-08-31T15:55:00Z');
        $this->forecast(['fetched_at' => '2026-08-31 16:00:00']);
        $this->conform($this->getJson('/api/v2/summary/current'))->assertJsonPath('data.observation.freshness.state', 'fresh')->assertJsonPath('data.forecast_today.freshness.state', 'fresh');
        CarbonImmutable::setTestNow('2026-08-31T16:00:01Z');
        $this->conform($this->getJson('/api/v2/summary/current'))->assertJsonPath('data.observation.freshness.state', 'delayed')->assertJsonPath('data.forecast_today.freshness.state', 'stale')->assertJsonPath('data.interpretation.freshness.state', 'stale');
        CarbonImmutable::setTestNow('2026-08-31T16:02:00Z');
        $this->conform($this->getJson('/api/v2/summary/current'))->assertJsonPath('data.observation.freshness.state', 'delayed');
        CarbonImmutable::setTestNow('2026-08-31T16:02:01Z');
        $this->conform($this->getJson('/api/v2/summary/current'))->assertJsonPath('data.observation.freshness.state', 'stale');
    }

    public function test_partial_forecast_and_future_subminute_observation_are_not_fabricated(): void
    {
        $this->observation('2026-08-31T16:00:30Z');
        $this->conform($this->getJson('/api/v2/summary/current'))->assertStatus(503);
        $this->observation();
        $this->forecast(['temperature_high' => null, 'temperature_low' => 60, 'weather_code' => null]);
        $this->conform($this->getJson('/api/v2/summary/current'))->assertOk()->assertJsonPath('data.forecast_today.temperature_high_f', null)->assertJsonPath('data.forecast_today.temperature_low_f', 60)->assertJsonPath('data.forecast_today.condition', null);
    }

    public function test_production_version_requires_real_release_metadata(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        $file = tempnam(sys_get_temp_dir(), 'weather-release-');
        $this->app->instance(ReleaseIdentity::class, new ReleaseIdentity($file));
        try {
            $this->conform($this->getJson('/api/v1/version'), 'error')->assertStatus(503);
            file_put_contents($file, json_encode(['contract' => 'midway.release-manifest/v1', 'application' => 'midway-weather', 'release_id' => '20260831T160000Z-1234567', 'source_commit' => str_repeat('a', 40), 'built_at' => '2026-08-31T16:00:00Z', 'contracts' => CanonicalResponse::CONTRACTS]));
            $this->conform($this->getJson('/api/v1/version'), 'version')->assertOk()->assertJsonPath('data.release', '20260831T160000Z-1234567');
        } finally {
            unlink($file);
        }
    }
}
