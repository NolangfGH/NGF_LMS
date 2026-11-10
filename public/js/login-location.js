(() => {
    const fields = ['audit_location_opt_in', 'audit_latitude', 'audit_longitude', 'audit_accuracy_km'];
    window.ngfLoginLocationFields = form => {
        const values = Object.fromEntries(fields.map(name => [name, form.elements.namedItem(name)?.value ?? '']));
        return values.audit_location_opt_in === '1' ? values : {};
    };
    document.querySelectorAll('[data-login-location]').forEach(panel => {
        const field = name => panel.querySelector(`[name="${name}"]`);
        const add = panel.querySelector('[data-location-add]');
        const remove = panel.querySelector('[data-location-remove]');
        const status = panel.querySelector('[data-location-status]');
        let generation = 0;
        const clear = () => {
            generation++;
            fields.forEach(name => { field(name).value = name === 'audit_location_opt_in' ? '0' : ''; });
            remove.hidden = true; add.disabled = false;
            status.textContent = 'No location shared. Signing in does not require location.';
        };
        remove.addEventListener('click', clear);
        add.addEventListener('click', () => {
            clear();
            if (!navigator.geolocation) { status.textContent = 'Device location is unavailable. You can sign in without it.'; return; }
            const run = generation;
            add.disabled = true; status.textContent = 'Waiting for your browser’s permission and an approximate reading…';
            navigator.geolocation.getCurrentPosition(position => {
                if (run !== generation) return;
                const latitude = Math.round(position.coords.latitude * 10) / 10;
                const longitude = Math.round(position.coords.longitude * 10) / 10;
                if (!Number.isFinite(latitude) || !Number.isFinite(longitude) || Math.abs(latitude) > 90 || Math.abs(longitude) > 180) { clear(); return; }
                field('audit_latitude').value = latitude.toFixed(1);
                field('audit_longitude').value = longitude.toFixed(1);
                field('audit_accuracy_km').value = String(Math.min(40000, Math.max(10, Math.ceil((position.coords.accuracy || 10000) / 1000))));
                field('audit_location_opt_in').value = '1';
                status.textContent = `Approximate area ${latitude.toFixed(1)}, ${longitude.toFixed(1)} will be shared at sign-in. You can remove it.`;
                remove.hidden = false; add.disabled = false;
            }, () => {
                if (run !== generation) return;
                clear(); status.textContent = 'Location was not shared or could not be read. You can sign in normally.';
            }, { enableHighAccuracy: false, timeout: 10000, maximumAge: 300000 });
        });
    });
})();
