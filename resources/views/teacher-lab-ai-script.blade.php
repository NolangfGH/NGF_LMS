<script>
(() => {
    const cachedDrafts=@json($aiDrafts);
    const forms=Array.from(document.querySelectorAll('[data-lab-review]'));
    const states=new WeakMap();
    function fields(form){return Array.from(form.querySelectorAll('[data-rubric],textarea[name="feedback"]'));}
    function total(form){
        const scores=Array.from(form.querySelectorAll('[data-rubric]'));
        form.querySelector('[data-rubric-total]').textContent=scores.every(score=>score.value!=='')
            ? 'Your draft total: '+scores.reduce((sum,score)=>sum+Number(score.value),0)+' / 16 points · not yet published'
            : 'Choose all four scores · maximum 16 points';
    }
    function apply(form,draft,automatic,before){
        const state=states.get(form);
        for(const field of fields(form)){
            if(automatic ? state.edited.has(field) : field.value!==before.get(field))continue;
            field.value=field.dataset.rubric ? String(draft.rubric[field.dataset.rubric]) : draft.feedback;
        }
        for(const criterion of Object.keys(draft.rubric)){
            form.querySelector('[data-ai-rationale="'+criterion+'"]').textContent='AI suggestion: '+draft.rubric[criterion]+'/4 — '+draft.rationale[criterion];
        }
        const list=form.querySelector('[data-ai-limitations]');list.replaceChildren();
        for(const limitation of draft.limitations){
            const item=document.createElement('li');item.textContent=limitation;list.append(item);
        }
        total(form);
    }
    async function load(form,automatic){
        const state=states.get(form);
        if(state.busy||state.finalizing)return;
        const before=new Map(fields(form).map(field=>[field,field.value]));
        const button=form.querySelector('[data-ai-suggest]'),status=form.querySelector('[data-ai-status]');
        state.busy=true;button.disabled=true;status.textContent='Preparing AI draft… Your final review has not been saved.';
        try{
            let draft=cachedDrafts[form.dataset.submission];
            if(!draft){
                const response=await fetch(form.dataset.aiUrl,{
                    method:'POST',
                    headers:{Accept:'application/json','X-CSRF-TOKEN':form.querySelector('[name="_token"]').value},
                });
                if(response.status===419)throw new Error('Your session expired. Reload the page. Your manual review is still available.');
                let result;
                try{result=await response.json();}
                catch{throw new Error('AI suggestions could not be loaded. Try again or review manually.');}
                if(!response.ok)throw new Error(result.message||'AI suggestions are unavailable. You can review manually.');
                draft=result.suggestion;cachedDrafts[form.dataset.submission]=draft;
            }
            if(state.finalizing)return;
            apply(form,draft,automatic,before);
            status.textContent='AI draft loaded. Review and change the scores and feedback, then save your teacher review. Automatic loading keeps your edits.';
            button.textContent='Use saved AI suggestions';
        }catch(error){
            status.textContent=error.message||'AI suggestions could not be loaded. Manual review remains available.';
        }finally{
            state.busy=false;
            button.disabled=state.finalizing || (form.dataset.aiConfigured!=='1'&&!cachedDrafts[form.dataset.submission]);
        }
    }
    for(const form of forms){
        const state={edited:new Set(),busy:false,finalizing:false};states.set(form,state);
        const changed=event=>{if(fields(form).includes(event.target)){state.edited.add(event.target);total(form);}};
        form.addEventListener('input',changed);form.addEventListener('change',changed);
        form.querySelector('[data-ai-suggest]').addEventListener('click',()=>load(form,false));
        form.addEventListener('submit',()=>{
            state.finalizing=true;form.querySelector('[data-ai-suggest]').disabled=true;
        });
    }
    // AI generation never submits the teacher's final review form.
    (async()=>{
        for(const form of forms){
            if(form.dataset.aiConfigured==='1'||cachedDrafts[form.dataset.submission])await load(form,true);
        }
    })();
})();
</script>
