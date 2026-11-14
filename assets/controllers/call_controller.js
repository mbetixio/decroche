import { Controller } from '@hotwired/stimulus';

/*
 * The phone screen. The seller talks into the microphone (the browser's speech recognition), the persona answers out
 * loud: each sentence of its reply arrives as soon as it is written, with its neural voice synthesized by the server
 * (or the browser's own voice). No chat: the screen of a phone, a preview of what is understood, and the transcript only
 * after hanging up.
 *
 * End of turn: a sentence left hanging ("and", "because", "the"…) is waited for (2 s); a segment the recognition closed
 * leaves after 1 s, otherwise after 1.4 s. Barge-in: while the persona speaks the microphone stays open, and if the seller
 * really talks (not a mere "yes", and not the echo of the persona's voice), the persona stops and listens.
 */

// Words after which a sentence is not over, by language.
const HANGING = {
    fr: /(?:^|\s)(et|ou|mais|donc|car|puis|alors|aussi|parce|que|qui|quoi|dont|où|de|du|des|d|le|la|les|l|un|une|au|aux|à|en|dans|sur|pour|par|avec|sans|chez|vers|entre|ce|cet|cette|ces|son|sa|ses|notre|nos|votre|vos|leur|leurs|mon|ma|mes|je|j|tu|il|elle|on|nous|vous|ils|elles|est|sont|suis|sommes|êtes|ai|as|a|avons|avez|ont|c|ça|y|ne|n|pas|plus|très|si|comme|euh|bah|ben|voilà|enfin|genre|actuellement|justement|notamment|exemple)$/i,
    en: /(?:^|\s)(and|or|but|so|because|then|also|that|which|who|what|whose|where|of|the|a|an|to|in|on|at|for|by|with|without|from|into|about|between|this|these|those|my|your|our|their|his|her|its|i|you|we|they|he|she|it|is|are|am|was|were|be|have|has|had|do|does|not|very|if|like|uh|um|well|actually|basically|mean|example)$/i,
};

const norm = (text) => (text || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/[^a-z0-9 ]+/g, ' ');
const mmss = (n) => String(Math.floor(n / 60)).padStart(2, '0') + ':' + String(n % 60).padStart(2, '0');
const pref = (key, fallback) => { try { return localStorage.getItem('decroche-' + key) ?? fallback; } catch { return fallback; } };
const keep = (key, value) => { try { localStorage.setItem('decroche-' + key, value); } catch { /* private mode */ } };

export default class extends Controller {
    static targets = ['state', 'since', 'clock', 'caption', 'hint', 'keypad', 'input', 'keys', 'pickup', 'finish', 'sheet', 'dot', 'dots', 'barge', 'browserVoice', 'voiceNote'];
    static values = {
        talkUrl: String,
        token: String,
        language: String,
        speechLocale: String,
        persona: String,
        left: Number,
        elapsed: Number,
        incoming: Boolean,
        resumed: Boolean,
        opening: String,
        openingAudio: String,
        sampleAudio: String,
        edgeVoice: String,
        labels: Object,
    };

    connect() {
        this.Recognition = window.SpeechRecognition || window.webkitSpeechRecognition;
        this.who = this.personaValue.split(' ')[0];
        this.left = this.leftValue;
        this.since = this.elapsedValue;
        this.inCall = this.sending = this.over = this.muted = false;
        this.speaker = pref('speaker', '1') === '1';
        this.subs = pref('subs', '0') === '1';
        this.bargeTarget.checked = pref('barge', '1') === '1';
        if (this.hasBrowserVoiceTarget) this.browserVoiceTarget.checked = pref('browser-voice', '0') === '1';
        this.timers = new Set();
        this.tick = setInterval(() => this.second(), 1000);
        this.clockTarget.textContent = mmss(this.left);
        this.toggleKey('subs', this.subs);
        this.renderSpeaker();
        this.setSheet(window.innerWidth >= 900);
        if (this.resumedValue) this.stateTarget.textContent = this.label('resume');
        this.voiceNoteTarget.textContent = this.serverVoice() ? this.label('voice_server', { '%voice%': this.edgeVoiceValue.replace(/^[a-z]{2}-[A-Z]{2}-|Neural$/g, '') }) : this.label('voice_browser');
    }

