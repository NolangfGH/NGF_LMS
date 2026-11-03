const {test}=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const vm=require('node:vm');
function page(){
    const checkboxes=[{checked:false,disabled:false},{checked:false,disabled:false},{checked:false,disabled:true}];checkboxes.forEach(input=>input.focus=()=>input.focused=true);
    const selected={},status={},listeners={},all={},form={},field={value:'CLASSCODE',focus(){this.focused=true;},select(){this.selected=true;}};
    all.addEventListener=(type,fn)=>all[type]=fn;form.addEventListener=(type,fn)=>form[type]=fn;
    const root={querySelector:s=>s==='[data-class-selection-count]'?selected:s==='[data-class-select-all]'?all:s==='[data-create-classes]'?form:status,querySelectorAll:()=>checkboxes.filter(input=>!input.disabled),addEventListener:(type,fn)=>listeners[type]=fn};
    const state={};vm.runInNewContext(fs.readFileSync('public/js/teacher-classes.js','utf8'),{document:{querySelector:()=>root,getElementById:()=>field},navigator:{clipboard:{writeText:async value=>{if(state.fail)throw Error('blocked');state.copied=value;}}}});
    return {checkboxes,selected,status,listeners,all,form,field,state};
}
test('only available sections are selected and empty submissions are stopped',()=>{
    const p=page();let prevented=false;p.form.submit({preventDefault(){prevented=true;}});assert.equal(prevented,true);
    p.all.click();assert.deepEqual(p.checkboxes.map(input=>input.checked),[true,true,false]);assert.match(p.selected.textContent,/2 section/);
    prevented=false;p.form.submit({preventDefault(){prevented=true;}});assert.equal(prevented,false);
});
test('join codes can be copied and fallback selects the correct field',async()=>{
    const p=page(),event={target:{closest:()=>({dataset:{classCopy:'class-code-1'}})}};
    await p.listeners.click(event);assert.equal(p.state.copied,'CLASSCODE');assert.match(p.status.textContent,/copied/);
    p.state.fail=true;await p.listeners.click(event);assert.equal(p.field.selected,true);assert.match(p.status.textContent,/Ctrl\+C/);
});
