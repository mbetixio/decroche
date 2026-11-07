import { Controller } from '@hotwired/stimulus';

/**
 * Submits its form as soon as it is shown (the debrief to write, behind a waiting screen).
 */
export default class extends Controller {
    connect() {
        this.element.requestSubmit();
    }
}
