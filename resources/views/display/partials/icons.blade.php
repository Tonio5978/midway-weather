{{--
    Inline SVG icon sprite.

    All weather + UI glyphs live here as <symbol>s and are drawn elsewhere with
    <svg><use href="#id"></use></svg>. Keeping them inline (rather than an icon
    font or remote sprite) means zero extra requests and full themeability via
    `currentColor`. Weather-code icon keys match App\Weather\Support\WeatherCode.
--}}
<svg width="0" height="0" aria-hidden="true" focusable="false" style="position:absolute">
    <defs>
        {{-- Shared stroke defaults are set per-<use> via CSS (.icon). --}}

        {{-- ── Weather condition icons ───────────────────────────────── --}}
        <symbol id="wi-clear-day" viewBox="0 0 24 24">
            <circle cx="12" cy="12" r="4.6"/>
            <g stroke-linecap="round">
                <line x1="12" y1="1.5" x2="12" y2="4"/>
                <line x1="12" y1="20" x2="12" y2="22.5"/>
                <line x1="1.5" y1="12" x2="4" y2="12"/>
                <line x1="20" y1="12" x2="22.5" y2="12"/>
                <line x1="4.4" y1="4.4" x2="6.2" y2="6.2"/>
                <line x1="17.8" y1="17.8" x2="19.6" y2="19.6"/>
                <line x1="19.6" y1="4.4" x2="17.8" y2="6.2"/>
                <line x1="6.2" y1="17.8" x2="4.4" y2="19.6"/>
            </g>
        </symbol>

        <symbol id="wi-clear-night" viewBox="0 0 24 24">
            <path d="M20 14.6A8 8 0 1 1 9.4 4 6.4 6.4 0 0 0 20 14.6Z"/>
        </symbol>

        <symbol id="wi-partly-cloudy-day" viewBox="0 0 24 24">
            <g stroke-linecap="round">
                <circle cx="8" cy="7.5" r="3"/>
                <line x1="8" y1="1.8" x2="8" y2="3"/>
                <line x1="2.3" y1="7.5" x2="3.5" y2="7.5"/>
                <line x1="3.9" y1="3.4" x2="4.8" y2="4.3"/>
                <line x1="12.1" y1="3.4" x2="11.2" y2="4.3"/>
            </g>
            <path d="M9 19.5a3.9 3.9 0 0 1-.5-7.77A5.3 5.3 0 0 1 18.8 12.9 3.4 3.4 0 0 1 18.3 19.5H9Z"/>
        </symbol>

        <symbol id="wi-partly-cloudy-night" viewBox="0 0 24 24">
            <path d="M15.6 3.6a4.4 4.4 0 1 0 3.4 6.9 5 5 0 0 1-3.4-6.9Z"/>
            <path d="M9 19.5a3.9 3.9 0 0 1-.5-7.77A5.3 5.3 0 0 1 18.8 12.9 3.4 3.4 0 0 1 18.3 19.5H9Z"/>
        </symbol>

        <symbol id="wi-cloudy" viewBox="0 0 24 24">
            <path d="M7 18.5a4.4 4.4 0 0 1-.5-8.77A5.9 5.9 0 0 1 18 9.7v.2A3.7 3.7 0 0 1 17 18.5H7Z"/>
        </symbol>

        <symbol id="wi-fog" viewBox="0 0 24 24">
            <path d="M7 13.5a3.9 3.9 0 0 1-.4-7.77A5.5 5.5 0 0 1 17.4 7.7 3.5 3.5 0 0 1 17 13.5H7Z"/>
            <g stroke-linecap="round">
                <line x1="4" y1="17.5" x2="18" y2="17.5"/>
                <line x1="7" y1="20.8" x2="20" y2="20.8"/>
            </g>
        </symbol>

        <symbol id="wi-drizzle" viewBox="0 0 24 24">
            <path d="M7 14.5a3.9 3.9 0 0 1-.4-7.77A5.5 5.5 0 0 1 17.4 8.7 3.5 3.5 0 0 1 17 14.5H7Z"/>
            <g stroke-linecap="round">
                <line x1="9" y1="17.5" x2="8.3" y2="19.6"/>
                <line x1="13" y1="17.5" x2="12.3" y2="19.6"/>
            </g>
        </symbol>

        <symbol id="wi-rain" viewBox="0 0 24 24">
            <path d="M7 13.8a3.9 3.9 0 0 1-.4-7.77A5.5 5.5 0 0 1 17.4 8 3.5 3.5 0 0 1 17 13.8H7Z"/>
            <g stroke-linecap="round">
                <line x1="8" y1="16.8" x2="7" y2="20.3"/>
                <line x1="12" y1="16.8" x2="11" y2="20.3"/>
                <line x1="16" y1="16.8" x2="15" y2="20.3"/>
            </g>
        </symbol>

        <symbol id="wi-showers" viewBox="0 0 24 24">
            <path d="M7 13a3.9 3.9 0 0 1-.4-7.77A5.5 5.5 0 0 1 17.4 7.2 3.5 3.5 0 0 1 17 13H7Z"/>
            <g stroke-linecap="round">
                <line x1="7" y1="16" x2="5.8" y2="20.5"/>
                <line x1="11" y1="16" x2="9.8" y2="20.5"/>
                <line x1="15" y1="16" x2="13.8" y2="20.5"/>
                <line x1="19" y1="16" x2="17.8" y2="20.5"/>
            </g>
        </symbol>

        <symbol id="wi-snow" viewBox="0 0 24 24">
            <path d="M7 13.5a3.9 3.9 0 0 1-.4-7.77A5.5 5.5 0 0 1 17.4 7.7 3.5 3.5 0 0 1 17 13.5H7Z"/>
            <g fill="currentColor" stroke="none">
                <circle cx="8" cy="18" r="1"/>
                <circle cx="12" cy="20" r="1"/>
                <circle cx="16" cy="18" r="1"/>
            </g>
        </symbol>

        <symbol id="wi-thunderstorm" viewBox="0 0 24 24">
            <path d="M7 13a3.9 3.9 0 0 1-.4-7.77A5.5 5.5 0 0 1 17.4 7.2 3.5 3.5 0 0 1 17 13H7Z"/>
            <path fill="currentColor" stroke="none" d="M13 13l-3.4 4.6H12l-1 4 4.2-5.4H12.8L13 13Z"/>
        </symbol>

        {{-- ── UI / metric icons ─────────────────────────────────────── --}}
        <symbol id="ui-droplet" viewBox="0 0 24 24">
            <path d="M12 3.4c2.9 3.5 5.4 6.5 5.4 9.4a5.4 5.4 0 0 1-10.8 0c0-2.9 2.5-5.9 5.4-9.4Z"/>
        </symbol>

        <symbol id="ui-wind" viewBox="0 0 24 24" stroke-linecap="round">
            <path d="M3 8h10.5a2.4 2.4 0 1 0-2.3-3.1"/>
            <path d="M3 12h14a2.4 2.4 0 1 1-2.3 3.1"/>
            <path d="M3 16h8.5a2.2 2.2 0 1 1-2 3"/>
        </symbol>

        <symbol id="ui-gauge" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round">
            <path d="M3.8 16a8.2 8.2 0 0 1 16.4 0"/>
            <line x1="12" y1="16" x2="16.2" y2="11.5"/>
            <circle cx="12" cy="16" r="1.3" fill="currentColor" stroke="none"/>
        </symbol>

        <symbol id="ui-thermometer" viewBox="0 0 24 24" stroke-linecap="round">
            <path d="M14 14.6V5.2a2 2 0 1 0-4 0v9.4a4 4 0 1 0 4 0Z"/>
        </symbol>

        <symbol id="ui-home" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round">
            <path d="M3.5 11.2 12 4.5l8.5 6.7"/>
            <path d="M5.8 10v9.5h12.4V10"/>
        </symbol>

        <symbol id="ui-sun-small" viewBox="0 0 24 24" stroke-linecap="round">
            <circle cx="12" cy="12" r="4"/>
            <line x1="12" y1="2.5" x2="12" y2="4.5"/>
            <line x1="12" y1="19.5" x2="12" y2="21.5"/>
            <line x1="2.5" y1="12" x2="4.5" y2="12"/>
            <line x1="19.5" y1="12" x2="21.5" y2="12"/>
            <line x1="5.2" y1="5.2" x2="6.6" y2="6.6"/>
            <line x1="17.4" y1="17.4" x2="18.8" y2="18.8"/>
            <line x1="18.8" y1="5.2" x2="17.4" y2="6.6"/>
            <line x1="6.6" y1="17.4" x2="5.2" y2="18.8"/>
        </symbol>

        <symbol id="ui-battery" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round">
            <rect x="2.5" y="8" width="16" height="8" rx="1.8"/>
            <line x1="21" y1="11" x2="21" y2="13"/>
        </symbol>

        <symbol id="ui-nav" viewBox="0 0 24 24" stroke-linejoin="round">
            <path d="M12 3 18.5 20 12 16 5.5 20 12 3Z"/>
        </symbol>

        <symbol id="ui-arrow-up" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round">
            <line x1="12" y1="19.5" x2="12" y2="5"/>
            <polyline points="6 11 12 5 18 11"/>
        </symbol>

        <symbol id="ui-arrow-down" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round">
            <line x1="12" y1="4.5" x2="12" y2="19"/>
            <polyline points="6 13 12 19 18 13"/>
        </symbol>

        <symbol id="ui-arrow-right" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round">
            <line x1="4.5" y1="12" x2="19" y2="12"/>
            <polyline points="14 6 20 12 14 18"/>
        </symbol>

        <symbol id="ui-sunrise" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round">
            <path d="M4 18h16"/>
            <path d="M8 18a4 4 0 0 1 8 0"/>
            <line x1="12" y1="3.5" x2="12" y2="6"/>
            <line x1="4.8" y1="10.5" x2="6.2" y2="11.6"/>
            <line x1="19.2" y1="10.5" x2="17.8" y2="11.6"/>
            <polyline points="9.5 6.5 12 4 14.5 6.5"/>
        </symbol>

        <symbol id="ui-sunset" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round">
            <path d="M4 18h16"/>
            <path d="M8 18a4 4 0 0 1 8 0"/>
            <line x1="12" y1="3.5" x2="12" y2="6"/>
            <line x1="4.8" y1="10.5" x2="6.2" y2="11.6"/>
            <line x1="19.2" y1="10.5" x2="17.8" y2="11.6"/>
            <polyline points="9.5 3.5 12 6 14.5 3.5"/>
        </symbol>
    </defs>
</svg>
