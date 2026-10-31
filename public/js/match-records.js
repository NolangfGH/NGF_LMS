(() => {
    const roots = [...document.querySelectorAll('[data-match-records]')];
    let busy = false;
    async function refresh() {
        if (busy || document.hidden) return;
        busy = true;
        try {
            for (const root of roots) {
                if (root.contains(document.activeElement)) continue;
                const response = await fetch(root.dataset.matchRecords, {headers: {Accept:'application/json'}, cache:'no-store'});
                if (response.ok) { const data = await response.json(); if (root.innerHTML !== data.html) root.innerHTML = data.html; }
            }
        } catch {} finally { busy = false; }
    }
    if (roots.length) setInterval(refresh, 8000);
    document.addEventListener('visibilitychange',refresh);
})();
