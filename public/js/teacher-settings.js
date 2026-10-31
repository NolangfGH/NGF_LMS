(() => {
    const forms = [...document.querySelectorAll('form[data-settings-form]')];
    const banner = document.getElementById('settings-unsaved');
    if (!banner) return;
    const dirty = new Set();
    const baseline = new Map();
    let current = null;
    let submitting = null;
    const snapshot = form => JSON.stringify([...form.elements].filter(field =>
        field.name && !field.disabled && !['submit','button','reset'].includes(field.type)
        && (!['checkbox','radio'].includes(field.type) || field.checked)
    ).map(field => [field.name,field.type === 'file' ? [...field.files].map(file => [file.name,file.size,file.lastModified]) : field.value]));
    const announce = message => {
        const status = document.getElementById('settings-live-status');
        if (status) status.textContent = message;
    };
    const preview = () => {
        const form = forms.find(item => item.dataset.settingsForm === 'preferences');
        if (!form) return;
        document.documentElement.dataset.teacherText = form.elements.namedItem('text_size').value;
        document.documentElement.dataset.teacherContrast = form.querySelector('input[type=checkbox][name=high_contrast]').checked ? 'high' : 'standard';
        document.documentElement.dataset.teacherMotion = form.querySelector('input[type=checkbox][name=reduce_motion]').checked ? 'reduce' : 'standard';
    };
    const counter = () => {
        const bio = document.getElementById('teacher-bio');
        const count = document.querySelector('[data-bio-count]');
        if (bio && count) count.textContent = String(Array.from(bio.value).length);
    };
    const refresh = form => {
        if (snapshot(form) === baseline.get(form)) dirty.delete(form);
        else dirty.add(form);
        if (dirty.has(form)) current = form;
        else if (current === form) current = [...dirty].at(-1) || null;
        banner.hidden = dirty.size === 0;
        document.getElementById('settings-dirty-count').textContent = dirty.size > 1 ? 'in ' + dirty.size + ' sections' : '';
        counter();
        preview();
    };
    forms.forEach(form => {
        baseline.set(form,snapshot(form));
        form.addEventListener('input',() => refresh(form));
        form.addEventListener('change',() => refresh(form));
        form.querySelector('[data-settings-reset]')?.addEventListener('click',() => { form.reset(); refresh(form); });
        form.addEventListener('submit',() => {
            submitting = form;
            form.querySelectorAll('button[type=submit],button:not([type])').forEach(button => { button.disabled = true; });
            announce('Saving your changes…');
        });
    });
    document.getElementById('settings-discard').addEventListener('click',() => {
        forms.forEach(form => { form.reset(); refresh(form); });
        announce('Unsaved changes discarded.');
    });
    document.getElementById('settings-save-current').addEventListener('click',() => {
        const form = current || [...dirty].at(-1);
        if (form) form.requestSubmit();
    });
    window.addEventListener('beforeunload',event => {
        if ([...dirty].some(form => form !== submitting)) {
            event.preventDefault();
            event.returnValue = '';
        }
    });
    document.querySelectorAll('[data-copy-target]').forEach(button => {
        button.addEventListener('click',async () => {
            const field = document.getElementById(button.dataset.copyTarget);
            if (!field) return;
            try {
                if (!navigator.clipboard?.writeText) throw new Error('Clipboard not available');
                await navigator.clipboard.writeText(field.value);
                announce('Class join code copied.');
            } catch {
                field.focus(); field.select();
                announce('Join code selected. Press Ctrl+C to copy.');
            }
        });
    });
    const failed = document.getElementById('settings-errors')?.dataset.settingsErrorForm;
    const failedForm = forms.find(form => form.dataset.settingsForm === failed);
    if (failedForm) {
        const details = failedForm.closest('details');
        if (details) details.open = true;
        failedForm.scrollIntoView({ block: 'center' });
    }
    counter();
})();
