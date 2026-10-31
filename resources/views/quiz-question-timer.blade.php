<script>
document.querySelectorAll('.question-quiz').forEach(form => {
    form.style.scrollMarginTop='80px';
    requestAnimationFrame(()=>form.scrollIntoView({block:'start'}));
    const deadline=Number(form.dataset.deadline), index=Number(form.dataset.index), untimed=form.dataset.untimed==="true", allotted=Number(form.dataset.seconds)||60;
    const status=form.querySelector('.question-save-status'), button=form.querySelector('.question-next');
    let busy=false, advancing=false, changed=false, queue=Promise.resolve(), debounce;
    function answer() {
        const data=new FormData(form);
        const multi=data.getAll('answer[]');
        return multi.length ? multi : (data.get('answer') || null);
    }
    async function send(url,value) {
        const response=await fetch(url,{method:'POST',headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':form.querySelector('[name="_token"]').value},body:JSON.stringify({question_index:index,answer:value,attempt_id:Number(form.querySelector('[name="attempt_id"]').value)})});
        const result=await response.json();
        if(!response.ok) throw new Error(Object.values(result.errors || {}).flat().join(' ') || result.message || 'Unable to save.');
        return result;
    }
    function save() {
        if(busy || advancing || !changed) return;
        const value=answer(); busy=true;changed=false;
        queue=queue.then(async()=>{
            try {const result=await send(form.dataset.draftUrl,value);if(result.finished || result.advanced) return location.reload();status.textContent='Answer saved.';}
            catch(error){changed=true;status.textContent='Answer not saved: '+error.message;}
            finally{busy=false;}
        });
    }
    async function next(expired=false) {
        if(advancing) return;
        advancing=true;
        const value=answer();
        button.disabled=true;form.querySelectorAll('[name^="answer"]').forEach(input=>input.disabled=true);
        status.textContent=expired?'Time is up. Locking the saved answer…':'Saving your answer…';
        try {await queue;await send(form.action,value);location.reload();}
        catch(error){status.textContent=error.message+' Refresh to check the current question.';form.querySelector('.question-reload').classList.remove('hidden');if(!navigator.onLine){advancing=false;}}
    }
    function tick() {
        form.querySelector('[role="progressbar"]').hidden=untimed;
        const ms=Math.max(0,deadline-serverNow()), seconds=Math.ceil(ms/1000);
        form.querySelector('.question-time').textContent=untimed?'Untimed':String(Math.floor(seconds/60)).padStart(2,'0')+':'+String(seconds%60).padStart(2,'0');
        const bar=form.querySelector('.question-time-bar');bar.style.width=Math.min(100,ms/(allotted*1000)*100)+'%';bar.classList.toggle('bg-red-600',seconds<=15);bar.classList.toggle('bg-green-700',seconds>15);
        form.querySelector('[role="progressbar"]').setAttribute('aria-valuenow',Math.min(allotted,seconds));
        if(!ms) next(true);
    }
    form.addEventListener('input',()=>{changed=true;clearTimeout(debounce);debounce=setTimeout(save,500);});
    form.addEventListener('submit',event=>{event.preventDefault();next();});
    window.addEventListener('online',()=>{if(serverNow()>=deadline) next(true);else save();});
    setInterval(save,3000);setInterval(tick,250);tick();
});
</script>
