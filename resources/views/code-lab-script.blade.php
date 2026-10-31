<script>
const labInitial = @json($labClient);
const labForm=document.getElementById('workspaceForm'), editor=document.getElementById('codeEditor'), saveStatus=document.getElementById('saveStatus'), labMessage=document.getElementById('labMessage');
let revision=labInitial.revision, dirty=false, conflict=false, saving=Promise.resolve(), debounce, submitting=false;
let timerState=labInitial.timer, timerClock=performance.now(), timeExpired=false, accessClosed=false, pollingTimer=false, escapeEditorTab=false;
function serverNow(){return timerState.serverNow+performance.now()-timerClock;}
function lockLabInputs(){
    const locked=timeExpired||accessClosed;
    labForm.querySelectorAll('textarea').forEach(control=>control.readOnly=locked);
    if(!submitting)['saveCode','resetCode','submitLab'].forEach(id=>document.getElementById(id).disabled=locked);
}
function refreshLabTimer(){
    const panel=document.getElementById('labTimer');
    if(!panel)return;
    panel.hidden=!timerState.expiresAt;
    document.getElementById('noLabTimer').hidden=Boolean(timerState.expiresAt);
    if(!timerState.expiresAt)return;
    const remaining=Math.max(0,timerState.expiresAt-serverNow());
    const seconds=Math.ceil(remaining/1000);
    timeExpired=remaining<=0;
    document.getElementById('timerRemaining').textContent=Math.floor(seconds/60).toString().padStart(2,'0')+':'+(seconds%60).toString().padStart(2,'0');
    document.getElementById('timerAllotted').textContent=timerState.durationSeconds/60;
    const total=Math.max(1,timerState.expiresAt-timerState.startedAt);
    const percent=Math.max(0,Math.min(100,remaining/total*100));
    document.getElementById('timerBar').style.width=percent+'%';
    document.getElementById('timerBar').style.backgroundColor=seconds<=60?'#b91c1c':seconds<=300?'#b45309':'#15803d';
    document.getElementById('timerProgress').setAttribute('aria-valuenow',Math.round(percent));
    document.getElementById('timerProgress').setAttribute('aria-valuetext',seconds+' seconds remaining');
    document.getElementById('timerHelp').textContent=timeExpired?'Time is up. Editing and submission are closed. Your last saved draft is kept; you can download any unsaved code.':'Your timer continues across refreshes. Save regularly and submit your code and explanation before time runs out.';
    lockLabInputs();
}
function applyLabTimer(timer){timerState=timer;timerClock=performance.now();refreshLabTimer();}
async function syncLabTimer(){
    if(pollingTimer||!document.getElementById('labTimer'))return;
    pollingTimer=true;
    try{
        const response=await fetch(labInitial.statusUrl,{headers:{Accept:'application/json'},cache:'no-store'});
        if([401,403,404].includes(response.status)){
            if(!accessClosed)showLabError('This assignment is no longer available. Your saved work is kept; download any unsaved code.');
            accessClosed=true;lockLabInputs();return;
        }
        if(!response.ok)return;
        const result=await response.json();accessClosed=result.closed;applyLabTimer(result.timer);lockLabInputs();
    }catch(error){/* Keep counting down from the last server clock while offline. */}
    finally{pollingTimer=false;}
}
function payload(){return {code:editor.value,reflection:labForm.elements.reflection.value};}
function showLabError(message){labMessage.textContent=message;labMessage.classList.remove('hidden');labMessage.scrollIntoView({behavior:'smooth',block:'center'});}
async function labRequest(url,method,data){
    const response=await fetch(url,{method,headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':labForm.querySelector('[name="_token"]').value},body:JSON.stringify(data)});
    const result=await response.json();
    if(!response.ok){
        if(response.status===409)conflict=true;
        if(result.errors?.time){timerState.expiresAt=serverNow();refreshLabTimer();}
        if(result.errors?.assignment||[401,403,404].includes(response.status)){accessClosed=true;lockLabInputs();}
        throw new Error(Object.values(result.errors||{}).flat().join(' ')||result.message||'Unable to save.');
    }
    return result;
}
function saveDraft(){
    const task=async()=>{
        refreshLabTimer();
        if(timeExpired||accessClosed)throw new Error('Work is closed. Your last saved draft is kept; download any unsaved code.');
        if(conflict)throw new Error('Another tab changed this draft. Download your code and reload before saving.');
        const data=payload(); if(!data.code.trim())throw new Error('Add some code before saving.');
        saveStatus.textContent='Saving…';
        const result=await labRequest(labInitial.saveUrl,'PUT',{...data,revision});
        revision=result.revision;dirty=JSON.stringify(payload())!==JSON.stringify(data);
        if(result.timer)applyLabTimer(result.timer);
        saveStatus.textContent=dirty?'New edits waiting to save':'Saved revision '+revision;
        if(!dirty)labMessage.classList.add('hidden');
    };
    saving=saving.catch(()=>{}).then(task);
    return saving;
}
function scheduleSave(){if(timeExpired||accessClosed)return;dirty=true;saveStatus.textContent='Unsaved changes';clearTimeout(debounce);debounce=setTimeout(()=>saveDraft().catch(error=>{saveStatus.textContent='Not saved';showLabError(error.message);}),800);}
function updateEditor(){const lines=editor.value.split('\n').length;document.getElementById('lineNumbers').textContent=Array.from({length:lines},(_,i)=>i+1).join('\n');document.getElementById('codeCount').textContent=lines+' lines · '+editor.value.length+' / 20,000 characters';}
labForm.addEventListener('input',()=>{updateEditor();scheduleSave();});
editor.addEventListener('scroll',()=>document.getElementById('lineNumbers').scrollTop=editor.scrollTop);
editor.addEventListener('keydown',event=>{
    if(event.key==='Escape'){escapeEditorTab=true;return;}
    if(event.key==='Tab' && escapeEditorTab){escapeEditorTab=false;return;}
    if(event.key==='Tab'&&!event.shiftKey&&!timeExpired&&!accessClosed){event.preventDefault();editor.setRangeText('    ',editor.selectionStart,editor.selectionEnd,'end');updateEditor();scheduleSave();}
    if((event.ctrlKey||event.metaKey)&&event.key.toLowerCase()==='s'){event.preventDefault();saveDraft().catch(error=>showLabError(error.message));}
});
document.getElementById('saveCode').onclick=()=>saveDraft().catch(error=>showLabError(error.message));
document.getElementById('downloadCode').onclick=()=>{const url=URL.createObjectURL(new Blob([editor.value],{type:'text/plain;charset=utf-8'}));const link=document.createElement('a');link.href=url;link.download='main.cpp';link.click();setTimeout(()=>URL.revokeObjectURL(url),1000);};
document.getElementById('resetCode').onclick=()=>{if(timeExpired||accessClosed)return;if(!confirm('Replace this draft with the starter code? Download your current code first if you want a copy.'))return;editor.value=labInitial.starter;updateEditor();editor.dispatchEvent(new CustomEvent('codequest:editor-change'));scheduleSave();};
const initialActivityUrl=document.getElementById('activityChooser').value;
document.getElementById('activityChooser').onchange=event=>{if(dirty && !confirm('This draft has unsaved changes. Switch activities anyway?')){event.target.value=initialActivityUrl;return;}dirty=false;location.href=event.target.value;};
window.addEventListener('beforeunload',event=>{if(dirty){event.preventDefault();event.returnValue='';}});
labForm.onsubmit=async event=>{
    event.preventDefault();if(submitting)return;
    refreshLabTimer();if(timeExpired||accessClosed)return showLabError('Your work time has ended. Your last saved draft is kept.');
    if(labForm.elements.reflection.value.trim().length<10)return showLabError('Explain your solution in at least 10 characters before submitting.');
    clearTimeout(debounce);submitting=true;const controls=Array.from(labForm.querySelectorAll('textarea,button,select'));controls.forEach(control=>control.disabled=true);document.getElementById('activityChooser').disabled=true;
    try{await saveDraft();const result=await labRequest(labInitial.submitUrl,'POST',{revision});dirty=false;saveStatus.textContent=result.message;location.reload();}
    catch(error){showLabError(error.message);controls.forEach(control=>control.disabled=false);document.getElementById('activityChooser').disabled=false;submitting=false;lockLabInputs();}
};
document.getElementById('checkCode').onclick=()=>{
    const source=editor.value;
    // Mask comments and quoted text so brackets and keywords inside them are not treated as code.
    const masked=source.replace(/\/\*[\s\S]*?\*\/|\/\/[^\n]*|"(?:\\.|[^"\\])*"|'(?:\\.|[^'\\])*'/g,match=>match.replace(/[^\n]/g,' '));
    const notes=[];const stack=[];const pairs={')':'(',']':'[','}':'{'};let unbalanced=false;
    for(const char of masked){if('([{'.includes(char))stack.push(char);else if(')]}'.includes(char)&&stack.pop()!==pairs[char])unbalanced=true;}
    notes.push(unbalanced||stack.length?'Review brackets: an opening or closing bracket may be missing.':'Bracket pairs appear balanced.');
    notes.push(/\bint\s+main\s*\(/.test(masked)?'A main function was found.':'Add an int main() function as your program entry point.');
    if(/\b(?:cout|cin)\b/.test(masked)&&!/#\s*include\s*<iostream>/.test(masked))notes.push('Console input/output usually needs #include <iostream>.');
    if(/\b(?:cout|cin)\b/.test(masked)&&!/std\s*::/.test(masked)&&!/using\s+namespace\s+std\s*;/.test(masked))notes.push('Use std::cout / std::cin, or check your namespace declaration.');
    if(/\bcout\s*>>/.test(masked))notes.push('Use cout << to display output. The >> operator is for reading input with cin.');
    if(/\bcin\s*<</.test(masked))notes.push('Use cin >> variable to read input. Use cout << to display output.');
    const suspect=[];masked.split('\n').forEach((line,index)=>{const text=line.trim();if(/^(?:std::cout|cout|return\b|int\s+\w+\s*=)/.test(text)&&!/[;{}]\s*$/.test(text))suspect.push(index+1);});
    if(suspect.length)notes.push('Check for missing semicolons on line(s): '+suspect.join(', ')+'.');
    if(/\b(?:while|for)\s*\(/.test(masked))notes.push('Loop check: explain which variable changes and why the condition eventually becomes false.');
    if(/TODO|Start coding|Write your|Print your/i.test(source))notes.push('Starter instructions may still be present. Check that you have completed each task.');
    notes.push('Test plan: try a normal value, a boundary value, and an unexpected value. Compare the result in your compiler with the assignment objective and sample output.');
    const list=document.getElementById('codeChecks');list.replaceChildren();notes.forEach(note=>{const item=document.createElement('li');item.className='rounded-xl bg-paper p-3 leading-6';item.textContent=note;list.append(item);});
    list.scrollIntoView({behavior:'smooth',block:'nearest'});
};
updateEditor();
refreshLabTimer();
setInterval(refreshLabTimer,1000);
setInterval(syncLabTimer,15000);
document.addEventListener('visibilitychange',()=>{if(!document.hidden){refreshLabTimer();syncLabTimer();}});
window.addEventListener('pageshow',event=>{if(event.persisted){refreshLabTimer();syncLabTimer();}});
</script>
