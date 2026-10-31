(() => {
    'use strict';
    const timers = new Map();
    const recordings = new Map();
    const voiceUrls = new WeakMap();
    const limit = 2 * 1024 * 1024;
    const errorBox = form => form.querySelector('[data-blog-form-error]');
    function showError(form, message) {
        const box = errorBox(form);
        if (box) { box.hidden = false; box.textContent = message; }
        else window.alert(message);
    }
    function voicePreview(panel, file) {
        const player = panel.querySelector('[data-voice-preview]');
        if (voiceUrls.has(panel)) URL.revokeObjectURL(voiceUrls.get(panel));
        if (file) { const url = URL.createObjectURL(file); voiceUrls.set(panel,url); player.src = url; }
        player.hidden = !file;
        panel.querySelector('[data-voice-remove]').hidden = !file;
    }
    function stopRecording(form) {
        const recording = recordings.get(form);
        if (!recording) return Promise.resolve();
        if (recording.recorder.state !== 'inactive') recording.recorder.stop();
        return recording.done;
    }
    async function record(panel) {
        const form = panel.closest('form');
        if (!window.MediaRecorder || !navigator.mediaDevices?.getUserMedia) {
            panel.querySelector('[data-voice-status]').textContent = 'Recording is unavailable. Use HTTPS or localhost, or upload a voice clip below.';
            return;
        }
        if (recordings.size) { panel.querySelector('[data-voice-status]').textContent = 'Stop the current recording first.'; return; }
        let stream;
        let interval;
        try {
            stream = await navigator.mediaDevices.getUserMedia({audio:true});
            if (form.querySelector('fieldset:disabled') || timers.get(form)?.finishing) { stream.getTracks().forEach(track=>track.stop()); return; }
            const mime = ['audio/webm;codecs=opus','audio/mp4','audio/ogg;codecs=opus'].find(type=>MediaRecorder.isTypeSupported(type));
            const recorder = new MediaRecorder(stream,{...(mime ? {mimeType:mime} : {}),audioBitsPerSecond:32000});
            const chunks = [];
            let resolve;
            const done = new Promise(fn=>{resolve=fn;});
            const started = Date.now();
            const status = panel.querySelector('[data-voice-status]');
            interval = window.setInterval(()=>{
                const seconds = Math.floor((Date.now()-started)/1000);
                status.textContent = `Recording ${seconds}s / 90s`;
                if (seconds >= 90 && recorder.state !== 'inactive') recorder.stop();
            },250);
            recorder.addEventListener('dataavailable',event=>{ if(event.data.size) chunks.push(event.data); });
            recorder.addEventListener('stop',()=>{
                window.clearInterval(interval);
                stream.getTracks().forEach(track=>track.stop());
                recordings.delete(form);
                const type = recorder.mimeType || chunks[0]?.type || 'audio/webm';
                const blob = new Blob(chunks,{type});
                const extension = type.includes('mp4') ? 'm4a' : type.includes('ogg') ? 'ogg' : 'webm';
                const input = panel.querySelector('[data-voice-file]');
                if (blob.size && blob.size <= limit) {
                    try {
                        const data = new DataTransfer();
                        data.items.add(new File([blob],`voice-message.${extension}`,{type}));
                        input.files = data.files;
                        input.dispatchEvent(new Event('change',{bubbles:true}));
                        status.textContent = 'Voice ready. Listen before sending.';
                    } catch (_) { status.textContent = 'This browser could not attach the recording. Upload a voice file instead.'; }
                } else status.textContent = 'Recording was empty or too large. Record again or upload a clip up to 2 MB.';
                panel.querySelector('[data-voice-stop]').hidden = true;
                panel.querySelector('[data-voice-record]').disabled = false;
                resolve();
            });
            recorder.addEventListener('error',()=>{ if(recorder.state !== 'inactive') recorder.stop(); });
            recordings.set(form,{recorder,done,stream});
            panel.querySelector('[data-voice-record]').disabled = true;
            panel.querySelector('[data-voice-stop]').hidden = false;
            status.textContent = 'Recording…';
            recorder.start(1000);
        } catch (_) {
            if(interval) window.clearInterval(interval);
            stream?.getTracks().forEach(track=>track.stop());
            recordings.delete(form);
            panel.querySelector('[data-voice-record]').disabled=false;
            panel.querySelector('[data-voice-stop]').hidden=true;
            panel.querySelector('[data-voice-status]').textContent = 'Microphone access was not granted or recording failed. You can type or upload a voice clip instead.';
        }
    }
    document.addEventListener('click',event=>{
        const button = event.target.closest('button');
        if (!button) return;
        const panel = button.closest('[data-blog-voice]');
        if (panel && button.matches('[data-voice-record]')) record(panel);
        if (panel && button.matches('[data-voice-stop]')) stopRecording(panel.closest('form'));
        if (panel && button.matches('[data-voice-remove]')) {
            const form = panel.closest('form');
            panel.querySelector('[data-voice-file]').value = '';
            voicePreview(panel,null);
            panel.querySelector('[data-voice-status]').textContent = 'Voice removed.';
            form.dataset.removeAudio = '1';
            if (timers.has(form)) saveDraft(form);
        }
        if (button.matches('[data-blog-start]')) startTimer(button.closest('form'));
    });
    document.addEventListener('change',event=>{
        if (event.target.matches('[data-voice-file]')) {
            const input = event.target;
            const file = input.files[0];
            input.setCustomValidity(file && file.size > limit ? 'Choose a voice clip up to 2 MB.' : '');
            if (file?.size > limit) { input.reportValidity(); return; }
            input.closest('form').dataset.removeAudio = file ? '0' : '1';
            voicePreview(input.closest('[data-blog-voice]'),file);
        }
        const form = event.target.closest('[data-blog-composer]');
        if (form && timers.has(form)) saveDraft(form);
    });
    let debounce;
    document.addEventListener('input',event=>{
        const form = event.target.closest('[data-blog-composer]');
        if (!form || !timers.has(form)) return;
        window.clearTimeout(debounce);
        debounce = window.setTimeout(()=>saveDraft(form),400);
    });
    function status(form,message) { const box=form.querySelector('[data-blog-save-status]'); if(box) box.textContent=message; }
    async function send(form,url,data,keepalive=false) {
        const response = await fetch(url,{method:'POST',headers:{Accept:'application/json','X-CSRF-TOKEN':form.querySelector('[name="_token"]').value},body:data,cache:'no-store',keepalive});
        const result = await response.json();
        if (!response.ok) throw new Error(Object.values(result.errors || {}).flat().join(' ') || result.message || 'The response could not be saved.');
        return result;
    }
    function signature(file) { return file ? `${file.name}:${file.size}:${file.lastModified}` : ''; }
    function payload(form,state,all=false) {
        const data = new FormData(form);
        for (const kind of ['image','audio']) {
            const file = form.querySelector(`[name="${kind}"]`)?.files[0];
            if (!file || (!all && state.uploaded[kind] === signature(file))) data.delete(kind);
        }
        if(form.dataset.removeAudio === '1') data.set('remove_audio','1');
        return data;
    }
    function adopt(form,result) {
        form.dataset.blogTimerActive = 'true';
        const state = {result,deadline:Date.parse(result.expires_at),offset:Date.parse(result.server_now)-Date.now(),duration:result.duration_seconds,uploaded:{},saving:false,finishing:false,autoTriggered:false};
        timers.set(form,state);
        form.querySelector('[data-blog-response-fields]').disabled = false;
        const body = form.querySelector('textarea[name="body"]');
        body.value = result.body;
        form.querySelector('[data-blog-start]').hidden = true;
        form.querySelector('[data-blog-time-label]').hidden = false;
        form.querySelector('[data-blog-time-track]').hidden = false;
        if (result.image_url) form.querySelector('[data-blog-saved-media]').textContent = 'Your previously attached image is saved with this draft.';
        if (result.audio_url) {
            const player = form.querySelector('[data-voice-preview]');
            player.src=result.audio_url; player.hidden=false;
            form.querySelector('[data-voice-remove]').hidden=false;
        }
        status(form,'Timer running. Your draft will save as you write.');
        tick(form,state);
    }
    async function startTimer(form) {
        if (timers.has(form)) return;
        const button=form.querySelector('[data-blog-start]'); button.disabled=true;
        const data=new FormData();
        if (form.dataset.commentId) data.set('comment_id',form.dataset.commentId);
        try { adopt(form,await send(form,form.dataset.startUrl,data)); }
        catch(error) { showError(form,error.message); button.disabled=false; }
    }
    async function saveDraft(form) {
        const state=timers.get(form);
        if(!state || state.saving || state.finishing || state.deadline <= Date.now()+state.offset) return;
        state.saving=true;
        const signatures = Object.fromEntries(['image','audio'].map(kind=>[kind,signature(form.querySelector(`[name="${kind}"]`)?.files[0])]));
        try {
            const result=await send(form,state.result.save_url,payload(form,state));
            if(result.finished) { window.location.assign(result.redirect); return; }
            state.uploaded=signatures;
            if(!state.finishing) status(form,'Draft saved privately. The timer is still running.');
        } catch(error) { status(form,'Draft not saved: '+error.message+' Keep this page open and retry sending.'); }
        finally { state.saving=false; }
    }
    async function submitTimed(form,automatic=false) {
        const state=timers.get(form);
        if(!state || state.finishing) return;
        state.finishing=true;
        if(automatic) { form.querySelector('textarea[name="body"]').readOnly=true; status(form,'Time ended. Sending your response automatically…'); }
        await stopRecording(form);
        const data=payload(form,state,true);
        form.querySelector('[data-blog-response-fields]').disabled=true;
        let failure;
        for(let retry=0;retry<3;retry++) {
            try {
                const result=await send(form,state.result.submit_url,data);
                if(result.finished) { window.location.assign(result.redirect); return; }
            } catch(error) { failure=error; }
            await new Promise(resolve=>window.setTimeout(resolve,1000));
        }
        state.finishing=false;
        form.querySelector('[data-blog-response-fields]').disabled=false;
        showError(form,(failure?.message || 'Sending failed.')+' Your last saved draft is kept. Use Send private response to retry.');
    }
    function tick(form,state) {
        if(!form.isConnected || state.finishing) return;
        const remaining=Math.max(0,state.deadline-(Date.now()+state.offset));
        const seconds=Math.ceil(remaining/1000);
        form.querySelector('[data-blog-time-label]').textContent=`${Math.floor(seconds/60)}:${String(seconds%60).padStart(2,'0')} left`;
        const percent=Math.max(0,Math.min(100,remaining/(state.duration*1000)*100));
        form.querySelector('[data-blog-time-bar]').style.width=percent+'%';
        form.querySelector('[data-blog-time-track]').setAttribute('aria-valuenow',Math.round(percent));
        if(!remaining && !state.autoTriggered) { state.autoTriggered=true; submitTimed(form,true); }
    }
    document.addEventListener('submit',async event=>{
        const form=event.target;
        if(!form.matches('form[enctype="multipart/form-data"]') || !form.querySelector('[data-blog-voice]')) return;
        if(form.dataset.blogTimed === 'true') {
            event.preventDefault(); event.stopImmediatePropagation();
            if(!timers.has(form)) { showError(form,'Press Start response first.'); return; }
            submitTimed(form); return;
        }
        if(recordings.has(form)) {
            event.preventDefault(); event.stopImmediatePropagation();
            await stopRecording(form); form.requestSubmit();
        }
    });
    window.blogHasActiveWork=()=>recordings.size > 0 || [...timers].some(([form])=>form.isConnected);
    window.setInterval(()=>{ for(const [form,state] of timers) tick(form,state); },250);
    window.setInterval(()=>{ for(const [form] of timers) if(form.isConnected) saveDraft(form); },3000);
    const active=window.blogActiveAttempt;
    if(active && !active.finished) {
        const form=[...document.querySelectorAll('[data-blog-composer]')].find(form=>form.dataset.commentId === String(active.comment_id ?? ''));
        if(form) {
            form.dataset.blogTimed='true';
            adopt(form,active);
            form.closest('details')?.setAttribute('open','');
        }
    }
    window.addEventListener('pagehide',()=>{
        for(const [form,state] of timers) {
            if(state.finishing) continue;
            const data=new FormData(); data.set('body',form.querySelector('textarea[name="body"]').value);
            send(form,state.result.save_url,data,true).catch(()=>{});
        }
        for(const {recorder,stream} of recordings.values()) {
            stream.getTracks().forEach(track=>track.stop());
            if(recorder.state !== 'inactive') recorder.stop();
        }
    });
})();
