(() => {
    const root = document.querySelector('[data-recitation-role]');
    if (!root) return;
    const find = selector => root.querySelector(selector), connection = find('[data-recitation-connection]');
    let stopped = false, polling = false;
    async function request(url, method = 'GET', body) {
        const controller = new AbortController(), timeout = setTimeout(() => controller.abort(), 12000);
        try {
            const response = await fetch(url, {method, headers: {Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': root.dataset.csrf || ''}, body: body ? JSON.stringify(body) : undefined, cache: 'no-store', signal: controller.signal});
            let data = {}; try { data = await response.json(); } catch {}
            if (!response.ok) {
                if ([401, 403, 419].includes(response.status)) stopped = true;
                const error = new Error(Object.values(data.errors || {}).flat().join(' ') || data.message || 'Unable to connect. Keep a copy and refresh or sign in again.');
                error.status = response.status; throw error;
            }
            return data;
        } finally { clearTimeout(timeout); }
    }
    const node = (tag, text, className) => {
        const element = document.createElement(tag); element.textContent = text;
        if (className) element.className = className;
        return element;
    };
    if (root.dataset.recitationRole === 'teacher') {
        let revision = null;
        const render = state => {
            revision = state.revision;
            const winners = state.winners.map(winner => {
                const card = node('article', '', 'rounded-2xl border border-green-200 bg-white p-5');
                card.append(node('h3', `#${winner.position} · ${winner.name}`, 'font-bold text-primary'), node('p', `${winner.score} recitation points · +${winner.xp} XP`, 'mt-2 text-sm'), node('pre', winner.answer || '', 'mt-3 max-h-40 overflow-auto whitespace-pre-wrap rounded-xl bg-paper p-3 font-mono text-sm'), node('p', new Date(winner.at).toLocaleTimeString(), 'mt-2 text-xs text-secondary'));
                return card;
            });
            find('[data-recitation-winners]').replaceChildren(...(winners.length ? winners : [node('p', `No correct places yet · ${state.status}`, 'rounded-xl bg-white p-4 text-sm')]));
            find('[data-recitation-drafts]').replaceChildren(...state.drafts.map(draft => {
                const card = node('article', '', 'min-w-0 rounded-2xl border bg-white p-5');
                card.append(node('h3', draft.name, 'font-bold text-primary'), node('p', `Saved revision ${draft.revision}`, 'mt-2 text-xs text-secondary'), node('pre', draft.answer || 'No draft saved yet.', 'mt-3 max-h-64 overflow-auto whitespace-pre-wrap rounded-xl bg-[#102416] p-4 font-mono text-sm text-green-100'));
                return card;
            }));
            connection.textContent = `Round ${state.status} · ${state.winners.length}/5 correct places · Last synced ${new Date().toLocaleTimeString()}`;
        };
        render(JSON.parse(find('[data-recitation-state]').textContent));
        async function poll() {
            if (document.hidden || stopped || polling) return;
            polling = true;
            try {
                const url = new URL(root.dataset.statusUrl, window.location.href); url.searchParams.set('revision', revision);
                const data = await request(url.toString());
                if (!data.unchanged) render(data);
                else connection.textContent = connection.textContent.replace(/Last synced .*/, `Last synced ${new Date().toLocaleTimeString()}`);
            } catch (error) { connection.textContent = `Live update paused: ${error.message}`; }
            finally { polling = false; }
        }
        setInterval(poll, 4000); document.addEventListener('visibilitychange', poll);
        return;
    }
    const editor = find('[data-recitation-answer]'), submit = find('[data-recitation-submit]'), saveStatus = find('[data-recitation-save]'), result = find('[data-recitation-result]');
    let revision = Number(root.dataset.revision), saved = editor.value, saving = null, timer = null, busy = false, conflicted = false, locked = root.dataset.locked === '1', awarded = root.dataset.awarded === '1', requestKey = null;
    const updateControls = () => { editor.readOnly = locked || conflicted; submit.disabled = locked || conflicted || busy || stopped; };
    function updateState(data) {
        if (data.award) {
            awarded = true;
            result.textContent = `Correct place #${data.award.position} · ${data.award.score} recitation points · +${data.award.xp} XP.`;
        }
        if (data.status) {
            locked = data.status !== 'open' || awarded;
            connection.textContent = `${data.status} · ${data.places_taken}/5 correct places taken · Last synced ${new Date().toLocaleTimeString()}`;
        } else if (awarded) locked = true;
        if (data.xp !== undefined) document.querySelectorAll('[data-student-xp]').forEach(label => { label.textContent = `${data.xp} XP · Level ${data.level}`; });
        updateControls();
    }
    async function flush() {
        clearTimeout(timer);
        if (saving) { await saving; if (saved !== editor.value) return flush(); return; }
        saving = (async () => {
            while (saved !== editor.value) {
                if (locked || conflicted || stopped) throw new Error('This round is closed or editing is paused. Copy your answer to keep it.');
                const answer = editor.value;
                saveStatus.textContent = 'Saving draft…';
                const data = await request(root.dataset.saveUrl, 'PUT', {answer, revision});
                revision = data.revision; saved = answer;
                saveStatus.textContent = `Saved draft revision ${revision}`;
            }
        })();
        try { await saving; }
        catch (error) {
            if (error.status === 409) conflicted = true;
            saveStatus.textContent = `Not saved: ${error.message}`; updateControls(); throw error;
        } finally { saving = null; }
    }
    editor.addEventListener('input', () => {
        requestKey = null; saveStatus.textContent = 'Unsaved changes…'; clearTimeout(timer);
        timer = setTimeout(() => flush().catch(() => {}), 750);
    });
    submit.addEventListener('click', async () => {
        if (busy || locked || conflicted || stopped) return;
        if (!editor.value.trim()) { result.textContent = 'Enter an answer first.'; editor.focus(); return; }
        busy = true; updateControls();
        try {
            await flush();
            requestKey ||= crypto.randomUUID();
            const data = await request(root.dataset.submitUrl, 'POST', {answer: editor.value, request_key: requestKey});
            result.textContent = data.message; updateState(data);
        } catch (error) { result.textContent = error.message; }
        finally { busy = false; updateControls(); }
    });
    find('[data-recitation-copy]').addEventListener('click', () => { editor.focus(); editor.select(); result.textContent = 'Your answer is selected. Use Copy or Ctrl/Cmd+C to keep a copy.'; });
    async function poll() {
        if (polling || stopped || document.hidden) return;
        polling = true;
        try { updateState(await request(root.dataset.statusUrl)); }
        catch (error) { connection.textContent = `Live update unavailable: ${error.message}`; }
        finally { polling = false; }
    }
    setInterval(poll, 4000); document.addEventListener('visibilitychange', poll); poll();
    window.addEventListener('beforeunload', event => { if (saved !== editor.value && !awarded) { event.preventDefault(); event.returnValue = ''; } });
})();
