<?php

namespace App\Weather\Presentation;

use App\Weather\Providers\Connections;
use App\Weather\Providers\MeasurementRegistry;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class SensorTiles
{
    public const ICONS = ['thermometer' => 'ui-thermometer', 'humidity' => 'ui-droplet', 'wind' => 'ui-wind', 'sun' => 'wi-clear-day'];

    public function bindings(): array
    {
        return Schema::hasTable('weather_sensor_tiles') ? DB::table('weather_sensor_tiles')->orderBy('created_at')->get()->all() : [];
    }

    public function definitions(): array
    {
        $result = [];
        foreach ($this->bindings() as $tile) {
            $result[$tile->id] = ['view' => 'display.tiles.sensor', 'label' => $tile->label, 'sizes' => ['standard', 'wide'], 'region' => 'metrics'];
        }

        return $result;
    }

    public function published(array $configuration): array
    {
        $enabled = array_column($configuration['tiles'], 'enabled', 'id');
        $result = [];
        foreach ($this->bindings() as $tile) {
            if ($enabled[$tile->id] ?? false) {
                $result[$tile->id] = $this->present((array) $tile);
            }
        }

        return $result;
    }

    /** Public presentation contains no station identifiers, raw packets or credential data. */
    public function present(array $tile): array
    {
        $m = DB::table('weather_measurements')->find($tile['measurement_id']);
        $station = $m ? DB::table('weather_stations')->find($m->station_id) : null;
        $definition = $m ? MeasurementRegistry::field($m->field) : null;
        $value = $definition ? MeasurementRegistry::value($m->field, $m->last_value) : null;
        $age = $m?->observed_at ? now()->timestamp - CarbonImmutable::parse($m->observed_at, 'UTC')->timestamp : null;
        $state = ! $station?->enabled || ! app(Connections::class)->enabled('ambient') ? 'disabled' : (! $definition || $value === null || $age === null ? 'unavailable' : (! $m->present ? 'missing' : ($m->quality !== 'valid' || $age < -300 ? 'invalid' : ($age > config('weather.staleness.stale_minutes', 20) * 60 ? 'stale' : ($age > config('weather.staleness.fresh_minutes', 10) * 60 ? 'delayed' : 'fresh')))));
        $convert = fn ($v) => $tile['unit'] === '°C' ? round(($v - 32) * 5 / 9, 1) : round($v, 2);
        $points = [];
        if ($definition && $station) {
            $rows = DB::table('weather_station_observations')->where('station_id', $station->id)->whereBetween('observed_at', [now()->subDay(), now()])->orderByDesc('observed_at')->limit(1440)->get()->reverse();
            // Reuse retained original-station packets only when their recorded identity matches.
            $legacy = DB::table('weather_station_settings')->where('id', 1)->value('legacy_station_id');
            if ((int) $legacy === (int) $station->id) {
                $old = DB::table('observations')->whereRaw('lower(device_mac) = ?', [strtolower($station->external_id)])->whereBetween('observed_at', [now()->subDay(), now()])->orderByDesc('observed_at')->limit(1440)->get();
                // Station-scoped rows take precedence at matching timestamps.
                $rows = $old->concat($rows)->keyBy('observed_at')->sortBy('observed_at')->take(-1440);
            }
            foreach ($rows as $row) {
                $v = MeasurementRegistry::value($m->field, (json_decode($row->raw ?? '{}', true) ?? [])[$m->field] ?? null);
                if ($v !== null) {
                    $points[] = ['t' => CarbonImmutable::parse($row->observed_at, 'UTC')->toIso8601String(), 'v' => $convert($v)];
                }
            }
        }
        $allValues = array_column($points, 'v');
        if (count($points) > 48) {
            $last = count($points) - 1;
            $points = array_map(fn ($i) => $points[(int) round($i * $last / 47)], range(0, 47));
        }

        return ['id' => $tile['id'] ?? 'preview', 'label' => $tile['label'], 'symbol' => self::ICONS[$tile['icon']], 'unit' => $tile['unit'], 'state' => $state, 'value' => in_array($state, ['fresh', 'delayed', 'stale']) ? $convert($value) : null, 'last_known' => $value === null ? null : $convert($value), 'observed_at' => $m?->observed_at ? CarbonImmutable::parse($m->observed_at, 'UTC')->toIso8601String() : null, 'history' => ['points' => $points, 'min' => $allValues ? min($allValues) : null, 'max' => $allValues ? max($allValues) : null]];
    }
}
