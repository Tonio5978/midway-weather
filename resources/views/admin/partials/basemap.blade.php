<div class="basemap-settings">
    <h3>Base map</h3>
    <p>Choose the map underneath the radar. RainViewer, your location, and map framing stay independent.</p>
    <div class="field-grid">
        <label>Map provider
            <select id="basemap-provider" aria-label="Map provider" data-path="basemap.provider">
                <option value="environment">Use environment configuration ({{ $basemaps[$environmentBasemap]['name'] }})</option>
                @foreach($basemaps as $id => $provider)<option value="{{ $id }}">{{ $provider['name'] }}</option>@endforeach
            </select>
        </label>
        <label id="stadia-auth-field" hidden>Stadia authentication
            <select id="basemap-authentication" aria-label="Stadia authentication" data-path="basemap.authentication">
                <option value="domain">Authorized domain / localhost</option>
                <option value="key">Owner's tile API key</option>
            </select>
        </label>
    </div>
    <p id="basemap-style" class="muted"></p>
    <div id="map-key-fields" hidden>
        <label for="map-tile-key">Browser tile key / public token</label>
        <input id="map-tile-key" type="password" autocomplete="off" spellcheck="false" maxlength="2048" aria-describedby="map-key-status map-key-warning" placeholder="Leave empty to keep the configured key">
        <p id="map-key-status" class="muted"></p>
        <label class="check"><input id="map-clear-key" type="checkbox">Remove the Admin-saved key on Save (use environment key if configured)</label>
        <p id="map-key-warning" class="muted">Saved keys are encrypted with this installation's APP_KEY. Tile keys are browser-visible on the public dashboard and in previews. Use a dedicated, restricted browser key; never enter a weather credential, account-management secret, or Mapbox sk. token.</p>
    </div>
    <div class="map-provider-guide" id="map-provider-guide">
        <h3>Provider setup</h3>
        <div data-map-guide="environment"><p>Keep the host's WEATHER_BASEMAP_PROVIDER and its tile credentials. This preserves existing installations. Select a named provider above to manage the map here instead.</p></div>
        <div data-map-guide="openstreetmap" hidden><p>No account or key is needed for modest household viewing. OpenStreetMap's public tiles are best effort, without an availability guarantee. Keep attribution visible and normal browser caching enabled. Do not prefetch, bulk-download, or use offline tiles.</p></div>
        <div data-map-guide="stadia" hidden><p>In your Stadia account, open Manage Properties → Authentication Configuration and authorize this installation's exact domain/subdomain. Domain authentication needs no key. Localhost and 127.0.0.1 support limited development access; LAN/IP/intranet installs may need your own tile API key. Choose the matching authentication option above.</p></div>
        <div data-map-guide="maptiler" hidden><p>In MapTiler Cloud → API keys, create a dedicated key and restrict Allowed HTTP origins to this installation's domain. Enter that key above. Use a separate development key for localhost or LAN testing. MapTiler Streets v4 uses raster tiles; attribution remains visible.</p></div>
        <div data-map-guide="mapbox" hidden><p>In Mapbox → Access tokens, create a new public pk. token with the read scopes needed for Static Tiles (styles:tiles), and restrict allowed URLs to this installation's origin. Avoid the unrestricted default token and all secret sk. tokens. Use a separate development token. Weather uses Dark v11 raster tiles; Mapbox Standard styles are not supported. Required Mapbox logo, credits, and feedback link are displayed.</p></div>
        <p class="muted">Preview origin: <strong id="map-preview-origin"></strong>. Authorize that address with your provider. Previews send the real browser origin; no headers are forged and no tile proxy is used.</p>
        <p><a id="map-provider-docs" target="_blank" rel="noopener noreferrer">Provider setup and usage requirements ↗</a></p>
        <p class="muted">Check your account's quotas and pricing before enabling a keyed provider. This app does not create accounts or purchase a plan. Previewing makes tile requests that may count against your existing quota.</p>
    </div>
    <p class="muted">If the selected map fails repeatedly, Weather temporarily uses OpenStreetMap and labels the fallback. Your saved selection stays intact and is retried with backoff. If both fail, the map is marked unavailable; radar reports its own status.</p>
    <p id="map-preview-result" role="status">Test the draft below before saving. Preview does not change the live dashboard.</p>
</div>
