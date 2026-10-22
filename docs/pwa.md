# Progressive Web App support

Midway Weather exposes a deployment-aware manifest at `/manifest.webmanifest`
and registers `/service-worker.js` from the weather display. Both URLs are
generated through Laravel helpers so alternate hosts and application base paths
remain supported.

The manifest launches Weather at the application root. Its `id` deliberately
retains the historical `/display/weather` value so browsers update an existing
installation rather than treating the root entry point as a second app. The
historical path remains a compatibility alias for installed Home Screen apps
and bookmarks.

The service worker is intentionally a small resilient-shell layer, not an
offline-weather feature. Its versioned cache contains only stable local CSS,
JavaScript, manifest, Leaflet runtime files, and app icons. It uses network-first
responses for those assets and removes old Midway shell caches on activation.

Page navigations, local `/api/` requests, JSON requests, RainViewer resources,
and Stadia Maps base tiles are never handled by the service worker. This avoids
showing a cached observation as current and avoids unbounded third-party tile
caches. A network connection remains required for live weather and radar.

For local visual QA only, `?pwa_preview=chromium`, `?pwa_preview=ios`, and
`?pwa_preview=standalone` expose the corresponding install-control states.
Production ignores this parameter.
