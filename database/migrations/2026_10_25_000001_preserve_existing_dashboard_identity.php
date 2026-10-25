<?php

use App\Weather\Configuration\WeatherSettings;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Snapshot the old UI only for an existing saved installation or one
        // already holding weather data. Fresh installs never inherit the suffix.
        DB::transaction(function () {
            $row = DB::table('weather_settings')->find(1);
            if (! $row && ! DB::table('observations')->exists() && ! DB::table('forecast_days')->exists()) {
                return;
            }
            $values = $row ? json_decode($row->configuration, true, 512, JSON_THROW_ON_ERROR) : app(WeatherSettings::class)->defaults();
            if ($row && array_key_exists('identity', $values)) {
                return;
            }
            $values['identity'] = [
                'title' => (string) config('weather.station_name', 'Midway Weather'),
                'subtitle' => ($values['map']['location'] ?? config('weather.location_name')).' · Turner Home',
                'icon' => 'dynamic',
            ];
            if (! $row) {
                DB::table('weather_settings')->insertOrIgnore([
                    'id' => 1, 'revision' => 1, 'configuration' => json_encode($values, JSON_THROW_ON_ERROR),
                    'created_at' => now(), 'updated_at' => now(),
                ]);

                return;
            }
            DB::table('weather_settings')->where('id', 1)->where('revision', $row->revision)->update([
                'configuration' => json_encode($values, JSON_THROW_ON_ERROR),
                'revision' => $row->revision + 1,
                'updated_at' => now(),
            ]);
        });
    }

    public function down(): void
    {
        // Personalized settings remain user-owned during code rollback.
    }
};
