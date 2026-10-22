<?php

declare(strict_types=1);

use App\Console\Commands\CleanupWeatherCommand;
use App\Console\Commands\CollectWeatherCommand;
use App\Console\Commands\RefreshForecastCommand;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Scheduled Weather Tasks
|--------------------------------------------------------------------------
|
| Cadence:
|   - Bootstrap/cache clear: monthly at midnight on the first
|   - Ambient observations : every minute
|   - Open-Meteo forecast  : hourly
|   - Cleanup + aggregation: daily at 3:00 AM
|
| withoutOverlapping() prevents a slow poll from stacking on top of the next
| one. runInBackground() keeps the per-minute scheduler tick snappy while the
| collection and forecast commands perform their network requests.
|
*/

$maintenanceLog = storage_path('logs/scheduler-maintenance.log');

// Keep this first: optimize:clear removes application cache entries, including
// scheduler mutexes. At midnight on the first, subsequent events should acquire
// fresh locks after maintenance completes.
Schedule::command('optimize:clear')
    ->monthlyOn(1, '00:00')
    ->timezone((string) config('weather.timezone'))
    ->withoutOverlapping(60)
    ->appendOutputTo($maintenanceLog);

Schedule::command(CollectWeatherCommand::class)
    ->everyMinute()
    ->withoutOverlapping(10)
    ->runInBackground();

Schedule::command(RefreshForecastCommand::class)
    ->hourly()
    ->withoutOverlapping(30)
    ->runInBackground();

Schedule::command(CleanupWeatherCommand::class)
    ->dailyAt('03:00')
    ->timezone((string) config('weather.timezone'))
    ->withoutOverlapping();

Schedule::call(fn () => cache()->put('weather.scheduler.last_tick', now()->toIso8601String(), now()->addDay()))->everyMinute();
