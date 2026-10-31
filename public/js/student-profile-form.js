(() => {
    const form=document.querySelector('[data-student-profile-form]');if(!form) return;
    let dirty=false,submitting=false;
    const password=form.elements.namedItem('password'), confirmation=form.elements.namedItem('password_confirmation');
    function check() {
        const required=Boolean(password.value || confirmation.value);
        confirmation.required=password.required || required;
        confirmation.setCustomValidity(confirmation.value && confirmation.value!==password.value ? 'Passwords must match.' : '');
    }
    form.addEventListener('input',()=>{dirty=true;check();});
    form.addEventListener('change',()=>{dirty=true;});
    form.addEventListener('submit',()=>{submitting=true;});
    window.addEventListener('beforeunload',event=>{if(dirty && !submitting) {event.preventDefault();event.returnValue='';}});
    check();
})();
