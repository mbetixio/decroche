import { Controller } from '@hotwired/stimulus';

/**
 * A message that goes away by itself after a delay (0: never, for an error the user must be able to read), or when
 * closed. The delay waits while the pointer or the focus is on the message.
 */
export default class extends Controller {
    static values = { delay: { type: Number, default: 5000 } };

    connect() {
        this.start();
        this.element.addEventListener('mouseenter', () => clearTimeout(this.timer));
        this.element.addEventListener('focusin', () => clearTimeout(this.timer));
        this.element.addEventListener('mouseleave', () => this.start());
    }

    start() {
        clearTimeout(this.timer);
        if (this.delayValue > 0) this.timer = setTimeout(() => this.close(), this.delayValue);
    }

    disconnect() {
        clearTimeout(this.timer);
    }

    close() {
        this.element.animate([{ opacity: 1 }, { opacity: 0, transform: 'translateY(-6px)' }], { duration: 200 }).onfinish = () => this.element.remove();
    }
}
