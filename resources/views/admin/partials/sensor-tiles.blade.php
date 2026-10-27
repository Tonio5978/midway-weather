<div class="panel"><h2>Sensor-backed tiles</h2><p>Select a verified measurement from an enabled station. Channel numbers describe connections, not a physical sensor's purpose. Newly discovered channels stay private until you publish them.</p>
<form id="sensor-form"><div class="field-grid">
<label>Tile to configure<select id="sensor-id"><option value="">Create a new sensor tile</option><option value="pool">Existing Pool temperature tile</option></select></label>
<label>Station<select id="sensor-station" required></select></label>
<label>Measurement<select id="sensor-measurement" required></select></label>
<label>Tile label<input id="sensor-label" maxlength="60" required></label>
<label>Icon<select id="sensor-icon"><option value="thermometer">Thermometer</option><option value="humidity">Droplet</option><option value="wind">Wind</option><option value="sun">Sun</option></select></label>
<label>Size<select id="sensor-size"><option value="standard">Standard</option><option value="wide">Wide</option></select></label>
<label>Units<select id="sensor-unit" required></select></label></div>
<p id="sensor-preview" role="status">Choose a measurement to preview its current state and available 24-hour history.</p>
<label class="check"><input type="checkbox" id="sensor-publish">Publish this measurement on the public dashboard</label>
<p class="muted">Labels and readings on enabled tiles are public. Disable a tile in the layout editor to unpublish it. Existing tile IDs and positions are retained when edited. Save or discard any layout draft before publishing.</p>
<div class="provider-actions"><button type="button" id="sensor-test">Preview measurement</button><button class="primary" type="submit">Publish sensor tile</button></div><p id="sensor-notice" role="status"></p></form></div>
