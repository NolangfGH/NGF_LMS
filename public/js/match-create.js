(() => {
    const form = document.querySelector('[data-match-create]');
    if (!form) { return; }
    const category = form.querySelector('[data-match-count]');
    const selectors = [...form.querySelectorAll('[data-roster-student]')];
    function filter() {
        const count = Number(category.value) === 10 ? 10 : 2;
        form.querySelectorAll('[data-match-slot]').forEach((row, index) => { row.hidden = index >= count; row.disabled = index >= count; });
        const active = selectors.slice(0, count);
        selectors.forEach((select, index) => {
            const section = form.querySelector(`[data-roster-filter="${index}"]`).value;
            const other = active.filter(node => node !== select).map(node => node.value).filter(Boolean);
            [...select.options].forEach(option => {
                if (!option.value) return;
                option.hidden = Boolean(section && option.dataset.room !== section);
                option.disabled = option.hidden || other.includes(option.value);
            });
            select.setCustomValidity(index < count && select.value && other.includes(select.value) ? 'Choose a different coder for every slot.' : '');
        });
        const available = Number(form.dataset.enrolled);
        form.querySelector('[data-match-roster-status]').textContent = available < count ? `This category needs ${count} students; ${available} are currently enrolled in your sections.` : `${count} coder slots · select each student once.`;
        const button = form.querySelector('[data-match-invite]');
        button.disabled = available < count;
        button.textContent = `Assign & notify all ${count} coders →`;
    }
    form.querySelectorAll('[data-roster-filter]').forEach(node => node.addEventListener('change', () => {
        const select = selectors[Number(node.dataset.rosterFilter)];
        if (node.value && select.selectedOptions[0]?.dataset.room !== node.value) select.value = '';
        filter();
    }));
    selectors.forEach(node => node.addEventListener('change',filter));
    category.addEventListener('change', filter);
    filter();
})();
