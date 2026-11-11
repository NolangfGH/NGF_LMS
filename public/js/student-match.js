(() => {
    const config=window.codingMatchConfig;
    if (!config || config.role!=='student') return;
    const get=id=>document.getElementById(id), editor=get('match-code'), status=get('match-save-status');
    let state=config.snapshot, offset=state.server_now-Date.now(), mine=state.participants.find(p=>p.mine);
    let revision=mine.revision, savedCode=mine.code, saving=null, timer=null, conflict=false, actionBusy=false, polling=false, sessionExpired=false;
    let submitted=mine.submissions.length>0;
    const message=value=>{get('match-action-message').textContent=value;};
    function tick() {
        const clock=window.CodingMatchClock.render(state,Date.now()+offset);
        const canEdit=clock.writable && !conflict && !submitted;
        const writable=canEdit && !actionBusy;
        editor.readOnly=!writable;
        get('match-save').disabled=!writable;
        get('match-submit').disabled=!writable;
        return canEdit;
    }
    async function request(url,method,data) {
        const controller=new AbortController(), timeout=setTimeout(()=>controller.abort(),15000);
        try {
            const response=await fetch(url,{method,headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':config.csrf},body:data===undefined ? undefined : JSON.stringify(data),cache:'no-store',signal:controller.signal});
            let payload={}; try {payload=await response.json();} catch {}
            if (!response.ok) {
                if (response.status===409) { conflict=true; status.textContent='Draft conflict. Download your code, then reload this page before editing.'; tick(); }
                if ([401,403,419].includes(response.status)) {sessionExpired=true;conflict=true; tick();}
                throw new Error(Object.values(payload.errors || {}).flat().join(' ') || payload.message || `Request failed (${response.status}). Keep a copy of your code and sign in again if needed.`);
            }
            return payload;
        } finally {clearTimeout(timeout);}
    }
    async function flush() {
        clearTimeout(timer);
        if (saving) return saving;
        saving=(async()=>{
            while (editor.value!==savedCode) {
                if (conflict || !tick()) throw new Error('Coding is closed or the draft has a conflict. Download your code to keep a copy.');
                const code=editor.value;
                status.textContent='Saving draft…';
                const result=await request(config.saveUrl,'PUT',{revision,code});
                revision=result.revision; savedCode=code;
                status.textContent=`Saved revision ${revision} · ${new Date().toLocaleTimeString()}`;
            }
        })();
        try {await saving;} catch(error) {if (!conflict) status.textContent='Not saved — retry Save draft. Your code is still in this editor.'; throw error;}
        finally {saving=null;}
    }
    function render() {
        mine=state.participants.find(p=>p.mine);
        if (mine.submissions.length) {
            submitted=true;
            clearTimeout(timer);
            get('match-submit').textContent='Final code submitted';
            status.textContent=editor.value===savedCode
                ? `Final submission received · Revision ${mine.submissions[0].revision}`
                : 'Final submission received. Download your local code if you need a copy.';
        }
        if (state.my_xp!==null && state.my_xp!==undefined) document.querySelectorAll('[data-student-xp]').forEach(node=>node.textContent=`${Number(state.my_xp).toLocaleString()} XP · Level ${state.my_level}`);
        get('match-invitation').hidden=!(state.phase==='inviting' && mine.response==='invited' && state.roster_valid);
        get('match-people').replaceChildren(...state.participants.map(p=>{
            const tag=document.createElement('span');tag.className='rounded-xl border border-green-200 bg-white px-4 py-3';
            tag.textContent=`${p.name}${p.mine ? ' (you)' : ''} · ${p.section} · ${p.response}`;return tag;
        }));
        const target=get('match-my-submissions');
        const fingerprint=JSON.stringify(mine.submissions);
        if (target.dataset.version!==fingerprint) {
            target.dataset.version=fingerprint;
            const rows=mine.submissions.map(s=>{
                const row=document.createElement('article');row.className='rounded-xl border border-green-100 bg-paper p-4';
                const title=document.createElement('b');title.textContent=`Revision ${s.revision} · ${s.on_time ? 'On time' : 'Overtime'} · ${s.verdict==='pending' ? 'Awaiting verification' : (s.verdict==='correct' ? 'Teacher-verified correct' : 'Needs improvement')}`;
                const time=document.createElement('p');time.className='mt-2 text-xs text-secondary';time.textContent=`Submitted ${new Date(s.submitted_at).toLocaleString()}`;
                const feedback=document.createElement('p');feedback.className='mt-3 whitespace-pre-wrap text-sm';feedback.textContent=s.feedback || 'Your teacher will check this submitted version.';
                row.append(title,time,feedback);return row;
            });
            if (!rows.length) {const empty=document.createElement('p');empty.textContent='No submission yet.';rows.push(empty);}
            target.replaceChildren(...rows);
        }
        tick();
    }
    async function poll() {
        if (polling || sessionExpired || document.hidden) return;
        polling=true;
        try {
            const next=await request(config.statusUrl,'GET');
            state=next;offset=state.server_now-Date.now();
            const remote=state.participants.find(p=>p.mine);
            if (!submitted && !remote.submissions.length && !saving && remote.revision!==revision) {
                conflict=true;status.textContent='Another tab saved a newer revision. Download your code before reloading. Editing paused.';
            }
            render(); get('match-connection').textContent=`Live · last synced ${new Date().toLocaleTimeString()}`;
        } catch(error) {get('match-connection').textContent=`Live update unavailable. ${error.message} Last saved data is kept.`;}
        finally {polling=false;}
    }
    editor.addEventListener('input',()=>{
        if (submitted) return;
        status.textContent='Unsaved changes…';clearTimeout(timer);
        timer=setTimeout(()=>flush().catch(error=>message(error.message)),750);
    });
    editor.addEventListener('keydown',event=>{
        if (event.key==='Tab' && !editor.readOnly) {
            event.preventDefault();const start=editor.selectionStart,end=editor.selectionEnd;
            editor.setRangeText('    ',start,end,'end');editor.dispatchEvent(new Event('input'));
        }
    });
    get('match-save').addEventListener('click',()=>flush().then(()=>message('Draft saved. Submit it when ready for verification.')).catch(error=>message(error.message)));
    get('match-submit').addEventListener('click',async()=>{
        if (actionBusy || !tick()) return;
        if (!window.confirm('Submit your final code? Each coder can submit once. Your workspace will lock after submission.')) return;
        actionBusy=true;tick();
        try {
            await flush();
            const result=await request(config.submitUrl,'POST',{revision});
            submitted=true;
            get('match-submit').textContent='Final code submitted';
            status.textContent=`Final submission received · Revision ${revision}`;
            message(result.message);
            await poll();
        } catch(error) {
            message(error.message);
            await poll();
        } finally {
            actionBusy=false;
            tick();
        }
    });
    for (const [id,decision] of [['match-accept','accepted'],['match-decline','declined']]) get(id).addEventListener('click',async()=>{
        get('match-accept').disabled=true;get('match-decline').disabled=true;
        try {await request(config.respondUrl,'POST',{decision});await poll();}
        catch(error) {message(error.message);} finally {get('match-accept').disabled=false;get('match-decline').disabled=false;}
    });
    get('match-download').addEventListener('click',()=>{
        const url=URL.createObjectURL(new Blob([editor.value],{type:'text/plain;charset=utf-8'})), link=document.createElement('a');
        link.href=url;link.download='main.cpp';link.click();setTimeout(()=>URL.revokeObjectURL(url),1000);
    });
    window.addEventListener('beforeunload',event=>{if (editor.value!==savedCode) {event.preventDefault();event.returnValue='';}});
    document.addEventListener('visibilitychange',()=>{if (!document.hidden) {tick();poll();}});
    render();poll();setInterval(poll,2000);setInterval(tick,250);
})();
