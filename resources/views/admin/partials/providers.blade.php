<div class="panel">
    <div class="panel-heading"><div><h2>Data Sources &amp; Providers</h2><p>Manage connections, discover stations, and inspect measurements. Discovery does not publish sensors on your dashboard.</p></div><button id="providers-reload">Reload status</button></div>
    <p id="providers-notice" role="status" hidden></p>
    <div id="provider-connections"></div>
</div>
<div class="panel">
    <h2>Stations &amp; measurements</h2>
    <p>Enable up to eight stations. Choose one primary station for the existing weather dashboard and API contracts. Changing primary keeps each station’s history separate.</p>
    <p class="muted">Discovery can identify channels, not their physical purpose. A numbered temperature channel is not automatically a pool sensor. Unknown fields stay diagnostic. Missing channels remain listed with their last known reading and time.</p>
    <div id="provider-stations"></div>
    <button id="providers-save-stations" hidden>Save station settings</button>
</div>
<details class="panel"><summary>Legacy normalized diagnostics</summary><p class="muted">Compatibility diagnostics for the existing dashboard. These are not additional provider connections.</p><div id="source-list"></div></details>
