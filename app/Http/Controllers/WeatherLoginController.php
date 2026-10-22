<?php

namespace App\Http\Controllers;

use App\Http\Middleware\WeatherAdminSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

final class WeatherLoginController
{
    public function show(Request $r)
    {
        return WeatherAdminSession::valid($r) ? redirect('/admin') : view('admin.login');
    }

    public function login(Request $r)
    {
        $data = $r->validate(['email' => 'required|string|email|max:254', 'password' => 'required|string|max:1024']);
        $key = 'weather-login:'.hash('sha256', $r->ip());
        $passwordValid = WeatherAdminSession::configured() && password_verify($data['password'], config('weather_admin.password_hash'));
        if (RateLimiter::tooManyAttempts($key, 5) || ! $passwordValid || ! hash_equals(strtolower(trim(config('weather_admin.email'))), strtolower(trim($data['email'])))) {
            RateLimiter::hit($key, 300);
            throw ValidationException::withMessages(['email' => 'Email address or password incorrect. Please try again.']);
        }
        RateLimiter::clear($key);
        $r->session()->regenerate();
        $r->session()->regenerateToken();
        $r->session()->put('weather_auth', ['version' => WeatherAdminSession::version(), 'started' => time(), 'seen' => time()]);

        return redirect('/admin');
    }

    public function logout(Request $r)
    {
        $r->session()->invalidate();
        $r->session()->regenerateToken();

        return redirect('/login');
    }
}
