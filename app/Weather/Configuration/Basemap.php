<?php

namespace App\Weather\Configuration;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Map-only configuration. Server weather-provider credentials never enter this path. */
final class Basemap
{
    private ?array $draftCredential = null;

    public static function catalog(): array
    {
        return [
            'openstreetmap' => ['name' => 'OpenStreetMap', 'style' => 'Standard · darkened for Weather', 'url' => 'https://operations.osmfoundation.org/policies/tiles/'],
            'stadia' => ['name' => 'Stadia', 'style' => 'Alidade Smooth Dark', 'url' => 'https://docs.stadiamaps.com/authentication/'],
            'maptiler' => ['name' => 'MapTiler', 'style' => 'Streets v4', 'url' => 'https://docs.maptiler.com/guides/developer/credentials/api-key/'],
            'mapbox' => ['name' => 'Mapbox', 'style' => 'Dark v11', 'url' => 'https://docs.mapbox.com/help/dive-deeper/how-to-use-mapbox-securely/'],
        ];
    }

    public function environmentProvider(): string
    {
        $provider = config('weather.basemap.provider', 'openstreetmap');

        return isset(self::catalog()[$provider]) ? $provider : 'openstreetmap';
    }

    public function useDraftCredential(?array $credential): void
    {
        $this->draftCredential = $credential;
    }

    private function environmentKey(string $provider): string
    {
        return trim((string) config('weather.basemap.'.match ($provider) {
            'stadia' => 'stadia_api_key',
            'maptiler' => 'maptiler_api_key',
            'mapbox' => 'mapbox_public_token',
            default => 'unused',
        }, ''));
    }

    public function key(string $provider): string
    {
        if (($this->draftCredential['provider'] ?? null) === $provider) {
            if ($this->draftCredential['clear'] ?? false) {
                return $this->environmentKey($provider);
            }
            if (($this->draftCredential['key'] ?? '') !== '') {
                return $this->draftCredential['key'];
            }
        }
        $row = Schema::hasTable('weather_map_credentials') ? DB::table('weather_map_credentials')->where('provider', $provider)->first() : null;
        if ($row) {
            try {
                return Crypt::decryptString($row->credential);
            } catch (DecryptException) {
                // A changed APP_KEY requires replacement; never expose ciphertext/errors.
                return '';
            }
        }

        return $this->environmentKey($provider);
    }

    public function credentialStatus(): array
    {
        $result = [];
        foreach (['stadia', 'maptiler', 'mapbox'] as $provider) {
            $stored = Schema::hasTable('weather_map_credentials') && DB::table('weather_map_credentials')->where('provider', $provider)->exists();
            $result[$provider] = ['configured' => $this->key($provider) !== '', 'source' => $stored ? 'admin' : ($this->environmentKey($provider) !== '' ? 'environment' : 'none')];
        }

        return $result;
    }

    /** Called inside the settings revision transaction, so a conflict cannot replace a key. */
    public function saveCredential(?array $credential): void
    {
        if (! $credential) {
            return;
        }
        if ($credential['clear'] ?? false) {
            DB::table('weather_map_credentials')->where('provider', $credential['provider'])->delete();
        } elseif (($credential['key'] ?? '') !== '') {
            DB::table('weather_map_credentials')->updateOrInsert(['provider' => $credential['provider']], ['credential' => Crypt::encryptString($credential['key']), 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function resolve(array $selection): array
    {
        $environment = $selection['provider'] === 'environment';
        $provider = $environment ? $this->environmentProvider() : $selection['provider'];
        // Environment mode always uses environment keys, ignoring Admin overrides.
        $key = $environment ? $this->environmentKey($provider) : $this->key($provider);
        $osm = ['label' => '© OpenStreetMap contributors', 'url' => 'https://www.openstreetmap.org/copyright'];
        $result = ['provider' => $provider, 'name' => self::catalog()[$provider]['name'], 'max_zoom' => 19, 'tile_size' => 256, 'zoom_offset' => 0, 'attribution' => [$osm], 'logo' => false];
        if ($provider === 'stadia') {
            $key = ! $environment && $selection['authentication'] === 'domain' ? '' : $key;

            return array_replace($result, ['tile_url' => 'https://tiles.stadiamaps.com/tiles/alidade_smooth_dark/{z}/{x}/{y}{r}.png'.($key !== '' ? '?api_key='.rawurlencode($key) : ''), 'max_zoom' => 20, 'attribution' => [['label' => '© Stadia Maps', 'url' => 'https://stadiamaps.com/attribution/'], ['label' => '© OpenMapTiles', 'url' => 'https://openmaptiles.org/'], $osm]]);
        }
        if ($provider === 'maptiler') {
            return array_replace($result, ['tile_url' => $key === '' ? null : 'https://api.maptiler.com/maps/streets-v4/{z}/{x}/{y}.png?key='.rawurlencode($key), 'max_zoom' => 20, 'tile_size' => 512, 'zoom_offset' => -1, 'attribution' => [['label' => '© MapTiler', 'url' => 'https://www.maptiler.com/copyright/'], $osm]]);
        }
        if ($provider === 'mapbox') {
            // Only browser public tokens may ever be included in display HTML/JSON.
            $key = str_starts_with($key, 'pk.') ? $key : '';

            return array_replace($result, ['tile_url' => $key === '' ? null : 'https://api.mapbox.com/styles/v1/mapbox/dark-v11/tiles/512/{z}/{x}/{y}?access_token='.rawurlencode($key), 'max_zoom' => 22, 'tile_size' => 512, 'zoom_offset' => -1, 'logo' => true, 'attribution' => [['label' => '© Mapbox', 'url' => 'https://www.mapbox.com/about/maps/'], $osm, ['label' => 'Improve this map', 'url' => 'https://apps.mapbox.com/feedback/']]]);
        }

        return [...$result, 'tile_url' => 'https://tile.openstreetmap.org/{z}/{x}/{y}.png'];
    }
}
