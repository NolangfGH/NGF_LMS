(() => {
    const forms = [...document.querySelectorAll('form[data-student-settings-form]')];
    const banner = document.getElementById('student-settings-unsaved');
    if (!banner) return;
    const dirty = new Set(), baseline = new Map();
    let current = null, submitting = null;
    const announce = text => { document.getElementById('student-settings-live-status').textContent = text; };
    const snapshot = form => JSON.stringify([...form.elements].filter(field =>
        field.name && !field.disabled && !['button','submit','reset'].includes(field.type)
        && (!['checkbox','radio'].includes(field.type) || field.checked)
    ).map(field => [field.name,field.value]));
    const previews = () => {
        const display = forms.find(form => form.dataset.studentSettingsForm === 'display');
        if (display) {
            document.documentElement.dataset.studentText = display.elements.namedItem('text_size').value;
            document.documentElement.dataset.studentContrast = display.querySelector('input[type=checkbox][name=high_contrast]').checked ? 'high' : 'standard';
            document.documentElement.dataset.studentMotion = display.querySelector('input[type=checkbox][name=reduce_motion]').checked ? 'reduce' : 'standard';
        }
        const avatar = document.querySelector('input[data-student-avatar]:checked:not(:disabled)');
        if (avatar) {
            document.getElementById('student-avatar-preview').textContent = avatar.dataset.avatarEmoji;
            document.getElementById('student-avatar-label').textContent = avatar.dataset.avatarLabel;
        }
        const visible = document.getElementById('student-leaderboard-visible');
        const ranking = document.getElementById('student-ranking-preview');
        if (visible && ranking) ranking.textContent = visible.checked ? ranking.dataset.publicName : ranking.dataset.privateAlias;
    };
    const refresh = form => {
        if (snapshot(form) === baseline.get(form)) dirty.delete(form);
        else dirty.add(form);
        if (dirty.has(form)) current = form;
        else if (current === form) current = [...dirty].at(-1) || null;
        banner.hidden = dirty.size === 0;
        document.getElementById('student-settings-dirty-count').textContent = dirty.size > 1 ? 'in ' + dirty.size + ' sections' : '';
        previews();
    };
    forms.forEach(form => {
        baseline.set(form,snapshot(form));
        form.addEventListener('input',() => refresh(form));
        form.addEventListener('change',() => refresh(form));
        form.querySelector('[data-student-reset]')?.addEventListener('click',() => { form.reset(); refresh(form); });
        form.addEventListener('submit',() => {
            submitting = form;
            form.querySelectorAll('button[type=submit],button:not([type])').forEach(button => { button.disabled = true; });
            announce('Saving your preferences…');
        });
    });
    document.getElementById('student-settings-discard').addEventListener('click',() => {
        forms.forEach(form => { form.reset(); refresh(form); });
        announce('Unsaved changes discarded.');
    });
    document.getElementById('student-settings-save-current').addEventListener('click',() => {
        (current || [...dirty].at(-1))?.requestSubmit();
    });
    window.addEventListener('beforeunload',event => {
        if ([...dirty].some(form => form !== submitting)) {
            event.preventDefault();
            event.returnValue = '';
        }
    });
    const dialog = document.getElementById('student-password-dialog');
    const openPassword = () => {
        if (!dialog) return;
        if (dialog.showModal) dialog.showModal();
        else { dialog.setAttribute('open',''); dialog.scrollIntoView({ block: 'center' }); }
    };
    document.querySelector('[data-open-student-password]')?.addEventListener('click',openPassword);
    dialog?.addEventListener('close',() => {
        const form = forms.find(item => item.dataset.studentSettingsForm === 'password');
        if (form && submitting !== form) { form.reset(); refresh(form); }
    });
    const failed = document.getElementById('student-settings-errors')?.dataset.studentErrorForm;
    if (failed === 'password') openPassword();
    else forms.find(form => form.dataset.studentSettingsForm === failed)?.scrollIntoView({ block: 'center' });
    previews();
})();
