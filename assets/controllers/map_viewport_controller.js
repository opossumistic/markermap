import { Controller } from '@hotwired/stimulus';
import { loadMapLibre } from 'app-maplibre';

/**
 * Pick initial map center/zoom for /maps/new — syncs hidden form fields on moveend.
 */
export default class extends Controller {
    static targets = [
        'canvas',
        'centerLat',
        'centerLng',
        'zoom',
        'searchQuery',
        'searchResults',
        'status',
    ];

    static values = {
        styleUrl: String,
        geocodeUrl: String,
        centerLat: { type: Number, default: 51.1657 },
        centerLng: { type: Number, default: 10.4515 },
        zoom: { type: Number, default: 6 },
    };

    searchMessageTimer = null;

    async connect() {
        try {
            this.maplibregl = await loadMapLibre();
            this.map = new this.maplibregl.Map({
                container: this.canvasTarget,
                style: this.styleUrlValue,
                center: [this.centerLngValue, this.centerLatValue],
                zoom: this.zoomValue,
                attributionControl: true,
            });
            this.map.addControl(new this.maplibregl.NavigationControl({ showCompass: false }), 'top-right');
            this.map.on('moveend', () => this.syncFields());
            this.map.on('load', () => this.syncFields());
            this.setStatus('Verschiebe die Karte und zoome auf deinen Ausschnitt.');
        } catch (error) {
            console.error(error);
            this.setStatus('Karte konnte nicht geladen werden — Koordinaten manuell prüfen.', true);
        }
    }

    disconnect() {
        this.map?.remove();
        this.map = null;
        if (this.searchMessageTimer) {
            clearTimeout(this.searchMessageTimer);
        }
    }

    syncFields() {
        if (!this.map) {
            return;
        }
        const center = this.map.getCenter();
        const zoom = this.map.getZoom();
        this.centerLatTarget.value = center.lat.toFixed(6);
        this.centerLngTarget.value = center.lng.toFixed(6);
        this.zoomTarget.value = zoom.toFixed(1);
    }

    async searchAddress(event) {
        event.preventDefault();
        if (!this.geocodeUrlValue || !this.hasSearchQueryTarget) {
            return;
        }

        const query = this.searchQueryTarget.value.trim();
        if (query.length < 3) {
            this.showSearchMessage('Bitte mindestens 3 Zeichen eingeben.');
            return;
        }

        this.showSearchMessage('Suche…', { dismissAfter: 0 });

        try {
            const url = new URL(this.geocodeUrlValue, window.location.origin);
            url.searchParams.set('q', query);
            const response = await fetch(url, { headers: { Accept: 'application/json' } });
            if (!response.ok) {
                this.showSearchMessage('Keine Treffer.');
                return;
            }
            const data = await response.json();
            const results = data.results ?? [];
            if (results.length === 0) {
                this.showSearchMessage('Keine Treffer.');
                return;
            }
            this.renderSearchResults(results);
        } catch (error) {
            console.error(error);
            this.showSearchMessage('Suche fehlgeschlagen.');
        }
    }

    renderSearchResults(results) {
        if (!this.hasSearchResultsTarget) {
            return;
        }
        this.searchResultsTarget.hidden = false;
        this.searchResultsTarget.replaceChildren();
        for (const hit of results) {
            const li = document.createElement('li');
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'map-search__result';
            btn.textContent = hit.displayName || `${hit.lat}, ${hit.lng}`;
            btn.addEventListener('click', () => {
                this.map?.flyTo({ center: [hit.lng, hit.lat], zoom: Math.max(this.map.getZoom(), 12) });
                this.searchResultsTarget.hidden = true;
                this.searchResultsTarget.replaceChildren();
                if (this.hasSearchQueryTarget) {
                    this.searchQueryTarget.value = hit.displayName || '';
                }
                this.setStatus('Ausschnitt gesetzt — bei Bedarf noch zoomen oder verschieben.');
            });
            li.appendChild(btn);
            this.searchResultsTarget.appendChild(li);
        }
    }

    showSearchMessage(message, { dismissAfter = 4000 } = {}) {
        if (!this.hasSearchResultsTarget) {
            this.setStatus(message);
            return;
        }
        this.searchResultsTarget.hidden = false;
        this.searchResultsTarget.replaceChildren();
        const li = document.createElement('li');
        li.className = 'map-search__message';
        li.textContent = message;
        this.searchResultsTarget.appendChild(li);
        if (this.searchMessageTimer) {
            clearTimeout(this.searchMessageTimer);
        }
        if (dismissAfter > 0) {
            this.searchMessageTimer = setTimeout(() => {
                this.searchResultsTarget.hidden = true;
                this.searchResultsTarget.replaceChildren();
            }, dismissAfter);
        }
    }

    setStatus(message, isError = false) {
        if (!this.hasStatusTarget) {
            return;
        }
        this.statusTarget.textContent = message;
        this.statusTarget.classList.toggle('is-error', isError);
    }
}
