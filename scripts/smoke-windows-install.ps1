# Disposable CI installation check. Never run against an existing installation.
$ErrorActionPreference = 'Stop'
function Check-NativeExit {
    if ($LASTEXITCODE -ne 0) { throw "Native command failed with exit code $LASTEXITCODE" }
}
if (-not $env:RUNNER_TEMP) { throw 'This smoke test requires an isolated CI runner.' }
$source = Split-Path $PSScriptRoot -Parent
$install = Join-Path $env:RUNNER_TEMP ('Weather Install ' + [guid]::NewGuid().ToString('N'))
$archive = $install + '.zip'
$php = (Get-Command php).Source
$server = $null
$scheduler = $null
try {
    # Exercise the guide's anonymous public ZIP download/extraction on Windows.
    $download = Join-Path $env:RUNNER_TEMP ('Weather Download ' + [guid]::NewGuid().ToString('N'))
    New-Item -ItemType Directory -Path $download | Out-Null
    Invoke-WebRequest -UseBasicParsing -Uri 'https://github.com/thenumber3/midway-weather/archive/refs/heads/main.zip' -OutFile "$download/midway-weather.zip"
    Expand-Archive -Path "$download/midway-weather.zip" -DestinationPath $download
    if (-not (Test-Path "$download/midway-weather-main/artisan")) { throw 'Public source ZIP layout did not match the guide.' }
    # Install the candidate commit, rather than masking future branch regressions
    # by testing only the already published source. No existing vendor/env/data.

    git -C $source archive --format=zip --output=$archive HEAD
    Check-NativeExit
    Expand-Archive -Path $archive -DestinationPath $install
    Push-Location $install
    Copy-Item .env.example .env
    New-Item -ItemType File -Path database/database.sqlite | Out-Null
    composer install --prefer-dist --no-interaction --no-progress
    Check-NativeExit
    composer check-platform-reqs
    Check-NativeExit
    php artisan key:generate
    Check-NativeExit
    php artisan migrate --force
    Check-NativeExit
    php artisan weather:demo --hours=48
    Check-NativeExit
    php artisan weather:status
    Check-NativeExit

    $server = Start-Process -FilePath $php -ArgumentList @('artisan', 'serve', '--host=127.0.0.1', '--port=8000') -WorkingDirectory $install -PassThru -RedirectStandardOutput "$install/server.log" -RedirectStandardError "$install/server-error.log"
    $ready = $false
    for ($i = 0; $i -lt 30; $i++) {
        try {
            $response = Invoke-WebRequest -UseBasicParsing http://127.0.0.1:8000/
            if ($response.StatusCode -eq 200 -and $response.Content.Contains('id="hero-temp"')) { $ready = $true; break }
        } catch { Start-Sleep -Seconds 1 }
    }
    if (-not $ready) { throw 'Demo dashboard failed to start.' }
    $model = Invoke-RestMethod http://127.0.0.1:8000/api/display/weather
    if ($null -eq $model.observation.temperature -or $model.forecast.days.Count -ne 7) { throw 'Demo API is missing observations or forecasts.' }

    # Verify the guide's separate database path, including spaces in a Windows path.
    New-Item -ItemType File -Path .\database\station.sqlite | Out-Null
    $stationDb = (Join-Path (Get-Location).Path 'database\station.sqlite').Replace('\', '/')
    $databaseLine = 'DB_DATABASE="' + $stationDb + '"'
    [IO.File]::AppendAllText((Join-Path $install '.env'), "`n$databaseLine`n", (New-Object Text.UTF8Encoding $false))
    php artisan optimize:clear
    Check-NativeExit
    php artisan migrate --force
    Check-NativeExit
    # Test-only synthetic data in the second disposable database, never a live station.
    php artisan weather:demo --hours=3
    Check-NativeExit

    # Exercise the application's real background command scheduling, without keys.
    $text = [IO.File]::ReadAllText((Join-Path $install '.env')).Replace('WEATHER_DEMO_MODE=false', 'WEATHER_DEMO_MODE=true')
    [IO.File]::WriteAllText((Join-Path $install '.env'), $text, (New-Object Text.UTF8Encoding $false))
    @'
<?php
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$heartbeat = cache()->get('weather.scheduler.last_tick');
$ambient = App\Models\CollectionStatus::where('source', 'ambient')->value('last_success_at');
echo json_encode(['heartbeat' => $heartbeat, 'ambient' => $ambient]);
'@ | Set-Content -Encoding ASCII smoke-state.php
    $before = (php smoke-state.php | ConvertFrom-Json).ambient
    Check-NativeExit
    $scheduler = Start-Process -FilePath $php -ArgumentList @('artisan', 'schedule:work') -WorkingDirectory $install -PassThru -RedirectStandardOutput "$install/scheduler.log" -RedirectStandardError "$install/scheduler-error.log"
    $scheduled = $false
    for ($i = 0; $i -lt 45; $i++) {
        Start-Sleep -Seconds 2
        $state = php smoke-state.php | ConvertFrom-Json
        Check-NativeExit
        if ($state.heartbeat -and $state.ambient -gt $before) { $scheduled = $true; break }
    }
    if (-not $scheduled) { throw 'Windows scheduler did not record both a heartbeat and a completed background demo collection.' }
    Write-Host "PASS: Windows PowerShell $($PSVersionTable.PSVersion), PHP $(& $php -r 'echo PHP_VERSION;'), Composer install/platform requirements, SQLite migrations, demo HTML/API, schedule:work heartbeat and background collection; path contains spaces. No live station credentials used."
} finally {
    foreach ($process in @($server, $scheduler)) {
        if ($process -and -not $process.HasExited) { taskkill /PID $process.Id /T /F | Out-Null }
    }
    if (Test-Path $install) {
        Get-ChildItem $install -Filter '*log' | ForEach-Object { Write-Host $_.Name; Get-Content $_.FullName -Tail 12 }
    }
    Pop-Location -ErrorAction SilentlyContinue
}
