<?php

// Optional hash-only configuration belongs outside the public web root.
$path = env('WEATHER_ADMIN_CREDENTIALS_FILE');
$credentials = is_string($path) && is_file($path) ? json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR) : [];

return [
    'email' => env('MIDWAY_WEATHER_ADMIN_EMAIL', $credentials['email'] ?? ''),
    'password_hash' => env('MIDWAY_WEATHER_ADMIN_PASSWORD_HASH', $credentials['password_hash'] ?? ''),
];
