(() => {
    const editor = document.querySelector('[data-checkpoint-editor]');
    if (!editor) return;
    const list = editor.querySelector('[data-checkpoint-list]');
    const template = editor.querySelector('[data-checkpoint-template]');
    const add = editor.querySelector('[data-checkpoint-add]');
    const count = editor.querySelector('[data-checkpoint-count]');
    const minimum = Number(editor.dataset.min);
    const maximum = Number(editor.dataset.max);
    const questions = () => Array.from(list.querySelectorAll('[data-checkpoint-question]'));
    function refresh() {
        const rows = questions();
        rows.forEach((row, index) => {
            row.querySelector('[data-checkpoint-number]').textContent = String(index + 1);
            row.querySelectorAll('[name]').forEach(input => {
                input.name = input.name.replace(/^checks\[[^\]]+\]/, `checks[${index}]`);
            });
            const remove = row.querySelector('[data-checkpoint-remove]');
            remove.disabled = rows.length <= minimum;
            remove.setAttribute('aria-label', `Remove checkpoint question ${index + 1}`);
        });
        add.disabled = rows.length >= maximum;
        count.textContent = `${rows.length} questions · minimum ${minimum} · maximum ${maximum}`;
    }
    add.addEventListener('click', () => {
        if (questions().length >= maximum) return;
        list.append(template.content.cloneNode(true));
        refresh();
        questions().at(-1).querySelector('input').focus();
    });
    list.addEventListener('click', event => {
        const remove = event.target.closest('[data-checkpoint-remove]');
        if (!remove || remove.disabled || questions().length <= minimum) return;
        const row = remove.closest('[data-checkpoint-question]');
        if (!row || !list.contains(row)) return;
        row.remove();
        refresh();
        add.focus();
    });
    refresh();
})();
