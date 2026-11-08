import { Controller } from '@hotwired/stimulus';

/**
 * Asks before a form with a consequence is sent (abandon a call, replace a key, reset a password).
 *
 *   <form data-controller="confirm" data-action="confirm#ask" data-confirm-message-value="Abandon this call?">
 */
export default class extends Controller {
    static values = { message: String };

    ask(event) {
        if (event.type !== 'submit') return;
        if (!window.confirm(this.messageValue)) event.preventDefault();
    }
}
