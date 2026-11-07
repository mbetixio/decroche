import { Controller } from '@hotwired/stimulus';

/**
 * The analysis screen: starts the analysis on the server (a minute or two) and walks through its steps meanwhile;
 * goes to the next onboarding step when it is done, offers to retry when it fails.
 */
export default class extends Controller {
    static targets = ['step', 'status', 'retry'];
    static values = { url: String, token: String };

    connect() {
        this.run();
    }

    disconnect() {
        clearInterval(this.timer);
    }

    async run() {
        this.retryTarget.hidden = true;
        this.statusTarget.classList.remove('bad');
        let current = 0;
        this.show(current);
        this.timer = setInterval(() => this.show(current = Math.min(current + 1, this.stepTargets.length - 1)), 9000);
        try {
            const body = new FormData();
            body.append('token', this.tokenValue);
            const response = await fetch(this.urlValue, { method: 'POST', body, headers: { Accept: 'application/json' } });
            const data = await response.json();
            clearInterval(this.timer);
            if (!response.ok) {
                throw new Error(data.message ?? data.error);
            }
            this.show(this.stepTargets.length);
            window.location.assign(data.next);
        } catch (error) {
            clearInterval(this.timer);
            this.statusTarget.textContent = error.message || '…';
            this.retryTarget.hidden = false;
        }
    }

    show(index) {
        this.stepTargets.forEach((step, i) => {
            step.classList.toggle('done', i < index);
            step.classList.toggle('active', i === index);
        });
    }
}
