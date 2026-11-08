import { Controller } from '@hotwired/stimulus';

/**
 * A form whose work takes a while on the server (writing a rehearsal, analysing a website): on submit, its button is
 * disabled (no double send) and an optional waiting screen, found by id, is shown.
 *
 *   <form data-controller="busy" data-action="busy#start" data-busy-overlay-value="draw-overlay">
 */
export default class extends Controller {
    static values = { overlay: String };

    start(event) {
        if (event.defaultPrevented) return;
        // Disabled after the submit has been taken into account, so that the button's own name/value is still sent.
        setTimeout(() => {
            this.element.querySelectorAll('button').forEach((button) => { button.disabled = true; });
            if (this.overlayValue) {
                const overlay = document.getElementById(this.overlayValue);
                if (overlay) overlay.hidden = false;
            }
        }, 0);
    }
}
