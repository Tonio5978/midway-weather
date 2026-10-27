<?php

namespace App\Weather\Providers;

use App\Weather\Exceptions\WeatherApiException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** One shared, persistent budget for scheduled collection, discovery and connection tests. */
final class ProviderRequests
{
    public function run(string $provider, array $credentials, callable $request): Response
    {
        // Older schemas remain readable while migrations run; clients can still be tested alone.
        $managed = Schema::hasTable('weather_provider_limits');
        $scopes = $provider === 'ambient' ? ['api:'.hash('sha256', $credentials['api_key']), 'app:'.hash('sha256', $credentials['application_key'])] : ['open_meteo'];
        $lock = Cache::lock('weather.provider.'.$provider, 30);
        if (! $lock->get()) {
            throw new PollDeferred('A provider request is already running.');
        }
        try {
            if ($managed) {
                DB::transaction(function () use ($scopes): void {
                    $now = now()->getTimestampMs();
                    foreach ($scopes as $scope) {
                        DB::table('weather_provider_limits')->insertOrIgnore(['scope' => $scope, 'next_at' => 0]);
                        if (DB::table('weather_provider_limits')->where('scope', $scope)->value('next_at') > $now) {
                            throw new PollDeferred('Provider cooldown is active. Wait before requesting again.');
                        }
                    }
                    foreach ($scopes as $scope) {
                        DB::table('weather_provider_limits')->where('scope', $scope)->update(['next_at' => $now + (str_starts_with($scope, 'app:') ? 334 : 1000)]);
                    }
                });
            }
            if ($managed) {
                DB::table('weather_provider_states')->updateOrInsert(['provider' => $provider], ['last_attempt_at' => now()]);
            }
            try {
                $response = $request();
            } catch (\Throwable) {
                if ($managed) {
                    DB::table('weather_provider_states')->where('provider', $provider)->update(['connectivity' => 'unreachable', 'last_error' => 'Network request failed.']);
                }
                throw WeatherApiException::forSource($provider === 'ambient' ? 'Ambient' : 'Open-Meteo', 'Network request failed.');
            }
            if ($managed) {
                $status = $response->status();
                $changes = ['connectivity' => $response->successful() ? 'connected' : 'rejected', 'http_status' => $status, 'last_error' => $response->successful() ? null : 'Provider returned HTTP '.$status.'.'];
                if ($response->successful()) {
                    $changes['last_connected_at'] = now();
                }
                DB::table('weather_provider_states')->where('provider', $provider)->update($changes);
                if (! $response->successful()) {
                    $after = $response->header('Retry-After');
                    $seconds = is_numeric($after) ? (int) $after : (strtotime($after ?: '') ?: time() + 60) - time();
                    $seconds = max(1, min(3600, $seconds ?: 60));
                    foreach ($scopes as $scope) {
                        DB::table('weather_provider_limits')->where('scope', $scope)->update(['next_at' => now()->getTimestampMs() + $seconds * 1000]);
                    }
                }
            }

            return $response;
        } finally {
            $lock->release();
        }
    }
}
