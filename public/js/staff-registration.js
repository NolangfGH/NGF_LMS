(() => {
    const form = document.getElementById('registration');
    if (!form) return;
    const roles = JSON.parse(document.getElementById('staff-role-data').textContent);
    const select = document.getElementById('staff-role');
    const button = document.getElementById('register-button');
    const feedback = document.getElementById('feedback');
    let busy = false;
    const updateRole = () => {
        const teaching = select.value === 'teacher';
        const setup = document.getElementById('teaching-setup');
        setup.hidden = !teaching;
        setup.disabled = !teaching;
        document.getElementById('role-title').textContent = roles.roles[select.value] + ' permissions';
        const rules = document.getElementById('role-rules');
        rules.replaceChildren();
        roles.rules[select.value].forEach(rule => { const item = document.createElement('li'); item.textContent = rule; rules.append(item); });
        document.getElementById('role-approval').textContent = teaching ? 'You can use your own teaching workspace after registration.' : 'A System Admin must approve this request before you can sign in.';
        button.textContent = teaching ? 'Create Teacher account & classroom →' : 'Submit ' + roles.roles[select.value] + ' access request →';
    };
    select.addEventListener('change', updateRole);
    updateRole();
    form.addEventListener('submit', async event => {
        event.preventDefault();
        if (busy) return;
        busy = true;
        button.disabled = true;
        feedback.className = 'mt-5 rounded-xl border border-green-200 bg-green-50 p-4 text-sm text-primary';
        feedback.textContent = 'Saving your account details…';
        try {
            const response = await fetch(form.action, {method: 'POST', headers: {Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content}, body: new FormData(form)});
            const result = await response.json();
            if (!response.ok) throw new Error(Object.values(result.errors || {}).flat().join(' ') || result.message || 'Unable to register. Please try again.');
            window.location.assign(result.redirect);
        } catch (error) {
            feedback.className = 'mt-5 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800';
            feedback.textContent = error.message;
            feedback.focus();
            button.disabled = false;
            busy = false;
            updateRole();
        }
    });
})();
