<?php

declare(strict_types=1);

namespace App\Weather\Services;

use App\Models\CollectionStatus;
use App\Models\ForecastDay;
use App\Weather\Clients\OpenMeteoClient;
use App\Weather\Exceptions\WeatherApiException;
use App\Weather\Providers\PollDeferred;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Orchestrates a forecast refresh: fetch → normalize → upsert per day →
 * record health. On failure the previously stored forecast is left untouched
 * so the display keeps showing the last good data.
 */
class ForecastService
{
    public function __construct(private readonly OpenMeteoClient $client) {}

    /**
     * Refresh the stored forecast.
     *
     * @return array{success: bool, days: int, error: string|null}
     */
    public function refresh(): array
    {
        try {
            $forecast = $this->client->dailyForecast();
        } catch (PollDeferred $e) {
            return ['success' => true, 'days' => 0, 'error' => null, 'skipped' => true];
        } catch (WeatherApiException $e) {
            $message = $e->getMessage();
            CollectionStatus::recordFailure(CollectionStatus::SOURCE_FORECAST, $message);
            Log::warning('Forecast refresh failed.', ['reason' => $message]);

            return ['success' => false, 'days' => 0, 'error' => $message];
        } catch (Throwable $e) {
            CollectionStatus::recordFailure(CollectionStatus::SOURCE_FORECAST, 'Unexpected forecast error.');
            Log::error('Forecast refresh error.', ['exception' => $e::class]);

            return ['success' => false, 'days' => 0, 'error' => 'Unexpected forecast error.'];
        }

        try {
            $keptDates = [];

            foreach ($forecast->days as $day) {
                ForecastDay::query()->updateOrCreate(
                    ['date' => $day->date->toDateString()],
                    $day->toAttributes(),
                );
                $keptDates[] = $day->date->toDateString();
            }

            // Drop stale rows from a previous, longer forecast horizon and any
            // days now in the past, so the table mirrors the current forecast.
            $today = CarbonImmutable::now((string) config('weather.timezone', 'America/New_York'))->toDateString();
            ForecastDay::query()
                ->where(function ($query) use ($keptDates): void {
                    $query->whereNotIn('date', $keptDates);
                })
                ->whereDate('date', '<', $today)
                ->delete();
        } catch (Throwable $e) {
            CollectionStatus::recordFailure(CollectionStatus::SOURCE_FORECAST, 'Could not save forecast.');
            Log::error('Failed to persist forecast.', ['exception' => $e::class]);

            return ['success' => false, 'days' => 0, 'error' => 'Could not save forecast.'];
        }

        CollectionStatus::recordSuccess(CollectionStatus::SOURCE_FORECAST);
        DB::table('weather_provider_states')->where('provider', 'open_meteo')->update(['last_collection_at' => now()]);
        cache()->forget('display.weather.payload');
        Log::debug('Forecast refresh succeeded.', ['days' => count($forecast->days)]);

        return ['success' => true, 'days' => count($forecast->days), 'error' => null];
    }
}
