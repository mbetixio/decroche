import { Controller } from '@hotwired/stimulus';
import { follow } from '../follow.js';

/**
 * A form whose work takes a while on the server (writing a call, a rehearsal, a debrief), followed step by step: it is
 * sent in the background, the steps the server reports are ticked off on a waiting screen, then the page the work
 * leads to opens. A form to correct comes back the usual way; without JavaScript, the form is sent as usual.
 *
 *   <form data-controller="follow" data-action="follow#submit" data-follow-overlay-value="draw-overlay">
 *
 * overlay: the id of the waiting screen holding the steps list ([data-steps]); auto: sent as soon as shown; stay: a
 * failure is shown here, with its retry button, instead of opening the page the server points to.
 */
export default class extends Controller {
    static values = { overlay: String, auto: Boolean, stay: Boolean };

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
        const overlay = document.getElementById(this.overlayValue);
        if (!overlay) {
            HTMLFormElement.prototype.submit.call(form);
            return;
        }
        const body = new FormData(form);
        if (submitter?.name) body.append(submitter.name, submitter.value);
        const list = overlay.querySelector('[data-steps]');
        const error = overlay.querySelector('[data-step-error]');
        const close = overlay.querySelector('[data-step-close]');
        list.replaceChildren();
        if (error) error.hidden = true;
        if (close) close.hidden = true;
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
            // Done: the page the work leads to. Failed: the page to go back to, unless this form stays (a page that
            // sends its work on its own must not be opened again, or it would try forever).
            if (end.url && (end.t === 'done' || !this.stayValue)) {
                window.location.assign(end.url);
                return;
            }
            throw new Error(end.error || '');
        } catch (e) {
            if (error) {
                error.textContent = e.message || error.dataset.fallback;
                error.hidden = false;
            }
            if (close) {
                close.hidden = false;
                // A close button dismisses the waiting screen; a submit button (retry) sends the form again.
                if (close.type !== 'submit') close.onclick = () => { overlay.hidden = true; };
            } else {
                overlay.hidden = true;
            }
            form.querySelectorAll('button').forEach((button) => { button.disabled = false; });
        }
    }
}
