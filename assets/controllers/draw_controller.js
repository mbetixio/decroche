import { Controller } from '@hotwired/stimulus';
import { follow } from '../follow.js';

/**
 * "New call": a situation is drawn and written on the server behind a waiting screen, its steps ticked off as they
 * come, then the briefing opens. An optional select or button value picks the situation.
 */
export default class extends Controller {
    static targets = ['overlay', 'error'];

    async submit(event) {
        event.preventDefault();
        const form = event.target.closest('form');
        const body = new FormData(form);
        if (event.submitter?.name) body.append(event.submitter.name, event.submitter.value);
        const overlay = this.hasOverlayTarget ? this.overlayTarget : document.getElementById('draw-overlay');
        if (overlay) overlay.hidden = false;
        if (this.hasErrorTarget) this.errorTarget.hidden = true;
        try {
            // The steps (situation drawn, latest calls checked, call written…) are ticked off as the server reports them.
            const list = overlay?.querySelector('[data-steps]');
            list?.replaceChildren();
            const response = await fetch(form.action, { method: 'POST', body, headers: { Accept: 'application/x-ndjson' } });
            const end = list ? await follow(response, list) : { t: 'error' };
            if (end.t !== 'done') throw new Error(end.error ?? '');
            window.location.assign(end.url);
        } catch (e) {
            if (overlay) overlay.hidden = true;
            if (this.hasErrorTarget) {
                this.errorTarget.textContent = e.message || this.errorTarget.dataset.fallback;
                this.errorTarget.hidden = false;
            } else {
                alert(e.message);
            }
        }
    }
}
