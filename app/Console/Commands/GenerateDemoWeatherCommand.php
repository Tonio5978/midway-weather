<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Weather\Services\DemoDataGenerator;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Generates realistic demo observations and a forecast so the dashboard can be
 * viewed before real credentials are configured.
 *
 * Guarded against production: it refuses to run when APP_ENV=production unless
 * --force is passed, so demo data cannot be seeded onto a live kiosk by
 * accident.
 */
class GenerateDemoWeatherCommand extends Command
{
    protected $signature = 'weather:demo
        {--hours=48 : How many hours of history to generate}
        {--force : Allow running in the production environment}';

    protected $description = 'Generate realistic demo weather data for development and previews';

    public function handle(DemoDataGenerator $generator): int
    {
        if (app()->environment('production') && ! $this->option('force')) {
            $this->error('Refusing to generate demo data in production. Use --force to override.');

            return self::FAILURE;
        }

        $hours = max(1, (int) $this->option('hours'));

        $this->info(sprintf('Generating %d hours of demo weather…', $hours));

        $result = $generator->generate(CarbonImmutable::now('UTC'), $hours);

        $this->info(sprintf(
            'Done. Stored %d observation(s) and %d forecast day(s).',
            $result['observations'],
            $result['forecast_days'],
        ));
        $this->line('Open <options=bold>/</> to view the dashboard.');

        return self::SUCCESS;
    }
}
