<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Persisted per-day aggregates of local station observations. These are cheap
 * to recompute but persisting them lets the display compare "rain today vs.
 * recent days" without scanning the full observation history on every request,
 * and preserves history even after detailed observations are pruned.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_summaries', function (Blueprint $table): void {
            $table->id();

            // Local calendar date (America/New_York) the summary covers.
            $table->date('date')->unique();

            $table->float('temperature_high')->nullable();
            $table->float('temperature_low')->nullable();
            $table->float('max_wind_gust')->nullable();
            $table->float('total_rain')->nullable();
            $table->float('max_uv')->nullable();
            $table->float('max_solar_radiation')->nullable();
            $table->float('min_pressure')->nullable();
            $table->float('max_pressure')->nullable();

            // How many observations fed this summary (diagnostics / partial days).
            $table->unsignedInteger('observation_count')->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_summaries');
    }
};
