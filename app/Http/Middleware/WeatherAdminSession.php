<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

final class WeatherAdminSession
{
    public static function version(): string
    {
        return hash('sha256', strtolower(trim(config('weather_admin.email')))."\0".config('weather_admin.password_hash'));
    }

    public static function configured(): bool
    {
        return filter_var(config('weather_admin.email'), FILTER_VALIDATE_EMAIL) !== false && (password_get_info(config('weather_admin.password_hash'))['algoName'] ?? 'unknown') !== 'unknown';
    }

    public static function valid(Request $r): bool
    {
        return self::configured() && hash_equals(self::version(), (string) $r->session()->get('weather_auth.version', '')) && (int) $r->session()->get('weather_auth.started', 0) > time() - 43200 && (int) $r->session()->get('weather_auth.seen', 0) > time() - 7200;
    }

    public function handle(Request $r, Closure $next)
    {
        if (! self::valid($r)) {
            $r->session()->invalidate();
            $r->session()->regenerateToken();

            return $r->expectsJson() ? response()->json(['message' => 'Authentication required.'], 401) : redirect()->route('login');
        }
        $r->session()->put('weather_auth.seen', time());

        return $next($r);
    }
}
