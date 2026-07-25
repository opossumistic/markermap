import { Controller } from '@hotwired/stimulus';
import { loadMapLibre } from 'app-maplibre';

/**
 * One-page map shell: browse markers, detail sheet, add/pick mode.
 * Sheet stays collapsed until marker click or "add".
 */
export default class extends Controller {
    static targets = [
        'canvas',
        'sheet',
        'sheetTitle',
        'detailPanel',
        'addPanel',
        'correctPanel',
        'pickerStatus',
        'lat',
        'lng',
        'street',
        'postalCode',
        'searchQuery',
        'searchResults',
        'correctionLocationId',
    ];

    static values = {
        styleUrl: String,
        locationsUrl: String,
        reverseUrl: String,
        geocodeUrl: String,
        reportUrlTemplate: String,
        reportToken: String,
        openAdd: Boolean,
        centerLng: { type: Number, default: 9.9937 },
        centerLat: { type: Number, default: 53.5511 },
        zoom: { type: Number, default: 11 },
        minLat: { type: Number, default: 53.38 },
        maxLat: { type: Number, default: 53.75 },
        minLng: { type: Number, default: 9.7 },
        maxLng: { type: Number, default: 10.35 },
    };

    mode = 'browse';
    locationMarkers = [];
    pickMarker = null;
    maplibregl = null;
    reverseTimer = null;
    reverseAbort = null;
    reverseSeq = 0;
    /** @type {Record<string, unknown>|null} */
    detailProps = null;

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

            this.map.on('load', () => {
                this.map.resize();
                this.loadLocations();
                if (this.openAddValue) {
                    this.startAdd();
                }
            });

            this.map.on('click', (event) => {
                if (this.mode === 'pick') {
                    this.placePickMarker(event.lngLat.lng, event.lngLat.lat);
                    return;
                }

                if (this.mode === 'detail' || this.mode === 'correct') {
                    this.closeSheet();
                    return;
                }

                // Browse: Kartenklick startet Vorschlag an diesem Punkt.
                this.beginAddAt(event.lngLat.lng, event.lngLat.lat);
            });

