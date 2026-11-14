/**
 * Follows a long piece of work told step by step by the server (JSON lines, see App\Http\ProgressStream): each step
 * is added to the list as it starts, the one before it ticked off, so the user sees where the work stands. Resolves
 * with the last line: {t: 'done', url} or {t: 'error', error, url?}. An answer that is not a stream of steps (an
 * expired session, a server error page) ends as an error with no message, so the page's own wording is shown.
 *
 * The list's items are cloned from a <template data-step-template> found in the list's container.
 */
export async function follow(response, list) {
    const failed = { t: 'error', error: '' };
    if (!response.ok || !(response.headers.get('Content-Type') || '').includes('ndjson')) return failed;
    const template = list.parentElement.querySelector('template[data-step-template]');
    const add = (label) => {
        tick(list);
        if (!template) return;
        const item = template.content.firstElementChild.cloneNode(true);
        item.classList.add('active');
        item.querySelector('[data-step-label]').textContent = label;
        list.append(item);
    };
    let end = failed;
    const read = (line) => {
        if (!line.trim()) return;
        let event;
        try { event = JSON.parse(line); } catch { return; }
        if (event.t === 'step') add(event.label);
        else end = event;
    };
    const reader = response.body.getReader();
    const decoder = new TextDecoder();
    let buffer = '';
    try {
        for (;;) {
            const { value, done } = await reader.read();
            if (done) break;
            buffer += decoder.decode(value, { stream: true });
            let cut;
            while ((cut = buffer.indexOf('\n')) >= 0) {
                read(buffer.slice(0, cut));
                buffer = buffer.slice(cut + 1);
            }
        }
        read(buffer);
    } finally {
        reader.releaseLock();
    }
    if (end.t === 'done') tick(list);

    return end;
}

/** The step in progress is done. */
function tick(list) {
    list.querySelectorAll('.active').forEach((item) => item.classList.replace('active', 'done'));
}
