(() => {
    'use strict';
    document.addEventListener('change', event => {
        const input = event.target;
        if (!input.matches('[data-blog-image-input]')) return;
        const preview = input.parentElement.parentElement.querySelector('[data-blog-image-preview]');
        if (!preview) return;
        if (preview.dataset.objectUrl) URL.revokeObjectURL(preview.dataset.objectUrl);
        const file = input.files[0];
        if (!file || !['image/jpeg', 'image/png', 'image/webp'].includes(file.type) || file.size > 2 * 1024 * 1024) {
            preview.hidden = true;
            input.setCustomValidity(file ? 'Choose a JPEG, PNG or WebP image up to 2 MB.' : '');
            input.reportValidity();
            return;
        }
        input.setCustomValidity('');
        preview.dataset.objectUrl = URL.createObjectURL(file);
        preview.src = preview.dataset.objectUrl;
        preview.hidden = false;
    });
    document.addEventListener('submit', event => {
        if (!event.target.closest('main') || !event.target.matches('form[enctype="multipart/form-data"]')) return;
        const button = event.target.querySelector('button[type="submit"], button:not([type])');
        if (button) {
            button.disabled = true;
            button.textContent = 'Saving…';
        }
    });
    const thread = document.querySelector('[data-blog-thread]');
    if (!thread) return;
    const status = document.querySelector('[data-blog-sync]');
    let running = false;
    let previousHtml = null;
    function hasUnsentResponse() {
        return window.blogHasActiveWork?.() || [...thread.querySelectorAll('audio')].some(audio=>!audio.paused) || thread.contains(document.activeElement) || [...thread.querySelectorAll('textarea, input, select')].some(input =>
            input.type === 'file' ? input.files.length : input.type === 'checkbox' ? input.checked !== input.defaultChecked :
                input.tagName === 'SELECT' ? !input.options[input.selectedIndex]?.defaultSelected && input.selectedIndex !== 0 :
                input.tagName === 'TEXTAREA' ? input.value.length > 0 : input.value !== input.defaultValue);
    }
    async function refresh() {
        if (running || document.hidden) return;
        // A learner or teacher's unsent response must survive a live refresh.
        if (hasUnsentResponse()) {
            status.textContent = 'Live updates paused while you write';
            return;
        }
        running = true;
        try {
            const response = await fetch(thread.dataset.threadUrl, {headers: {Accept: 'text/html'}, cache: 'no-store'});
            if (!response.ok || response.redirected) throw new Error('Session or connection unavailable');
            const html = await response.text();
            if (hasUnsentResponse()) {
                status.textContent = 'Live updates paused while you write';
                return;
            }
            if (html !== previousHtml) {
                thread.innerHTML = html;
                previousHtml = html;
            }
            status.textContent = 'Private discussion up to date · refreshes every 15s';
        } catch (_) {
            status.textContent = 'Live updates unavailable. Refresh the page or sign in again.';
        } finally {
            running = false;
        }
    }
    window.setInterval(refresh, 15000);
    window.addEventListener('pageshow', () => {
        document.querySelectorAll('form button:disabled').forEach(button => { button.disabled = false; });
    });
})();