    // Leaving the page ends everything: the microphone, the reply being streamed, the pending timers.
    disconnect() {
        clearInterval(this.tick);
        this.over = true;
        this.stopListening();
        this.unwatch();
        this.abortTurn?.();
        this.hush();
        this.timers.forEach(clearTimeout);
        this.timers.clear();
    }

    // A timer the page cancels when it is left.
    later(fn, ms) {
        const id = setTimeout(() => { this.timers.delete(id); fn(); }, ms);
        this.timers.add(id);

        return id;
    }

    label(key, params = {}) {
        let text = this.labelsValue[key] ?? key;
        for (const [k, v] of Object.entries(params)) text = text.replace(k, v);
        return text;
    }

    // ── The call ────────────────────────────────────────────────────────────────────────────────────────────────────

    async pickUp() {
        if (this.inCall || this.over) return;
        this.pickupTarget.hidden = true;
        this.finishTarget.hidden = false;
        if (!this.incomingValue && !this.resumedValue) {
            this.setState('ringing', this.label('ringing'));
            await this.ringback(1600);
            // Hung up while it rang: nobody picks up.
            if (this.over) return;
        }
        this.inCall = true;
        this.keysTarget.hidden = false;
        this.sinceTarget.hidden = false;
        this.hintTarget.textContent = '';
        if (!this.Recognition) {
            this.showKeypad(true);
            this.hintTarget.textContent = this.label('no_recognition');
        }
        if (this.openingValue) {
            this.cut = false;
            await this.speakLine(this.openingValue, this.openingAudioValue || null);
        }
        this.listen();
    }

    second() {
        this.clockTarget.textContent = mmss(this.left);
        this.clockTarget.parentElement.classList.toggle('late', this.left <= 60);
        if (!this.inCall || this.over) return;
        this.since++;
        this.sinceTarget.textContent = mmss(this.since);
        this.left = Math.max(0, this.left - 1);
        if (this.left === 0 && !this.sending) this.end(this.label('time_up'));
    }

    end(why) {
        if (this.over) return;
        this.over = true;
        this.stopListening();
        this.setState('ended', why);
        this.keysTarget.hidden = true;
        this.keypadTarget.hidden = true;
        this.hintTarget.textContent = this.label('analysing');
        this.later(() => this.finishTarget.requestSubmit(), 1500);
    }

    // Hanging up sends the call to the coach: the debrief takes some thirty seconds, behind a waiting screen.
    hangUp() {
        this.over = true;
        this.stopListening();
        this.hush();
        const overlay = document.getElementById('debrief-overlay');
        if (overlay) overlay.hidden = false;
    }

