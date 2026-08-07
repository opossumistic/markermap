import { Controller } from '@hotwired/stimulus';

/**
 * Suggests URL slug from map name; stops once the user edits the slug manually.
 */
export default class extends Controller {
    static targets = ['name', 'slug', 'hint'];

    static values = {
        suggestUrl: String,
    };

    slugTouched = false;
    debounceTimer = null;
    abort = null;
    seq = 0;

    connect() {
        if (this.hasSlugTarget && this.slugTarget.value.trim() !== '') {
            this.slugTouched = true;
        }
    }

    disconnect() {
        if (this.debounceTimer) {
            clearTimeout(this.debounceTimer);
        }
        this.abort?.abort();
    }

    nameInput() {
        if (this.slugTouched) {
            return;
        }
        this.scheduleSuggest();
    }

    slugInput() {
        this.slugTouched = this.slugTarget.value.trim() !== '';
    }

    scheduleSuggest() {
        if (this.debounceTimer) {
            clearTimeout(this.debounceTimer);
        }
        this.debounceTimer = setTimeout(() => this.suggest(), 280);
    }

    async suggest() {
        if (this.slugTouched || !this.hasNameTarget || !this.hasSlugTarget) {
            return;
        }

        const name = this.nameTarget.value.trim();
        if (name.length < 2) {
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
            if (!response.ok || seq !== this.seq || this.slugTouched) {
                return;
            }
            const data = await response.json();
            if (!data.slug || this.slugTouched) {
                return;
            }
            this.slugTarget.value = data.slug;
            if (this.hasHintTarget && data.path) {
                this.hintTarget.textContent = `Wird zu ${data.path}`;
            }
        } catch (error) {
            if (error?.name === 'AbortError') {
                return;
            }
            console.error(error);
        }
    }
}
