import { Controller } from '@hotwired/stimulus';

/**
 * The working language picked on the first step: the page switches to it at once, as a preview.
 */
export default class extends Controller {
    preview(event) {
        const url = new URL(window.location.href);
        url.searchParams.set('lang', event.target.value);
        window.location.replace(url);
    }
}
