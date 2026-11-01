(() => {
    const form=document.querySelector('[data-bee-builder]'); if(!form)return;
    const teams=form.querySelector('[data-bee-teams]'), addTeam=form.querySelector('[data-bee-add-team]'), category=form.querySelector('[data-bee-category]');
    const teamRows=()=>Array.from(teams.querySelectorAll('[data-bee-team]'));
    function syncTeams(){
        const rows=teamRows();
        rows.forEach((row,index)=>{
            row.querySelector('[data-bee-team-number]').textContent=String(index+1);
            row.querySelectorAll('[name]').forEach(input=>input.name=input.name.replace(/^teams\[[^\]]+\]/,`teams[${index}]`));
            row.querySelector('[data-bee-remove-team]').disabled=rows.length<=2;
            row.querySelectorAll('[data-bee-group-field]').forEach(field=>{field.hidden=category.value!=='group';field.querySelectorAll('input,select').forEach(input=>input.disabled=category.value!=='group');});
        });
        addTeam.disabled=rows.length>=40;
        form.querySelector('[data-bee-team-count]').textContent=`${rows.length} entries · minimum 2 · maximum 40`;
    }
    addTeam.addEventListener('click',()=>{if(teamRows().length>=40)return;teams.append(form.querySelector('[data-bee-team-template]').content.cloneNode(true));syncTeams();teamRows().at(-1).querySelector('select').focus();});
    teams.addEventListener('click',event=>{const button=event.target.closest('[data-bee-remove-team]');if(!button||teamRows().length<=2)return;button.closest('[data-bee-team]').remove();syncTeams();addTeam.focus();});
    category.addEventListener('change',syncTeams);syncTeams();
    function syncQuestionTypes(){
        form.querySelectorAll('[data-bee-question-type]').forEach(select=>{
            const row=select.closest('[data-bee-question]'),fillBlank=select.value==='fill_blank';
            [['[data-bee-choice-fields]',fillBlank],['[data-bee-blank-fields]',!fillBlank]].forEach(([selector,hidden])=>{
                const fields=row.querySelector(selector);fields.hidden=hidden;fields.querySelectorAll('input,select,textarea').forEach(input=>input.disabled=hidden);
            });
        });
    }
    form.addEventListener('change',event=>{if(event.target.matches('[data-bee-question-type]'))syncQuestionTypes();});syncQuestionTypes();
    form.querySelectorAll('[data-bee-bank]').forEach(bank=>{
        const list=bank.querySelector('[data-bee-regular]'),add=bank.querySelector('[data-bee-add-question]');
        const rows=()=>Array.from(list.querySelectorAll('[data-bee-question]'));
        const sync=()=>{rows().forEach((row,index)=>{row.querySelector('[data-bee-question-number]').textContent=String(index+1);row.querySelectorAll('[name]').forEach(input=>input.name=input.name.replace(/\[regular\]\[[^\]]+\]/,`[regular][${index}]`));row.querySelector('[data-bee-remove-question]').disabled=rows().length<=1;});add.disabled=rows().length>=15;};
        add.addEventListener('click',()=>{if(rows().length>=15)return;list.append(bank.querySelector('[data-bee-question-template]').content.cloneNode(true));sync();syncQuestionTypes();rows().at(-1).open=true;rows().at(-1).querySelector('input').focus();});
        list.addEventListener('click',event=>{const button=event.target.closest('[data-bee-remove-question]');if(!button||rows().length<=1)return;button.closest('[data-bee-question]').remove();sync();add.focus();});sync();
    });
})();
