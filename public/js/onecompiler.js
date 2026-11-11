(() => {
    const launchers = document.querySelectorAll('[data-onecompiler-editor]');
    if (!launchers.length) return;
    const origin = 'https://onecompiler.com';
    let session = null, loadingTimer = null;
    const dialog = document.createElement('dialog');
    dialog.className = 'onecompiler-dialog';
    dialog.setAttribute('aria-labelledby', 'onecompiler-title');
    dialog.innerHTML = `<div class="onecompiler-shell">
        <header class="onecompiler-header"><div><h2 id="onecompiler-title"></h2><p data-oc-intro></p></div><button type="button" class="onecompiler-close" data-oc-close aria-label="Close OneCompiler">Close ✕</button></header>
        <div class="onecompiler-toolbar"><button type="button" class="onecompiler-apply" data-oc-run disabled>Run C++</button><button type="button" data-oc-reload>Load LMS draft</button><button type="button" data-oc-apply disabled>Bring code back</button><button type="button" data-oc-copy>Copy LMS code</button><a class="onecompiler-link" href="https://onecompiler.com/cpp" target="_blank" rel="noopener noreferrer">Full OneCompiler site ↗</a></div>
        <p class="onecompiler-status" role="status" aria-live="polite" data-oc-status></p>
        <details class="onecompiler-expected" data-oc-expected hidden><summary>Sample correct output</summary><pre data-oc-expected-code></pre></details>
        <textarea class="onecompiler-manual" data-oc-manual hidden readonly aria-label="Code to copy manually"></textarea>
        <iframe class="onecompiler-frame" title="OneCompiler C++ editor and output console" referrerpolicy="no-referrer"></iframe>
    </div>`;
    document.body.append(dialog);
    const find = selector => dialog.querySelector(selector);
    const frame = find('iframe'), status = find('[data-oc-status]'), apply = find('[data-oc-apply]'), run = find('[data-oc-run]');
    const reload = find('[data-oc-reload]'), expected = find('[data-oc-expected]');
    const readCode = editor => typeof editor.value === 'string' ? editor.value : editor.textContent;
    const sourceCode = () => session.review ? session.capturedCode : readCode(session.editor);
    const writable = editor => editor.tagName === 'TEXTAREA' && !editor.readOnly && !editor.disabled;
    const normalize = code => code.replace(/\r\n?/g, '\n');
    function sendCode() {
        if (!session) return;
        frame.contentWindow.postMessage({eventType: 'populateCode', language: 'cpp', files: [{name: 'main.cpp', content: session.sentCode}]}, origin);
    }
    function waitForEditor() {
        clearInterval(loadingTimer);
        const started = Date.now();
        loadingTimer = setInterval(() => {
            if (!session || session.confirmed) {
                clearInterval(loadingTimer);
            } else if (Date.now() - started > 20000) {
                clearInterval(loadingTimer);
                status.textContent = 'OneCompiler has not confirmed the code yet. Reload the code, or copy it into the full site.';
            } else {
                sendCode();
            }
        }, 500);
    }
    function loadDraft() {
        if (!session) return;
        session.sentCode = sourceCode();
        session.receivedCode = null;
        session.confirmed = false;
        apply.disabled = true;
        run.disabled = true;
        status.textContent = 'Loading main.cpp into OneCompiler…';
        sendCode();
        waitForEditor();
    }
    frame.addEventListener('load', sendCode);
    window.addEventListener('message', event => {
        if (!session || event.origin !== origin || event.source !== frame.contentWindow) return;
        const data = event.data;
        if (!data || data.language !== 'cpp' || !Array.isArray(data.files)) return;
        const file = data.files.find(item => item && item.name === 'main.cpp');
        if (!file || typeof file.content !== 'string' || file.content.length > 50000) return;
        if (!session.confirmed && normalize(file.content) !== normalize(session.sentCode)) {
            sendCode();
            return;
        }
        session.confirmed = true;
        clearInterval(loadingTimer);
        session.receivedCode = file.content;
        apply.disabled = session.review || !writable(session.editor);
        run.disabled = false;
        status.textContent = session.review
            ? 'Code loaded. Choose Run C++ and compare the console with the sample output.'
            : 'Your code is loaded. Choose Run C++. External runs do not submit work, check hidden tests, or award XP.';
    });
    document.addEventListener('click', event => {
        const button = event.target.closest?.('[data-onecompiler-editor]');
        if (!button || dialog.open) return;
        const editor = document.getElementById(button.dataset.onecompilerEditor);
        if (!editor) return;
        const review = button.dataset.onecompilerReview === 'true';
        const capturedCode = readCode(editor);
        const sample = button.dataset.onecompilerExpected ? document.getElementById(button.dataset.onecompilerExpected) : null;
        session = {editor, launcher: button, review, capturedCode, sentCode: capturedCode, receivedCode: null, confirmed: false};
        find('#onecompiler-title').textContent = review ? 'OneCompiler · Review C++' : 'NGF_Compiler · C++ practice';
        find('[data-oc-intro]').textContent = review
            ? 'Test the captured main.cpp. Changes in OneCompiler stay in this testing session.'
            : 'Run your code here. Use Bring code back to keep edits in your open LMS draft.';
        reload.textContent = review ? 'Reload captured code' : 'Load LMS draft';
        expected.hidden = !sample;
        expected.open = false;
        find('[data-oc-expected-code]').textContent = sample?.textContent ?? '';
        apply.hidden = review || editor.tagName !== 'TEXTAREA';
        apply.disabled = true;
        run.disabled = true;
        status.textContent = 'Opening OneCompiler with main.cpp. If it does not load, use Copy LMS code and open the full site.';
        find('[data-oc-manual]').hidden = true;
        dialog.showModal();
        frame.src = origin + '/embed/cpp?listenToEvents=true&codeChangeEvent=true&hideLanguageSelection=true&hideNew=true&hideNewFileOption=true&hideTitle=true';
        waitForEditor();
    });
    reload.addEventListener('click', loadDraft);
    run.addEventListener('click', () => {
        if (session?.confirmed) frame.contentWindow.postMessage({eventType: 'triggerRun'}, origin);
    });
    apply.addEventListener('click', () => {
        if (!session || session.review || session.receivedCode === null) return;
        const editor = session.editor;
        if (!writable(editor)) {
            apply.disabled = true;
            status.textContent = 'This LMS editor is now closed or read-only. You can still copy your code from OneCompiler.';
            return;
        }
        if (readCode(editor) !== session.sentCode && readCode(editor) !== session.receivedCode) {
            status.textContent = 'The LMS draft changed while OneCompiler was open. Copy your OneCompiler code and review both versions before replacing it.';
            return;
        }
        if (editor.maxLength > 0 && session.receivedCode.length > editor.maxLength) {
            status.textContent = `This editor allows ${editor.maxLength.toLocaleString()} characters. Shorten your code in OneCompiler first.`;
            return;
        }
        editor.value = session.receivedCode;
        editor.dispatchEvent(new Event('input', {bubbles: true}));
        session.sentCode = editor.value;
        status.textContent = 'Code brought back to your LMS draft. Save your draft and submit it here when ready.';
    });
    find('[data-oc-copy]').addEventListener('click', async () => {
        if (!session) return;
        const code = sourceCode();
        try {
            await navigator.clipboard.writeText(code);
            status.textContent = 'LMS code copied. Open the full OneCompiler site and paste it into main.cpp.';
        } catch {
            const manual = find('[data-oc-manual]');
            manual.value = code;
            manual.hidden = false;
            manual.focus();
            manual.select();
            status.textContent = 'Copy the selected code, then paste it into the full OneCompiler site.';
        }
    });
    find('[data-oc-close]').addEventListener('click', () => dialog.close());
    dialog.addEventListener('close', () => {
        const launcher = session?.launcher;
        session = null;
        clearInterval(loadingTimer);
        frame.removeAttribute('src');
        find('[data-oc-manual]').value = '';
        find('[data-oc-expected-code]').textContent = '';
        launcher?.focus();
    });
})();