    // One exchange, streamed: each sentence is said as soon as it arrives, while the persona writes the next.
    async say(text, voiceMs = null) {
        text = text.trim();
        if (!text || this.sending || this.over) return;
        this.sending = true;
        this.stopListening();
        this.cut = false;
        this.heardSoFar = '';
        const interrupted = !!this.wasCut;
        this.wasCut = false;
        const abort = new AbortController();
        this.abortTurn = () => abort.abort();
        this.later(() => { if (this.sending) this.watch(); }, 300);
        this.show(text, 'me sent');
        this.setState('thinking', '');
        let chain = Promise.resolve(), result = null, first = true;
        try {
            const response = await fetch(this.talkUrlValue + (this.serverVoice() ? '' : '?browserVoice=1'), {
                method: 'POST',
                signal: abort.signal,
                headers: { 'Content-Type': 'application/json', Accept: 'application/x-ndjson', 'X-CSRF-Token': this.tokenValue },
                body: JSON.stringify({ message: text, voiceMs, interrupted }),
            });
            if (!response.ok) {
                const data = await response.json().catch(() => ({ error: this.label('crackled', { '%name%': this.who }) }));
                this.show('⚠️ ' + data.error, 'warn');
                if (data.ended) this.end(this.label('ended'));
                return;
            }
            const reader = response.body.getReader(), decoder = new TextDecoder();
            let buffer = '';
            for (;;) {
                const { value, done } = await reader.read();
                if (done) break;
                buffer += decoder.decode(value, { stream: true });
                let nl;
                while ((nl = buffer.indexOf('\n')) >= 0) {
                    const line = buffer.slice(0, nl).trim();
                    buffer = buffer.slice(nl + 1);
                    if (!line) continue;
                    const event = JSON.parse(line);
                    if (event.t === 'say') {
                        if (first) { first = false; if (!this.subs && this.speaker) this.show('', ''); }
                        chain = chain.then(() => this.sayPart(event.text, event.audio));
                    } else if (event.t === 'done') {
                        result = event;
                    } else if (event.t === 'error') {
                        chain = chain.then(() => this.show('⚠️ ' + event.error, 'warn'));
                    }
                }
            }
            if (result) {
                this.left = result.secondsLeft;
                this.dotTargets.forEach((dot, i) => dot.classList.toggle('on', i < result.revealed));
                this.dotsTarget.setAttribute('aria-label', this.dotsTarget.getAttribute('aria-label').replace(/\d+ \//, result.revealed + ' /'));
            }
            await chain;
            if (result?.ended) this.end(result.endedBy === 'persona' ? this.label('hung_up', { '%name%': this.who }) : this.label('time_up'));
        } catch {
            await chain;
            if (!this.barged) this.show('⚠️ ' + this.label('crackled', { '%name%': this.who }), 'warn');
        } finally {
            this.sending = false;
            this.abortTurn = null;
            this.unwatch();
            const seed = this.barged || '';
            this.barged = null;
            if (seed) this.wasCut = true;
            if (!this.over) this.later(() => this.listen(seed), seed ? 150 : 0);
        }
    }

    // ── The persona's voice ─────────────────────────────────────────────────────────────────────────────────────────

    serverVoice() {
        return !!this.edgeVoiceValue && pref('browser-voice', '0') !== '1';
    }

    async speakLine(text, audioUrl) {
        this.heardSoFar = text;
        if (this.subs || !this.speaker) this.show(text, 'them');
        if (!this.speaker) return;
        this.setState('speaking', this.label('speaking', { '%name%': this.who }));
        if (audioUrl && this.serverVoice()) {
            try { await this.play(audioUrl); return; } catch { /* the voice service is down: the browser's voice takes over */ }
        }
        await this.browserSpeak(text);
    }

    async sayPart(text, audio) {
        if (this.cut) return;
        this.heardSoFar = (this.heardSoFar ? this.heardSoFar + ' ' : '') + text;
        if (this.subs || !this.speaker) this.show(this.heardSoFar, 'them');
        if (!this.speaker) return;
        this.setState('speaking', this.label('speaking', { '%name%': this.who }));
        if (audio && this.serverVoice()) {
            const bytes = Uint8Array.from(atob(audio), (c) => c.charCodeAt(0));
            const src = URL.createObjectURL(new Blob([bytes], { type: 'audio/mpeg' }));
            try { await this.play(src); return; } catch { /* fall back on the browser's voice */ } finally { URL.revokeObjectURL(src); }
        }
        await this.browserSpeak(text);
    }

    play(url) {
        return new Promise((resolve, reject) => {
            const audio = new Audio(url);
            this.audio = audio;
            this.stopAudio = () => { audio.pause(); this.audio = null; resolve(); };
            audio.onended = () => { this.audio = null; resolve(); };
            audio.onerror = () => { this.audio = null; reject(new Error('audio')); };
            audio.play().catch(reject);
        });
    }

    browserSpeak(text) {
        if (!('speechSynthesis' in window)) return Promise.resolve();
        return new Promise((resolve) => {
            const utterance = new SpeechSynthesisUtterance(text);
            const voice = this.browserVoice();
            utterance.lang = voice ? voice.lang : this.speechLocaleValue;
            utterance.rate = 1.12;
            if (voice) utterance.voice = voice;
            utterance.onend = utterance.onerror = () => resolve();
            speechSynthesis.speak(utterance);
        });
    }

    // The most natural voice of the browser in the company's language.
    browserVoice() {
        const voices = speechSynthesis.getVoices().filter((v) => v.lang?.toLowerCase().startsWith(this.languageValue));
        const score = (v) => (/natural|neural|online|premium/i.test(v.name) ? 50 : 0) + (/enhanced|google/i.test(v.name) ? 20 : 0) + (v.lang.replace('_', '-') === this.speechLocaleValue ? 10 : 0);
        return voices.sort((a, b) => score(b) - score(a))[0] ?? null;
    }

    hush() {
        this.cut = true;
        if (this.audio && this.stopAudio) this.stopAudio();
        if ('speechSynthesis' in window) speechSynthesis.cancel();
    }

    // A click on the persona's avatar cuts them off.
    interrupt() {
        if (this.element.dataset.state === 'speaking') this.hush();
    }

    // ── Listening ───────────────────────────────────────────────────────────────────────────────────────────────────

    recognizer() {
        const rec = new this.Recognition();
        rec.lang = this.speechLocaleValue;
        rec.continuous = true;
        rec.interimResults = true;
        return rec;
    }

    listen(seed = '') {
        if (!this.Recognition || this.muted || this.over || this.sending || this.listening || !this.inCall) {
            if (this.inCall && !this.sending && !this.over) this.setState('waiting', this.label(this.muted ? 'mic_off' : 'your_turn'));
            return;
        }
        this.finalText = seed ? seed + ' ' : '';
        this.startedAt = seed ? performance.now() : 0;
        this.lastAt = this.startedAt;
        const rec = this.recognizer();
        this.rec = rec;
        rec.onresult = (e) => {
            let interim = '';
            for (let i = e.resultIndex; i < e.results.length; i++) {
                if (e.results[i].isFinal) this.finalText += e.results[i][0].transcript + ' ';
                else interim += e.results[i][0].transcript;
            }
            const now = performance.now();
            if (!this.startedAt) this.startedAt = now;
            this.lastAt = now;
            this.heard = (this.finalText + interim).trim();
            this.show(this.heard, 'me');
            this.setState('talking', this.label('you_talk'));
            clearTimeout(this.timer);
            this.timer = setTimeout(() => this.turnDone(), this.pause(this.heard, interim));
        };
        rec.onerror = (e) => {
            if (e.error === 'not-allowed' || e.error === 'service-not-allowed') {
                this.muted = true;
                this.toggleKey('mute', true);
                this.show(this.label('mic_blocked_help'), 'warn');
                this.setState('waiting', this.label('mic_blocked'));
                this.showKeypad(true);
            }
        };
        rec.onend = () => {
            this.listening = false;
            if (!this.sending && !this.over && !this.muted && this.inCall && !this.sendingVoice) this.later(() => this.listen(), 200);
        };
        try {
            rec.start();
            this.listening = true;
            this.heard = seed;
            if (seed) {
                this.show(seed, 'me');
                this.setState('talking', this.label('you_talk'));
                this.timer = setTimeout(() => this.turnDone(), 1400);
            } else {
                this.setState('listening', this.label('your_turn'));
            }
        } catch { /* already started */ }
    }

    pause(heard, interim) {
        const hanging = HANGING[this.languageValue] ?? HANGING.en;
        if (hanging.test((heard || '').trim().replace(/[.,!?…]+$/, ''))) return 2000;
        return interim.trim() === '' ? 1000 : 1400;
    }

    turnDone() {
        const text = (this.heard || '').trim();
        if (!text) return;
        const ms = Math.round(this.lastAt - this.startedAt) + 600;
        this.sendingVoice = true;
        clearTimeout(this.timer);
        try { this.rec?.stop(); } catch { /* already stopped */ }
        this.listening = false;
        this.say(text, ms).finally(() => { this.sendingVoice = false; });
    }

    stopListening() {
        clearTimeout(this.timer);
        try { this.rec?.abort(); } catch { /* already stopped */ }
        this.listening = false;
    }

    // Barge-in: is the microphone hearing the seller, or the echo of the persona's own voice?
    echo(text) {
        const said = ' ' + norm(this.heardSoFar) + ' ';
        const words = norm(text).split(/\s+/).filter((w) => w.length > 2);
        if (!words.length) return true;
        return words.filter((w) => said.includes(' ' + w + ' ')).length / words.length >= 0.5;
    }

    watch() {
        if (!this.Recognition || this.muted || this.over || !this.inCall || pref('barge', '1') !== '1' || this.watching) return;
        const rec = this.recognizer();
        this.watchRec = rec;
        this.watching = true;
        rec.onresult = (e) => {
            let text = '';
            for (let i = 0; i < e.results.length; i++) text += e.results[i][0].transcript + ' ';
            text = text.trim();
            if (text.split(/\s+/).length >= 4 && !this.echo(text)) this.bargeIn(text);
        };
        rec.onend = () => { this.watching = false; if (this.sending && !this.over && !this.barged) this.later(() => this.watch(), 150); };
        try { rec.start(); } catch { this.watching = false; }
    }

    unwatch() {
        this.watching = false;
        try { this.watchRec?.abort(); } catch { /* already stopped */ }
        this.watchRec = null;
    }

    bargeIn(text) {
        if (this.barged) return;
        this.barged = text;
        this.unwatch();
        this.hush();
        this.abortTurn?.();
        this.show(text, 'me');
        this.setState('talking', this.label('you_talk'));
    }

    // ── Keys and sheet ──────────────────────────────────────────────────────────────────────────────────────────────

    toggleMute() {
        this.muted = !this.muted;
        this.toggleKey('mute', this.muted);
        if (this.muted) {
            this.stopListening();
            if (!this.sending) this.setState('waiting', this.label('mic_off'));
        } else {
            this.listen();
        }
    }

    toggleSpeaker() {
        this.speaker = !this.speaker;
        keep('speaker', this.speaker ? '1' : '0');
        this.renderSpeaker();
        if (!this.speaker) this.hush();
    }

    toggleSubs() {
        this.subs = !this.subs;
        keep('subs', this.subs ? '1' : '0');
        this.toggleKey('subs', this.subs);
    }

    toggleKeypad() {
        this.showKeypad(this.keypadTarget.hidden);
    }

    // Opened from its key, the sheet takes the focus; closed (its button, Escape), the focus goes back to the key.
    toggleSheet() {
        const open = this.sheetTarget.hidden;
        this.setSheet(open);
        if (open) this.element.querySelector('#sheet-title')?.focus();
    }

    closeSheet() {
        if (this.sheetTarget.hidden) return;
        this.setSheet(false);
        this.element.querySelector('[data-key="sheet"]')?.focus();
    }

    toggleBarge() {
        keep('barge', this.bargeTarget.checked ? '1' : '0');
        if (!this.bargeTarget.checked) this.unwatch();
    }

    toggleBrowserVoice(event) {
        keep('browser-voice', event.target.checked ? '1' : '0');
    }

    testVoice() {
        this.hush();
        this.cut = false;
        if (this.serverVoice() && this.sampleAudioValue) {
            this.play(this.sampleAudioValue).catch(() => {});
        } else {
            this.browserSpeak(this.label('sample', { '%name%': this.personaValue }));
        }
    }

    type(event) {
        event.preventDefault();
        const text = this.inputTarget.value;
        this.inputTarget.value = '';
        this.say(text);
    }

    // ── Helpers ─────────────────────────────────────────────────────────────────────────────────────────────────────

    // The state line is a live region: it is only written when it changes, so a screen reader says it once.
    setState(state, text) {
        this.element.dataset.state = state;
        if (this.stateTarget.textContent !== text) this.stateTarget.textContent = text;
    }

    show(text, cls = '') {
        this.captionTarget.textContent = text;
        this.captionTarget.className = 'cs-caption ' + cls;
        // A long reply scrolls within the caption, its latest words in view.
        this.captionTarget.scrollTop = this.captionTarget.scrollHeight;
    }

    toggleKey(name, on) {
        this.element.querySelector(`[data-key="${name}"]`)?.setAttribute('aria-pressed', on ? 'true' : 'false');
    }

    renderSpeaker() {
        this.toggleKey('speaker', !this.speaker);
    }

    showKeypad(open) {
        this.keypadTarget.hidden = !open;
        this.toggleKey('keypad', open);
        if (open) this.inputTarget.focus();
    }

    setSheet(open) {
        this.sheetTarget.hidden = !open;
        this.element.classList.toggle('with-sheet', open);
        this.toggleKey('sheet', open);
    }

    // The ringback tone (440 Hz) while "it rings".
    ringback(ms) {
        return new Promise((resolve) => {
            try {
                const ctx = new (window.AudioContext || window.webkitAudioContext)();
                const osc = ctx.createOscillator(), gain = ctx.createGain();
                osc.frequency.value = 440;
                gain.gain.value = 0.06;
                osc.connect(gain).connect(ctx.destination);
                osc.start();
                setTimeout(() => { osc.stop(); ctx.close(); resolve(); }, ms);
            } catch {
                setTimeout(resolve, ms);
            }
        });
    }
}
