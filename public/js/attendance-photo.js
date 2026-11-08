(() => {
    document.querySelectorAll('[data-attendance-photo]').forEach(form => {
        const input = form.querySelector('[data-selfie-input]'), preview = form.querySelector('[data-selfie-preview]'), message = form.querySelector('[data-selfie-message]'), button = form.querySelector('button');
        let previewUrl = null, busy = false;
        input.addEventListener('change', () => {
            if (previewUrl) URL.revokeObjectURL(previewUrl);
            preview.hidden = !input.files[0];
            if (input.files[0]) { previewUrl = URL.createObjectURL(input.files[0]); preview.src = previewUrl; }
        });
        async function resize(file) {
            const url = URL.createObjectURL(file), image = new Image();
            try {
                await new Promise((resolve, reject) => { image.onload = resolve; image.onerror = () => reject(new Error('Choose a valid JPEG, PNG or WebP photo.')); image.src = url; });
                const scale = Math.min(1, 1200 / Math.max(image.naturalWidth, image.naturalHeight)), canvas = document.createElement('canvas');
                canvas.width = Math.max(1, Math.round(image.naturalWidth * scale)); canvas.height = Math.max(1, Math.round(image.naturalHeight * scale));
                canvas.getContext('2d').drawImage(image, 0, 0, canvas.width, canvas.height);
                const blob = await new Promise(resolve => canvas.toBlob(resolve, 'image/jpeg', 0.8));
                if (!blob || blob.size > 2 * 1024 * 1024) throw new Error('Please choose a smaller photo (up to 2 MB).');
                return blob;
            } finally { URL.revokeObjectURL(url); }
        }
        form.addEventListener('submit', async event => {
            event.preventDefault();
            if (busy || !form.reportValidity()) return;
            busy = true; button.disabled = true; message.textContent = 'Preparing your private photo…';
            let controller, timeout;
            try {
                const data = new FormData(form), blob = await resize(input.files[0]);
                data.set('photo', blob, 'attendance-selfie.jpg');
                controller = new AbortController(); timeout = setTimeout(() => controller.abort(), 20000);
                message.textContent = 'Sending to your class teacher…';
                const response = await fetch(form.action, {method: 'POST', headers: {Accept: 'application/json'}, body: data, signal: controller.signal});
                let payload = {}; try { payload = await response.json(); } catch {}
                if (!response.ok) throw new Error(Object.values(payload.errors || {}).flat().join(' ') || payload.message || 'Unable to send. Refresh and sign in again if needed.');
                message.textContent = payload.message; window.location.reload();
            } catch (error) { message.textContent = `Not sent: ${error.message} Refresh to check for a pending request before trying again.`; }
            finally { clearTimeout(timeout); busy = false; button.disabled = false; }
        });
    });
})();
