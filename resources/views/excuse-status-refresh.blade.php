<script>
(() => {
    let editingForm=false;
    document.addEventListener('input',event=>{if(event.target.closest('form'))editingForm=true;});
    document.addEventListener('change',event=>{if(event.target.closest('form'))editingForm=true;});
    setInterval(()=>{
        if(document.hidden||editingForm||document.querySelector('.question-quiz,.timed-quiz'))return;
        if(document.querySelector('[data-excuse-pending]'))location.reload();
    },30000);
})();
</script>
