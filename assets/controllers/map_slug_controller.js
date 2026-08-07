import { Controller } from '@hotwired/stimulus';

/**
 * Shows live /maps/{slug} preview from the map name (slug is server-allocated on submit).
 */
export default class extends Controller {
    static targets = ['name', 'hint'];

    static values = {
        suggestUrl: String,
    };

    debounceTimer = null;
    abort = null;
    seq = 0;

    disconnect() {
        if (this.debounceTimer) {
            clearTimeout(this.debounceTimer);
        }
        this.abort?.abort();
    }

    nameInput() {
        if (this.debounceTimer) {
            clearTimeout(this.debounceTimer);
        }
        this.debounceTimer = setTimeout(() => this.suggest(), 280);
    }

    async suggest() {
        if (!this.hasNameTarget || !this.hasHintTarget) {
            return;
        }

        const name = this.nameTarget.value.trim();
        if (name.length < 2) {
            this.hintTarget.textContent = '';
            return;
        }

        this.abort?.abort();
        this.abort = new AbortController();
        const seq = ++this.seq;

        try {
            const url = new URL(this.suggestUrlValue, window.location.origin);
            url.searchParams.set('name', name);
            const response = await fetch(url, {
                headers: { Accept: 'application/json' },
                signal: this.abort.signal,
            });
            if (!response.ok || seq !== this.seq) {
                return;
            }
            const data = await response.json();
            if (data.path) {
                this.hintTarget.textContent = `Adresse: ${data.path}`;
            }
        } catch (error) {
            if (error?.name === 'AbortError') {
                return;
            }
            console.error(error);
        }
    }
}
