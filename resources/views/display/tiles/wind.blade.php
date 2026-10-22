@php use App\Weather\Support\Format; @endphp
                <div class="tile metric metric--wind" data-tile="wind">
                    <div class="metric-head"><svg class="icon" aria-hidden="true"><use href="#ui-wind"></use></svg><span>Wind</span></div>
                    <div class="metric-body">
                        <div class="metric-primary">
                            <span class="metric-value" id="wind-speed" data-bind="observation.wind.speed" data-fmt="speed">{{ Format::speed($get('observation.wind.speed')) }}</span>
                            <span class="metric-unit">mph</span>
                        </div>
                        <div class="wind-copy">
                            <span class="wind-from">from <strong id="wind-dir" data-bind="observation.wind.direction_abbr" data-fmt="text">{{ $get('observation.wind.direction_abbr', Format::DASH) }}</strong></span>
                        </div>
                        <div class="wind-compass" aria-label="Wind direction compass">
                            <span class="wind-cardinal wind-cardinal--n">N</span>
                            <span class="wind-cardinal wind-cardinal--e">E</span>
                            <span class="wind-cardinal wind-cardinal--s">S</span>
                            <span class="wind-cardinal wind-cardinal--w">W</span>
                            <span class="wind-tick wind-tick--ne"></span>
                            <span class="wind-tick wind-tick--se"></span>
                            <span class="wind-tick wind-tick--sw"></span>
                            <span class="wind-tick wind-tick--nw"></span>
                            <svg class="wind-arrow" id="wind-arrow" aria-hidden="true" style="transform: translate(-50%, -50%) rotate({{ ((int) $get('observation.wind.direction', 0) + 180) % 360 }}deg)"><use href="#ui-nav"></use></svg>
                        </div>
                    </div>
                    <div class="metric-foot">
                        <dl class="metric-subs">
                            <div><dt>Gust</dt><dd><span id="wind-gust" data-bind="observation.wind.gust" data-fmt="speed">{{ Format::speed($get('observation.wind.gust')) }}</span></dd></div>
                            <div><dt>Avg 10m</dt><dd><span id="wind-avg10" data-bind="observation.wind.avg_10m" data-fmt="speed">{{ Format::speed($get('observation.wind.avg_10m')) }}</span></dd></div>
                            <div><dt>Peak</dt><dd><span id="wind-maxgust" data-bind="observation.wind.max_daily_gust" data-fmt="speed">{{ Format::speed($get('observation.wind.max_daily_gust')) }}</span></dd></div>
                        </dl>
                        @include('display.partials.trend', ['id' => 'trend-wind', 't' => $get('trends.wind')])
                    </div>
                </div>
