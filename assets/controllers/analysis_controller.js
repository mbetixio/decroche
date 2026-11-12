import { Controller } from '@hotwired/stimulus';
import { follow } from '../follow.js';

/**
 * The analysis screen: starts the analysis on the server (a minute or two) and ticks its steps off as the server
 * reports them (the website read, the offer sheet written, the situations written); goes to the next onboarding step
 * when it is done, offers to retry when it fails.
 */
export default class extends Controller {
    static targets = ['status', 'retry'];
    static values = { url: String, token: String };

    connect() {
        this.run();
    }

    async run() {
        this.retryTarget.hidden = true;
        const list = this.element.querySelector('[data-steps]');
        list.replaceChildren();
        try {
            const body = new FormData();
            body.append('token', this.tokenValue);
            const response = await fetch(this.urlValue, { method: 'POST', body, headers: { Accept: 'application/x-ndjson' } });
            const end = await follow(response, list);
            if (end.t !== 'done') throw new Error(end.error);
            window.location.assign(end.url);
        } catch (error) {
            this.statusTarget.textContent = error.message || '…';
            this.retryTarget.hidden = false;
        }
    }
}
