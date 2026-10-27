<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('weather_map_settings', function (Blueprint $table): void {
            $table->unsignedTinyInteger('id')->primary();
            $table->string('provider');
            $table->string('authentication');
            $table->timestamps();
        });
        Schema::create('weather_map_credentials', function (Blueprint $table): void {
            $table->string('provider')->primary();
            $table->text('credential');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('weather_map_credentials');
        Schema::dropIfExists('weather_map_settings');
    }
};
