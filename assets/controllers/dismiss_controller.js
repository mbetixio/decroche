import { Controller } from '@hotwired/stimulus';

/**
 * A message that goes away by itself after a delay, or when closed.
 */
export default class extends Controller {
    static values = { delay: { type: Number, default: 5000 } };

    connect() {
        this.timer = setTimeout(() => this.close(), this.delayValue);
    }

    disconnect() {
        clearTimeout(this.timer);
    }

    close() {
        this.element.animate([{ opacity: 1 }, { opacity: 0, transform: 'translateY(-6px)' }], { duration: 200 }).onfinish = () => this.element.remove();
    }
}
