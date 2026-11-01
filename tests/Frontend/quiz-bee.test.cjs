const {test}=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const vm=require('node:vm');
const source=name=>fs.readFileSync(path.join(__dirname,'../../public/js',name),'utf8');
class Element {
    constructor(tag='div'){this.tag=tag;this.children=[];this.dataset={};this.style={};this.attrs={};this.listeners={};this.classList={toggle:(name,on)=>this.attrs[name]=on};}
    append(...items){items.forEach(item=>{item.parentElement=this;this.children.push(item);});}
    replaceChildren(...items){this.children=[];this.append(...items);}
    setAttribute(key,value){this.attrs[key]=value;}
    addEventListener(type,callback){this.listeners[type]=callback;}
    all(){return this.children.flatMap(child=>[child,...child.all()]);}
    querySelectorAll(selector){return this.all().filter(item=>item.tag===selector);}
    querySelector(selector){return this.all().find(item=>selector==='input:checked'?item.tag==='input'&&item.checked:selector.startsWith('input[value=')?item.tag==='input'&&item.value===selector.match(/"([^"]+)"/)[1]:item.tag===selector);}
}
function arena(){
    const start=Date.parse('2026-11-01T02:00:00Z');
    const state={now:start,requests:[],intervals:{},snapshot:{id:1,title:'C++ Bee',status:'running',server_now:new Date(start).toISOString(),regular_total:3,
        question:{id:1,sequence:1,level:'easy',kind:'regular',number:1,total:1,prompt:'Print <script> safely',options:['A','B','<img onerror=evil>','D'],opened_at:new Date(start).toISOString(),closes_at:new Date(start+60000).toISOString(),closed:false,answered:0,eligible:2,can_answer:true,own_answer:null,own_correct:null,correct:null},
        standings:[{rank:1,name:'Team <script>',correct:0,xp_per_member:0,ready:true,in_round:true,answered:false,own:true}],members:[{nickname:'Coder',captain:true,xp:0}]}};
    const keys=['state','message','phase','question-label','prompt','options','submit','receipt','explanation','standings','members','seconds','bar','answer'];
    const controls=Object.fromEntries(keys.map(key=>[`[data-bee-${key}]`,new Element()]));
    controls['[data-bee-state]'].textContent=JSON.stringify(state.snapshot);
    const track=new Element();track.append(controls['[data-bee-bar]']);
    const actions=['start','next','cancel','ready'].map(action=>{const element=new Element('button');element.dataset.beeAction=action;return element;});
    const root={dataset:{role:'student',statusUrl:'/status',actionUrl:'/action',csrf:'test-csrf'},querySelector:key=>controls[key],querySelectorAll:()=>actions};
    const document={hidden:false,listeners:{},querySelector:()=>root,createElement:tag=>new Element(tag),addEventListener(type,fn){this.listeners[type]=fn;}};
    const fetch=async(url,options)=>{state.requests.push({url,options});if(options.method==='POST'&&state.holdPost)await state.holdPost;return {ok:state.httpStatus?false:true,status:state.httpStatus||200,json:async()=>options.method==='POST'?{message:'Saved'}:structuredClone(state.snapshot)};};
    vm.runInNewContext(source('quiz-bee-arena.js'),{document,window:{confirm:()=>true},fetch,Date:{parse:Date.parse,now:()=>state.now},setInterval:(fn,ms)=>state.intervals[ms]=fn});
    return {state,controls,track,root,actions,document,clock:state.intervals[200],poll:state.intervals[5000],options:controls['[data-bee-options]'],submit:()=>controls['[data-bee-answer]'].listeners.submit({preventDefault(){}})};
}
const tick=()=>new Promise(resolve=>setImmediate(resolve));

test('the countdown bar uses an absolute deadline and disables answers at zero',()=>{
    const page=arena();assert.equal(page.controls['[data-bee-seconds]'].textContent,'60');
    page.state.now+=15000;page.clock();assert.equal(page.track.attrs['aria-valuenow'],'45');assert.equal(page.controls['[data-bee-bar]'].style.width,'75%');
    page.state.now+=45000;page.clock();assert.equal(page.controls['[data-bee-submit]'].disabled,true);assert.ok(page.options.querySelectorAll('input').every(input=>input.disabled));
    assert.equal(page.state.requests.length,0);
});

test('polling preserves an unsent selection and renders question text without HTML injection',async()=>{
    const page=arena(),selected=page.options.querySelectorAll('input')[2];selected.checked=true;
    page.state.snapshot.server_now=new Date(page.state.now+1000).toISOString();await page.poll();
    assert.equal(page.options.querySelectorAll('input')[2],selected);assert.equal(selected.checked,true);
    assert.equal(page.options.all().some(element=>element.tag==='img'),false);assert.equal(page.options.querySelectorAll('span')[2].textContent,'<img onerror=evil>');
    page.state.snapshot.question={...page.state.snapshot.question,id:2,sequence:2};await page.poll();assert.notEqual(page.options.querySelectorAll('input')[2],selected);
});

test('one click sends the selected answer and prevents a duplicate in-flight submission',async()=>{
    const page=arena();page.options.querySelectorAll('input')[2].checked=true;
    let release;page.state.holdPost=new Promise(resolve=>release=resolve);page.submit();page.submit();
    assert.equal(page.state.requests.length,1);assert.equal(page.controls['[data-bee-submit]'].disabled,true);
    const request=page.state.requests[0];assert.equal(request.options.headers['X-CSRF-TOKEN'],'test-csrf');assert.deepEqual(JSON.parse(request.options.body),{action:'answer',run_id:1,answer:2});
    page.state.snapshot.question.own_answer=2;page.state.snapshot.question.can_answer=false;release();await tick();await tick();
    assert.equal(page.state.requests.filter(request=>request.options.method==='POST').length,1);assert.equal(page.controls['[data-bee-submit]'].disabled,true);
});

test('cancellation is accepted without a question while older running snapshots cannot rewind play',async()=>{
    const page=arena();page.state.snapshot.question.sequence=0;await page.poll();assert.match(page.controls['[data-bee-phase]'].textContent,/EASY/);
    page.state.snapshot.status='cancelled';page.state.snapshot.question=null;await page.poll();
    assert.equal(page.controls['[data-bee-prompt]'].textContent,'This match was cancelled.');assert.equal(page.controls['[data-bee-submit]'].disabled,true);
});

test('expired sessions stop polling and hidden pages avoid background requests',async()=>{
    const page=arena();page.document.hidden=true;await page.poll();assert.equal(page.state.requests.length,0);
    page.document.hidden=false;page.state.httpStatus=403;await page.poll();page.clock();await page.poll();
    assert.equal(page.state.requests.length,1);assert.equal(page.controls['[data-bee-submit]'].disabled,true);assert.match(page.controls['[data-bee-message]'].textContent,/Session ended/);
});

function builder(){
    const element=extra=>({listeners:{},addEventListener(type,fn){this.listeners[type]=fn;},focus(){this.focused=true;},...extra});
    const teams=element({rows:[],querySelectorAll:()=>teams.rows,append:row=>teams.rows.push(row)}),category=element({value:'individual'}),add=element(),count={};
    function row(index){const fields=[element({name:`teams[${index}][name]`,value:'Team'}),element({name:`teams[${index}][captain_id]`,value:'1'}),element({name:`teams[${index}][members][]`,value:'2'})],number={},remove=element();const groups=[{querySelectorAll:()=>[fields[0]]},{querySelectorAll:()=>[fields[2]]}];const item={fields,groups,number,removeButton:remove,querySelector:s=>s==='[data-bee-team-number]'?number:s==='select'?fields[1]:remove,querySelectorAll:s=>s==='[name]'?fields:groups,remove:()=>teams.rows.splice(teams.rows.indexOf(item),1)};remove.closest=()=>item;return item;}
    teams.rows=[row(0),row(1)];
    const list=element({rows:[],querySelectorAll:()=>list.rows,append:item=>list.rows.push(item)}),addQuestion=element();
    function question(index){const number={},input=element({name:`questions[easy][regular][${index}][prompt]`,value:'Prompt'}),remove=element();const item={input,removeButton:remove,querySelector:s=>s==='[data-bee-question-number]'?number:s==='input'?input:remove,querySelectorAll:()=>[input],remove:()=>list.rows.splice(list.rows.indexOf(item),1)};remove.closest=()=>item;return item;}
    list.rows=[question(0)];const bank={querySelector:s=>({'[data-bee-regular]':list,'[data-bee-add-question]':addQuestion,'[data-bee-question-template]':{content:{cloneNode:()=>question('__QUESTION__')}}})[s]};
    const form={querySelector:s=>({'[data-bee-teams]':teams,'[data-bee-add-team]':add,'[data-bee-category]':category,'[data-bee-team-count]':count,'[data-bee-team-template]':{content:{cloneNode:()=>row('__TEAM__')}}})[s],querySelectorAll:()=>[bank]};
    vm.runInNewContext(source('quiz-bee-builder.js'),{document:{querySelector:()=>form}});
    return {teams,category,add,count,list,addQuestion};
}

test('builder caps teams at forty, preserves values on reindex and enables at most four additional members',()=>{
    const page=builder();assert.ok(page.teams.rows.every(row=>row.removeButton.disabled));assert.equal(page.teams.rows[0].fields[2].disabled,true);
    page.category.value='group';page.category.listeners.change();assert.equal(page.teams.rows[0].fields[2].disabled,false);
    for(let i=0;i<40;i++)page.add.listeners.click();assert.equal(page.teams.rows.length,40);assert.equal(page.add.disabled,true);
    const last=page.teams.rows[39];last.fields[0].value='Saved team';page.teams.listeners.click({target:{closest:()=>page.teams.rows[1].removeButton}});
    assert.equal(page.teams.rows.length,39);assert.equal(last.fields[0].name,'teams[38][name]');assert.equal(last.fields[0].value,'Saved team');
});

test('builder caps regular questions at fifteen and keeps at least one',()=>{
    const page=builder();assert.equal(page.list.rows[0].removeButton.disabled,true);
    for(let i=0;i<20;i++)page.addQuestion.listeners.click();assert.equal(page.list.rows.length,15);assert.equal(page.addQuestion.disabled,true);
    const last=page.list.rows[14];page.list.listeners.click({target:{closest:()=>page.list.rows[0].removeButton}});
    assert.equal(last.input.name,'questions[easy][regular][13][prompt]');assert.equal(page.addQuestion.disabled,false);
});
