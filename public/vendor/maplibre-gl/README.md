# MapLibre GL JS (self-hosted)

Version: **5.6.2** (UMD + CSS)

Update:

```bash
curl -fsSL -o public/vendor/maplibre-gl/maplibre-gl.js \
  https://unpkg.com/maplibre-gl@5.6.2/dist/maplibre-gl.js
curl -fsSL -o public/vendor/maplibre-gl/maplibre-gl.css \
  https://unpkg.com/maplibre-gl@5.6.2/dist/maplibre-gl.css
```

Loaded same-origin via `assets/maplibre.js` — avoids CDN third-party requests for the library itself. Basemap tiles still come from `MAP_STYLE_URL` (OpenFreeMap).
