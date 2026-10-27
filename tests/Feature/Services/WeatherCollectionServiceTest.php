<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Models\CollectionStatus;
use App\Models\Observation;
use App\Weather\Services\WeatherCollectionService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Support\MakesWeatherData;
use Tests\TestCase;

/**
 * The collection cycle must be resilient: it stores good data, records health,
 * and — most importantly — NEVER throws and never destroys the last good
 * observation when the upstream API fails.
 */
final class WeatherCollectionServiceTest extends TestCase
{
    use MakesWeatherData;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'weather.ambient.api_key' => 'test-api-key',
            'weather.ambient.application_key' => 'test-app-key',
            'weather.ambient.device_mac' => 'AA:BB:CC:DD:EE:FF',
            'weather.ambient.retry_times' => 1,
            'weather.ambient.retry_sleep_ms' => 0,
        ]);
    }

    private function service(): WeatherCollectionService
    {
        $this->travel(61)->seconds();

        return app(WeatherCollectionService::class);
    }

    public function test_a_successful_cycle_stores_the_observation_and_records_success(): void
    {
        Http::fake(['*' => Http::response($this->ambientDevicesBody(['tempf' => 75.0]), 200)]);

        $result = $this->service()->collect();

        $this->assertTrue($result['success']);
        $this->assertTrue($result['created']);
        $this->assertNull($result['error']);
        $this->assertDatabaseCount('observations', 1);

        $status = CollectionStatus::query()->where('source', 'ambient')->first();
        $this->assertNotNull($status);
        $this->assertSame(0, $status->consecutive_failures);
        $this->assertNotNull($status->last_success_at);
    }

    public function test_a_failed_cycle_records_failure_and_never_throws(): void
    {
        Http::fake(['*' => Http::response('', 429)]);

        $result = $this->service()->collect();

        $this->assertFalse($result['success']);
        $this->assertNull($result['observation']);
        $this->assertSame('Ambient request failed: Rate limit exceeded (HTTP 429).', $result['error']);

        $status = CollectionStatus::query()->where('source', 'ambient')->first();
        $this->assertSame(1, $status->consecutive_failures);
        $this->assertNotNull($status->last_failure_at);
        $this->assertSame('Ambient request failed: Rate limit exceeded (HTTP 429).', $status->last_error);
    }

    public function test_a_failure_after_prior_success_keeps_the_last_good_observation(): void
    {
        // Seed one good observation the "hard way" (as a prior successful poll).
        $existing = $this->storeObservation(CarbonImmutable::parse('2026-07-21T11:00:00Z'), ['temperature' => 68.0]);

        // Now the upstream API goes down.
        Http::fake(['*' => Http::failedConnection()]);

        $result = $this->service()->collect();

        $this->assertFalse($result['success']);
        // The last good reading is still the latest and is untouched.
        $this->assertDatabaseCount('observations', 1);
        $latest = Observation::query()->latestFirst()->first();
        $this->assertTrue($existing->is($latest));
        $this->assertSame(68.0, $latest->temperature);
    }

    public function test_repeated_failures_increment_the_consecutive_counter(): void
    {
        Http::fake(['*' => Http::response('', 500)]);

        $this->service()->collect();
        $this->service()->collect();
        $this->service()->collect();

        $status = CollectionStatus::query()->where('source', 'ambient')->first();
        $this->assertSame(3, $status->consecutive_failures);
    }

    public function test_a_success_after_failures_resets_the_counter(): void
    {
        // A single fake with a re-keyed URL does not override the first, so the
        // ordered responses (two failures, then a success) are expressed as one
        // sequence — each collect() makes exactly one request (retry_times=1).
        Http::fake(['*' => Http::sequence()
            ->push('', 500)
            ->push('', 500)
            ->push($this->ambientDevicesBody(), 200)]);

        $this->service()->collect();
        $this->service()->collect();
        $this->service()->collect();

        $status = CollectionStatus::query()->where('source', 'ambient')->first();
        $this->assertSame(0, $status->consecutive_failures);
        $this->assertNull($status->last_error);
    }

    public function test_re_polling_the_same_reading_updates_rather_than_duplicates(): void
    {
        // Same observed-at minute, updated temperature across two polls.
        Http::fake(['*' => Http::sequence()
            ->push($this->ambientDevicesBody(['dateutc' => 1_700_000_000_000, 'tempf' => 70.0]), 200)
            ->push($this->ambientDevicesBody(['dateutc' => 1_700_000_000_000, 'tempf' => 71.0]), 200)]);

        $first = $this->service()->collect();
        $this->assertTrue($first['created']);

        // Same observed_at minute, updated temperature -> update in place.
        $second = $this->service()->collect();

        $this->assertFalse($second['created']);
        $this->assertDatabaseCount('observations', 1);
        $this->assertSame(71.0, Observation::query()->first()->temperature);
    }
}
