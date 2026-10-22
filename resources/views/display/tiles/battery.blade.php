@php use App\Weather\Support\Format; @endphp
                <div class="tile metric metric--battery" data-tile="battery" id="battery-tile" data-ok="{{ $get('observation.battery.all_ok') === false ? 'false' : 'true' }}">
                    <div class="metric-head"><svg class="icon" aria-hidden="true"><use href="#ui-battery"></use></svg><span>Batteries</span></div>
                    <div class="metric-body">
                        <span class="battery-status" id="battery-status">
                            @if ($get('observation.battery.all_ok') === false)
                                Low battery
                            @elseif ($get('observation.battery.all_ok') === true)
                                All OK
                            @else
                                {{ Format::DASH }}
                            @endif
                        </span>
                    </div>
                    <dl class="metric-subs">
                        <div><dt>Outdoor</dt><dd id="battery-outdoor">{{ $get('observation.battery.outdoor_ok') === null ? Format::DASH : ($get('observation.battery.outdoor_ok') ? 'OK' : 'Low') }}</dd></div>
                        <div><dt>Indoor</dt><dd id="battery-indoor">{{ $get('observation.battery.indoor_ok') === null ? Format::DASH : ($get('observation.battery.indoor_ok') ? 'OK' : 'Low') }}</dd></div>
                    </dl>
                </div>
