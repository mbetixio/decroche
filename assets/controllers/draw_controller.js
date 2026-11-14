import { Controller } from '@hotwired/stimulus';
import { follow } from '../follow.js';

/**
 * "New call": a situation is drawn and written on the server behind a waiting screen, its steps ticked off as they
 * come, then the briefing opens. An optional select or button value picks the situation. A failure is said on the
 * waiting screen, which a button closes.
 */
export default class extends Controller {
    static targets = ['overlay'];

    async submit(event) {
        event.preventDefault();
        const form = event.currentTarget.closest('form') ?? event.target.closest('form');
        const body = new FormData(form);
        if (event.submitter?.name) body.append(event.submitter.name, event.submitter.value);
        const overlay = this.hasOverlayTarget ? this.overlayTarget : document.getElementById('draw-overlay');
        if (!overlay) {
            form.submit();
            return;
        }
        const list = overlay.querySelector('[data-steps]');
        const error = overlay.querySelector('[data-step-error]');
        const close = overlay.querySelector('[data-step-close]');
        list.replaceChildren();
        error.hidden = true;
        close.hidden = true;
        overlay.hidden = false;
        try {
            // The steps (situation drawn, latest calls checked, call written…) are ticked off as the server reports them.
            const response = await fetch(form.action, { method: 'POST', body, headers: { Accept: 'application/x-ndjson' } });
            const end = await follow(response, list);
            if (end.t !== 'done') throw new Error(end.error || '');
            window.location.assign(end.url);
        } catch (e) {
            error.textContent = e.message || error.dataset.fallback;
            error.hidden = false;
            close.hidden = false;
            close.onclick = () => { overlay.hidden = true; };
        }
    }
}
