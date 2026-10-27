<?php

namespace App\Providers;

use App\Weather\Configuration\Basemap;
use App\Weather\Configuration\ConfiguredPhraseSource;
use App\Weather\Configuration\WeatherSettings;
use App\Weather\Contracts\ConditionPhraseSource;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(WeatherSettings::class);
        $this->app->scoped(Basemap::class);
        $this->app->bind(ConditionPhraseSource::class, ConfiguredPhraseSource::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (! $this->app->runningUnitTests()) {
            app(WeatherSettings::class)->apply();
        }
    }
}
