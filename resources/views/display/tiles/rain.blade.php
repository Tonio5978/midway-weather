@php use App\Weather\Support\Format; @endphp
                <div class="tile metric metric--rain" data-tile="rain" id="rain-tile" data-raining="{{ $get('rain.is_raining') ? 'true' : 'false' }}">
                    <div class="metric-head"><svg class="icon" aria-hidden="true"><use href="#ui-droplet"></use></svg><span>Rainfall</span></div>
                    <div class="metric-body">
                        <div class="metric-primary">
                            <span class="metric-value" id="rain-daily" data-bind="rain.daily" data-fmt="rain">{{ Format::rain($get('rain.daily')) }}</span>
                            <span class="metric-unit">today</span>
                        </div>
                    </div>
                    <div class="metric-foot">
                        <dl class="metric-subs">
                            <div><dt>Rate</dt><dd><span id="rain-hourly" data-bind="rain.hourly" data-fmt="rain">{{ Format::rain($get('rain.hourly')) }}</span><small>/hr</small></dd></div>
                            <div><dt>Week</dt><dd><span id="rain-weekly" data-bind="rain.weekly" data-fmt="rain">{{ Format::rain($get('rain.weekly')) }}</span></dd></div>
                            <div><dt>Month</dt><dd><span id="rain-monthly" data-bind="rain.monthly" data-fmt="rain">{{ Format::rain($get('rain.monthly')) }}</span></dd></div>
                        </dl>
                    </div>
                </div>
