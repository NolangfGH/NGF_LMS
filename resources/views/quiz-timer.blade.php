<script>
const pageServerTime = {{ $quizServerTime }};
const pageStart = performance.now();
const serverNow = () => pageServerTime + (performance.now() - pageStart);
document.querySelectorAll('.timed-quiz').forEach(form => {
    const deadline = Number(form.dataset.deadline);
    const duration = Math.max(1,deadline - Number(form.dataset.started));
    const status = form.querySelector('.save-status');
    const button = form.querySelector('.submit-quiz');
    let finishing = false, busy = false, changed = false;
    let queue = Promise.resolve();
    function answers() {
        const values = Array(Number(form.dataset.count)).fill(null);
        for (const [name,value] of new FormData(form)) {
            const match = name.match(/^answers\[(\d+)\](\[\])?$/);
            if (!match) continue;
            const index = Number(match[1]);
            if(match[2]) { if(!Array.isArray(values[index])) values[index]=[]; values[index].push(value); }
            else values[index]=value;
        }
        return values;
    }
    async function send(url,payload) {
        const response = await fetch(url,{method:'POST',headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':form.querySelector('[name="_token"]').value},body:JSON.stringify({answers:payload})});
        if(!response.ok) { const result=await response.json(); throw new Error(Object.values(result.errors || {}).flat().join(' ') || result.message || 'Could not save.'); }
        return response;
    }
    function save() {
        if(finishing || busy || !changed) return;
        busy=true; changed=false;
        const payload=answers();
        queue=queue.then(async()=>{
            try { const response=await send(form.dataset.draftUrl,payload); const result=await response.json(); if(result.finished) return location.reload(); status.textContent='Answers saved automatically.'; }
            catch(error) { changed=true; status.textContent='Answers not saved: '+error.message+' Reconnecting…'; }
            finally { busy=false; }
        });
    }
    async function finish(automatic=false) {
        if(finishing) return;
        if(!automatic && !confirm('Submit your answers and finish your only attempt?')) return;
        finishing=true;
        const payload=answers();
        form.querySelectorAll('input,button').forEach(control=>control.disabled=true);
        status.textContent=automatic?'Time is up. Submitting saved answers…':'Submitting answers…';
        try { await queue; await send(form.action,payload); location.href='{{ route('student.quiz') }}'; }
        catch(error) { status.textContent=error.message+' Refresh the page to check your saved result.'; button.disabled=false; button.textContent='Check saved result'; button.onclick=event=>{event.preventDefault();location.reload();}; }
    }
    function tick() {
        const remaining=Math.max(0,deadline-serverNow());
        const seconds=Math.ceil(remaining/1000);
        form.querySelector('.timer-text').textContent=String(Math.floor(seconds/60)).padStart(2,'0')+':'+String(seconds%60).padStart(2,'0');
        const percent=Math.min(100,remaining/duration*100);
        const bar=form.querySelector('.timer-bar'); bar.style.width=percent+'%';
        bar.classList.toggle('bg-red-600',percent<=20); bar.classList.toggle('bg-green-700',percent>20);
        form.querySelector('[role="progressbar"]').setAttribute('aria-valuenow',Math.round(percent));
        if(remaining<=0) finish(true);
    }
    let debounce;
    form.addEventListener('input',()=>{changed=true;clearTimeout(debounce);debounce=setTimeout(save,600);});
    form.addEventListener('submit',event=>{event.preventDefault();finish();});
    window.addEventListener('online',save);
    setInterval(save,3000); setInterval(tick,250); tick();
});
</script>
