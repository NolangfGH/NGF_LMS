const {test}=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const vm=require('node:vm');
const path=require('node:path');
const read=name=>fs.readFileSync(path.join(__dirname,'../../public/js',name),'utf8');
const settle=()=>new Promise(resolve=>setImmediate(resolve));
function node() {return {listeners:{},dataset:{},value:'',innerHTML:'previous',textContent:'',addEventListener(event,callback){this.listeners[event]=callback;},fire(event,details={}){return this.listeners[event]?.({preventDefault(){},...details});},contains(target){return this.focused===target && target!==null;}};}
function roster() {
    const fields=Object.fromEntries(Object.entries({q:'',classroom:'',status:'active',progress:'all',sort:'name',per_page:'100',page:'2'}).map(([name,value])=>[name,{...node(),name,value}]));
    const elements=Object.values(fields);elements.namedItem=name=>fields[name];
    const form={...node(),elements,querySelectorAll:()=>elements.filter(e=>!['q','page'].includes(e.name))};
    const fragments=Object.fromEntries(['metrics','sections','table'].map(name=>[name,node()]));
    const root={...node(),dataset:{statusUrl:'http://localhost/teacher-students/status',indexUrl:'http://localhost/teacher-students',exportUrl:'http://localhost/teacher-students/export'},
        querySelectorAll:()=>Object.values(fragments),querySelector:selector=>fragments[selector.match(/"(.+)"/)[1]]};
    const ids={'student-roster':root,'roster-filters':form,'roster-live-status':node(),'roster-export':node(),'roster-refresh':node()};
    const document={...node(),hidden:false,activeElement:null,getElementById:id=>ids[id]};
    const timers=new Map(),intervals=[];let timer=0;
    const data={updated_at:'Oct 29 · 8:00 AM',fragments:{metrics:'new metrics',sections:'new sections',table:'new students'}};
    const network={handler:null,calls:[]};
    const history={replaceState(...args){this.url=args[2];}};
    class FormData {constructor(form){this.entries=form.elements.map(field=>[field.name,field.value]);}[Symbol.iterator](){return this.entries[Symbol.iterator]();}}
    const context={document,FormData,URLSearchParams,URL,AbortController,history,location:{href:'http://localhost/teacher-students',origin:'http://localhost'},
        fetch:async(url,options)=>{network.calls.push({url,options});return network.handler ? network.handler(url,options) : {ok:true,json:async()=>structuredClone(data)};},
        setInterval(callback,ms){intervals.push({callback,ms});},setTimeout(callback,ms){const id=++timer;timers.set(id,{callback,ms});return id;},clearTimeout(id){timers.delete(id);}};
    vm.runInNewContext(read('student-roster.js'),context);
    return {fields,form,root,fragments,ids,document,history,network,
        interval(){return intervals[0].callback();},
        timeout(ms){const entry=[...timers.entries()].find(([,t])=>t.ms===ms);timers.delete(entry[0]);return entry[1].callback();}};
}
test('changing filters resets pagination and updates saved results and the filtered CSV link',async()=>{
    const app=roster();app.fields.classroom.value='3';await app.fields.classroom.fire('change');
    const query=new URL(app.network.calls[0].url).searchParams;
    assert.equal(query.get('page'),'1');assert.equal(query.get('classroom'),'3');
    assert.equal(app.fragments.table.innerHTML,'new students');assert.match(app.history.url,/teacher-students\?/);
    assert.match(app.ids['roster-export'].href,/classroom=3/);assert.equal(new URL(app.ids['roster-export'].href).searchParams.has('page'),false);
});
test('debounced search uses the latest text and a section link without a page stays on page one',async()=>{
    const app=roster();app.fields.q.value='Alpha';app.fields.q.fire('input');await app.timeout(500);
    assert.equal(new URL(app.network.calls[0].url).searchParams.get('q'),'Alpha');
    app.fields.page.value='3';const link={href:'http://localhost/teacher-students?classroom=2',textContent:'Grade 8 B'};
    app.root.fire('click',{target:{closest:()=>link}});await settle();
    assert.equal(new URL(app.network.calls[1].url).searchParams.get('page'),'1');assert.equal(app.fields.page.value,'1');
});
test('stale requests cannot replace newer filter results',async()=>{
    const app=roster();let finish;
    app.network.handler=()=>new Promise(resolve=>{finish=resolve;});
    const first=app.ids['roster-refresh'].fire('click');await settle();
    app.network.handler=async()=>({ok:true,json:async()=>({updated_at:'newer',fragments:{table:'latest matching students'}})});
    app.fields.q.value='Latest';await app.form.fire('submit');await settle();
    finish({ok:true,json:async()=>({updated_at:'old',fragments:{table:'stale students'}})});await first;
    assert.equal(app.fragments.table.innerHTML,'latest matching students');assert.equal(app.network.calls[0].options.signal.aborted,true);
});
test('failed or hidden refreshes preserve roster content and focused account actions',async()=>{
    const app=roster();app.network.handler=async()=>{throw new Error('Offline');};await app.ids['roster-refresh'].fire('click');
    assert.equal(app.fragments.table.innerHTML,'previous');assert.match(app.ids['roster-live-status'].textContent,/last successfully loaded roster/);
    const calls=app.network.calls.length;app.document.hidden=true;await app.interval();assert.equal(app.network.calls.length,calls);
    app.document.hidden=false;const button={};app.document.activeElement=button;app.fragments.table.focused=button;
    await app.interval();assert.equal(app.network.calls.length,calls);
});
test('profile forms check password confirmation and warn before leaving unsaved changes',()=>{
    const password={...node(),required:false},confirmation={...node(),setCustomValidity(value){this.error=value;}};
    const form={...node(),elements:{namedItem:name=>name==='password'?password:confirmation}};
    const window=node();vm.runInNewContext(read('student-profile-form.js'),{document:{querySelector:()=>form},window});
    password.value='NewPassword123';confirmation.value='Different123';form.fire('input');
    assert.equal(confirmation.required,true);assert.equal(confirmation.error,'Passwords must match.');
    const event={preventDefault(){this.prevented=true;}};window.listeners.beforeunload(event);assert.equal(event.prevented,true);
    confirmation.value=password.value;form.fire('input');assert.equal(confirmation.error,'');form.fire('submit');
    const submitted={preventDefault(){this.prevented=true;}};window.listeners.beforeunload(submitted);assert.equal(submitted.prevented,undefined);
});
