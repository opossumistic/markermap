import { Controller } from '@hotwired/stimulus';

/**
 * Auto-dismiss flash toasts after a short delay (click dismisses early).
 */
export default class extends Controller {
    static values = {
        delay: { type: Number, default: 5500 },
    };

    connect() {
        this.element.addEventListener('click', this.dismiss);
        this.timeout = window.setTimeout(() => this.dismiss(), this.delayValue);
    }

    disconnect() {
        this.element.removeEventListener('click', this.dismiss);
        clearTimeout(this.timeout);
    }

    dismiss = () => {
        clearTimeout(this.timeout);
        if (this.element.classList.contains('is-leaving')) {
            return;
        }
        this.element.classList.add('is-leaving');
        window.setTimeout(() => this.element.remove(), 280);
    };
}
