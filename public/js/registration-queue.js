(() => {
    const panel = document.getElementById('registration-queue');
    if (!panel) return;
    const message = document.getElementById('registration-live');
    let busy = false;
    let editing = false;
    panel.addEventListener('input', () => { editing = true; });
    panel.addEventListener('submit', event => {
        if (event.submitter?.value === 'rejected') {
            const note = event.target.querySelector('[name="note"]');
            if (!note.value.trim()) {
                event.preventDefault();
                note.setCustomValidity('Enter a reason before declining this registration.');
                note.reportValidity();
                note.addEventListener('input', () => note.setCustomValidity(''), {once: true});
            }
        }
    });
    async function refresh() {
        if (busy || document.hidden) return;
        if (editing || panel.contains(document.activeElement)) {
            message.textContent = 'Automatic refresh paused to preserve your review note. Save your decision to refresh the queue.';
            return;
        }
        busy = true;
        try {
            const response = await fetch(panel.dataset.statusUrl, {headers: {Accept: 'application/json'}, cache: 'no-store'});
            if (!response.ok) throw new Error('Refresh unavailable. Sign in again if your session has expired.');
            const data = await response.json();
            if (!editing && !panel.contains(document.activeElement)) {
                panel.innerHTML = data.html;
                message.textContent = 'Updated ' + data.updated_at + ' · Refreshes every 20 seconds.';
            }
        } catch (error) { message.textContent = error.message; }
        finally { busy = false; }
    }
    document.getElementById('registration-refresh')?.addEventListener('click', refresh);
    setInterval(refresh, 20000);
})();
