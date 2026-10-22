<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\CanonicalResponse;
use App\Support\ReleaseIdentity;
use App\Weather\Services\CanonicalSummary;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class OperationalContractController extends Controller
{
    public function version(Request $request, ReleaseIdentity $release): JsonResponse
    {
        try {
            return CanonicalResponse::send($request, ['data' => $release->data()]);
        } catch (\Throwable $error) {
            report($error);

            return CanonicalResponse::error($request, 'release_metadata_unavailable', 'Release metadata is unavailable.', 503);
        }
    }

    public function health(Request $request, CanonicalSummary $summary, ReleaseIdentity $release): JsonResponse
    {
        $now = CarbonImmutable::now('UTC');
        $checks = [];
        $availability = 'available';
        try {
            DB::select('select 1');
            $checks[] = ['name' => 'database', 'availability' => 'available', 'critical' => true];
            $projection = $summary->project($now);
            $state = $projection['availability'] ?? 'unavailable';
            $checks[] = ['name' => 'weather_projection', 'availability' => $state, 'critical' => false];
            if ($state !== 'available') {
                $availability = 'degraded';
            }
        } catch (\Throwable $error) {
            report($error);
            $availability = 'unavailable';
            $checks[] = ['name' => 'local_weather_store', 'availability' => 'unavailable', 'critical' => true];
        }
        $data = ['application' => 'midway-weather', 'availability' => $availability, 'checks' => $checks];
        try {
            $data['release'] = $release->data()['release'];
        } catch (\Throwable $error) {
            report($error);
            $data['availability'] = 'unavailable';
            $data['checks'][] = ['name' => 'release_metadata', 'availability' => 'unavailable', 'critical' => true];
        }

        return CanonicalResponse::send($request, ['data' => $data], $data['availability'] === 'unavailable' ? 503 : 200, $now);
    }
}
