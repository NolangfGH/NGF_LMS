(() => {
    const standalone = () => window.matchMedia('(display-mode: standalone)').matches || navigator.standalone === true || navigator.userAgent.includes('NGFCodeQuestAndroid/');
    const installButtons = [...document.querySelectorAll('[data-app-install]')];
    const installHints = [...document.querySelectorAll('[data-app-install-hint]')];
    let installPrompt = null;
    const hint = message => installHints.forEach(node => { node.textContent = message; });
    const installed = () => {
        installButtons.forEach(button => { button.textContent = 'App is installed'; button.disabled = true; });
        hint('Open your portal to continue using your existing account.');
    };
    if (standalone()) installed();
    window.addEventListener('beforeinstallprompt', event => {
        event.preventDefault();
        installPrompt = event;
        if (!standalone()) hint('Ready to install. Tap Install website app and confirm in your browser.');
    });
    window.addEventListener('appinstalled', installed);
    installButtons.forEach(button => button.addEventListener('click', async () => {
        if (!installPrompt) {
            hint(/iPad|iPhone|iPod/.test(navigator.userAgent) ? 'In Safari, tap Share → Add to Home Screen. Enable Open as Web App if offered, then tap Add.' : 'Open this page in Chrome, then use its menu → Install app or Add to Home screen. Your browser decides when an install prompt is available.');
            return;
        }
        const prompt = installPrompt;
        installPrompt = null;
        button.disabled = true;
        try {
            await prompt.prompt();
            const choice = await prompt.userChoice;
            hint(choice.outcome === 'accepted' ? 'Installation requested. Look for the NGF CodeQuest icon on your device.' : 'Installation cancelled. You can continue in your browser.');
        } catch {
            hint('Use your browser menu → Install app or Add to Home screen.');
        } finally {
            if (!standalone()) button.disabled = false;
        }
    }));

    const connection = document.createElement('div');
    connection.className = 'ngf-connection-status';
    connection.setAttribute('role', 'status');
    connection.setAttribute('aria-live', 'polite');
    connection.textContent = 'You appear offline. Reconnect before saving or submitting. Check saved status after reconnecting; exam timers and deadlines continue.';
    connection.hidden = navigator.onLine !== false;
    document.body.append(connection);
    window.addEventListener('offline', () => { connection.hidden = false; });
    window.addEventListener('online', () => { connection.hidden = true; });

    if ('serviceWorker' in navigator && window.isSecureContext) {
        window.addEventListener('load', () => {
            navigator.serviceWorker.register('/sw.js', {scope: '/', updateViaCache: 'none'}).catch(() => {
                hint('The website is available. If installation is unavailable, refresh in your main browser while connected.');
            });
        });
    }

    const sidebar = document.querySelector('aside.portal-sidebar');
    const header = document.querySelector('header.portal-header');
    if (!sidebar || !header) return;
    document.body.classList.add('ngf-mobile-ready');
    sidebar.id ||= 'ngf-mobile-sidebar';
    const icon = path => { const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg'); svg.setAttribute('viewBox', '0 0 24 24'); svg.setAttribute('aria-hidden', 'true'); const line = document.createElementNS('http://www.w3.org/2000/svg', 'path'); line.setAttribute('d', path); line.setAttribute('fill', 'none'); line.setAttribute('stroke', 'currentColor'); line.setAttribute('stroke-width', '2'); line.setAttribute('stroke-linecap', 'round'); svg.append(line); return svg; };
    const toggle = document.createElement('button');
    toggle.type = 'button'; toggle.className = 'ngf-mobile-menu-toggle'; toggle.setAttribute('aria-label', 'Open portal menu'); toggle.setAttribute('aria-controls', sidebar.id); toggle.setAttribute('aria-expanded', 'false'); toggle.append(icon('M4 6h16M4 12h16M4 18h16')); header.prepend(toggle);
    const closeButton = document.createElement('button');
    closeButton.type = 'button'; closeButton.className = 'ngf-mobile-menu-close'; closeButton.setAttribute('aria-label', 'Close portal menu'); closeButton.append(icon('m6 6 12 12M18 6 6 18')); sidebar.firstElementChild.prepend(closeButton);
    const backdrop = document.createElement('button');
    backdrop.type = 'button'; backdrop.className = 'ngf-mobile-menu-backdrop'; backdrop.setAttribute('aria-label', 'Close portal navigation'); backdrop.tabIndex = -1; document.body.append(backdrop);
    let opened = false;
    const close = (restoreFocus = true) => {
        opened = false; document.body.classList.remove('ngf-mobile-menu-open'); toggle.setAttribute('aria-expanded', 'false'); sidebar.removeAttribute('role'); sidebar.removeAttribute('aria-modal');
        if (restoreFocus) toggle.focus();
    };
    toggle.addEventListener('click', () => {
        if (opened) return close();
        opened = true; document.body.classList.add('ngf-mobile-menu-open'); toggle.setAttribute('aria-expanded', 'true'); sidebar.setAttribute('role', 'dialog'); sidebar.setAttribute('aria-modal', 'true'); sidebar.setAttribute('aria-label', 'Portal navigation'); closeButton.focus();
    });
    closeButton.addEventListener('click', () => close());
    backdrop.addEventListener('click', () => close());
    sidebar.addEventListener('keydown', event => {
        if (!opened) return;
        if (event.key === 'Escape') { event.preventDefault(); close(); }
        if (event.key === 'Tab') {
            const items = [...sidebar.querySelectorAll('a[href],button:not(:disabled),input:not(:disabled),summary,[tabindex="0"]')].filter(item => item.getClientRects().length);
            if (event.shiftKey && document.activeElement === items[0]) { event.preventDefault(); items.at(-1)?.focus(); }
            else if (!event.shiftKey && document.activeElement === items.at(-1)) { event.preventDefault(); items[0]?.focus(); }
        }
    });
    window.matchMedia('(min-width:1024px)').addEventListener('change', event => { if (event.matches && opened) close(false); });
})();
