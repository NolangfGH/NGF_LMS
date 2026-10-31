document.querySelectorAll('[data-ordering]').forEach(list=>{
    if(list.dataset.ready)return;list.dataset.ready='true';let dragged=null;
    const changed=()=>list.closest('form').dispatchEvent(new Event('input',{bubbles:true}));
    list.addEventListener('click',event=>{const row=event.target.closest('[data-order-step]');if(!row)return;
        if(event.target.closest('[data-order-up]')&&row.previousElementSibling){row.previousElementSibling.before(row);changed();}
        if(event.target.closest('[data-order-down]')&&row.nextElementSibling){row.nextElementSibling.after(row);changed();}
    });
    list.addEventListener('dragstart',event=>{dragged=event.target.closest('[data-order-step]');event.dataTransfer?.setData('text/plain','move-step');});
    list.addEventListener('dragover',event=>event.preventDefault());
    list.addEventListener('drop',event=>{event.preventDefault();const row=event.target.closest('[data-order-step]');if(row&&dragged&&row!==dragged){row.before(dragged);changed();}dragged=null;});
});
