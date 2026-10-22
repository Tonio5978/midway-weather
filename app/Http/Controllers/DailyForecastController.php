<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\CanonicalResponse;
use App\Weather\Services\DailyForecast;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DailyForecastController extends Controller
{
    public function __invoke(Request $request, DailyForecast $forecast): JsonResponse
    {
        $now = CarbonImmutable::now('UTC');
        $data = $forecast->project($now);

        return $data === null
            ? CanonicalResponse::error($request, 'forecast_unavailable', 'No usable daily forecast is available.', 503, ['availability' => 'unavailable'], $now)
            : CanonicalResponse::send($request, ['data' => $data], 200, $now);
    }
}
