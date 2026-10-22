<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class CanonicalResponse
{
    public const CONTRACTS = ['contracts-core' => '1.0.0', 'contracts-weather' => '1.0.0'];

    public static function send(Request $request, array $body, int $status = 200, ?CarbonImmutable $now = null): JsonResponse
    {
        $id = $request->header('X-Midway-Request-ID');
        if (! is_string($id) || ! preg_match('/^[A-Za-z0-9][A-Za-z0-9._:-]{0,127}$/D', $id)) {
            $id = (string) Str::uuid();
        }
        $meta = ['request_id' => $id, 'generated_at' => ($now ?? CarbonImmutable::now('UTC'))->toIso8601String()];
        if ($request->is('api/v2/summary/current')) {
            $meta['contract'] = ['name' => 'weather.summary.current', 'version' => 2, 'artifact' => ['name' => 'contracts-weather', 'version' => self::CONTRACTS['contracts-weather']]];
        }

        return response()->json([...$body, 'meta' => $meta], $status)
            ->header('X-Midway-Request-ID', $id)
            ->header('Cache-Control', 'no-store, max-age=0');
    }

    public static function error(Request $request, string $code, string $message, int $status, array $details = [], ?CarbonImmutable $now = null): JsonResponse
    {
        $error = ['code' => $code, 'message' => $message];
        if ($details !== []) {
            $error['details'] = $details;
        }

        return self::send($request, ['error' => $error], $status, $now);
    }
}
