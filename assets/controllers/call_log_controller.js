import { Controller } from '@hotwired/stimulus';

/**
 * The call log that writes itself: each line appears in turn and its words are typed, then the log starts again.
 *
 * Nothing moves while it writes: every line keeps its place in the layout from the start (a pending line is only
 * invisible, see .call-log .is-pending), and the words still to type are kept as invisible text, so a line has its
 * final height before its first letter. With reduced motion, the whole log is shown at once.
 */
export default class extends Controller {
    static targets = ['line'];

    connect() {
        this.texts = this.lineTargets.map((line) => line.querySelector('span').innerHTML);
        this.motion = window.matchMedia('(prefers-reduced-motion: reduce)');
        if (this.motion.matches) return;
        this.play();
    }

    disconnect() {
        this.stopped = true;
        clearTimeout(this.timer);
    }

    async play() {
        this.lineTargets.forEach((line, i) => {
            line.classList.add('is-pending');
            line.querySelector('span').innerHTML = this.texts[i];
        });
        for (const [i, line] of this.lineTargets.entries()) {
            await this.wait(i === 0 ? 400 : 650);
            if (this.stopped) return;
            line.classList.remove('is-pending');
            await this.type(line.querySelector('span'), this.texts[i]);
        }
        await this.wait(5000);
        if (!this.stopped) this.play();
    }

    // Types a plain line two characters at a time; a rich line (a score in bold) appears whole.
    async type(span, html) {
        if (html.includes('<')) { span.innerHTML = html; return; }
        const text = span.textContent;
        const typed = document.createTextNode('');
        const rest = document.createElement('span');
        rest.className = 'call-log-rest';
        rest.textContent = text;
        span.replaceChildren(typed, rest);
        for (let n = 1; n <= text.length; n += 2) {
            typed.data = text.slice(0, n);
            rest.textContent = text.slice(n);
            await this.wait(18);
            if (this.stopped) return;
        }
        span.innerHTML = html;
    }

    wait(ms) {
        return new Promise((resolve) => { this.timer = setTimeout(resolve, ms); });
    }
}
