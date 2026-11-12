import { Controller } from '@hotwired/stimulus';
import { follow } from '../follow.js';

/**
 * A form whose work takes a while on the server (writing a call, a rehearsal, a debrief), followed step by step: it is
 * sent in the background, the steps the server reports are ticked off on a waiting screen, then the page the work
 * leads to opens. A form to correct comes back the usual way; without JavaScript, the form is sent as usual.
 *
 *   <form data-controller="follow" data-action="follow#submit" data-follow-overlay-value="draw-overlay">
 *
 * overlay: the id of the waiting screen holding the steps list ([data-steps]); auto: sent as soon as shown.
 */
export default class extends Controller {
    static values = { overlay: String, auto: Boolean };

    connect() {
        if (this.autoValue) this.send();
    }

    submit(event) {
        if (event.defaultPrevented) return;
        event.preventDefault();
        this.send(event.submitter);
    }

    async send(submitter = null) {
        const form = this.element;
        const body = new FormData(form);
        if (submitter?.name) body.append(submitter.name, submitter.value);
        const overlay = document.getElementById(this.overlayValue);
        const list = overlay.querySelector('[data-steps]');
        const error = overlay.querySelector('[data-step-error]');
        list.replaceChildren();
        if (error) error.hidden = true;
        overlay.hidden = false;
        form.querySelectorAll('button').forEach((button) => { button.disabled = true; });
        try {
            const response = await fetch(form.action, { method: 'POST', body, headers: { Accept: 'application/x-ndjson' } });
            if (response.status === 422) {
                // The form has something to correct: the page shows it.
                HTMLFormElement.prototype.submit.call(form);
                return;
            }
            const end = await follow(response, list);
            if (end.url) {
                window.location.assign(end.url);
                return;
            }
            throw new Error(end.error);
        } catch (e) {
            if (error) {
                error.textContent = e.message || error.dataset.fallback;
                error.hidden = false;
            }
            setTimeout(() => {
                overlay.hidden = !error;
                form.querySelectorAll('button').forEach((button) => { button.disabled = false; });
            }, error ? 0 : 4000);
        }
    }
}
