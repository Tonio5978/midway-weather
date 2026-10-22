<?php

declare(strict_types=1);

namespace App\Weather\Services;

use App\Models\CollectionStatus;
use App\Models\Observation;
use App\Weather\Clients\AmbientWeatherClient;
use App\Weather\Exceptions\WeatherApiException;
use Carbon\CarbonImmutable;
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
            $data = $this->client->latestObservation();
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
            $result = $this->repository->store($data);
        } catch (Throwable $e) {
            CollectionStatus::recordFailure(CollectionStatus::SOURCE_AMBIENT, 'Could not save observation.');
            Log::error('Failed to persist observation.', ['exception' => $e::class]);

            return ['success' => false, 'created' => false, 'observation' => null, 'error' => 'Could not save observation.'];
        }

        // Keep today's summary current for the "rain today vs. recent" module.
        try {
            $timezone = (string) config('weather.timezone', 'America/New_York');
            $this->summaries->rebuildForDate(CarbonImmutable::now($timezone)->startOfDay());
        } catch (Throwable $e) {
            // A summary failure should not fail the collection itself.
            Log::warning('Failed to rebuild daily summary during collection.', ['exception' => $e::class]);
        }

        CollectionStatus::recordSuccess(CollectionStatus::SOURCE_AMBIENT);
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
