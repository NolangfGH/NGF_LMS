(() => {
    document.querySelectorAll('[data-clear-on-entry]').forEach(field => {
        const clearPrefill = () => {
            field.value = '';
            field.removeEventListener('focus', clearPrefill);
            field.removeEventListener('pointerdown', clearPrefill);
            field.dispatchEvent(new Event('input', {bubbles: true}));
        };
        field.addEventListener('focus', clearPrefill);
        field.addEventListener('pointerdown', clearPrefill);
    });

    const menus = [...document.querySelectorAll('[data-profile-menu]')];
    const close = menu => {
        menu.open = false;
        menu.querySelector('summary').setAttribute('aria-expanded', 'false');
    };
    menus.forEach(menu => {
        const trigger = menu.querySelector('summary');
        trigger.setAttribute('aria-expanded', String(menu.open));
        menu.addEventListener('toggle', () => {
            trigger.setAttribute('aria-expanded', String(menu.open));
            if (menu.open) {
                menus.filter(other => other !== menu).forEach(close);
            }
        });
        menu.addEventListener('focusout', event => {
            if (!menu.contains(event.relatedTarget)) {
                close(menu);
            }
        });
        menu.addEventListener('keydown', event => {
            if (event.key === 'Escape' && menu.open) {
                close(menu);
                trigger.focus();
                event.preventDefault();
            }
        });
    });
    document.addEventListener('click', event => {
        menus.filter(menu => menu.open && !menu.contains(event.target)).forEach(close);
    });
})();
