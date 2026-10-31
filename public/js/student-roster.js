(() => {
    const root=document.getElementById('student-roster'), form=document.getElementById('roster-filters');
    if(!root || !form) return;
    const status=document.getElementById('roster-live-status'), exportLink=document.getElementById('roster-export');
    let active=null,searchTimer=null;
    const params=()=>new URLSearchParams(new FormData(form));
    function setForm(query) {for(const field of form.elements) if(field.name && query.has(field.name)) field.value=query.get(field.name);}
    async function refresh(query=params(),passive=false) {
        if(document.hidden || passive && active) return;
        if(passive && [...root.querySelectorAll('[data-roster-fragment]')].some(node=>node.contains(document.activeElement))) return;
        active?.abort();const controller=new AbortController();active=controller;
        const timeout=setTimeout(()=>controller.abort(),15000);
        try {
            const response=await fetch(root.dataset.statusUrl+'?'+query,{headers:{Accept:'application/json'},cache:'no-store',signal:controller.signal});
            const data=await response.json();
            if(!response.ok) throw new Error(Object.values(data.errors || {}).flat().join(' ') || data.message || 'Sign in again if your session ended.');
            if(active!==controller) return;
            for(const [name,html] of Object.entries(data.fragments)) {
                const slot=root.querySelector(`[data-roster-fragment="${name}"]`);
                if(slot && slot.innerHTML!==html) slot.innerHTML=html;
            }
            setForm(query);
            const indexUrl=root.dataset.indexUrl+'?'+query;
            history.replaceState(null,'',indexUrl);
            const exportQuery=new URLSearchParams(query);exportQuery.delete('page');exportLink.href=root.dataset.exportUrl+'?'+exportQuery;
            status.textContent=`Saved data · Updated ${data.updated_at} · Refreshes every 15 seconds`;
        } catch(error) {
            if(active===controller) status.textContent=`Refresh unavailable: ${error.name==='AbortError' ? 'The request timed out.' : error.message} Showing the last successfully loaded roster. Use Refresh now to retry.`;
        } finally {clearTimeout(timeout);if(active===controller) active=null;}
    }
    function apply() {form.elements.namedItem('page').value='1';return refresh();}
    form.addEventListener('submit',event=>{event.preventDefault();clearTimeout(searchTimer);apply();});
    form.querySelectorAll('select').forEach(node=>node.addEventListener('change',apply));
    form.elements.namedItem('q').addEventListener('input',()=>{clearTimeout(searchTimer);searchTimer=setTimeout(apply,500);});
    root.addEventListener('click',async event=>{
        const copyButton=event.target.closest('[data-copy-class-code]');
        if(copyButton?.dataset?.copyClassCode) {
            event.preventDefault();
            const field=document.getElementById(copyButton.dataset.copyClassCode);
            if(!field) return;
            const announcement=document.getElementById('roster-copy-status');
            copyButton.disabled=true;
            try {
                if(!navigator.clipboard?.writeText) throw new Error('Clipboard unavailable');
                await navigator.clipboard.writeText(field.value);
                announcement.textContent=`Class code ${field.value} copied. Share it with students in this section.`;
            } catch {
                field.focus();field.select();
                announcement.textContent='Class code selected. Press Ctrl+C or use your device’s Copy action.';
            } finally {
                copyButton.disabled=false;
                announcement.hidden=false;
            }
            return;
        }
        const link=event.target.closest('a');if(!link || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
        const url=new URL(link.href,location.href);
        if(url.origin!==location.origin || url.pathname!==new URL(root.dataset.indexUrl).pathname || link.textContent.trim()==='Reset') return;
        event.preventDefault();const query=url.searchParams;if(!query.has('page')) query.set('page','1');setForm(query);refresh(query);
    });
    document.getElementById('roster-refresh').addEventListener('click',()=>refresh());
    document.addEventListener('visibilitychange',()=>{if(!document.hidden) refresh(params(),true);});
    setInterval(()=>refresh(params(),true),15000);
})();
