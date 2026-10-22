@php use App\Weather\Support\Format; @endphp
                <div class="tile metric metric--uv" data-tile="uv">
                    <div class="metric-head"><svg class="icon" aria-hidden="true"><use href="#ui-sun-small"></use></svg><span>UV Index</span></div>
                    <div class="metric-body">
                        <div class="metric-primary">
                            <span class="metric-value" id="uv-index" data-bind="observation.uv_index" data-fmt="uv">{{ Format::uv($get('observation.uv_index')) }}</span>
                        </div>
                        <span class="metric-tag" id="uv-category" data-bind="observation.uv_category" data-fmt="text">{{ $get('observation.uv_category', Format::DASH) }}</span>
                    </div>
                </div>
