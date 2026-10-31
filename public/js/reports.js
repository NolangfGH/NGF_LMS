(() => {
    const range = document.getElementById('report-range');
    const dates = document.getElementById('report-custom-dates');
    if (range && dates) {
        const syncDates = () => {
            const custom = range.value === 'custom';
            dates.hidden = !custom;
            dates.querySelectorAll('input').forEach(input => {
                input.disabled = !custom;
                input.required = custom;
            });
        };
        range.addEventListener('change', syncDates);
        syncDates();
    }
    const menu = document.getElementById('report-menu');
    const sidebar = document.getElementById('report-sidebar');
    if (menu && sidebar) {
        const close = () => { sidebar.classList.remove('is-open'); menu.setAttribute('aria-expanded','false'); };
        menu.addEventListener('click', () => menu.setAttribute('aria-expanded',String(sidebar.classList.toggle('is-open'))));
        document.querySelector('[data-report-close-menu]')?.addEventListener('click',close);
        document.addEventListener('keydown', event => { if (event.key === 'Escape') close(); });
    }
    const dialog = document.getElementById('report-student-dialog');
    const detail = document.getElementById('report-student-detail');
    document.querySelectorAll('[data-report-inspect]').forEach(link => {
        link.addEventListener('click', event => {
            const template = document.getElementById('report-evidence-' + link.dataset.reportInspect);
            if (!dialog?.showModal || !template || !detail) return;
            event.preventDefault();
            detail.replaceChildren(template.content.cloneNode(true));
            dialog.showModal();
        });
    });
    dialog?.addEventListener('click', event => { if (event.target === dialog) dialog.close(); });
    if (document.body.dataset.printReport === 'true') {
        document.querySelectorAll('.report-chart-table').forEach(table => { table.open = true; });
        Promise.resolve(document.fonts?.ready).then(() => window.print());
    }
})();
