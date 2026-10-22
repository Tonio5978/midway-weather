@php use App\Weather\Support\Format; @endphp
                <div class="tile metric metric--humidity" data-tile="humidity">
                    <div class="metric-head"><svg class="icon" aria-hidden="true"><use href="#ui-droplet"></use></svg><span>Humidity</span></div>
                    <div class="metric-body">
                        <div class="metric-primary">
                            <span class="metric-value" id="humidity" data-bind="observation.humidity" data-fmt="percent">{{ Format::percent($get('observation.humidity')) }}</span>
                        </div>
                    </div>
                    <div class="metric-foot">
                        <dl class="metric-subs">
                            <div><dt>Dew Point</dt><dd><span id="humidity-dew" data-bind="observation.dew_point" data-fmt="tempPrecise">{{ Format::tempPrecise($get('observation.dew_point')) }}</span></dd></div>
                        </dl>
                        @include('display.partials.trend', ['id' => 'trend-humidity', 't' => $get('trends.humidity')])
                    </div>
                </div>
