(() => {
    const root = document.querySelector('[data-explanation-editor]');
    if (!root) return;
    const list = root.querySelector('[data-explanation-list]');
    const add = root.querySelector('[data-explanation-add]');
    const template = root.querySelector('[data-explanation-template]');
    const status = root.querySelector('[data-explanation-count]');
    const rows = () => Array.from(list.querySelectorAll('[data-explanation]'));
    const refresh = () => {
        const items = rows();
        items.forEach((row, index) => {
            row.querySelector('[data-explanation-number]').textContent = String(index + 1);
            row.querySelectorAll('[name]').forEach(field => { field.name = field.name.replace(/^sections\[[^\]]+\]/, `sections[${index}]`); });
            const code = row.querySelector('[data-explanation-code]');
            code.id = `explanation-${index}-code`;
            row.querySelectorAll('[data-onecompiler-editor]').forEach(button => { button.dataset.onecompilerEditor = code.id; });
            const remove = row.querySelector('[data-explanation-remove]');
            remove.disabled = items.length <= 1;
            remove.setAttribute('aria-label', `Remove explanation ${index + 1}`);
        });
        add.disabled = items.length >= 12;
        status.textContent = `${items.length} explanations · maximum 12`;
    };
    add.addEventListener('click', () => {
        if (rows().length >= 12) return;
        list.append(template.content.cloneNode(true));
        refresh();
        const row = rows().at(-1);
        window.dispatchEvent(new CustomEvent('ngf:explanation-added', {detail: row}));
        row.querySelector('input').focus();
    });
    list.addEventListener('click', event => {
        const button = event.target.closest('[data-explanation-remove]');
        if (!button || button.disabled || rows().length <= 1) return;
        const row = button.closest('[data-explanation]');
        if (!row || !list.contains(row)) return;
        row.remove();
        refresh();
        add.focus();
    });
    refresh();
})();
