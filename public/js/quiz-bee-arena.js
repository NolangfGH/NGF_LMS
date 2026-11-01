(() => {
    const root=document.querySelector('[data-bee-arena]');if(!root)return;
    const find=selector=>root.querySelector(selector),teacher=root.dataset.role==='teacher';
    let state=JSON.parse(find('[data-bee-state]').textContent),offset=Date.parse(state.server_now)-Date.now(),questionId=null,busy=false,polling=false,stopped=false;
    const message=text=>{find('[data-bee-message]').textContent=text;};
    const remaining=()=>state.question&&!state.question.closed?Math.max(0,Date.parse(state.question.closes_at)-(Date.now()+offset)):0;
    function clock(){
        const seconds=Math.ceil(remaining()/1000),bar=find('[data-bee-bar]'),track=bar.parentElement;
        find('[data-bee-seconds]').textContent=state.question?String(seconds):'—';bar.style.width=`${seconds/60*100}%`;track.setAttribute('aria-valuenow',String(seconds));track.dataset.urgent=String(seconds<=10);
        find('[data-bee-submit]').disabled=stopped||busy||!state.question?.can_answer||seconds<=0;
        find('[data-bee-options]').querySelectorAll('input').forEach(input=>input.disabled=stopped||busy||!state.question?.can_answer||seconds<=0);
    }
    function render(next){
        if(Date.parse(next.server_now)<Date.parse(state.server_now)||(next.status==='running'&&(next.question?.sequence||0)<(state.question?.sequence||0))||(['completed','cancelled'].includes(state.status)&&next.status!==state.status))return;
        state=next;offset=Date.parse(state.server_now)-Date.now();
        const q=state.question,options=find('[data-bee-options]');
        find('[data-bee-phase]').textContent=state.status==='completed'?'Match complete · final standings':state.status==='cancelled'?'Match cancelled':q?`${q.level.toUpperCase()} · ${q.kind==='tiebreak'?'Tie-breaker':'Regular round'} · Question ${q.number} of ${q.total}`:state.status==='draft'?'Unpublished draft':'Lobby · captains mark ready';
        root.querySelectorAll('[data-bee-action]').forEach(button=>{const action=button.dataset.beeAction;button.hidden=action==='cancel'?['completed','cancelled'].includes(state.status):!state[`can_${action}`];button.disabled=busy||stopped;});
        find('[data-bee-question-label]').textContent=q?`${q.answered} / ${q.eligible} eligible teams answered`:'Waiting room';
        find('[data-bee-prompt]').textContent=q?q.prompt:state.status==='cancelled'?'This match was cancelled.':state.status==='draft'?'Review the draft and publish invitations.':'Your captain marks ready. The teacher starts when every captain is ready.';
        const fillBlank=q?.type==='fill_blank';
        if(questionId!==q?.id){
            questionId=q?.id;options.replaceChildren();
            if(q&&fillBlank){
                const label=document.createElement('label'),input=document.createElement('input'),span=document.createElement('span');
                span.textContent='Your answer · fill in the blank';input.type='text';input.name='answer';input.value='';input.required=true;input.maxLength=500;input.autocomplete='off';input.spellcheck=false;input.setAttribute('autocapitalize','off');input.setAttribute('aria-label','Fill in the blank answer');label.dataset.fillBlank='true';label.append(span,input);options.append(label);
                const help=document.createElement('p');help.className='bee-help';help.textContent=q.case_sensitive?'Capitalization, punctuation and internal spacing must match. Outer spaces are ignored.':'Capitalization and outer spaces are ignored. Punctuation and internal spacing must match.';options.append(help);
            }else if(q)q.options.forEach((text,index)=>{const label=document.createElement('label'),input=document.createElement('input'),span=document.createElement('span');input.type='radio';input.name='answer';input.value=String(index);input.required=true;label.dataset.option=String(index);span.textContent=text;label.append(input,span);options.append(label);});
        }
        options.querySelectorAll('label').forEach((label,index)=>label.classList.toggle('bee-correct',q?.closed&&(fillBlank?q.own_correct===true:q.correct===index)));
        if(q?.own_answer!==null&&q?.own_answer!==undefined){const input=fillBlank?options.querySelector('input'):options.querySelector(`input[value="${q.own_answer}"]`);if(input){if(fillBlank)input.value=q.own_answer;else input.checked=true;}}
        find('[data-bee-receipt]').textContent=q?.closed?(q.own_correct===null?'Question closed.':`${q.own_correct?'Correct':'Incorrect / unanswered'} · ${q.own_seconds} seconds · +${q.own_xp} XP per member`):q?.own_answer!==null&&q?.own_answer!==undefined?'Answer saved. It cannot be changed.':q&&!q.can_answer?(teacher?'Monitoring answers…':'Only your captain can answer, or your team is not in this tie-breaker.'):'Submit before the countdown reaches zero. Answers are final.';
        find('[data-bee-explanation]').hidden=!q?.closed;find('[data-bee-explanation]').textContent=q?.closed?`Correct answer${fillBlank&&q.accepted_answers.length>1?'s':''}: ${fillBlank?q.accepted_answers.join(' / '):q.options[q.correct]}. ${q.why||''}`:'';
        const table=find('[data-bee-standings]');table.replaceChildren();state.standings.forEach(row=>{const tr=document.createElement('tr');tr.dataset.own=String(Boolean(row.own));const progress=state.status==='lobby'?(row.ready?'Ready':'Invited'):!row.in_round?'Not in tie-breaker':q?.closed&&teacher?(row.last_correct?'Correct':'Incorrect / timeout')+` · ${row.seconds??'—'}s`:row.answered?'Answered':'Waiting';[row.rank,row.name,row.correct,row.xp_per_member,progress].forEach(value=>{const td=document.createElement('td');td.textContent=String(value);tr.append(td);});table.append(tr);});
        const members=find('[data-bee-members]');members.replaceChildren();state.members.forEach(member=>{const p=document.createElement('p');p.textContent=`${member.nickname}${member.captain?' · Captain':''} · ${member.xp} XP earned in this match`;members.append(p);});clock();
        message(state.status==='completed'?'Results and XP are saved. Unresolved ties share a rank.':state.status==='cancelled'?'Match cancelled. Previously credited XP remains saved.':q?.closed?'Question closed. The teacher can advance.':state.status==='lobby'?'Waiting for captains and teacher.':'Live match connected. Each question has a server-enforced deadline.');
    }
    async function refresh(){
        if(polling||stopped||document.hidden)return;polling=true;
        try{const response=await fetch(root.dataset.statusUrl,{headers:{Accept:'application/json'},cache:'no-store'});if([401,403,419].includes(response.status)){stopped=true;message('Session ended. Sign in again.');return;}if(!response.ok)throw Error('Connection unavailable');render(await response.json());}
        catch{message('Connection interrupted. The server timer continues. Reconnect or refresh; saved answers remain final.');}finally{polling=false;}
    }
    async function act(action,answer){
        if(busy||stopped)return;busy=true;clock();root.querySelectorAll('[data-bee-action]').forEach(button=>button.disabled=true);
        try{const response=await fetch(root.dataset.actionUrl,{method:'POST',headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':root.dataset.csrf},body:JSON.stringify({action,run_id:state.question?.id,answer})});const data=await response.json();if(!response.ok)throw Error(data.message||'Unable to save. Refresh the match.');await refresh();}
        catch(error){message(error.message);await refresh();}finally{busy=false;clock();root.querySelectorAll('[data-bee-action]').forEach(button=>button.disabled=stopped);}
    }
    root.querySelectorAll('[data-bee-action]').forEach(button=>button.addEventListener('click',()=>{if(button.dataset.beeAction==='cancel'&&!window.confirm('Cancel this match? Saved answers and credited XP will be retained.'))return;act(button.dataset.beeAction);}));
    find('[data-bee-answer]').addEventListener('submit',event=>{event.preventDefault();const fillBlank=state.question?.type==='fill_blank',selected=fillBlank?find('[data-bee-options]').querySelector('input')?.value.trim():find('[data-bee-options]').querySelector('input:checked')?.value;if(selected===null||selected===undefined||selected===''){message(fillBlank?'Enter an answer before submitting.':'Choose an answer before submitting.');return;}act('answer',fillBlank?selected:Number(selected));});
    document.addEventListener('visibilitychange',()=>{if(!document.hidden)refresh();});render(state);setInterval(clock,200);setInterval(refresh,teacher?3000:5000);
})();
