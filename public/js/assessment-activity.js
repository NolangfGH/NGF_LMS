(function () {
    function createActivityMonitor({ root, document, browser, fetcher = (...args) => browser.fetch(...args), clock = () => browser.performance.now() }) {
        const status = root.querySelector('[data-assessment-status]');
        const warning = root.querySelector('[data-assessment-warning]');
        const fullscreen = root.querySelector('[data-assessment-fullscreen]');
        const fullscreenNote = root.querySelector('[data-assessment-fullscreen-note]');
        let active = false, stopped = false, hiddenAt = null, blurredAt = null, blurTimer = null, polling = false;
        let enteredFullscreen = Boolean(document.fullscreenElement);
        const duration = start => start === null ? 0 : Math.min(86400, Math.max(0, Math.round((clock() - start) / 1000)));
        const warn = message => { warning.hidden = false; warning.textContent = message; };
        const suspend = message => { active = false; status.textContent = message; };
        async function emit(signal, away = 0) {
            if (!active || stopped) return;
            const requestKey = browser.crypto?.randomUUID?.() || 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, char => { const value = Math.floor(Math.random() * 16); return (char === 'x' ? value : (value & 3) | 8).toString(16); });
            const payload = { kind: root.dataset.assessmentKind, id: Number(root.dataset.assessmentId), signal, request_key: requestKey, observed_at: new Date().toISOString(), away_seconds: away };
            try {
                const response = await fetcher(root.dataset.reportUrl, { method: 'POST', credentials: 'same-origin', keepalive: true,
                    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': root.dataset.csrf }, body: JSON.stringify(payload) });
                if ([401, 403, 404, 409, 419].includes(response.status)) suspend('Activity logging stopped. The activity or sign-in session may have ended.');
                else if (!response.ok) throw new Error('report unavailable');
            } catch (_) {
                if (!stopped) status.textContent = 'Page activity could not be saved right now. Your answer saving and assessment timer work separately.';
            }
        }
        async function refresh() {
            if (stopped || polling || document.hidden) return;
            polling = true;
            try {
                const url = `${root.dataset.stateUrl}?kind=${encodeURIComponent(root.dataset.assessmentKind)}&id=${encodeURIComponent(root.dataset.assessmentId)}`;
                const response = await fetcher(url, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
                if ([401, 403, 404, 419].includes(response.status)) { stopped = true; suspend('Activity unavailable or sign-in session ended.'); return; }
                if (!response.ok) throw new Error('status unavailable');
                const state = await response.json();
                if (stopped) return;
                if (state.active && !active) { active = true; hiddenAt = null; blurredAt = null; emit('monitor_started'); }
                else if (!state.active) active = false;
                status.textContent = active ? 'Active · page changes are recorded for teacher review.' : 'Focus monitoring is inactive. It starts when this activity is open for you.';
            } catch (_) { if (!stopped) status.textContent = 'Unable to check activity status. Your assessment timer continues according to its saved schedule.'; }
            finally { polling = false; }
        }
        const onVisibility = () => {
            if (!active) { if (!document.hidden) refresh(); return; }
            if (document.hidden) {
                browser.clearTimeout(blurTimer);
                if (hiddenAt === null) { hiddenAt = clock(); blurredAt = null; emit('page_hidden'); }
            } else {
                if (hiddenAt !== null) { const away = duration(hiddenAt); hiddenAt = null; emit('page_visible', away); warn('You returned to the assessment page. Stay here while you work. Any running timer has continued.'); }
                refresh();
            }
        };
        const onBlur = () => {
            browser.clearTimeout(blurTimer);
            blurTimer = browser.setTimeout(() => { if (active && !document.hidden && blurredAt === null) { blurredAt = clock(); emit('window_blur'); } }, 300);
        };
        const onFocus = () => {
            browser.clearTimeout(blurTimer);
            if (active && !document.hidden && blurredAt !== null) { const away = duration(blurredAt); blurredAt = null; emit('window_focus', away); warn('Focus returned to this assessment. Stay on this page while you work.'); }
            refresh();
        };
        const onFullscreen = () => {
            if (document.fullscreenElement) { enteredFullscreen = true; emit('fullscreen_enter'); }
            else if (enteredFullscreen) { enteredFullscreen = false; emit('fullscreen_exit'); if (active) warn('Fullscreen was exited. You can continue here or enter fullscreen again.'); }
        };
        fullscreen.disabled = typeof document.documentElement.requestFullscreen !== 'function';
        if (fullscreen.disabled) fullscreenNote.textContent = 'Fullscreen is unavailable on this browser. You can still take the assessment.';
        fullscreen.addEventListener('click', async () => {
            try { await document.documentElement.requestFullscreen(); fullscreenNote.textContent = 'Use your browser’s exit control or Escape to leave fullscreen.'; }
            catch (_) { fullscreenNote.textContent = 'Fullscreen was not available or was declined. Continue on this page.'; }
        });
        document.addEventListener('visibilitychange', onVisibility);
        document.addEventListener('fullscreenchange', onFullscreen);
        browser.addEventListener('blur', onBlur);
        browser.addEventListener('focus', onFocus);
        browser.addEventListener('pagehide', event => {
            if (event.persisted) return;
            stopped = true;
            browser.clearInterval(interval);
            browser.clearTimeout(blurTimer);
        });
        const interval = browser.setInterval(refresh, 15000);
        refresh();
        return { refresh };
    }
    if (typeof module === 'object' && module.exports) module.exports = { createActivityMonitor };
    if (typeof document !== 'undefined') document.querySelectorAll('[data-assessment-activity]').forEach(root => createActivityMonitor({ root, document, browser: window }));
})();
