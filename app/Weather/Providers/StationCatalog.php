<?php

namespace App\Weather\Providers;

use App\Models\CollectionStatus;
use App\Models\Observation;
use App\Models\StationObservation;
use App\Weather\Data\ObservationData;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class StationCatalog
{
    /** A station transport time must never be substituted for its observation time. */
    public static function timestamp(array $data): ?CarbonImmutable
    {
        try {
            $time = is_numeric($data['dateutc'] ?? null) ? CarbonImmutable::createFromTimestampMs((int) $data['dateutc'], 'UTC') : (is_string($data['date'] ?? null) ? CarbonImmutable::parse($data['date'])->utc() : null);

            return $time && $time->year >= 2000 && $time->lessThanOrEqualTo(now()->addMinutes(5)) ? $time : null;
        } catch (\Throwable) {
            return null;
        }
    }

    public function ingest(array $devices, bool $collect, ?int $revision = null): array
    {
        return DB::transaction(function () use ($devices, $collect, $revision): array {
            if ($revision !== null && (app(Connections::class)->row('ambient')?->revision ?? 0) !== $revision) {
                throw new PollDeferred('Provider configuration changed. Retry discovery or collection.');
            }
            $seen = [];
            $results = [];
            $created = [];
            $newStations = 0;
            foreach (array_slice($devices, 0, 64) as $device) {
                $mac = $device['macAddress'] ?? null;
                if (! is_string($mac) || ! preg_match('/^[a-f0-9]{2}(:[a-f0-9]{2}){5}$/i', $mac)) {
                    continue;
                }
                $mac = strtolower($mac);
                $station = DB::table('weather_stations')->where('provider', 'ambient')->where('external_id', $mac)->first();
                if (! $station) {
                    $label = is_string($device['info']['name'] ?? null) ? mb_substr($device['info']['name'], 0, 100) : 'Ambient station';
                    $newStations++;
                    $id = DB::table('weather_stations')->insertGetId(['provider' => 'ambient', 'external_id' => $mac, 'label' => $label, 'enabled' => false, 'last_seen_at' => now()]);
                    $station = DB::table('weather_stations')->find($id);
                }
                $seen[] = $station->id;
                DB::table('weather_stations')->where('id', $station->id)->update(['last_seen_at' => now()]);
                $data = is_array($device['lastData'] ?? null) ? $device['lastData'] : [];
                $this->measurements($station->id, $data);
            }
            $missing = DB::table('weather_stations')->whereNotIn('id', $seen)->pluck('id');
            DB::table('weather_measurements')->whereIn('station_id', $missing)->update(['present' => false]);
            $settings = app(PrimaryWeatherStore::class)->settings();
            if (! $settings) {
                // Freeze the legacy identity using the configured MAC, then existing history.
                $mac = trim((string) config('weather.ambient.device_mac'));
                if ($mac === '') {
                    $mac = (string) Observation::query()->latestFirst()->value('device_mac');
                }
                $legacy = $mac !== '' ? DB::table('weather_stations')->where('external_id', strtolower($mac))->first() : ($seen ? DB::table('weather_stations')->find($seen[0]) : null);
                if ($legacy) {
                    DB::table('weather_station_settings')->insertOrIgnore(['id' => 1, 'revision' => 0, 'primary_station_id' => $legacy->id, 'legacy_station_id' => $legacy->id]);
                    DB::table('weather_stations')->where('id', $legacy->id)->update(['enabled' => true]);
                }
            }
            if ($settings && $newStations) {
                DB::table('weather_station_settings')->where('id', 1)->increment('revision');
            }
            if (! $collect) {
                return ['stations' => count($seen), 'results' => []];
            }
            $byMac = [];
            foreach ($devices as $device) {
                if (is_string($device['macAddress'] ?? null)) {
                    $byMac[strtolower($device['macAddress'])] = $device;
                }
            }
            foreach (DB::table('weather_stations')->where('enabled', true)->get() as $station) {
                $data = $byMac[$station->external_id]['lastData'] ?? null;
                $time = is_array($data) ? self::timestamp($data) : null;
                if (! $time) {
                    DB::table('weather_measurements')->where('station_id', $station->id)->update(['present' => false]);
                    CollectionStatus::recordFailure('station:'.$station->id, is_array($data) ? 'No valid station observation timestamp.' : 'Station missing from provider response.');
                    $results[$station->id] = null;

                    continue;
                }
                $reading = ObservationData::fromAmbient($data, $byMac[$station->external_id]['macAddress']);
                $attributes = $reading->toAttributes();
                $attributes['station_id'] = $station->id;
                $attributes['observed_at'] = $time->startOfMinute();
                $model = new StationObservation;
                $stored = $model->newQuery()->updateOrCreate(['station_id' => $station->id, 'observed_at' => $attributes['observed_at']], $attributes);
                $created[$station->id] = $stored->wasRecentlyCreated;
                DB::table('weather_stations')->where('id', $station->id)->update(['last_collection_at' => now()]);
                CollectionStatus::recordSuccess('station:'.$station->id);
                $results[$station->id] = $reading;
            }

            return ['stations' => count($seen), 'results' => $results, 'created' => $created];
        });
    }

    private function measurements(int $stationId, array $data): void
    {
        DB::table('weather_measurements')->where('station_id', $stationId)->update(['present' => false]);
        $time = self::timestamp($data);
        foreach (array_slice($data, 0, 256, true) as $field => $raw) {
            if (! is_string($field) || ! preg_match('/^[a-zA-Z0-9_]{1,80}$/', $field) || in_array($field, ['date', 'dateutc', 'tz', 'macAddress', 'apiKey', 'applicationKey'])) {
                continue;
            }
            $definition = MeasurementRegistry::field($field);
            $value = MeasurementRegistry::value($field, $raw);
            $key = ['station_id' => $stationId, 'field' => $field];
            $old = DB::table('weather_measurements')->where($key)->first();
            $updates = ['label' => $definition['label'] ?? $field, 'quantity' => $definition['quantity'] ?? null, 'unit' => $definition['unit'] ?? null, 'present' => true, 'last_seen_at' => now(), 'quality' => $definition ? ($value !== null && $time ? 'valid' : 'invalid') : 'unknown'];
            // Out-of-order responses and missing/invalid values never replace the last valid sample.
            if ($value !== null && $time && (! $old?->observed_at || $time->greaterThanOrEqualTo(CarbonImmutable::parse($old->observed_at, 'UTC')))) {
                $updates['last_value'] = $value;
                $updates['observed_at'] = $time;
            }
            DB::table('weather_measurements')->updateOrInsert($key, $updates);
        }
    }
}
