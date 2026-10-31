(() => {
    const search = document.getElementById('lesson-search');
    if (!search) return;
    const status = document.getElementById('lesson-status');
    const source = document.getElementById('lesson-source');
    const cards = [...document.querySelectorAll('[data-lesson-card]')];
    const headerSearch = document.querySelector('header input');
    const filter = () => {
        const term = search.value.trim().toLocaleLowerCase();
        let count = 0;
        cards.forEach(card => {
            const visible = card.dataset.title.toLocaleLowerCase().includes(term)
                && (status.value === 'all' || card.dataset.status === status.value)
                && (source.value === 'all' || card.dataset.source === source.value);
            card.hidden = !visible;
            if (visible) count++;
        });
        document.getElementById('lesson-result-count').textContent = count + ' of ' + cards.length + ' lessons shown';
        document.getElementById('lesson-empty').hidden = count !== 0;
    };
    search.addEventListener('input', () => { if (headerSearch) headerSearch.value = search.value; filter(); });
    status.addEventListener('change', filter);
    source.addEventListener('change', filter);
    if (headerSearch) {
        headerSearch.setAttribute('aria-label', 'Search lessons');
        headerSearch.placeholder = 'Search C++ lessons...';
        headerSearch.addEventListener('input', () => { search.value = headerSearch.value; filter(); });
    }
    document.getElementById('lesson-clear').addEventListener('click', () => {
        search.value = ''; status.value = 'all'; source.value = 'all';
        if (headerSearch) headerSearch.value = '';
        filter();
    });
    filter();
})();
