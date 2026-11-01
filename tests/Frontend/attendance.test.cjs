const {test}=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const vm=require('node:vm');
const source=fs.readFileSync(require('node:path').join(__dirname,'../../public/js/attendance.js'),'utf8');
const tick=()=>new Promise(resolve=>setImmediate(resolve));
function page(){
    const state={now:Date.parse('2026-11-01T00:30:00Z'),requests:[],intervals:{},revision:'1',writes:0,confirm:true};
    const windowNode={dataset:{open:'2026-11-01T00:25:00Z',close:'2026-11-01T00:45:00Z',enabled:'1'}},bar={},message={},search={value:''},filter={value:'all'},selected={},empty={};
    const rows=['present','late','unmarked'].map((status,i)=>({hidden:false,dataset:{search:`coder ${i} id${i}`,status,pending:i===1?'1':'0'},checkbox:{checked:false},querySelector(){return this.checkbox;}}));
    const controls=[{disabled:false},{disabled:true}];const dialog={open:false,showModal(){this.open=true;},close(){this.open=false;}};
    const nodes={'[data-attendance-window]':windowNode,'[data-attendance-bar]':bar,'[data-attendance-projector-status]':{},'[data-attendance-search]':search,'[data-attendance-filter]':filter,'[data-attendance-selected]':selected,'[data-attendance-empty]':empty,'[data-attendance-code]':{textContent:'TESTCODE'},'[data-attendance-dialog]':dialog};
    const workspace={querySelector(selector){if(selector==='[data-attendance-revision]')return {dataset:{attendanceRevision:state.revision}};if(selector==='dialog[open]')return dialog.open?dialog:null;return nodes[selector];},querySelectorAll(selector){if(selector==='[data-attendance-row]')return rows;if(selector==='[data-attendance-select]:checked')return rows.filter(row=>row.checkbox.checked);return controls;},set innerHTML(value){state.writes++;state.revision='2';this.html=value;}};
    const listeners={},windowListeners={};const root={dataset:{statusUrl:'/class-attendance/status'},querySelector:selector=>selector==='[data-attendance-workspace]'?workspace:message,addEventListener:(type,fn)=>listeners[type]=fn};
    const document={hidden:false,querySelector:()=>root,addEventListener(){}};
    const browserWindow={confirm:()=>state.confirm,addEventListener:(type,fn)=>windowListeners[type]=fn,print:()=>state.printed=true};
    const fetch=async(url,options)=>{state.requests.push({url,options});if(options.method==='POST'&&state.holdPost)await state.holdPost;const failed=options.method==='POST'&&state.failPost;return {status:failed?409:200,ok:!failed,json:async()=>failed?{message:'Attendance changed. Refresh first.'}:options.method==='POST'?{message:'Attendance saved.',redirect:'http://localhost/class-attendance?classroom=1&date=2026-11-01'}:{html:'Updated workspace',revision:2,server_now:new Date(state.now).toISOString()}};};
    class Data{constructor(form){this.values=new Map(Object.entries(form.values||{}));}set(key,value){this.values.set(key,value);}}
    vm.runInNewContext(source,{document,window:browserWindow,location:{href:'http://localhost/class-attendance?classroom=1&date=2026-11-01',search:'?classroom=1&date=2026-11-01',assign:()=>state.navigated=true},navigator:{clipboard:{writeText:async value=>state.copied=value}},URL,FormData:Data,Date:{parse:Date.parse,now:()=>state.now},fetch,setInterval:(fn,ms)=>state.intervals[ms]=fn});
    const target=selector=>({matches:pattern=>pattern===selector,closest:pattern=>pattern===selector?{}:null});
    return {state,rows,bar,windowNode,message,search,filter,selected,empty,workspace,document,dialog,controls,listeners,windowListeners,target,clock:state.intervals[1000],poll:state.intervals[15000]};
}
test('check-in bar reflects the absolute scheduled window and closes at the server deadline',()=>{
    const p=page();assert.equal(p.bar.value,75);assert.match(p.windowNode.textContent,/15 min remaining/);
    p.state.now+=900000;p.clock();assert.equal(p.bar.value,0);assert.equal(p.windowNode.textContent,'Check-in window ended');
    p.windowNode.dataset.enabled='0';p.clock();assert.equal(p.windowNode.textContent,'Check-in closed');
});
test('search and status filters show the matching roster without modifying saved attendance',()=>{
    const p=page();p.search.value='id1';p.listeners.input({target:p.target('[data-attendance-search]')});
    assert.deepEqual(p.rows.map(row=>row.hidden),[true,false,true]);p.search.value='';p.filter.value='pending';p.listeners.change({target:p.target('[data-attendance-filter]')});
    assert.deepEqual(p.rows.map(row=>row.hidden),[true,false,true]);assert.equal(p.state.requests.length,0);
});
test('polling pauses for unsaved edits, hidden pages and projector mode',async()=>{
    const p=page();p.dialog.open=true;await p.poll();assert.equal(p.state.requests.length,0);p.dialog.open=false;p.document.hidden=true;await p.poll();assert.equal(p.state.requests.length,0);
    p.document.hidden=false;p.listeners.change({target:p.target('[data-attendance-select]')});await p.poll();assert.equal(p.state.requests.length,0);
    p.state.confirm=false;await p.listeners.click({target:p.target('[data-attendance-refresh]')});assert.equal(p.state.requests.length,0);
    p.state.confirm=true;await p.listeners.click({target:p.target('[data-attendance-refresh]')});assert.equal(p.state.writes,1);
});
test('a single submit sends its named action, blocks duplicates and restores disabled fields on conflict',async()=>{
    const p=page(),form={values:{revision:'1'},dataset:{},action:'/class-attendance/1'},event={target:{closest:()=>form},submitter:{name:'action',value:'finalize'},preventDefault(){}};
    let release;p.state.holdPost=new Promise(resolve=>release=resolve);p.state.failPost=true;
    const pending=p.listeners.submit(event);p.listeners.submit(event);assert.equal(p.state.requests.length,1);assert.equal(p.controls[0].disabled,true);
    assert.equal(p.state.requests[0].options.body.values.get('action'),'finalize');release();await pending;
    assert.equal(p.state.writes,0);assert.equal(p.controls[0].disabled,false);assert.equal(p.controls[1].disabled,true);assert.match(p.message.textContent,/Refresh first/);
});
test('successful saves refresh the roster and class passcode copy uses the displayed code',async()=>{
    const p=page(),form={dataset:{},action:'/class-attendance/1'};
    await p.listeners.submit({target:{closest:()=>form},submitter:{name:'action',value:'open'},preventDefault(){}});
    assert.equal(p.state.writes,1);assert.equal(p.state.requests.length,2);assert.equal(p.message.textContent,'Attendance saved.');
    await p.listeners.click({target:p.target('[data-attendance-copy]')});assert.equal(p.state.copied,'TESTCODE');
});
