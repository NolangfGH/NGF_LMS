(() => {
    const search = document.querySelector('[data-guide-search]');
    const role = document.querySelector('[data-guide-role]');
    if (!search || !role) return;
    const topics = [...document.querySelectorAll('[data-guide-topic]')];
    const update = () => {
        const terms = search.value.toLocaleLowerCase().trim().split(/\s+/).filter(Boolean);
        let count = 0;
        topics.forEach(topic => {
            const matchesRole = role.value === 'all' || topic.dataset.guideRoles.split(' ').includes(role.value);
            const matchesText = terms.every(term => new RegExp(`\\b${term.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}`, 'i').test(topic.textContent));
            const visible = matchesRole && matchesText;
            topic.hidden = !visible;
            document.querySelector(`[data-guide-link="${topic.id}"]`)?.toggleAttribute('hidden', !visible);
            if (visible) count++;
        });
        document.querySelector('[data-guide-count]').textContent = `${count} of ${topics.length} help topics`;
        document.querySelector('[data-guide-empty]').hidden = count !== 0;
    };
    search.addEventListener('input', update);
    role.addEventListener('change', update);
    document.querySelector('[data-guide-clear]').addEventListener('click', () => {
        search.value = '';
        role.value = 'all';
        update();
        search.focus();
    });
    update();
})();
