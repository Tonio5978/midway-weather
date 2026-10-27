<?php

namespace App\Http\Controllers;

use App\Weather\Clients\AmbientWeatherClient;
use App\Weather\Clients\OpenMeteoClient;
use App\Weather\Exceptions\WeatherApiException;
use App\Weather\Providers\Connections;
use App\Weather\Providers\PollDeferred;
use App\Weather\Providers\PrimaryWeatherStore;
use App\Weather\Providers\StationCatalog;
use App\Weather\Services\StationHealthService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class WeatherProvidersController
{
    public function index(Connections $connections)
    {
        $providers = [];
        foreach (Connections::PROVIDERS as $provider => $name) {
            $state = DB::table('weather_provider_states')->where('provider', $provider)->first();
            $providers[] = [...$connections->describe($provider), 'transport' => $state, 'collection' => app(StationHealthService::class)->statusSummary($provider === 'ambient' ? 'ambient' : 'forecast')];
        }
        $settings = app(PrimaryWeatherStore::class)->settings();
        $stations = [];
        foreach (DB::table('weather_stations')->orderBy('id')->get() as $station) {
            $channels = DB::table('weather_measurements')->where('station_id', $station->id)->orderBy('id')->get()->map(function ($m) use ($station) {
                $age = $m->observed_at ? max(0, now()->timestamp - CarbonImmutable::parse($m->observed_at, 'UTC')->timestamp) : null;
                $state = $m->quantity === null ? 'diagnostic' : (! $m->present ? 'missing' : ($m->quality !== 'valid' ? 'invalid' : ($age === null ? 'unavailable' : ($age > config('weather.staleness.stale_minutes', 20) * 60 ? 'stale' : ($age > config('weather.staleness.fresh_minutes', 10) * 60 ? 'delayed' : 'fresh')))));

                return [...(array) $m, 'state' => $state, 'publishable' => $m->quantity !== null && (bool) $station->enabled];
            })->all();
            $stations[] = [...(array) $station, 'primary' => $settings?->primary_station_id === $station->id, 'legacy' => $settings?->legacy_station_id === $station->id, 'measurements' => $channels, 'collection' => app(StationHealthService::class)->statusSummary('station:'.$station->id)];
        }

        return response()->json(['providers' => $providers, 'stations' => $stations, 'station_revision' => $settings?->revision ?? 0, 'primary_station_id' => $settings?->primary_station_id]);
    }

    public function save(Request $r, string $provider, Connections $connections)
    {
        $values = $r->validate(['revision' => 'required|integer|min:0', 'mode' => ['required', Rule::in(['environment', 'admin'])], 'enabled' => 'required|boolean', 'api_key' => ['nullable', 'string', 'max:2048', 'not_regex:/[\x00-\x20\x7f]/'], 'application_key' => ['nullable', 'string', 'max:2048', 'not_regex:/[\x00-\x20\x7f]/'], 'forecast_days' => 'sometimes|integer|between:1,16']);
        if ($provider === 'open_meteo' && (($values['api_key'] ?? '') !== '' || ($values['application_key'] ?? '') !== '')) {
            throw ValidationException::withMessages(['credentials' => 'Open-Meteo uses the existing keyless forecast integration.']);
        }
        $values['revision'] = (int) $values['revision'];
        $values['forecast_days'] ??= $connections->forecastDays();
        $connections->save($provider, $values);

        return response()->json($connections->describe($provider));
    }

    public function probe(string $provider, Connections $connections)
    {
        try {
            if ($provider === 'ambient') {
                $inventory = app(AmbientWeatherClient::class)->inventory();
                app(StationCatalog::class)->ingest($inventory['devices'], false, $inventory['revision']);
                $message = 'Connection succeeded. Stations and measurement channels discovered; enabled stations are collected on the normal schedule.';
            } else {
                $forecast = app(OpenMeteoClient::class)->dailyForecast();
                $message = 'Connection succeeded: '.count($forecast->days).' forecast days received. Stored forecast data is unchanged.';
            }

            return response()->json(['message' => $message]);
        } catch (PollDeferred $e) {
            return response()->json(['message' => $e->getMessage()], 429);
        } catch (WeatherApiException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Throwable) {
            return response()->json(['message' => 'Provider data could not be processed. Last good readings are preserved.'], 422);
        }
    }

    public function stations(Request $r)
    {
        $values = $r->validate(['revision' => 'required|integer|min:0', 'primary_station_id' => 'required|integer|exists:weather_stations,id', 'stations' => 'required|array|min:1|max:64', 'stations.*' => 'required|array:id,enabled,label', 'stations.*.id' => 'required|integer|distinct|exists:weather_stations,id', 'stations.*.enabled' => 'required|boolean', 'stations.*.label' => ['required', 'string', 'max:100', 'not_regex:/[\x00-\x1f\x7f]/']]);
        $enabled = array_filter($values['stations'], fn ($s) => $s['enabled']);
        if (count($enabled) > 8 || ! in_array($values['primary_station_id'], array_column($enabled, 'id'), true)) {
            throw ValidationException::withMessages(['stations' => 'Enable the primary station and no more than eight stations.']);
        }
        DB::transaction(function () use ($values) {
            $ids = array_map('intval', array_column($values['stations'], 'id'));
            sort($ids);
            $current = DB::table('weather_stations')->orderBy('id')->pluck('id')->all();
            if ($ids !== $current) {
                throw ValidationException::withMessages(['stations' => 'Discovery changed the station list. Reload all stations before saving.']);
            }
            DB::table('weather_station_settings')->insertOrIgnore(['id' => 1, 'revision' => 0, 'primary_station_id' => null, 'legacy_station_id' => null]);
            if (! DB::table('weather_station_settings')->where('id', 1)->where('revision', $values['revision'])->update(['primary_station_id' => $values['primary_station_id'], 'revision' => $values['revision'] + 1])) {
                throw ValidationException::withMessages(['revision' => 'Station settings changed. Reload before saving.']);
            }
            foreach ($values['stations'] as $station) {
                DB::table('weather_stations')->where('id', $station['id'])->update(['enabled' => $station['enabled'], 'label' => $station['label']]);
            }
        });
        foreach (['display.weather.payload', 'weather.condition-summary.current', 'weather.condition-summary.history'] as $key) {
            cache()->forget($key);
        }

        return response()->json(['message' => 'Station settings saved. The primary station supplies the existing dashboard and weather APIs.']);
    }
}
