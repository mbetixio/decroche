import { Controller } from '@hotwired/stimulus';

/**
 * "Rehearse a meeting": what is asked of the seller follows the source they pick (a prospect sheet, or the transcript of
 * a real call). The field's label, its example and its help change with the choice.
 */
export default class extends Controller {
    static targets = ['text'];
    static values = { modes: Object };

    connect() {
        this.update();
    }

    update() {
        const checked = this.element.querySelector('input[type=radio]:checked');
        const mode = this.modesValue[checked && checked.value === '1' ? 'call' : 'sheet'];
        if (!mode || !this.hasTextTarget) return;
        const field = this.textTarget;
        field.placeholder = mode.placeholder;
        field.rows = mode.rows;
        const label = this.element.querySelector(`label[for="${field.id}"]`);
        if (label) label.textContent = mode.label;
        const help = document.getElementById(`${field.id}_help`);
        if (help) help.textContent = mode.help;
    }
}
