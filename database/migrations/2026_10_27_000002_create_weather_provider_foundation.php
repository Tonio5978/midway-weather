<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('weather_provider_connections', function (Blueprint $t): void {
            $t->string('provider')->primary();
            $t->unsignedInteger('revision')->default(0);
            $t->string('mode')->default('environment');
            $t->boolean('enabled')->default(true);
            $t->text('credentials')->nullable();
            $t->json('options')->nullable();
            $t->timestamps();
        });
        Schema::create('weather_provider_states', function (Blueprint $t): void {
            $t->string('provider')->primary();
            $t->timestamp('last_attempt_at')->nullable();
            $t->timestamp('last_connected_at')->nullable();
            $t->timestamp('last_collection_at')->nullable();
            $t->string('connectivity')->default('not_tested');
            $t->string('last_error')->nullable();
            $t->unsignedSmallInteger('http_status')->nullable();
        });
        Schema::create('weather_provider_limits', function (Blueprint $t): void {
            $t->string('scope')->primary();
            $t->bigInteger('next_at')->default(0);
        });
        Schema::create('weather_stations', function (Blueprint $t): void {
            $t->id();
            $t->string('provider');
            $t->string('external_id');
            $t->string('label');
            $t->boolean('enabled')->default(false);
            $t->timestamp('last_seen_at')->nullable();
            $t->timestamp('last_collection_at')->nullable();
            $t->unique(['provider', 'external_id']);
        });
        Schema::create('weather_station_settings', function (Blueprint $t): void {
            $t->unsignedTinyInteger('id')->primary();
            $t->unsignedInteger('revision')->default(0);
            $t->foreignId('primary_station_id')->nullable()->constrained('weather_stations');
            $t->foreignId('legacy_station_id')->nullable()->constrained('weather_stations');
        });
        Schema::create('weather_measurements', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('station_id')->constrained('weather_stations');
            $t->string('field');
            $t->string('label');
            $t->string('quantity')->nullable();
            $t->string('unit')->nullable();
            $t->double('last_value')->nullable();
            $t->timestamp('observed_at')->nullable();
            $t->timestamp('last_seen_at')->nullable();
            $t->boolean('present')->default(false);
            $t->string('quality')->default('unknown');
            $t->unique(['station_id', 'field']);
        });
        // The legacy tables stay byte-for-byte compatible with the prior release.
        Schema::create('weather_station_observations', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('station_id')->constrained('weather_stations');
            $t->timestamp('observed_at');
            foreach (['temperature', 'feels_like', 'dew_point', 'wind_speed', 'wind_gust', 'wind_speed_avg_2m', 'wind_speed_avg_10m', 'max_daily_gust', 'pressure_relative', 'pressure_absolute', 'rain_hourly', 'rain_daily', 'rain_24h', 'rain_weekly', 'rain_monthly', 'rain_event', 'uv_index', 'solar_radiation', 'indoor_temperature'] as $column) {
                $t->float($column)->nullable();
            }
            foreach (['humidity', 'wind_direction', 'wind_direction_avg_2m', 'wind_direction_avg_10m', 'indoor_humidity'] as $column) {
                $t->integer($column)->nullable();
            }
            $t->boolean('battery_outdoor_ok')->nullable();
            $t->boolean('battery_indoor_ok')->nullable();
            $t->string('device_mac')->nullable();
            $t->string('station_tz')->nullable();
            $t->json('raw')->nullable();
            $t->timestamps();
            $t->unique(['station_id', 'observed_at']);
        });
        Schema::create('weather_station_summaries', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('station_id')->constrained('weather_stations');
            $t->date('date');
            foreach (['temperature_high', 'temperature_low', 'max_wind_gust', 'total_rain', 'max_uv', 'max_solar_radiation', 'min_pressure', 'max_pressure'] as $column) {
                $t->float($column)->nullable();
            }
            $t->unsignedInteger('observation_count');
            $t->timestamps();
            $t->unique(['station_id', 'date']);
        });
    }

    public function down(): void
    {
        foreach (['weather_station_summaries', 'weather_station_observations', 'weather_measurements', 'weather_station_settings', 'weather_stations', 'weather_provider_limits', 'weather_provider_states', 'weather_provider_connections'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
