<?php

declare(strict_types=1);

namespace App\Weather\Services;

use App\Models\CollectionStatus;
use App\Models\Observation;
use App\Weather\Clients\AmbientWeatherClient;
use App\Weather\Exceptions\WeatherApiException;
use App\Weather\Providers\PollDeferred;
use App\Weather\Providers\PrimaryWeatherStore;
use App\Weather\Providers\StationCatalog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Orchestrates one Ambient collection cycle: fetch → normalize → persist →
 * update daily summary → record health. All failures are caught and recorded
 * so the scheduler command never throws; it inspects the returned result.
 */
class WeatherCollectionService
{
    public function __construct(
        private readonly AmbientWeatherClient $client,
        private readonly ObservationRepository $repository,
        private readonly DailySummaryService $summaries,
    ) {}

    /**
     * Run a single collection cycle.
     *
     * @return array{success: bool, created: bool, observation: Observation|null, error: string|null}
     */
    public function collect(): array
    {
        try {
            $inventory = $this->client->inventory();
            $devices = $inventory['devices'];
            if ($devices === []) {
                throw WeatherApiException::forSource('Ambient', 'Response contained no devices.');
            }
            $batch = app(StationCatalog::class)->ingest($devices, true, $inventory['revision']);
            $selection = app(PrimaryWeatherStore::class)->settings();
            $legacy = $batch['results'][$selection?->legacy_station_id] ?? null;
            $legacyResult = $legacy ? $this->repository->store($legacy) : null;
            if (array_filter($batch['results'])) {
                DB::table('weather_provider_states')->where('provider', 'ambient')->update(['last_collection_at' => now()]);
            }
            // Keep today's summary current for the "rain today vs. recent" module.
            try {
                $timezone = (string) config('weather.timezone', 'America/New_York');
                foreach (array_filter($batch['results']) as $stationId => $reading) {
                    $this->summaries->rebuildForDate($reading->observedAt->setTimezone($timezone)->startOfDay(), $stationId);
                }
            } catch (Throwable $e) {
                // A summary failure should not fail the collection itself.
                Log::warning('Failed to rebuild daily summary during collection.', ['exception' => $e::class]);
            }

            $data = $batch['results'][$selection?->primary_station_id] ?? null;
            if (! $data) {
                throw WeatherApiException::forSource('Ambient', 'Primary station is missing or has no usable observation.');
            }
        } catch (PollDeferred $e) {
            return ['success' => true, 'created' => false, 'observation' => $this->repository->latest(), 'error' => null, 'skipped' => true];
        } catch (WeatherApiException $e) {
            $message = $e->getMessage();
            CollectionStatus::recordFailure(CollectionStatus::SOURCE_AMBIENT, $message);
            Log::warning('Ambient collection failed.', ['reason' => $message]);

            return ['success' => false, 'created' => false, 'observation' => null, 'error' => $message];
        } catch (Throwable $e) {
            // Defensive: never leak an unexpected exception message to storage.
            CollectionStatus::recordFailure(CollectionStatus::SOURCE_AMBIENT, 'Unexpected collection error.');
            Log::error('Ambient collection error.', ['exception' => $e::class]);

            return ['success' => false, 'created' => false, 'observation' => null, 'error' => 'Unexpected collection error.'];
        }

        try {
            $result = $selection->primary_station_id === $selection->legacy_station_id ? $legacyResult : ['observation' => $this->repository->latest(), 'created' => $batch['created'][$selection->primary_station_id] ?? false];
            if (! $result || ! $result['observation']) {
                throw new \RuntimeException('No stored primary observation.');
            }
            DB::table('weather_provider_states')->where('provider', 'ambient')->update(['last_collection_at' => now()]);
        } catch (Throwable $e) {
            CollectionStatus::recordFailure(CollectionStatus::SOURCE_AMBIENT, 'Could not save observation.');
            Log::error('Failed to persist observation.', ['exception' => $e::class]);

            return ['success' => false, 'created' => false, 'observation' => null, 'error' => 'Could not save observation.'];
        }

        CollectionStatus::recordSuccess(CollectionStatus::SOURCE_AMBIENT);
        cache()->forget('display.weather.payload');
        Log::debug('Ambient collection succeeded.', [
            'observed_at' => $result['observation']->observed_at->toIso8601String(),
            'created' => $result['created'],
        ]);

        return [
            'success' => true,
            'created' => $result['created'],
            'observation' => $result['observation'],
            'error' => null,
        ];
    }
}
