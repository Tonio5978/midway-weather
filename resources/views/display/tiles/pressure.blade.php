@php use App\Weather\Support\Format; @endphp
                <div class="tile metric metric--pressure" data-tile="pressure">
                    <div class="metric-head"><svg class="icon" aria-hidden="true"><use href="#ui-gauge"></use></svg><span>Pressure</span></div>
                    <div class="metric-body">
                        <div class="metric-primary">
                            <span class="metric-value" id="pressure-rel" data-bind="observation.pressure.relative" data-fmt="pressure">{{ Format::pressure($get('observation.pressure.relative')) }}</span>
                            <span class="metric-unit">inHg</span>
                        </div>
                    </div>
                    <div class="metric-foot">
                        <dl class="metric-subs">
                            <div><dt>Absolute</dt><dd><span id="pressure-abs" data-bind="observation.pressure.absolute" data-fmt="pressure">{{ Format::pressure($get('observation.pressure.absolute')) }}</span></dd></div>
                        </dl>
                        @include('display.partials.trend', ['id' => 'trend-pressure', 't' => $get('trends.pressure')])
                    </div>
                </div>
