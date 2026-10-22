@php use App\Weather\Support\Format; @endphp
                <div class="tile metric metric--indoor" data-tile="indoor">
                    <div class="metric-head"><svg class="icon" aria-hidden="true"><use href="#ui-home"></use></svg><span>Indoor</span></div>
                    <div class="metric-body metric-body--split">
                        <div class="metric-primary">
                            <span class="metric-value" id="indoor-temp" data-bind="observation.indoor.temperature" data-fmt="temp">{{ Format::temp($get('observation.indoor.temperature')) }}</span>
                        </div>
                        <div class="metric-primary metric-primary--sub">
                            <span class="metric-value" id="indoor-humidity" data-bind="observation.indoor.humidity" data-fmt="percent">{{ Format::percent($get('observation.indoor.humidity')) }}</span>
                        </div>
                    </div>
                    <dl class="metric-subs metric-subs--labels">
                        <div><dt>Temp</dt></div>
                        <div><dt>Humidity</dt></div>
                    </dl>
                </div>
