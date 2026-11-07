import { Controller } from '@hotwired/stimulus';

/**
 * A Symfony form collection: adds a row from the prototype held in a <template> (its placeholder replaced by a fresh
 * index) and removes rows.
 */
export default class extends Controller {
    static targets = ['list', 'template'];
    static values = { index: Number, placeholder: { type: String, default: '__name__' } };

    add() {
        const html = this.templateTarget.innerHTML.replaceAll(this.placeholderValue, String(this.indexValue++));
        this.listTarget.insertAdjacentHTML('beforeend', html);
        this.listTarget.lastElementChild?.querySelector('input:not([type=hidden]), textarea')?.focus();
    }

    remove(event) {
        event.currentTarget.closest('.situation-card, .criterion-row')?.remove();
    }
}
