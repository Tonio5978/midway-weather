<?php

namespace App\Weather\Providers;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

final class Connections
{
    public const PROVIDERS = ['ambient' => 'Ambient Weather', 'open_meteo' => 'Open-Meteo'];

    public function row(string $provider): ?object
    {
        return Schema::hasTable('weather_provider_connections') ? DB::table('weather_provider_connections')->where('provider', $provider)->first() : null;
    }

    public function credentials(): array
    {
        $row = $this->row('ambient');
        if ($row?->mode === 'admin') {
            try {
                return $row->credentials ? json_decode(Crypt::decryptString($row->credentials), true, flags: JSON_THROW_ON_ERROR) : [];
            } catch (DecryptException) {
                return []; // Fail closed; never silently select a different account.
            }
        }

        return ['api_key' => (string) config('weather.ambient.api_key'), 'application_key' => (string) config('weather.ambient.application_key')];
    }

    public function enabled(string $provider): bool
    {
        return (bool) ($this->row($provider)?->enabled ?? true);
    }

    public function forecastDays(): int
    {
        $options = json_decode($this->row('open_meteo')?->options ?? '{}', true);

        return (int) ($options['forecast_days'] ?? config('weather.open_meteo.forecast_days', 7));
    }

    public function describe(string $provider): array
    {
        $row = $this->row($provider);
        $keys = $provider === 'ambient' ? $this->credentials() : [];

        return ['id' => $provider, 'name' => self::PROVIDERS[$provider], 'revision' => $row?->revision ?? 0, 'mode' => $row?->mode ?? 'environment', 'enabled' => $this->enabled($provider), 'configured' => $provider !== 'ambient' || (! empty($keys['api_key']) && ! empty($keys['application_key'])), 'forecast_days' => $provider === 'open_meteo' ? $this->forecastDays() : null];
    }

    public function save(string $provider, array $values): void
    {
        DB::transaction(function () use ($provider, $values): void {
            DB::table('weather_provider_connections')->insertOrIgnore(['provider' => $provider, 'revision' => 0, 'mode' => 'environment', 'enabled' => true, 'created_at' => now(), 'updated_at' => now()]);
            $row = $this->row($provider);
            if ($row->revision !== $values['revision']) {
                throw ValidationException::withMessages(['revision' => 'Provider settings changed. Reload before saving.']);
            }
            $encrypted = $row->credentials;
            if ($provider === 'ambient' && $values['mode'] === 'admin') {
                $keys = $row->mode === 'admin' ? $this->credentials() : [];
                foreach (['api_key', 'application_key'] as $key) {
                    if (($values[$key] ?? '') !== '') {
                        $keys[$key] = $values[$key];
                    }
                }
                if (empty($keys['api_key']) || empty($keys['application_key'])) {
                    throw ValidationException::withMessages(['credentials' => 'Enter both Ambient keys when enabling an Admin override. Blank fields keep existing Admin keys.']);
                }
                $encrypted = Crypt::encryptString(json_encode($keys, JSON_THROW_ON_ERROR));
            }
            // Restoring environment management explicitly removes the override.
            if ($values['mode'] === 'environment') {
                $encrypted = null;
            }
            $updated = DB::table('weather_provider_connections')->where('provider', $provider)->where('revision', $values['revision'])->update(['revision' => $values['revision'] + 1, 'mode' => $values['mode'], 'enabled' => $values['enabled'], 'credentials' => $encrypted, 'options' => json_encode($provider === 'open_meteo' && $values['mode'] === 'admin' ? ['forecast_days' => $values['forecast_days']] : []), 'updated_at' => now()]);
            DB::table('weather_provider_states')->updateOrInsert(['provider' => $provider], ['last_attempt_at' => null, 'last_connected_at' => null, 'last_collection_at' => null, 'connectivity' => 'not_tested', 'last_error' => null, 'http_status' => null]);
            if (! $updated) {
                throw ValidationException::withMessages(['revision' => 'Provider settings changed. Reload before saving.']);
            }
        });
        cache()->forget('display.weather.payload');
    }
}
