import { Controller } from '@hotwired/stimulus';

/**
 * "New call": a situation is drawn and written on the server (some fifteen seconds) behind a waiting screen, then the
 * briefing opens. An optional select or button value picks the situation.
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
            const response = await fetch(form.action, { method: 'POST', body, headers: { Accept: 'application/json' } });
            const data = await response.json();
            if (!response.ok || !data.url) throw new Error(data.error ?? '');
            window.location.assign(data.url);
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
