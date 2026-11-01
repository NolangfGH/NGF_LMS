(() => {
    const root = document.querySelector('[data-grading-sheet]');
    if (!root) return;
    const live = document.getElementById('grading-live');
    const status = root.querySelector('[data-grading-status]');
    let dirty = root.dataset.hasUnsaved === 'true';
    let busy = false;
    let stopped = false;
    const announce = text => { if (status) status.textContent = text; };
    const syncScores = () => {
        root.querySelectorAll('[data-score-mode]').forEach(select => {
            const input = select.closest('fieldset')?.querySelector('[data-score-value]');
            if (input) input.disabled = select.value !== 'scored';
        });
    };
    const syncEntryModes = () => {
        root.querySelectorAll('[data-assessment-create]').forEach(form => {
            const mode = form.querySelector('[data-grading-entry-mode]');
            const source = form.querySelector('[data-grading-source]');
            const fields = form.querySelector('[data-grading-source-fields]');
            if (!mode || !source || !fields) return;
            const automatic = mode.value === 'automatic';
            source.disabled = !automatic;
            source.required = automatic;
            fields.hidden = !automatic;
        });
    };
    const maskedLinks = () => {
        root.querySelectorAll('a').forEach(link => {
            const url = new URL(link.href, window.location.href);
            if (url.origin !== window.location.origin || !url.pathname.startsWith('/grading-sheet')) return;
            if (root.dataset.masked === 'true') url.searchParams.set('mask', '1');
            else url.searchParams.delete('mask');
            link.href = url.href;
        });
    };
    const editing = () => dirty || Boolean(document.activeElement?.closest('[data-grading-edit]'));
    root.addEventListener('input', event => {
        if (event.target.closest('[data-grading-edit]')) {
            dirty = true;
            announce('Refresh paused while you edit. Save your changes or reload to discard them.');
        }
    });
    root.addEventListener('change', event => {
        if (event.target.closest('[data-grading-edit]')) dirty = true;
        if (event.target.matches('[data-score-mode]')) syncScores();
        if (event.target.matches('[data-grading-entry-mode]')) syncEntryModes();
        if (event.target.matches('[data-grading-source]')) {
            const option = event.target.selectedOptions[0];
            const form = event.target.closest('form');
            if (option?.dataset.max) {
                form.elements.max_score.value = option.dataset.max;
                form.elements.title.value = option.dataset.title;
                form.elements.category.value = option.dataset.category;
                form.elements.due_date.value = option.dataset.due || '';
            }
        }
    });
    root.querySelector('[data-grading-mask]')?.addEventListener('click', event => {
        root.dataset.masked = String(root.dataset.masked !== 'true');
        event.currentTarget.setAttribute('aria-pressed', root.dataset.masked);
        event.currentTarget.textContent = 'Classroom display: ' + (root.dataset.masked === 'true' ? 'aliases' : 'names');
        maskedLinks();
    });
    const refresh = async () => {
        if (busy || stopped || !live || document.hidden || root.dataset.print === 'true') return;
        if (editing()) {
            announce('Refresh paused while you edit. Save your changes or reload to discard them.');
            return;
        }
        busy = true;
        try {
            const url = new URL(root.dataset.statusUrl, window.location.href);
            if (root.dataset.masked === 'true') url.searchParams.set('mask', '1');
            else url.searchParams.delete('mask');
            const response = await fetch(url.href, {headers: {'Accept': 'application/json'}, cache: 'no-store'});
            if ([401, 403, 419].includes(response.status)) {
                stopped = true;
                announce('Your session has ended. Sign in again to view or update grading records.');
                return;
            }
            if (!response.ok) throw new Error('refresh');
            const data = await response.json();
            // An edit can begin while a refresh is in flight; never overwrite it.
            if (!editing() && typeof data.html === 'string') {
                live.innerHTML = data.html;
                syncScores();
                syncEntryModes();
                maskedLinks();
                announce('Learning records are up to date. Refreshing every 15 seconds.');
            }
        } catch {
            announce('Live refresh is temporarily unavailable. Your saved records remain available; try Refresh data.');
        } finally { busy = false; }
    };
    root.querySelector('[data-grading-refresh]')?.addEventListener('click', refresh);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) refresh(); });
    syncScores();
    syncEntryModes();
    maskedLinks();
    if (root.dataset.print === 'true') Promise.resolve(document.fonts?.ready).then(() => window.print());
    else setInterval(refresh, 15000);
})();
