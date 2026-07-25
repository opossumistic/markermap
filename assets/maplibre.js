const MAPLIBRE_JS = '/vendor/maplibre-gl/maplibre-gl.js';
const MAPLIBRE_CSS = '/vendor/maplibre-gl/maplibre-gl.css';

let loadingPromise = null;

export function ensureMapLibreCss() {
    if (document.querySelector(`link[href="${MAPLIBRE_CSS}"]`)) {
        return;
    }

    const link = document.createElement('link');
    link.rel = 'stylesheet';
    link.href = MAPLIBRE_CSS;
    document.head.appendChild(link);
}

/**
 * Load self-hosted MapLibre UMD (same-origin — no CDN cookie/consent surface).
 * @returns {Promise<typeof window.maplibregl>}
 */
export function loadMapLibre() {
    ensureMapLibreCss();

    if (window.maplibregl) {
        return Promise.resolve(window.maplibregl);
    }

    if (loadingPromise) {
        return loadingPromise;
    }

    loadingPromise = new Promise((resolve, reject) => {
        const script = document.createElement('script');
        script.src = MAPLIBRE_JS;
        script.async = true;
        script.onload = () => {
            if (!window.maplibregl) {
                reject(new Error('maplibregl global missing after script load'));
                return;
            }
            resolve(window.maplibregl);
        };
        script.onerror = () => reject(new Error('Failed to load self-hosted MapLibre'));
        document.head.appendChild(script);
    });

    return loadingPromise;
}
