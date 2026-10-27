<?php

namespace App\Http\Controllers;

use App\Weather\Configuration\WeatherSettings;
use App\Weather\Presentation\SensorTiles;
use App\Weather\Providers\MeasurementRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class WeatherSensorTilesController
{
    public function index(WeatherSettings $settings, SensorTiles $tiles)
    {
        return response()->json(['revision' => $settings->read()['revision'], 'bindings' => $tiles->bindings(), 'icons' => array_keys(SensorTiles::ICONS)]);
    }

    public function save(Request $r, WeatherSettings $settings, SensorTiles $tiles)
    {
        $v = $r->validate(['id' => 'nullable|string|max:60', 'revision' => 'required|integer|min:0', 'measurement_id' => 'required|integer', 'label' => ['required', 'string', 'max:60', 'not_regex:/[\x00-\x1F\x7F]/u'], 'icon' => ['required', Rule::in(array_keys(SensorTiles::ICONS))], 'unit' => 'required|string|max:20', 'size' => ['required', Rule::in(['standard', 'wide'])], 'publish' => 'sometimes|boolean', 'preview' => 'sometimes|boolean']);
        $m = DB::table('weather_measurements')->find($v['measurement_id']);
        $definition = $m ? MeasurementRegistry::field($m->field) : null;
        $station = $m ? DB::table('weather_stations')->find($m->station_id) : null;
        if (! $definition || ! $station?->enabled || ! in_array($v['unit'], $definition['quantity'] === 'temperature' ? ['°F', '°C'] : [$definition['unit']], true)) {
            throw ValidationException::withMessages(['measurement_id' => 'Select a verified measurement from an enabled station and its supported unit.']);
        }
        if (($v['id'] ?? '') === 'pool' && $definition['quantity'] !== 'temperature') {
            throw ValidationException::withMessages(['id' => 'The existing Pool tile accepts temperature measurements.']);
        }
        if ($v['preview'] ?? false) {
            return response()->json(['preview' => $tiles->present($v)]);
        }
        if (! ($v['publish'] ?? false)) {
            throw ValidationException::withMessages(['publish' => 'Explicitly approve publication on the public dashboard.']);
        }
        DB::transaction(function () use ($v, $settings) {
            $id = $v['id'] ?? null;
            if ($id && $id !== 'pool' && ! DB::table('weather_sensor_tiles')->where('id', $id)->exists()) {
                throw ValidationException::withMessages(['id' => 'Unknown sensor tile. Reload before editing.']);
            }
            if (! $id && DB::table('weather_sensor_tiles')->count() >= 12) {
                throw ValidationException::withMessages(['id' => 'At most 12 sensor tiles can be configured. Reuse an existing tile.']);
            }
            $id ??= 'sensor-'.Str::uuid();
            $bundle = $settings->read();
            DB::table('weather_sensor_tiles')->updateOrInsert(['id' => $id], ['measurement_id' => $v['measurement_id'], 'label' => $v['label'], 'icon' => $v['icon'], 'unit' => $v['unit'], 'updated_at' => now(), 'created_at' => now()]);
            $values = $bundle['values'];
            $found = false;
            foreach ($values['tiles'] as &$tile) {
                if ($tile['id'] === $id) {
                    $tile['enabled'] = true;
                    $tile['size'] = $v['size'];
                    $found = true;
                }
            }
            unset($tile);
            if (! $found) {
                $values['tiles'][] = ['id' => $id, 'enabled' => true, 'size' => $v['size']];
            }
            $settings->save($values, (int) $v['revision']);
        });

        return $this->index($settings, $tiles);
    }
}
