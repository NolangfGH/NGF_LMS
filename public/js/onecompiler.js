(() => {
    const launchers = document.querySelectorAll('[data-onecompiler-editor]');
    if (!launchers.length) return;
    const origin = 'https://onecompiler.com';
    let session = null, loadingTimer = null;
    const dialog = document.createElement('dialog');
    dialog.className = 'onecompiler-dialog';
    dialog.setAttribute('aria-labelledby', 'onecompiler-title');
    dialog.innerHTML = `<div class="onecompiler-shell">
        <header class="onecompiler-header"><div><h2 id="onecompiler-title">OneCompiler · C++ practice</h2><p>Run your code here. Use “Bring code back” before closing to keep edits in your LMS draft. Save and submit in the LMS.</p></div><button type="button" class="onecompiler-close" data-oc-close aria-label="Close OneCompiler">Close ✕</button></header>
        <div class="onecompiler-toolbar"><button type="button" class="onecompiler-apply" data-oc-run disabled>Run C++</button><button type="button" data-oc-reload>Load LMS draft</button><button type="button" data-oc-apply disabled>Bring code back</button><button type="button" data-oc-copy>Copy LMS code</button><a class="onecompiler-link" href="https://onecompiler.com/cpp" target="_blank" rel="noopener noreferrer">Full OneCompiler site ↗</a></div>
        <p class="onecompiler-status" role="status" aria-live="polite" data-oc-status></p>
        <textarea class="onecompiler-manual" data-oc-manual hidden readonly aria-label="Code to copy manually"></textarea>
        <iframe class="onecompiler-frame" title="OneCompiler C++ editor and output console" referrerpolicy="no-referrer"></iframe>
    </div>`;
    document.body.append(dialog);
    const find = selector => dialog.querySelector(selector);
    const frame = find('iframe'), status = find('[data-oc-status]'), apply = find('[data-oc-apply]'), run = find('[data-oc-run]');
    const readCode = editor => typeof editor.value === 'string' ? editor.value : editor.textContent;
    const writable = editor => editor.tagName === 'TEXTAREA' && !editor.readOnly && !editor.disabled;
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
                status.textContent = 'OneCompiler has not confirmed your code yet. Try Load LMS draft, or copy the code and open the full site.';
            } else {
                sendCode();
            }
        }, 500);
    }
    function loadDraft() {
        if (!session) return;
        session.sentCode = readCode(session.editor);
        session.receivedCode = null;
        session.confirmed = false;
        apply.disabled = true;
        run.disabled = true;
        status.textContent = 'Loading your current main.cpp into OneCompiler…';
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
        if (!session.confirmed && file.content !== session.sentCode) {
            sendCode();
            return;
        }
        session.confirmed = true;
        clearInterval(loadingTimer);
        session.receivedCode = file.content;
        apply.disabled = !writable(session.editor);
        run.disabled = false;
        status.textContent = 'Your code is loaded. Choose Run C++. External runs do not submit work, check hidden tests, or award XP.';
    });
    const bindLauncher = button => button.addEventListener('click', () => {
        const editor = document.getElementById(button.dataset.onecompilerEditor);
        if (!editor) return;
        session = {editor, launcher: button, sentCode: readCode(editor), receivedCode: null, confirmed: false};
        apply.disabled = true;
        run.disabled = true;
        status.textContent = 'Opening OneCompiler with your current code. If it does not load, use Copy LMS code and open the full site.';
        find('[data-oc-manual]').hidden = true;
        dialog.showModal();
        frame.src = origin + '/embed/cpp?listenToEvents=true&codeChangeEvent=true&hideLanguageSelection=true&hideNew=true&hideNewFileOption=true&hideTitle=true';
        waitForEditor();
    });
    launchers.forEach(bindLauncher);
    window.addEventListener('ngf:explanation-added', event => { event.detail.querySelectorAll('[data-onecompiler-editor]').forEach(bindLauncher); });
    find('[data-oc-reload]').addEventListener('click', loadDraft);
    run.addEventListener('click', () => {
        if (session?.confirmed) frame.contentWindow.postMessage({eventType: 'triggerRun'}, origin);
    });
    apply.addEventListener('click', () => {
        if (!session || session.receivedCode === null) return;
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
        const code = readCode(session.editor);
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
        launcher?.focus();
    });
})();
