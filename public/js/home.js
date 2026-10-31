(() => {
    const root = document.getElementById('home-live-root');
    if (!root) return;
    const button = document.getElementById('home-refresh');
    const status = document.getElementById('home-live-status');
    const statusText = document.getElementById('home-status-text');
    const search = document.getElementById('home-search');
    const fragmentNames = ['stats','portals','catalog','example'];
    const lastHtml = new Map();
    let revision = root.dataset.homeRevision, pending = false, timer = null, failures = 0;
    const setStatus = (state, text) => { status.dataset.homeState = state; statusText.textContent = text; };
    const filter = () => {
        const query = (search?.value || '').trim().toLocaleLowerCase();
        const cards = [...root.querySelectorAll('[data-home-lesson-card]')];
        let visible = 0;
        cards.forEach(card => {
            card.hidden = !card.dataset.search.includes(query);
            if (!card.hidden) visible++;
        });
        document.getElementById('home-result-count').textContent = visible + ' of ' + cards.length + ' lessons shown';
        document.getElementById('home-no-matches').hidden = visible !== 0;
    };
    const schedule = delay => {
        clearTimeout(timer);
        timer = null;
        if (!document.hidden) timer = setTimeout(refresh,delay);
    };
    const refresh = async () => {
        if (pending || document.hidden) return;
        if (navigator.onLine === false) {
            setStatus('offline','You are offline. Your last loaded data is kept. Updates resume when you reconnect.');
            schedule(30000);
            return;
        }
        pending = true;
        button.disabled = true;
        setStatus('updating','Refreshing saved classroom data…');
        const controller = new AbortController();
        const deadline = setTimeout(() => controller.abort(),10000);
        let nextDelay = 15000, serverRetry = 0;
        try {
            const response = await fetch(root.dataset.homeEndpoint,{
                credentials:'same-origin',cache:'no-store',headers:{Accept:'application/json'},signal:controller.signal,
            });
            if (!response.ok) {
                serverRetry = Math.min(120000,Math.max(0,Number(response.headers.get('Retry-After')) || 0)*1000);
                throw new Error('Unable to refresh');
            }
            const data = await response.json();
            if (data.available !== true || typeof data.revision !== 'string' || typeof data.updated_label !== 'string'
                || !data.fragments || !fragmentNames.every(name => typeof data.fragments[name] === 'string')) throw new Error('Invalid update');
            const changed = revision !== data.revision;
            fragmentNames.forEach(name => {
                const slot = root.querySelector('[data-home-slot="' + name + '"]');
                const html = data.fragments[name];
                if (slot && changed && lastHtml.get(name) !== html) {
                    const wasOpen = slot.querySelector('[data-home-output]')?.open;
                    slot.innerHTML = html;
                    const exampleOutput = slot.querySelector('[data-home-output]');
                    if (exampleOutput && wasOpen !== undefined) exampleOutput.open = wasOpen;
                }
                lastHtml.set(name,html);
            });
            revision = data.revision;
            root.dataset.homeRevision = revision;
            const lastSuccess = document.getElementById('home-last-success');
            lastSuccess.textContent = data.updated_label;
            if (typeof data.updated_at === 'string') lastSuccess.setAttribute('datetime',data.updated_at);
            if (Number.isInteger(data.year)) document.querySelectorAll('[data-home-year]').forEach(year => { year.textContent = String(data.year); });
            if (changed) filter();
            failures = 0;
            setStatus('live','Live data loaded. Refreshes every 15 seconds while this page is visible.');
        } catch {
            failures++;
            nextDelay = Math.max(serverRetry,Math.min(60000,15000*2**Math.min(failures,2)));
            setStatus(navigator.onLine === false ? 'offline' : 'error','Live updates are temporarily unavailable. Your last loaded values are kept. Retrying automatically.');
        } finally {
            clearTimeout(deadline);
            pending = false;
            button.disabled = false;
            schedule(nextDelay);
        }
    };
    search?.addEventListener('input',filter);
    button.addEventListener('click',refresh);
    document.addEventListener('visibilitychange',() => {
        clearTimeout(timer); timer = null;
        if (!document.hidden) refresh();
    });
    window.addEventListener('online',refresh);
    window.addEventListener('offline',() => setStatus('offline','You are offline. Your last loaded data is kept.'));
    filter();
    schedule(15000);
})();
