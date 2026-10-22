<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Detailed station observations sourced from the Ambient Weather `lastData`
 * payload. Most numeric columns are nullable because not every station reports
 * every parameter. The full normalized source payload is retained as JSON so
 * additional fields can be surfaced later without re-collecting.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('observations', function (Blueprint $table): void {
            $table->id();

            // Ambient's `dateutc` (ms since epoch), stored as a UTC datetime.
            // Uniqueness on this column prevents duplicate observations when a
            // poll returns the same reading we already stored.
            $table->timestamp('observed_at')->unique();

            // Temperature family (°F).
            $table->float('temperature')->nullable();
            $table->float('feels_like')->nullable();
            $table->float('dew_point')->nullable();

            // Humidity (%).
            $table->unsignedTinyInteger('humidity')->nullable();

            // Wind (mph / degrees).
            $table->float('wind_speed')->nullable();
            $table->float('wind_gust')->nullable();
            $table->unsignedSmallInteger('wind_direction')->nullable();
            $table->float('wind_speed_avg_2m')->nullable();
            $table->unsignedSmallInteger('wind_direction_avg_2m')->nullable();
            $table->float('wind_speed_avg_10m')->nullable();
            $table->unsignedSmallInteger('wind_direction_avg_10m')->nullable();
            $table->float('max_daily_gust')->nullable();

            // Pressure (inHg).
            $table->float('pressure_relative')->nullable();
            $table->float('pressure_absolute')->nullable();

            // Rain (inches / in-hr).
            $table->float('rain_hourly')->nullable();
            $table->float('rain_daily')->nullable();
            $table->float('rain_24h')->nullable();
            $table->float('rain_weekly')->nullable();
            $table->float('rain_monthly')->nullable();
            $table->float('rain_event')->nullable();

            // Sun / radiation.
            $table->float('uv_index')->nullable();
            $table->float('solar_radiation')->nullable();

            // Indoor comfort.
            $table->float('indoor_temperature')->nullable();
            $table->unsignedTinyInteger('indoor_humidity')->nullable();

            // Battery condition (true = OK, false = Low), null when not reported.
            $table->boolean('battery_outdoor_ok')->nullable();
            $table->boolean('battery_indoor_ok')->nullable();

            // Station metadata that can drift between firmware updates.
            $table->string('device_mac')->nullable();
            $table->string('station_tz')->nullable();

            // Complete normalized source payload for future-proofing.
            $table->json('raw')->nullable();

            $table->timestamps();

            // "Most recent" and range scans drive nearly every read.
            $table->index('observed_at', 'observations_observed_at_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('observations');
    }
};
