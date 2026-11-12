/**
 * Follows a long piece of work told step by step by the server (JSON lines, see App\Http\ProgressStream): each step
 * is added to the list as it starts, the one before it ticked off, so the user sees where the work stands. Resolves
 * with the last line: {t: 'done', url} or {t: 'error', error, url?}.
 *
 * The list's items are cloned from a <template data-step-template> found in the list's container.
 */
export async function follow(response, list) {
    const template = list.parentElement.querySelector('template[data-step-template]');
    const add = (label) => {
        tick(list);
        const item = template.content.firstElementChild.cloneNode(true);
        item.classList.add('active');
        item.querySelector('[data-step-label]').textContent = label;
        list.append(item);
    };
    const reader = response.body.getReader();
    const decoder = new TextDecoder();
    let buffer = '';
    let end = { t: 'error', error: '' };
    for (;;) {
        const { value, done } = await reader.read();
        if (done) break;
        buffer += decoder.decode(value, { stream: true });
        let cut;
        while ((cut = buffer.indexOf('\n')) >= 0) {
            const line = buffer.slice(0, cut).trim();
            buffer = buffer.slice(cut + 1);
            if (!line) continue;
            const event = JSON.parse(line);
            if (event.t === 'step') add(event.label);
            else end = event;
        }
    }
    if (end.t === 'done') tick(list);

    return end;
}

/** The step in progress is done. */
function tick(list) {
    list.querySelectorAll('.active').forEach((item) => item.classList.replace('active', 'done'));
}
