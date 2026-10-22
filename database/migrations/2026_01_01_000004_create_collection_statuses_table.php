<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Health tracking for each collection source (keyed by `source`, e.g.
 * "ambient" and "forecast"). One row per source, updated on every poll so the
 * display can show last-success/last-failure, consecutive failures, and a
 * safe (non-sensitive) error summary without exposing traces or credentials.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collection_statuses', function (Blueprint $table): void {
            $table->id();

            $table->string('source')->unique();

            $table->timestamp('last_success_at')->nullable();
            $table->timestamp('last_failure_at')->nullable();
            $table->unsignedInteger('consecutive_failures')->default(0);

            // Short, display-safe description of the most recent failure.
            $table->string('last_error')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collection_statuses');
    }
};
