<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\CanonicalResponse;
use App\Weather\Services\CanonicalSummary;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CanonicalSummaryController extends Controller
{
    public function __invoke(Request $request, CanonicalSummary $summary): JsonResponse
    {
        $now = CarbonImmutable::now('UTC');
        $data = $summary->project($now);

        return $data === null
            ? CanonicalResponse::error($request, 'weather_data_unavailable', 'No usable weather observation is available.', 503, ['availability' => 'unavailable'], $now)
            : CanonicalResponse::send($request, ['data' => $data], 200, $now);
    }
}
