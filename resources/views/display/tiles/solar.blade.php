@php use App\Weather\Support\Format; @endphp
                <div class="tile metric metric--solar" data-tile="solar">
                    <div class="metric-head"><svg class="icon" aria-hidden="true"><use href="#ui-sun-small"></use></svg><span>Solar</span></div>
                    <div class="metric-body">
                        <div class="metric-primary">
                            <span class="metric-value" id="solar" data-bind="observation.solar_radiation" data-fmt="solar">{{ Format::solar($get('observation.solar_radiation')) }}</span>
                            <span class="metric-unit">W/m²</span>
                        </div>
                    </div>
                </div>
