(() => {
    const panel = document.getElementById('registration-status');
    if (!panel) return;
    const states = {
        pending: ['Awaiting teacher approval', 'Your request has been sent to your class administrator. You can sign in after approval.'],
        approved: ['Registration approved', 'Your account is ready. Sign in with your Student ID and password to begin.'],
        rejected: ['Registration declined', 'Your account has not been activated. Contact your class administrator about the reason below.']
    };
    let busy = false;
    async function refresh() {
        if (busy || document.hidden) return;
        busy = true;
        try {
            const response = await fetch(panel.dataset.checkUrl, {headers: {Accept: 'application/json'}, cache: 'no-store'});
            if (!response.ok) throw new Error('Sign in again to check your registration status.');
            const data = await response.json();
            if (!states[data.status]) throw new Error('Unable to check your status. Please try again later.');
            document.getElementById('review-heading').textContent = states[data.status][0];
            document.getElementById('review-description').textContent = states[data.status][1];
            document.getElementById('review-note').textContent = data.note || '';
            document.getElementById('review-note-panel').hidden = !data.note;
            document.getElementById('review-time').textContent = data.reviewed_at ? 'Reviewed ' + data.reviewed_at : 'No review recorded yet.';
            document.getElementById('review-archived').hidden = !data.archived;
            document.getElementById('review-live').textContent = 'Status checked just now · Updates every 20 seconds.';
        } catch (error) { document.getElementById('review-live').textContent = error.message; }
        finally { busy = false; }
    }
    setInterval(refresh, 20000);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) refresh(); });
})();
