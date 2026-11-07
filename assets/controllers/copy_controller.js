import { Controller } from '@hotwired/stimulus';

/**
 * Copies a value (an invitation link, an MCP address) to the clipboard, and says so for a moment.
 */
export default class extends Controller {
    static targets = ['source', 'label'];

    async copy() {
        const value = this.sourceTarget.value ?? this.sourceTarget.textContent;
        try {
            await navigator.clipboard.writeText(value);
        } catch {
            this.sourceTarget.select?.();
            document.execCommand('copy');
        }
        if (this.hasLabelTarget) {
            const before = this.labelTarget.textContent;
            this.labelTarget.textContent = '✓';
            setTimeout(() => { this.labelTarget.textContent = before; }, 1500);
        }
    }
}
