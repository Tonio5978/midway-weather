<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Weather\Services\DisplayPresenter;
use App\Weather\Support\TemperatureMood;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Serves the standalone kiosk display page.
 *
 * The page is server-rendered with the current view model so it shows real
 * data on first paint (no blank flash while JS boots), then the bundled
 * vanilla JS polls the JSON endpoint to keep it live.
 */
class DisplayController extends Controller
{
    public function __invoke(DisplayPresenter $presenter, Request $request): View
    {
        return view('display.weather', [
            'display' => $presenter->present(),
            // A query-string override is deliberately local-only and never
            // enters the presenter/API payload or changes stored observations.
            'temperatureMoodPreview' => app()->environment('local')
                ? TemperatureMood::normalize($request->query('temperature_theme'))
                : null,
        ]);
    }
}
