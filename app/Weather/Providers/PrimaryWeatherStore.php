<?php

namespace App\Weather\Providers;

use App\Models\DailySummary;
use App\Models\Observation;
use App\Models\StationObservation;
use App\Models\StationSummary;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Read compatibility: the original station retains the original tables and all their history. */
final class PrimaryWeatherStore
{
    public function settings(): ?object
    {
        return Schema::hasTable('weather_station_settings') ? DB::table('weather_station_settings')->find(1) : null;
    }

    public function primaryId(): ?int
    {
        return $this->settings()?->primary_station_id;
    }

    public function observations(?int $stationId = null): Builder
    {
        $id = $stationId ?? $this->primaryId();
        $model = new Observation;
        if ($id !== null && $id !== $this->settings()?->legacy_station_id) {
            $model = new StationObservation;

            return $model->newQuery()->where('station_id', $id);
        }

        return $model->newQuery();
    }

    public function summaries(?int $stationId = null): Builder
    {
        $id = $stationId ?? $this->primaryId();
        $model = new DailySummary;
        if ($id !== null && $id !== $this->settings()?->legacy_station_id) {
            $model = new StationSummary;

            return $model->newQuery()->where('station_id', $id);
        }

        return $model->newQuery();
    }

    public function statusSource(string $source): string
    {
        $settings = $this->settings();
        if ($source !== 'ambient' || ! $settings?->primary_station_id) {
            return $source;
        }
        $stationSource = 'station:'.$settings->primary_station_id;

        return $settings->primary_station_id !== $settings->legacy_station_id || DB::table('collection_statuses')->where('source', $stationSource)->exists() ? $stationSource : $source;
    }
}
