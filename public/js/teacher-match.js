(() => {
    const config=window.codingMatchConfig;
    if (!config || config.role!=='teacher') return;
    const get=id=>document.getElementById(id);
    let state=config.snapshot,offset=state.server_now-Date.now(),polling=false,actionBusy=false,sessionExpired=false;
    const cards=new Map();
    function node(tag,text,className) {const result=document.createElement(tag);result.textContent=text || '';if(className) result.className=className;return result;}
    function field(form,label,name,tag='textarea') {
        const wrapper=node('label',label,'mt-3 block text-xs font-bold');const input=node(tag);
        input.name=name;input.className='mt-2 w-full rounded-xl border bg-white p-3 text-sm font-normal';
        wrapper.append(input);form.append(wrapper);return input;
    }
    async function send(form) {
        if (actionBusy) return;
        actionBusy=true;
        get('match-action-message').textContent='Saving your decision…';
        const buttons=[...form.querySelectorAll('button')];buttons.forEach(button=>button.disabled=true);
        try {
            const response=await fetch(form.action,{method:'POST',headers:{Accept:'application/json','X-CSRF-TOKEN':config.csrf},body:new FormData(form),signal:AbortSignal.timeout(15000)});
            const data=await response.json();
            if (!response.ok) throw new Error(Object.values(data.errors || {}).flat().join(' ') || data.message || 'Could not save.');
            get('match-action-message').textContent=data.message;await poll();
        } catch(error) {get('match-action-message').textContent=`Could not confirm the save: ${error.message} Check the live status before retrying.`;}
        finally {actionBusy=false;buttons.forEach(button=>button.disabled=false);render();}
    }
    function submissionCard(submission) {
        const card=node('article',null,'rounded-xl border border-green-200 bg-paper p-4');
        const summary=node('p',null,'text-sm font-bold text-primary');card.append(summary);
        const details=node('details');details.append(node('summary','View immutable submitted code','mt-3 cursor-pointer text-xs font-bold'));
        const code = node('pre', submission.code, 'mt-3 max-h-80 overflow-auto rounded-xl bg-[#102518] p-4 font-mono text-xs leading-6 text-green-100');
        code.id = 'match-submitted-code-' + submission.id;
        code.tabIndex = 0;
        const compiler = node('button', 'Open in OneCompiler ↗', 'onecompiler-open');
        compiler.type = 'button';
        compiler.dataset.onecompilerEditor = code.id;
        compiler.dataset.onecompilerReview = 'true';
        compiler.dataset.onecompilerExpected = 'match-expected-output';
        const launcher = node('div', null, 'onecompiler-launcher');
        launcher.append(compiler, node('p', 'Opens this C++ code in the external OneCompiler editor for testing.', 'onecompiler-note'));
        details.append(code, launcher);
        const download=node('a','Download .cpp for testing','mt-3 inline-block text-xs font-bold text-primary underline');download.href=submission.download_url;details.append(download);card.append(details);
        const form=node('form');form.method='POST';form.action=config.reviewUrl.replace('/submissions/0/','/submissions/'+submission.id+'/');
        const csrf=node('input');csrf.type='hidden';csrf.name='_token';csrf.value=config.csrf;form.append(csrf);
        const verdict=field(form,'Teacher verification *','verdict','select');
        for(const [value,text] of [['revise','Needs improvement'],['correct','Correct — tested and verified']]) {const option=node('option',text);option.value=value;verdict.append(option);}
        verdict.value=submission.verdict==='correct' ? 'correct' : 'revise';
        const observed=field(form,'Observed output from your test (required for correct)','observed_output');observed.maxLength=10000;observed.rows=3;observed.value=submission.observed_output || '';observed.spellcheck=false;
        const feedback=field(form,'Actionable feedback *','feedback');feedback.required=true;feedback.minLength=5;feedback.maxLength=3000;feedback.rows=3;feedback.value=submission.feedback || '';
        const label=node('label',null,'my-3 flex items-start gap-2 text-xs leading-6');const checkbox=node('input');checkbox.type='checkbox';checkbox.name='verified';checkbox.value='1';checkbox.className='mt-1';
        label.append(checkbox,node('span','I tested this submitted version and verified it meets the task and sample output. Required for a correct verdict.'));form.append(label);
        function requirements() {checkbox.required=verdict.value==='correct';observed.required=verdict.value==='correct';}
        verdict.addEventListener('change',requirements);requirements();
        form.append(node('button','Save verification & feedback','rounded-xl bg-primary px-4 py-3 text-xs font-bold text-white disabled:bg-gray-300'));
        form.addEventListener('submit',event=>{event.preventDefault();send(form);});card.append(form);
        const savedFeedback=node('p',null,'mt-3 whitespace-pre-wrap text-sm');card.append(savedFeedback);
        return {card,summary,form,savedFeedback};
    }
    function render() {
        for(const participant of state.participants) {
            const panel=document.querySelector(`[data-monitor="${participant.id}"]`);
            panel.querySelector('[data-response]').textContent=participant.response;
            const code=panel.querySelector('[data-live-code]');if(code.textContent!==participant.code) code.textContent=participant.code;
            panel.querySelector('[data-saved]').textContent=`Revision ${participant.revision} · ${participant.saved_at ? 'Saved '+new Date(participant.saved_at).toLocaleString() : 'No changes saved yet'}`;
            const list=panel.querySelector('[data-submissions]');
            if (!participant.submissions.length && !list.children.length) list.append(node('p','No submitted versions yet.','text-xs text-secondary'));
            for(const submission of [...participant.submissions].sort((a,b)=>a.id-b.id)) {
                let view=cards.get(submission.id);
                if (!view) {
                    if (!list.querySelector('article')) list.replaceChildren();
                    view=submissionCard(submission);cards.set(submission.id,view);list.append(view.card);
                }
                view.summary.textContent=`#${submission.id} · Revision ${submission.revision} · ${submission.on_time ? 'On time' : 'Overtime'} · ${submission.verdict} · ${new Date(submission.submitted_at).toLocaleString()}`;
                view.form.hidden=state.phase==='awarded' || state.phase==='cancelled';
                view.form.querySelectorAll('button').forEach(button=>button.disabled=actionBusy);
                view.savedFeedback.textContent=submission.feedback ? 'Saved feedback: '+submission.feedback : '';
            }
        }
        const candidate=state.participants.flatMap(p=>p.submissions.map(s=>({...s,name:p.name}))).find(s=>s.id===state.candidate_id);
        get('match-candidate').textContent=candidate ? `Earliest verified correct: ${candidate.name}, submission #${candidate.id}. ${state.pending_earlier ? `${state.pending_earlier} earlier submission(s) still need review.` : 'All earlier submissions have been reviewed.'}` : 'No teacher-verified correct submission yet.';
        get('match-winning-id').value=state.candidate_id || '';
        get('match-award-button').disabled=!candidate || state.pending_earlier>0 || state.phase!=='active' && state.phase!=='overtime' || !state.roster_valid || actionBusy;
        get('match-award').hidden=['awarded','cancelled'].includes(state.phase);
        get('match-cancel-panel').hidden=['awarded','cancelled'].includes(state.phase);
        window.CodingMatchClock.render(state,Date.now()+offset);
    }
    async function poll() {
        if (polling || sessionExpired || document.hidden) return;
        polling=true;
        try {
            const response=await fetch(config.statusUrl,{headers:{Accept:'application/json'},cache:'no-store',signal:AbortSignal.timeout(15000)});
            if(!response.ok) {if([401,403,419].includes(response.status)) sessionExpired=true;throw new Error(`Live status unavailable (${response.status}). Sign in again if your session ended.`);}
            state=await response.json();offset=state.server_now-Date.now();render();
            get('match-connection').textContent=`Live · refreshes every 3 seconds · last synced ${new Date().toLocaleTimeString()}`;
        } catch(error) {get('match-connection').textContent=error.message+' Your current feedback text is kept.';}
        finally {polling=false;}
    }
    for(const id of ['match-award','match-cancel']) get(id).addEventListener('submit',event=>{event.preventDefault();send(get(id));});
    render();poll();setInterval(poll,3000);setInterval(()=>window.CodingMatchClock.render(state,Date.now()+offset),250);
    document.addEventListener('visibilitychange',poll);
})();
