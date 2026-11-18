(() => {
    'use strict';
    const snapshots = new WeakMap();
    const read = editor => editor.tagName === 'TEXTAREA' ? editor.value : editor.textContent;
    const editable = editor => editor.tagName === 'TEXTAREA' && !editor.readOnly && !editor.disabled;
    const message = (root, text) => { root.querySelector('[data-wokwi-status]').textContent = text; };

    async function copy(button, root, editor) {
        const code = read(editor);
        root.querySelector('[data-wokwi-source]').value = code;
        snapshots.set(root, {editor, code});
        if (!code.trim()) {
            message(root, 'This editor is empty. Add a sketch, then copy it to Wokwi.');
            return;
        }
        try {
            await navigator.clipboard.writeText(code);
            message(root, 'Sketch copied. Paste into sketch.ino in Wokwi. Save or submit your tested code back in the LMS.');
        } catch {
            root.querySelector('[data-wokwi-help]').open = true;
            message(root, 'Automatic copying is unavailable. Select and copy the sketch below, then paste it into Wokwi’s sketch.ino.');
        }
    }

    document.addEventListener('click', event => {
        const button = event.target.closest('[data-wokwi-open], [data-wokwi-copy], [data-wokwi-apply]');
        if (!button) { return; }
        const root = button.closest('[data-wokwi-launcher]');
        const editor = document.getElementById(button.dataset.wokwiEditor);
        if (!root || !editor) {
            event.preventDefault();
            if (root) { message(root, 'This sketch editor is unavailable. Reload the page and try again.'); }
            return;
        }
        if (button.hasAttribute('data-wokwi-apply')) {
            const returned = root.querySelector('[data-wokwi-return]');
            const code = returned?.value ?? '';
            if (!editable(editor)) {
                message(root, 'This is a read-only example or submission. Copy it into your own open draft to edit it.');
                return;
            }
            if (!code.trim() || code.length > (editor.maxLength > 0 ? editor.maxLength : 20000)) {
                message(root, 'Paste a nonempty tested sketch within this editor’s character limit.');
                return;
            }
            const snapshot = snapshots.get(root);
            if (snapshot && (snapshot.editor !== editor || snapshot.code !== read(editor))) {
                message(root, 'Your LMS draft changed after copying. Compare both versions and paste the tested code directly into the editor to keep your latest changes.');
                return;
            }
            editor.value = code;
            editor.dispatchEvent(new Event('input', {bubbles: true}));
            snapshots.set(root, {editor, code});
            message(root, 'Tested sketch placed in the LMS editor. Save the draft and submit when ready.');
            return;
        }
        void copy(button, root, editor);
    });
})();
