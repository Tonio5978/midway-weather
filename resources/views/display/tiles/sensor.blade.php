@if ($sensor = ($display['sensor_tiles'][$tileId] ?? null))
<div class="tile metric metric--sensor" data-tile="{{ $tileId }}" data-sensor-tile>
    <div class="metric-head"><svg class="sensor-icon" aria-hidden="true"><use href="#{{ $sensor['symbol'] }}"/></svg><span>{{ $sensor['label'] }}</span></div>
    <div class="metric-body"><span class="metric-value">{{ $sensor['value'] ?? '—' }}</span><span class="metric-unit">{{ $sensor['unit'] }}</span></div>
    <p class="metric-foot sensor-state">{{ ucfirst($sensor['state']) }}@if($sensor['value'] === null && $sensor['last_known'] !== null) · Last known {{ $sensor['last_known'] }} {{ $sensor['unit'] }}@endif</p>
    <p class="metric-foot sensor-time">{{ $sensor['observed_at'] ? 'Observed '.\Carbon\CarbonImmutable::parse($sensor['observed_at'])->setTimezone(config('weather.timezone'))->format('M j, g:i A') : 'No reading yet' }}</p>
    <p class="metric-foot sensor-history">{{ $sensor['history']['min'] !== null ? '24h available range: '.$sensor['history']['min'].'–'.$sensor['history']['max'].' '.$sensor['unit'] : 'No available history in 24h' }}</p>
</div>
@elseif ($tileId === 'pool')
<div class="tile metric metric--sensor" data-tile="pool" data-sensor-placeholder hidden><div class="metric-head">Pool temperature</div><div class="metric-body"><span class="metric-value">—</span></div><p class="metric-foot">Select a station and temperature measurement in Admin</p></div>
@endif
