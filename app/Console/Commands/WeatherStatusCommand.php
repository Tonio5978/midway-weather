<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Weather\Services\ObservationRepository;
use App\Weather\Services\StationHealthService;
use Illuminate\Console\Command;

/**
 * Prints a concise health report for the collection pipeline. Useful for
 * verifying a fresh install and troubleshooting stale data on the Pi.
 * Exit code is non-zero when the station data is stale or absent.
 */
class WeatherStatusCommand extends Command
{
    protected $signature = 'weather:status';

    protected $description = 'Show collection health, latest observation age, and stale-data state';

    public function handle(ObservationRepository $observations, StationHealthService $health): int
    {
        $latest = $observations->latest();
        $freshness = $health->observationFreshness($latest);
        $age = $health->observationAgeMinutes($latest);

        $this->line('');
        $this->line('<options=bold>Midway Weather — Status</>');
        $this->line(str_repeat('─', 40));
        $this->line('Demo mode:  '.(config('weather.demo_mode') ? '<comment>ON</comment>' : 'off'));

        if ($latest === null) {
            $this->warn('No observations collected yet.');
        } else {
            $this->line(sprintf('Latest obs: %s (%d min ago)', $latest->observed_at->toIso8601String(), $age));
            $this->line(sprintf('Freshness:  %s', $this->colorFreshness($freshness)));
            $this->line(sprintf('Temp:       %s°F', $latest->temperature ?? 'n/a'));
        }

        $this->renderSource('Ambient', $health->statusSummary('ambient'));
        $this->renderSource('Forecast', $health->statusSummary('forecast'));
        $this->line('');

        return match ($freshness) {
            StationHealthService::FRESH, StationHealthService::DELAYED => self::SUCCESS,
            default => self::FAILURE,
        };
    }

    /**
     * @param  array{last_success_at: string|null, last_failure_at: string|null, consecutive_failures: int, last_error: string|null, healthy: bool}  $summary
     */
    private function renderSource(string $label, array $summary): void
    {
        $this->line('');
        $this->line(sprintf('<options=bold>%s</>', $label));
        $this->line('  Last success: '.($summary['last_success_at'] ?? '—'));
        $this->line('  Last failure: '.($summary['last_failure_at'] ?? '—'));
        $this->line('  Consecutive failures: '.$summary['consecutive_failures']);

        if ($summary['last_error'] !== null) {
            $this->line('  Last error: <comment>'.$summary['last_error'].'</comment>');
        }
    }

    private function colorFreshness(string $freshness): string
    {
        return match ($freshness) {
            StationHealthService::FRESH => '<info>fresh</info>',
            StationHealthService::DELAYED => '<comment>delayed</comment>',
            StationHealthService::STALE => '<fg=red>stale</>',
            default => '<fg=red>no data</>',
        };
    }
}
