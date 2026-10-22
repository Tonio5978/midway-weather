<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Daily forecast rows from Open-Meteo. Upserted by date so a fresh forecast
 * pull overwrites the same day rather than accumulating duplicates, and the
 * previous forecast survives if a later pull fails.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('forecast_days', function (Blueprint $table): void {
            $table->id();

            // Local forecast date. Unique so each day has exactly one row.
            $table->date('date')->unique();

            // WMO weather interpretation code (maps to icon + label).
            $table->unsignedSmallInteger('weather_code')->nullable();

            $table->float('temperature_high')->nullable();
            $table->float('temperature_low')->nullable();
            $table->unsignedTinyInteger('precipitation_probability')->nullable();
            $table->float('precipitation_amount')->nullable();
            $table->float('wind_speed_max')->nullable();
            $table->float('wind_gust_max')->nullable();

            // Stored as UTC datetimes; presented in the configured local zone.
            $table->timestamp('sunrise')->nullable();
            $table->timestamp('sunset')->nullable();

            // When this forecast was fetched (used for forecast staleness).
            $table->timestamp('fetched_at');

            $table->timestamps();

            $table->index('date', 'forecast_days_date_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('forecast_days');
    }
};
