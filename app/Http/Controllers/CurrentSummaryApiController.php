<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Weather\Services\DisplayPresenter;
use App\Weather\Services\StationHealthService;
use App\Weather\Support\TemperatureMood;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;

/** Public compact contract for trusted Midway ecosystem consumers. */
final class CurrentSummaryApiController extends Controller
{
    public function __invoke(DisplayPresenter $presenter): JsonResponse
    {
        $now = CarbonImmutable::now('UTC');
        $timezone = (string) config('weather.timezone', 'America/New_York');
        $summary = $presenter->currentSummary($now);
        $ttl = max(1, (int) config('weather.api.summary_ttl_seconds', 300));

        $envelope = [
            'contractVersion' => 1,
            'kind' => 'weather.summary.current',
            'source' => 'midway-weather',
            'generatedAt' => $now->setTimezone($timezone)->toIso8601String(),
            'observedAt' => $summary['observed_at'],
            'stale' => $summary['freshness'] === StationHealthService::STALE,
            'ttlSeconds' => $ttl,
            'data' => null,
            'error' => $summary['error'],
        ];

        if ($summary['available'] !== true) {
            return response()->json($envelope, 503)
                ->header('Cache-Control', 'no-store, max-age=0');
        }

        $temperature = $summary['temperature'];
        $high = $summary['today_high'];
        $low = $summary['today_low'];
        $mood = (string) $summary['mood'];
        $range = TemperatureMood::range($mood);

        $envelope['data'] = [
            'temperature' => ['value' => $temperature, 'unit' => 'F', 'display' => sprintf('%s°F', rtrim(rtrim(number_format($temperature, 1, '.', ''), '0'), '.'))],
            'today' => [
                'high' => $high, 'low' => $low,
                'highDisplay' => $high === null ? null : sprintf('%s°', rtrim(rtrim(number_format($high, 1, '.', ''), '0'), '.')),
                'lowDisplay' => $low === null ? null : sprintf('%s°', rtrim(rtrim(number_format($low, 1, '.', ''), '0'), '.')),
            ],
            'condition' => ['code' => $summary['condition']['code'], 'label' => $summary['condition']['label'], 'icon' => $summary['condition']['icon'], 'isDaytime' => $summary['condition']['is_daytime']],
            'atmosphere' => $summary['atmosphere'],
            'mood' => ['key' => $mood, 'label' => ucfirst($mood), 'temperatureRange' => $range],
            'summary' => ['category' => $summary['summary']['category'], 'message' => $summary['summary']['message'], 'phraseId' => $summary['summary']['phrase_id']],
        ];

        return response()->json($envelope)
            ->header('Cache-Control', 'no-store, max-age=0');
    }
}
