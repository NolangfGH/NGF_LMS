(() => {
    const editors = new WeakSet();
    const classStyles = {
        'lesson-bold': ['fontWeight', 'bold'], 'lesson-italic': ['fontStyle', 'italic'],
        'lesson-underline': ['textDecoration', 'underline'], 'lesson-strike': ['textDecoration', 'line-through'],
        'lesson-align-left': ['textAlign', 'left'], 'lesson-align-center': ['textAlign', 'center'], 'lesson-align-right': ['textAlign', 'right'],
        'lesson-color-green': ['color', '#14532d'], 'lesson-color-blue': ['color', '#1e40af'], 'lesson-color-red': ['color', '#991b1b'],
        'lesson-highlight': ['backgroundColor', '#fef08a'],
    };
    function initialize(root) {
        if (editors.has(root)) return;
        const editor = root.querySelector('[data-format-editor]');
        const html = root.querySelector('[data-format-html]');
        const fallback = root.querySelector('[data-format-fallback]');
        const plain = fallback.querySelector('textarea');
        const count = root.querySelector('[data-format-count]');
        const enhanced = root.querySelector('[data-format-enhanced]');
        const form = root.closest('form');
        let range = null;
        editors.add(root);
        // Convert saved, allowlisted formatting classes to editable native formatting.
        editor.querySelectorAll('[class]').forEach(element => {
            Object.entries(classStyles).forEach(([name, [property, value]]) => {
                if (element.classList.contains(name)) { element.style[property] = value; element.classList.remove(name); }
            });
        });
        const remember = () => {
            const selected = window.getSelection();
            if (selected?.rangeCount && editor.contains(selected.anchorNode) && editor.contains(selected.focusNode)) {
                range = selected.getRangeAt(0).cloneRange();
                root.querySelectorAll('button[data-format-command]').forEach(button => {
                    if (['bold', 'italic', 'underline', 'strikeThrough', 'superscript', 'subscript'].includes(button.dataset.formatCommand)) {
                        button.setAttribute('aria-pressed', String(document.queryCommandState(button.dataset.formatCommand)));
                    }
                });
            }
        };
        const sync = () => {
            plain.value = editor.innerText.trim();
            html.value = editor.innerHTML;
            const length = [...plain.value].length;
            const valid = length > 0 && length <= 5000 && [...html.value].length <= 20000;
            count.textContent = length + ' / 5,000 characters' + (length > 5000 ? ' · shorten explanation' : '');
            editor.setAttribute('aria-invalid', String(!valid));
            return valid;
        };
        const apply = (command, value = null) => {
            editor.focus();
            const selection = window.getSelection();
            if (range && editor.contains(range.commonAncestorContainer)) { selection.removeAllRanges(); selection.addRange(range); }
            if (command === 'justify') { command = 'justify' + value; value = null; }
            document.execCommand('styleWithCSS', false, true);
            document.execCommand(command, false, value);
            remember(); sync();
        };
        root.addEventListener('mousedown', event => {
            if (event.target.closest('button[data-format-command]')) event.preventDefault();
        });
        root.addEventListener('click', event => {
            const button = event.target.closest('button[data-format-command]');
            if (button) apply(button.dataset.formatCommand, button.dataset.formatValue || null);
        });
        root.addEventListener('change', event => {
            const select = event.target.closest('select[data-format-command]');
            if (select?.value) { apply(select.dataset.formatCommand, select.value); select.value = ''; }
        });
        editor.addEventListener('input', sync);
        ['keyup', 'mouseup', 'focusout'].forEach(event => editor.addEventListener(event, remember));
        document.addEventListener('selectionchange', remember);
        editor.addEventListener('paste', event => {
            event.preventDefault();
            document.execCommand('insertText', false, event.clipboardData?.getData('text/plain') || '');
            sync();
        });
        editor.addEventListener('drop', event => event.preventDefault());
        form?.addEventListener('submit', event => {
            if (!root.isConnected || sync()) return;
            if (!event.defaultPrevented) { event.preventDefault(); editor.focus(); }
            count.textContent = 'Enter 1–5,000 characters with simple formatting before saving.';
        });
        fallback.hidden = true;
        plain.required = false;
        html.disabled = false;
        enhanced.hidden = false;
        sync();
    }
    document.querySelectorAll('[data-lesson-formatting]').forEach(initialize);
    window.addEventListener('ngf:explanation-added', event => {
        event.detail.querySelectorAll('[data-lesson-formatting]').forEach(initialize);
    });
})();
