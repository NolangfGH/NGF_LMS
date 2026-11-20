(() => {
    'use strict';
    document.addEventListener('click', event => {
        const button = event.target.closest('[data-load-lesson-video]');
        if (!button) { return; }
        const root = button.closest('[data-lesson-video]');
        const player = root?.querySelector('[data-lesson-video-player]');
        if (!player || player.children.length) { return; }
        const frame = document.createElement('iframe');
        frame.src = button.dataset.videoSrc;
        frame.title = button.dataset.videoTitle || 'Lesson video';
        frame.allow = 'encrypted-media; fullscreen; picture-in-picture';
        frame.allowFullscreen = true;
        frame.referrerPolicy = 'strict-origin-when-cross-origin';
        frame.className = 'lesson-video-frame';
        player.append(frame);
        player.hidden = false;
        button.hidden = true;
        if (root.querySelector('[data-video-placeholder]')) root.querySelector('[data-video-placeholder]').hidden = true;
    });
})();
