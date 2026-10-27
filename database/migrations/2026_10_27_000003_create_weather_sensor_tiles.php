<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('weather_sensor_tiles', function (Blueprint $t) {
            $t->string('id')->primary();
            $t->foreignId('measurement_id')->constrained('weather_measurements');
            $t->string('label', 60);
            $t->string('icon');
            $t->string('unit');
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('weather_sensor_tiles');
    }
};
