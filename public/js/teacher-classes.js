(() => {
    const root=document.querySelector('[data-teacher-classes]');if(!root)return;
    const checkboxes=()=>Array.from(root.querySelectorAll('[data-class-section]:not(:disabled)'));
    const count=()=>{const selected=checkboxes().filter(input=>input.checked).length;root.querySelector('[data-class-selection-count]').textContent=selected?`${selected} section(s) selected · a separate join code will be generated for each.`:'Choose at least one available section.';};
    root.querySelector('[data-class-select-all]').addEventListener('click',()=>{checkboxes().forEach(input=>input.checked=true);count();});
    root.addEventListener('change',event=>{if(event.target.matches('[data-class-section]'))count();});count();
    root.addEventListener('click',async event=>{const button=event.target.closest('[data-class-copy]');if(!button)return;const field=document.getElementById(button.dataset.classCopy),status=root.querySelector('[data-class-copy-status]');if(!field)return;try{await navigator.clipboard.writeText(field.value);status.textContent='Join code copied. Share it only with students in this section.';}catch{field.focus();field.select();status.textContent='Code selected. Press Ctrl+C to copy.';}});
    root.querySelector('[data-create-classes]').addEventListener('submit',event=>{if(!checkboxes().some(input=>input.checked)){event.preventDefault();root.querySelector('[data-class-selection-count]').textContent='Select at least one available section before creating classes.';checkboxes()[0]?.focus();}});
})();