            this.map.on('error', (event) => {
                console.error('MapLibre error', event.error);
            });
        } catch (error) {
            console.error(error);
            this.canvasTarget.replaceChildren();
            const err = document.createElement('p');
            err.className = 'map-flash';
            err.textContent = 'Karte konnte nicht geladen werden. Bitte Seite neu laden.';
            this.canvasTarget.append(err);
        }
    }

    disconnect() {
        this.clearLocationMarkers();
        this.cancelReverse();
        if (this.pickMarker) {
            this.pickMarker.remove();
            this.pickMarker = null;
        }
        if (this.map) {
            this.map.remove();
            this.map = null;
        }
    }

    startAdd(event) {
        event?.preventDefault();
        this.beginAddAt();
    }

    async searchAddress(event) {
        event.preventDefault();
        if (!this.hasSearchQueryTarget || !this.geocodeUrlValue) {
            return;
        }

        const query = this.searchQueryTarget.value.trim();
        if (query.length < 3) {
            this.showSearchMessage('Bitte mindestens 3 Zeichen eingeben.');
            return;
        }

        this.showSearchMessage('Suche…');

        try {
            const url = new URL(this.geocodeUrlValue, window.location.origin);
            url.searchParams.set('q', query);
            const response = await fetch(url, { headers: { Accept: 'application/json' } });
            if (!response.ok) {
                this.showSearchMessage('Keine Treffer in Hamburg.');
                return;
            }

            const data = await response.json();
            const results = data.results ?? [];
            if (results.length === 0) {
                this.showSearchMessage('Keine Treffer in Hamburg.');
                return;
            }

            this.renderSearchResults(results);
        } catch (error) {
            console.error(error);
            this.showSearchMessage('Suche fehlgeschlagen. Bitte erneut versuchen.');
        }
    }

    renderSearchResults(results) {
        if (!this.hasSearchResultsTarget) {
            return;
        }

        this.searchResultsTarget.replaceChildren();
        for (const hit of results) {
            const li = document.createElement('li');
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'map-search__hit';
            btn.textContent = hit.displayName;
            btn.addEventListener('click', () => this.applySearchHit(hit));
            li.append(btn);
            this.searchResultsTarget.append(li);
        }
        this.searchResultsTarget.hidden = false;
    }

    showSearchMessage(message) {
        if (!this.hasSearchResultsTarget) {
            return;
        }
        this.searchResultsTarget.replaceChildren();
        const li = document.createElement('li');
        li.className = 'map-search__empty';
        li.textContent = message;
        this.searchResultsTarget.append(li);
        this.searchResultsTarget.hidden = false;
    }

    clearSearchResults() {
        if (!this.hasSearchResultsTarget) {
            return;
        }
        this.searchResultsTarget.replaceChildren();
        this.searchResultsTarget.hidden = true;
    }

    applySearchHit(hit) {
        this.clearSearchResults();
        this.mode = 'pick';
        this.map.getCanvas().style.cursor = 'crosshair';
        this.openSheet('add');
        this.sheetTitleTarget.textContent = 'Box vorschlagen';
        this.applyAddress(hit);
        this.placePickMarker(hit.lng, hit.lat, { pan: true, skipReverse: true });
        const label = [hit.street, hit.postalCode, hit.district].filter(Boolean).join(', ')
            || hit.displayName;
        this.setPickerStatus(`Adresse: ${label}`);
        requestAnimationFrame(() => this.map?.resize());
    }

    beginAddAt(lng = null, lat = null) {
        this.mode = 'pick';
        this.map.getCanvas().style.cursor = 'crosshair';
        this.openSheet('add');
        this.sheetTitleTarget.textContent = 'Box vorschlagen';

        if (lng !== null && lat !== null) {
            this.placePickMarker(lng, lat);
        } else {
            this.setPickerStatus('Klicke auf die Karte, um den Standort zu setzen.');
            const existingLat = this.parseCoord(this.latTarget.value);
            const existingLng = this.parseCoord(this.lngTarget.value);
            if (existingLat !== null && existingLng !== null) {
                this.placePickMarker(existingLng, existingLat, { pan: true });
            }
        }

        requestAnimationFrame(() => this.map?.resize());
    }

    closeSheet() {
        this.mode = 'browse';
        this.cancelReverse();
        if (this.map) {
            this.map.getCanvas().style.cursor = '';
        }
        if (this.pickMarker) {
            this.pickMarker.remove();
            this.pickMarker = null;
        }
        this.sheetTarget.hidden = true;
        if (this.hasDetailPanelTarget) {
            this.detailPanelTarget.hidden = true;
        }
        if (this.hasAddPanelTarget) {
            this.addPanelTarget.hidden = true;
        }
        if (this.hasCorrectPanelTarget) {
            this.correctPanelTarget.hidden = true;
        }
        this.element.classList.remove('map-shell--sheet-open');
        requestAnimationFrame(() => this.map?.resize());
    }

    openSheet(which) {
        this.sheetTarget.hidden = false;
        if (this.hasDetailPanelTarget) {
            this.detailPanelTarget.hidden = which !== 'detail';
        }
        if (this.hasAddPanelTarget) {
            this.addPanelTarget.hidden = which !== 'add';
        }
        if (this.hasCorrectPanelTarget) {
            this.correctPanelTarget.hidden = which !== 'correct';
        }
        this.element.classList.add('map-shell--sheet-open');
    }

    showDetail(props) {
        if (this.mode === 'pick' || this.mode === 'correct') {
            return;
        }

        this.mode = 'detail';
        this.detailProps = props;
        this.sheetTitleTarget.textContent = props.label ?? props.title ?? 'Tauschbox';
        this.detailPanelTarget.innerHTML = this.buildDetailHtml(props);
        this.openSheet('detail');
        requestAnimationFrame(() => this.map?.resize());
    }

    startCorrect(event) {
        event?.preventDefault();
        const button = event?.currentTarget;
        const locationId = Number(button?.dataset?.locationId);
        if (!Number.isFinite(locationId) || locationId <= 0) {
            return;
        }

        const props = this.detailProps?.id === locationId ? this.detailProps : { id: locationId };
        this.mode = 'correct';
        this.fillCorrectionForm(props);
        this.sheetTitleTarget.textContent = 'Eintrag bearbeiten';
        this.openSheet('correct');
        requestAnimationFrame(() => this.map?.resize());
    }

    /**
     * Prefill correction form from the open detail (same GeoJSON props).
     * File input stays empty — current photo remains unless a new one is uploaded.
     */
    fillCorrectionForm(props) {
        if (!this.hasCorrectPanelTarget) {
            return;
        }

        const panel = this.correctPanelTarget;
        const form = panel.querySelector('form');
        if (form instanceof HTMLFormElement) {
            form.reset();
        }

        if (this.hasCorrectionLocationIdTarget) {
            this.correctionLocationIdTarget.value = String(props.id ?? '');
        }

        const title = panel.querySelector('[name="location_correction[title]"]');
        if (title instanceof HTMLInputElement) {
            title.value = props.title ?? '';
        }

        const description = panel.querySelector('[name="location_correction[description]"]');
        if (description instanceof HTMLTextAreaElement) {
            description.value = props.description ?? '';
        }

        const selected = new Set(Array.isArray(props.categories) ? props.categories.map(String) : []);
        panel.querySelectorAll('[name="location_correction[categories][]"]').forEach((input) => {
            if (input instanceof HTMLInputElement) {
                input.checked = selected.has(input.value);
            }
        });

        const email = panel.querySelector('[name="location_correction[email]"]');
        if (email instanceof HTMLInputElement) {
            email.value = '';
        }
    }

    reportGone(event) {
        event?.preventDefault();
        const button = event?.currentTarget;
        const locationId = Number(button?.dataset?.locationId);
        if (!Number.isFinite(locationId) || locationId <= 0) {
            return;
        }
        if (!window.confirm('Diesen Eintrag als „nicht mehr vorhanden“ melden?')) {
            return;
        }

        const action = (this.reportUrlTemplateValue || '').replace('__ID__', String(locationId));
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = action;
        form.setAttribute('data-turbo', 'false');

        const token = document.createElement('input');
        token.type = 'hidden';
        token.name = '_token';
        token.value = this.reportTokenValue;
        form.append(token);

        document.body.append(form);
        form.submit();
    }

    async loadLocations() {
        const response = await fetch(this.locationsUrlValue, {
            headers: { Accept: 'application/json' },
        });

        if (!response.ok) {
            console.error('Failed to load locations', response.status);
            return;
        }

        const geojson = await response.json();
        this.renderMarkers(geojson);
    }

    clearLocationMarkers() {
        for (const marker of this.locationMarkers) {
            marker.remove();
        }
        this.locationMarkers = [];
    }

    renderMarkers(geojson) {
        this.clearLocationMarkers();

        for (const feature of geojson.features ?? []) {
            const [lng, lat] = feature.geometry.coordinates;
            const props = feature.properties ?? {};
            const disputed = props.status === 'disputed';
            const pending = props.status === 'pending';

            const el = document.createElement('button');
            el.type = 'button';
            el.className = pending
                ? 'map-marker map-marker--pending'
                : disputed
                    ? 'map-marker map-marker--disputed'
                    : 'map-marker map-marker--active';
            el.setAttribute(
                'aria-label',
                pending
                    ? `${props.label ?? props.title ?? 'Tauschbox'} (in Prüfung)`
                    : (props.label ?? props.title ?? 'Tauschbox'),
            );
            el.addEventListener('click', (event) => {
                event.stopPropagation();
                this.showDetail(props);
            });

            const marker = new this.maplibregl.Marker({ element: el, anchor: 'bottom' })
                .setLngLat([lng, lat])
                .addTo(this.map);

            this.locationMarkers.push(marker);
        }
    }

    placePickMarker(lng, lat, { pan = false, skipReverse = false } = {}) {
        if (!this.inBounds(lng, lat)) {
            this.setPickerStatus('Bitte einen Punkt innerhalb Hamburgs wählen.', true);
            return;
        }

        const roundedLat = Number(lat.toFixed(6));
        const roundedLng = Number(lng.toFixed(6));

        this.latTarget.value = String(roundedLat);
        this.lngTarget.value = String(roundedLng);

        if (!this.pickMarker) {
            const el = document.createElement('div');
            el.className = 'map-marker map-marker--active map-marker--picker';
            this.pickMarker = new this.maplibregl.Marker({ element: el, anchor: 'bottom', draggable: true })
                .setLngLat([roundedLng, roundedLat])
                .addTo(this.map);

            this.pickMarker.on('dragend', () => {
                const pos = this.pickMarker.getLngLat();
                this.placePickMarker(pos.lng, pos.lat);
            });
        } else {
            this.pickMarker.setLngLat([roundedLng, roundedLat]);
        }

        if (pan) {
            this.map.flyTo({ center: [roundedLng, roundedLat], zoom: Math.max(this.map.getZoom(), 14) });
        }

        if (skipReverse) {
            return;
        }

        this.setPickerStatus('Adresse wird ermittelt…');
        this.scheduleReverseGeocode(roundedLat, roundedLng);
    }

    scheduleReverseGeocode(lat, lng) {
        if (!this.reverseUrlValue) {
            this.setPickerStatus(`Standort gesetzt: ${lat}, ${lng}`);
            return;
        }

        clearTimeout(this.reverseTimer);
        this.reverseTimer = setTimeout(() => {
            this.reverseGeocode(lat, lng);
        }, 350);
    }

    cancelReverse() {
        clearTimeout(this.reverseTimer);
        this.reverseTimer = null;
        if (this.reverseAbort) {
            this.reverseAbort.abort();
            this.reverseAbort = null;
        }
    }

    async reverseGeocode(lat, lng) {
        if (this.reverseAbort) {
            this.reverseAbort.abort();
        }
        const abort = new AbortController();
        this.reverseAbort = abort;
        const seq = ++this.reverseSeq;

        try {
            const url = new URL(this.reverseUrlValue, window.location.origin);
            url.searchParams.set('lat', String(lat));
            url.searchParams.set('lng', String(lng));

            const response = await fetch(url, {
                headers: { Accept: 'application/json' },
                signal: abort.signal,
            });

            if (seq !== this.reverseSeq) {
                return;
            }

            if (!response.ok) {
                this.setPickerStatus(`Standort gesetzt: ${lat}, ${lng} — Adresse nicht gefunden.`);
                return;
            }

            const data = await response.json();
            this.applyAddress(data);
            const label = [data.street, data.postalCode, data.district].filter(Boolean).join(', ');
            this.setPickerStatus(label ? `Adresse: ${label}` : `Standort gesetzt: ${lat}, ${lng}`);
        } catch (error) {
            if (error?.name === 'AbortError') {
                return;
            }
            console.error(error);
            this.setPickerStatus(`Standort gesetzt: ${lat}, ${lng} — Adresse konnte nicht geladen werden.`);
        } finally {
            if (this.reverseAbort === abort) {
                this.reverseAbort = null;
            }
        }
    }

    applyAddress(data) {
        if (this.hasStreetTarget) {
            this.streetTarget.value = data.street ?? '';
        }
        if (this.hasPostalCodeTarget) {
            this.postalCodeTarget.value = data.postalCode ?? '';
        }
    }

    inBounds(lng, lat) {
        return lat >= this.minLatValue
            && lat <= this.maxLatValue
            && lng >= this.minLngValue
            && lng <= this.maxLngValue;
    }

    parseCoord(value) {
        if (value === null || value === undefined || value === '') {
            return null;
        }
        const n = Number(value);
        return Number.isFinite(n) ? n : null;
    }

    setPickerStatus(message, isError = false) {
        if (!this.hasPickerStatusTarget) {
            return;
        }
        this.pickerStatusTarget.textContent = message;
        this.pickerStatusTarget.classList.toggle('is-error', isError);
    }

    buildDetailHtml(props) {
        const status = props.status === 'disputed'
            ? '<p class="map-popup__status">Status ungeprüft</p>'
            : props.status === 'pending'
                ? '<p class="map-popup__status">Wird noch verifiziert — Details nach Freigabe</p>'
                : '';
        // Pending: API already strips UGC; still skip image/description/categories defensively.
        const isPending = props.status === 'pending';
        const image = !isPending && props.image_url
            ? `<img class="map-detail__image" src="${this.escape(props.image_url)}" alt="" loading="lazy">`
            : '';
        const categories = !isPending && Array.isArray(props.category_labels) && props.category_labels.length
            ? `<p class="map-detail__cats">${props.category_labels.map((c) => this.escape(c)).join(' · ')}</p>`
            : '';
        const meta = [props.street, props.district].filter(Boolean).map((v) => this.escape(v)).join(', ');
        const description = !isPending && props.description
            ? `<p>${this.escape(props.description)}</p>`
            : '';
        const id = Number(props.id);
        const actions = Number.isFinite(id) && id > 0 && !isPending
            ? `<div class="map-detail__actions">
                <button type="button" class="map-btn map-btn--block" data-action="map-shell#startCorrect" data-location-id="${id}">Bearbeiten</button>
                <button type="button" class="map-btn map-btn--block map-btn--muted" data-action="map-shell#reportGone" data-location-id="${id}">Nicht mehr vorhanden melden</button>
               </div>`
            : '';

        return `${image}${categories}${meta ? `<p>${meta}</p>` : ''}${status}${description}${actions}`;
    }

    escape(value) {
        return String(value)
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;');
    }
}
