(() => {
    const panel = document.getElementById('staff-request');
    if (!panel) return;
    const states = {pending: 'Awaiting System Admin approval', active: 'Access approved', rejected: 'Access request declined'};
    let busy = false;
    async function refresh() {
        if (busy || document.hidden) return;
        busy = true;
        try {
            const response = await fetch(panel.dataset.checkUrl, {headers: {Accept: 'application/json'}, cache: 'no-store'});
            if (!response.ok) throw new Error('Sign in again to view your private access request.');
            const result = await response.json();
            if (!states[result.status]) throw new Error('Unable to check your status. Try again later.');
            document.getElementById('staff-request-status').textContent = states[result.status];
            document.getElementById('staff-assigned-role').textContent = result.status === 'active' ? 'Your approved role is ' + result.role + '. Sign in with your registered email and password.' : 'You cannot enter the staff workspace until your request is approved.';
            document.getElementById('staff-review-note').textContent = result.note || '';
            document.getElementById('staff-request-live').textContent = 'Status checked just now · Updates every 20 seconds.';
        } catch (error) { document.getElementById('staff-request-live').textContent = error.message; }
        finally { busy = false; }
    }
    setInterval(refresh, 20000);
})();
