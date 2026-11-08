import { Controller } from '@hotwired/stimulus';

/**
 * The call log that writes itself: each line appears in turn and its words are typed, then the log starts again.
 * With reduced motion, every line is shown at once.
 */
export default class extends Controller {
    static targets = ['line'];

    connect() {
        this.texts = this.lineTargets.map((line) => line.querySelector('span').innerHTML);
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
        this.play();
    }

    disconnect() {
        clearTimeout(this.timer);
    }

    async play() {
        this.lineTargets.forEach((line) => { line.hidden = true; });
        for (const [i, line] of this.lineTargets.entries()) {
            await this.wait(i === 0 ? 400 : 650);
            line.hidden = false;
            await this.type(line.querySelector('span'), this.texts[i]);
        }
        await this.wait(5000);
        this.play();
    }

    async type(span, html) {
        // Rich lines (a score in bold) appear whole; plain ones are typed.
        if (html.includes('<')) { span.innerHTML = html; return; }
        span.textContent = '';
        for (let n = 1; n <= html.length; n += 2) {
            span.textContent = html.slice(0, n);
            await this.wait(18);
        }
        span.innerHTML = html;
    }

    wait(ms) {
        return new Promise((resolve) => { this.timer = setTimeout(resolve, ms); });
    }
}
