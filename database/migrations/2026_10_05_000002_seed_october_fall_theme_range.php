<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Before the first named theme shipped, Automatic had no ranges.
        // Preserve manual selections, custom ranges and every other setting.
        DB::transaction(function () {
            $row = DB::table('weather_settings')->find(1);
            if (! $row) {
                return;
            }
            $values = json_decode($row->configuration, true, 512, JSON_THROW_ON_ERROR);
            if ($values['theme']['selection'] !== 'automatic' || $values['theme']['schedules'] !== []) {
                return;
            }
            $values['theme']['schedules'] = [['theme' => 'october-fall', 'start' => '10-01', 'end' => '11-30']];
            DB::table('weather_settings')->where('id', 1)->where('revision', $row->revision)->update([
                'configuration' => json_encode($values, JSON_THROW_ON_ERROR),
                'revision' => $row->revision + 1,
                'updated_at' => now(),
            ]);
        });
    }

    public function down(): void
    {
        // User-owned configuration is preserved during code rollback.
    }
};
