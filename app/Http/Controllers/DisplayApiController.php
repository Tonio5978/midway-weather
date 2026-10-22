<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Weather\Services\DisplayPresenter;
use Illuminate\Http\JsonResponse;

/**
 * Local JSON endpoint the browser polls (default every 60s).
 *
 * This is the ONLY weather data source the browser touches — it never calls
 * Ambient or Open-Meteo directly, so API keys never reach the client. The
 * response is a single, self-contained view model. A short-lived cache
 * absorbs bursts (e.g. several kiosks) without re-querying on every request.
 */
class DisplayApiController extends Controller
{
    public function __invoke(DisplayPresenter $presenter): JsonResponse
    {
        // Presenter output changes at most once per collection cycle, so a brief
        // cache keeps repeated polls cheap on the Pi while staying fresh.
        $payload = cache()->remember(
            'display.weather.payload',
            now()->addSeconds(15),
            static fn () => $presenter->present(),
        );

        return response()
            ->json($payload)
            // Never let an intermediary or the browser serve a stale snapshot;
            // the JS handles its own polling cadence.
            ->header('Cache-Control', 'no-store, max-age=0');
    }
}
