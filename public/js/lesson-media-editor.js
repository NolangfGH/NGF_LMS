(function () {
    'use strict';
    function previewVideoUrl(value, start = 0) {
        let url;
        try { url = new URL(value.trim()); } catch { return null; }
        if (url.protocol !== 'https:' || url.username || url.password || url.port && url.port !== '443') return null;
        const seconds = Math.max(0, Math.min(14400, Math.trunc(Number(start)) || 0));
        const path = url.pathname.replace(/^\/+|\/+$/g, '');
        if (['youtube.com','www.youtube.com','m.youtube.com','youtube-nocookie.com','www.youtube-nocookie.com','youtu.be'].includes(url.hostname)) {
            const id = url.hostname === 'youtu.be' ? path : path === 'watch' ? url.searchParams.get('v') : /^(?:embed|shorts|live)\/([A-Za-z0-9_-]{11})$/.exec(path)?.[1];
            return /^[A-Za-z0-9_-]{11}$/.test(id || '') ? 'https://www.youtube-nocookie.com/embed/'+id+'?rel=0'+(seconds ? '&start='+seconds : '') : null;
        }
        if (['vimeo.com','www.vimeo.com','player.vimeo.com'].includes(url.hostname)) {
            const match = /^(?:video\/)?([0-9]{1,12})(?:\/([A-Za-z0-9]{1,64}))?$/.exec(path);
            if (!match) return null;
            const hash = url.searchParams.get('h') || match[2];
            if (hash && !/^[A-Za-z0-9]{1,64}$/.test(hash)) return null;
            return 'https://player.vimeo.com/video/'+match[1]+'?'+(hash ? 'h='+hash+'&' : '')+'dnt=1'+(seconds ? '#t='+seconds+'s' : '');
        }
        return null;
    }
    const options = {
        width:['full','large','medium','small'], alignment:['center','left','right'],
        aspect:['natural','wide','standard','square','portrait'], fit:['contain','cover'],
        position:['center','top','bottom','left','right'], frame:['card','border','plain'],
    };
    function mediaClasses(kind, settings) {
        return ['lesson-media', ...Object.keys(options).filter(key => kind === 'image' || !['fit','position'].includes(key)).map(key => {
            const allowed = options[key].filter(value => kind !== 'video' || value !== 'natural');
            const value = allowed.includes(settings[kind+'_'+key]) ? settings[kind+'_'+key] : allowed[0];
            return 'lesson-media-'+(key === 'alignment' ? 'align' : key)+'-'+value;
        })].join(' ');
    }
    function initialize(document, window) {
        const editors = new WeakMap();
        function attach(root) {
            if (editors.has(root)) return;
            const image = root.querySelector('[data-explanation-image-preview]');
            const upload = root.querySelector('[data-explanation-image]');
            const remove = root.querySelector('[data-remove-image]');
            const imagePlaceholder = root.querySelector('[data-image-placeholder]');
            const video = root.querySelector('[data-media-preview="video"]');
            const button = video.querySelector('[data-load-lesson-video]');
            const player = video.querySelector('[data-lesson-video-player]');
            const placeholders = video.querySelector('[data-video-placeholder]');
            let selectedUrl = '';
            const release = () => { if (selectedUrl) { window.URL.revokeObjectURL(selectedUrl); selectedUrl = ''; } };
            const refresh = () => {
                const settings = Object.fromEntries([...root.querySelectorAll('[data-media-field]')].map(field => [field.dataset.mediaField, field.value]));
                ['image','video'].forEach(kind => {
                    root.querySelector('[data-media-preview="'+kind+'"]').className = mediaClasses(kind, settings);
                    const caption = root.querySelector('[data-media-caption="'+kind+'"]');
                    caption.textContent = settings[kind+'_caption'] || '';
                    caption.hidden = !caption.textContent.trim();
                });
                const src = selectedUrl || (!remove?.checked ? image.dataset.savedSrc : '');
                if (src) { if (image.getAttribute('src') !== src) image.src = src; }
                else { image.removeAttribute('src'); }
                image.hidden = !src;
                imagePlaceholder.hidden = !!src;
                const url = previewVideoUrl(settings.video_url || '', settings.video_start);
                const title = settings.video_title.trim() || 'Lesson video';
                if ((url || '') !== button.dataset.videoSrc) {
                    player.replaceChildren(); player.hidden = true; placeholders.hidden = false; button.hidden = false;
                }
                button.dataset.videoSrc = url || '';
                button.dataset.videoTitle = title;
                button.disabled = !url;
                const frame = player.querySelector('iframe');
                if (frame) frame.title = title;
                video.querySelector('[data-video-preview-title]').textContent = title;
                video.querySelector('[data-video-preview-status]').textContent = url ? 'Ready · Play loads the video provider.' : settings.video_url ? 'Use a valid HTTPS YouTube or Vimeo video link.' : 'Paste a supported video URL to preview.';
            };
            upload.addEventListener('change', () => {
                release();
                const file = upload.files?.[0];
                if (file && ['image/jpeg','image/png','image/webp'].includes(file.type) && file.size <= 2097152) selectedUrl = window.URL.createObjectURL(file);
                refresh();
            });
            root.addEventListener('input', refresh);
            root.addEventListener('change', refresh);
            editors.set(root, release);
            refresh();
        }
        document.querySelectorAll('[data-media-editor]').forEach(attach);
        window.addEventListener('ngf:explanation-added', event => event.detail.querySelectorAll('[data-media-editor]').forEach(attach));
        window.addEventListener('ngf:explanation-removed', event => event.detail.querySelectorAll('[data-media-editor]').forEach(root => editors.get(root)?.()));
    }
    if (typeof module !== 'undefined' && module.exports) module.exports = { previewVideoUrl, mediaClasses };
    if (typeof window !== 'undefined' && typeof document !== 'undefined') initialize(document, window);
})();
