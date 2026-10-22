<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Models\DailySummary;
use App\Weather\Services\RetentionService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\MakesWeatherData;
use Tests\TestCase;

/**
 * Retention prunes only the detailed observation stream. Daily summaries and
 * forecast rows are deliberately preserved so long-term context survives the
 * loss of the raw readings that produced it.
 */
final class RetentionServiceTest extends TestCase
{
    use MakesWeatherData;
    use RefreshDatabase;

    private RetentionService $service;

    private CarbonImmutable $now;

    protected function setUp(): void
    {
        parent::setUp();
        config(['weather.retention_days' => 10]);
        $this->service = app(RetentionService::class);
        $this->now = CarbonImmutable::parse('2026-07-21T12:00:00Z');
    }

    public function test_it_prunes_observations_older_than_the_retention_window(): void
    {
        $fresh = $this->storeObservation($this->now->subDays(2));
        $old = $this->storeObservation($this->now->subDays(15));

        $result = $this->service->prune($this->now);

        $this->assertSame(1, $result['deleted']);
        $this->assertSame(10, $result['retention_days']);
        $this->assertDatabaseHas('observations', ['id' => $fresh->id]);
        $this->assertDatabaseMissing('observations', ['id' => $old->id]);
    }

    public function test_the_retention_boundary_is_inclusive_of_recent_data(): void
    {
        // Exactly at the cutoff minus a minute is kept; comfortably past is dropped.
        $justInside = $this->storeObservation($this->now->subDays(10)->addMinutes(1));
        $justOutside = $this->storeObservation($this->now->subDays(10)->subMinutes(1));

        $this->service->prune($this->now);

        $this->assertDatabaseHas('observations', ['id' => $justInside->id]);
        $this->assertDatabaseMissing('observations', ['id' => $justOutside->id]);
    }

    public function test_pruning_leaves_summaries_and_forecast_rows_untouched(): void
    {
        $this->storeObservation($this->now->subDays(30));
        $this->storeForecastDay($this->now->subDays(30));
        DailySummary::query()->create([
            'date' => $this->now->subDays(30)->toDateString(),
            'temperature_high' => 90.0,
            'temperature_low' => 70.0,
            'observation_count' => 100,
        ]);

        $result = $this->service->prune($this->now);

        $this->assertSame(1, $result['deleted']);
        // History and forecast survive even though the raw readings are gone.
        $this->assertDatabaseCount('daily_summaries', 1);
        $this->assertDatabaseCount('forecast_days', 1);
        $this->assertDatabaseCount('observations', 0);
    }

    public function test_pruning_an_empty_table_deletes_nothing(): void
    {
        $result = $this->service->prune($this->now);

        $this->assertSame(0, $result['deleted']);
    }
}
