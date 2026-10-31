(() => {
    const selectors = [...document.querySelectorAll('[data-roster-student]')];
    function filter() {
        selectors.forEach((select, index) => {
            const section = document.querySelector(`[data-roster-filter="${index}"]`).value;
            const opponent = selectors[1-index].value;
            [...select.options].forEach(option => {
                if (!option.value) return;
                option.hidden = Boolean(section && option.dataset.room !== section);
                option.disabled = option.hidden || option.value === opponent;
            });
            select.setCustomValidity(select.value && select.value === opponent ? 'Choose two different coders.' : '');
        });
    }
    document.querySelectorAll('[data-roster-filter]').forEach(node => node.addEventListener('change', () => {
        const select = selectors[Number(node.dataset.rosterFilter)];
        if (node.value && select.selectedOptions[0]?.dataset.room !== node.value) select.value = '';
        filter();
    }));
    selectors.forEach(node => node.addEventListener('change',filter));
    if (selectors.length === 2) filter();
})();
