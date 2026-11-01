(() => {
    const root=document.querySelector('[data-attendance]');if(!root)return;
    const workspace=root.querySelector('[data-attendance-workspace]'),message=root.querySelector('[data-attendance-message]');
    let dirty=false,busy=false,polling=false,stopped=false,offset=0,readSequence=0;
    const announce=text=>message.textContent=text;
    function filter(){
        const query=(workspace.querySelector('[data-attendance-search]')?.value||'').trim().toLocaleLowerCase(),status=workspace.querySelector('[data-attendance-filter]')?.value||'all';let shown=0;
        workspace.querySelectorAll('[data-attendance-row]').forEach(row=>{row.hidden=!row.dataset.search.includes(query)||(status!=='all'&&(status==='pending'?row.dataset.pending!=='1':row.dataset.status!==status));if(!row.hidden)shown++;});
        const empty=workspace.querySelector('[data-attendance-empty]');if(empty)empty.hidden=shown>0;
    }
    function selected(){const label=workspace.querySelector('[data-attendance-selected]');if(label)label.textContent=`${workspace.querySelectorAll('[data-attendance-select]:checked').length} selected`;}
    function clock(){
        const window=workspace.querySelector('[data-attendance-window]');if(!window)return;
        const now=Date.now()+offset,open=Date.parse(window.dataset.open),close=Date.parse(window.dataset.close),active=window.dataset.enabled==='1'&&now>=open&&now<close;
        window.textContent=window.dataset.enabled!=='1'?'Check-in closed':now<open?'Check-in scheduled':active?`Check-in open · ${Math.ceil((close-now)/60000)} min remaining`:'Check-in window ended';
        const bar=workspace.querySelector('[data-attendance-bar]');if(bar)bar.value=active?Math.max(0,Math.min(100,(close-now)/(close-open)*100)):0;
        const projector=workspace.querySelector('[data-attendance-projector-status]');if(projector)projector.textContent=window.textContent;
    }
    async function refresh(force=false){
        if(stopped||(!force&&(polling||document.hidden||dirty||busy||workspace.querySelector('dialog[open]'))))return;
        polling=true;const sequence=++readSequence;
        try{
            const response=await fetch(root.dataset.statusUrl,{headers:{Accept:'application/json'},cache:'no-store'});if([401,403,419].includes(response.status)){stopped=true;throw Error('Session ended. Sign in again.');}
            const data=await response.json();if(!response.ok)throw Error(data.message||'Unable to refresh attendance.');
            if(sequence!==readSequence||(!force&&(dirty||busy)))return;
            const current=workspace.querySelector('[data-attendance-revision]')?.dataset.attendanceRevision;
            if(force||String(data.revision??'')!==String(current??'')){workspace.innerHTML=data.html;dirty=false;}
            offset=Date.parse(data.server_now)-Date.now();clock();announce('Connected · latest saved records loaded. Automatic refresh every 15 seconds.');
        }catch(error){if(sequence===readSequence)announce(error.message+' Your displayed records are retained.');}finally{if(sequence===readSequence)polling=false;}
    }
    root.addEventListener('input',event=>{if(event.target.matches('[data-attendance-search]'))filter();else if(event.target.closest('[data-attendance-form]')||event.target.matches('[data-attendance-select]')){dirty=true;announce('Unsaved changes · automatic roster refresh paused until you save or refresh.');}});
    root.addEventListener('change',event=>{
        if(event.target.matches('[data-attendance-filter]'))filter();
        if(event.target.matches('[data-attendance-all]')){workspace.querySelectorAll('[data-attendance-row]').forEach(row=>{const checkbox=row.querySelector('[data-attendance-select]');if(checkbox&&!row.hidden)checkbox.checked=event.target.checked;});dirty=true;selected();}
        if(event.target.matches('[data-attendance-select]')){dirty=true;selected();}
    });
    root.addEventListener('submit',async event=>{
        const form=event.target.closest('[data-attendance-form]');if(!form)return;event.preventDefault();if(busy||stopped)return;
        if(form.dataset.attendanceConfirm&&!window.confirm(form.dataset.attendanceConfirm))return;
        const data=new FormData(form);if(event.submitter?.name)data.set(event.submitter.name,event.submitter.value);
        busy=true;const controls=Array.from(workspace.querySelectorAll('input,select,textarea,button')).map(control=>[control,control.disabled]);controls.forEach(([control])=>control.disabled=true);
        try{
            const response=await fetch(form.action,{method:'POST',headers:{Accept:'application/json'},body:data});const result=await response.json();
            if(!response.ok)throw Error(Object.values(result.errors||{}).flat().join(' ')||result.message||'Unable to save attendance.');
            dirty=false;
            if(new URL(result.redirect,location.href).search!==location.search){location.assign(result.redirect);return;}
            await refresh(true);announce(result.message);
        }catch(error){announce(error.message);}
        finally{busy=false;controls.forEach(([control,disabled])=>control.disabled=disabled);}
    });
    root.addEventListener('click',async event=>{
        if(event.target.closest('[data-attendance-refresh]')){if(dirty&&!window.confirm('Discard unsaved attendance changes and load the latest records?'))return;dirty=false;await refresh(true);}
        if(event.target.closest('[data-attendance-print]'))window.print();
        if(event.target.closest('[data-attendance-project]'))workspace.querySelector('[data-attendance-dialog]')?.showModal();
        if(event.target.closest('[data-attendance-dismiss]'))workspace.querySelector('[data-attendance-dialog]')?.close();
        if(event.target.closest('[data-attendance-copy]')){try{await navigator.clipboard.writeText(workspace.querySelector('[data-attendance-code]').textContent.trim());announce('Class passcode copied. Share it only with students attending this class.');}catch{announce('Clipboard unavailable. Select the displayed class code and press Ctrl+C.');}}
    });
    window.addEventListener('beforeunload',event=>{if(dirty){event.preventDefault();event.returnValue='';}});
    document.addEventListener('visibilitychange',()=>{if(!document.hidden)refresh();});clock();setInterval(clock,1000);setInterval(()=>refresh(),15000);
})();
