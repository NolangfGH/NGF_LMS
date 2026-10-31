const {test}=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const vm=require('node:vm');
const path=require('node:path');
const source=name=>fs.readFileSync(path.join(__dirname,'../../public/js',name),'utf8');
const settle=()=>new Promise(resolve=>setImmediate(resolve));

function element(tag='div') {
    return {tagName:tag,children:[],dataset:{},style:{},listeners:{},textContent:'',value:'',hidden:false,disabled:false,
        addEventListener(event,callback) {this.listeners[event]=callback;},
        fire(event,data={}) {return this.listeners[event]?.({preventDefault(){},...data});},
        append(...items) {this.children.push(...items);},prepend(item) {this.children.unshift(item);},
        replaceChildren(...items) {this.children=items;},setAttribute(name,value) {this[name]=value;},
        contains(target) {return this===target || this.children.some(c=>c.contains?.(target));},
        querySelector(selector) {return descendants(this).find(c=>c.tagName===selector) || null;},
        querySelectorAll(selector) {return descendants(this).filter(c=>c.tagName===selector);},
        setRangeText(text,start,end) {this.value=this.value.slice(0,start)+text+this.value.slice(end);},
    };
}
function descendants(node) {return node.children.flatMap(c=>[c,...descendants(c)]);}
function snapshot() {
    return {server_now:100000,phase:'active',roster_valid:true,started_at:99000,deadline_at:159000,time_limit_minutes:1,
        candidate_id:null,pending_earlier:0,winner:null,xp_awarded:0,award_title:'Loop Champion',award_note:null,
        participants:[{id:1,mine:true,name:'Alpha',section:'Grade 8 A',response:'accepted',code:'int main() {}',revision:0,saved_at:null,submissions:[]},
            {id:2,mine:false,name:'Beta',section:'Grade 8 B',response:'accepted'}]};
}
function environment(role='student',initial=snapshot()) {
    const ids=new Map(),panels=new Map(),intervals=[],timers=new Map(),requests=[];
    let nextId=0,now=initial.server_now;
    const get=id=>{if(!ids.has(id)) ids.set(id,element());return ids.get(id);};
    const document={hidden:false,activeElement:null,listeners:{},getElementById:get,createElement:element,
        addEventListener(event,callback){this.listeners[event]=callback;},
        querySelector(selector) {return panels.get(Number(selector.match(/"(\d+)"/)?.[1]));},
    };
    const window=element();
    const config={role,snapshot:structuredClone(initial),statusUrl:'/status',saveUrl:'/save',submitUrl:'/submit',respondUrl:'/respond',reviewUrl:'/teacher/1/submissions/0/review',csrf:'test-token'};
    window.codingMatchConfig=config;
    const network={state:structuredClone(initial),handler:null};
    const fetch=async(url,options={})=>{
        const request={url,method:options.method || 'GET',body:typeof options.body==='string' ? JSON.parse(options.body) : options.body};requests.push(request);
        if(network.handler) {const response=await network.handler(request);if(response) return response;}
        if(url==='/save') {
            const own=network.state.participants[0];own.code=request.body.code;own.revision=request.body.revision+1;
            return reply({revision:own.revision});
        }
        return reply(url==='/status' ? structuredClone(network.state) : {message:'Saved'});
    };
    class ClockDate extends Date {static now(){return now;}}
    class FormData {constructor(form){this.form=form;}}
    const context={window,document,fetch,Date:ClockDate,AbortController,AbortSignal,FormData,Event:class{},Blob,URL,
        setTimeout(callback,ms){const id=++nextId;timers.set(id,{callback,ms});return id;},clearTimeout(id){timers.delete(id);},
        setInterval(callback,ms){intervals.push({callback,ms});},console};
    if(role==='teacher') {
        for(const participant of initial.participants) {
            const children=Object.fromEntries(['response','saved','live-code','submissions'].map(key=>[key,element()]));
            panels.set(participant.id,{querySelector:selector=>children[selector.slice(6,-1)]});
        }
    } else get('match-code').value=initial.participants[0].code;
    vm.createContext(context);vm.runInContext(source('match-clock.js'),context);
    vm.runInContext(source(role==='teacher'?'teacher-match.js':'student-match.js'),context);
    return {get,network,requests,window,context,panels,
        advance(ms){now+=ms;},
        async interval(ms){for(const entry of intervals.filter(t=>t.ms===ms)) await entry.callback();await settle();},
        timeout(ms){const entry=[...timers].find(([,t])=>t.ms===ms);if(!entry) throw new Error('Timer not found: '+ms);timers.delete(entry[0]);return entry[1].callback();},
    };
}
function reply(data,status=200) {return {ok:status<400,status,json:async()=>data};}

test('shared clock opens at server start, counts down, and permits overtime until finalized',()=>{
    const window={};vm.runInNewContext(source('match-clock.js'),{window});
    const state={...snapshot(),phase:'countdown',started_at:5000,deadline_at:65000};
    assert.equal(window.CodingMatchClock.describe(state,4999).writable,false);
    assert.equal(window.CodingMatchClock.describe(state,5000).writable,true);
    assert.equal(window.CodingMatchClock.describe(state,35000).percent,50);
    assert.equal(window.CodingMatchClock.describe(state,65000).phase,'overtime');
    assert.equal(window.CodingMatchClock.describe(state,65000).writable,true);
    assert.equal(window.CodingMatchClock.describe({...state,roster_valid:false},6000).writable,false);
    assert.equal(window.CodingMatchClock.describe({...state,phase:'awarded'},6000).percent,0);
    assert.equal(window.CodingMatchClock.describe({...state,phase:'cancelled'},6000).writable,false);
});

test('typing while saving serializes revisions and never overwrites the current editor',async()=>{
    const app=environment();await settle();
    let resolveSave;
    app.network.handler=request=>request.url==='/save' && request.body.revision===0 ? new Promise(resolve=>{resolveSave=resolve;}) : null;
    const editor=app.get('match-code');editor.value='first draft';editor.fire('input');const pending=app.timeout(750);await settle();
    editor.value='second draft';editor.fire('input');app.timeout(750);await settle();
    assert.equal(app.requests.filter(r=>r.url==='/save').length,1);
    resolveSave(reply({revision:1}));await pending;await settle();
    const saves=app.requests.filter(r=>r.url==='/save');
    assert.equal(saves.length,2);assert.equal(saves[1].body.revision,1);assert.equal(saves[1].body.code,'second draft');
    assert.equal(editor.value,'second draft');assert.match(app.get('match-save-status').textContent,/Saved revision 2/);
});

test('submit first saves the current code and submits the returned revision',async()=>{
    const app=environment();await settle();
    const editor=app.get('match-code');editor.value='ready to submit';editor.fire('input');
    await app.get('match-submit').fire('click');
    const writes=app.requests.filter(r=>r.method!=='GET');
    assert.deepEqual(writes.map(r=>r.url),['/save','/submit']);
    assert.equal(writes[1].body.revision,1);assert.equal(editor.value,'ready to submit');
    assert.match(app.get('match-action-message').textContent,/Saved/);
});

test('a failed autosave keeps the local draft and displays a recoverable warning',async()=>{
    const app=environment();await settle();
    app.network.handler=request=>{if(request.url==='/save') throw new Error('Network offline');};
    const editor=app.get('match-code');editor.value='my unsaved work';editor.fire('input');await app.timeout(750);
    assert.equal(editor.value,'my unsaved work');assert.match(app.get('match-save-status').textContent,/Not saved/);
    assert.match(app.get('match-action-message').textContent,/Network offline/);
    const event={preventDefault(){this.prevented=true;}};app.window.listeners.beforeunload(event);assert.equal(event.prevented,true);
    app.network.handler=null;await app.get('match-save').fire('click');
    assert.match(app.get('match-save-status').textContent,/Saved revision 1/);
});

test('a revision conflict pauses editing without replacing unsaved code',async()=>{
    const app=environment();await settle();
    app.network.handler=request=>request.url==='/save' ? reply({message:'A newer draft exists'},409) : null;
    const editor=app.get('match-code');editor.value='local conflict copy';editor.fire('input');await app.timeout(750);
    assert.equal(editor.value,'local conflict copy');assert.equal(editor.readOnly,true);
    assert.equal(app.get('match-submit').disabled,true);assert.match(app.get('match-save-status').textContent,/Download your code/);
});

test('polling detects another tab and preserves local work; terminal results lock the arena',async()=>{
    const app=environment();await settle();
    const editor=app.get('match-code');editor.value='unsaved local copy';
    app.network.state.participants[0].revision=2;app.network.state.participants[0].code='remote version';
    await app.interval(2000);assert.equal(editor.value,'unsaved local copy');assert.equal(editor.readOnly,true);
    assert.match(app.get('match-save-status').textContent,/Another tab/);
    app.network.state.phase='awarded';app.network.state.winner='Alpha';app.network.state.xp_awarded=100;
    await app.interval(2000);assert.match(app.get('match-result').textContent,/Alpha.*100 XP/);
});

test('overtime does not disable editing, but a completed match does',async()=>{
    const app=environment();await settle();app.advance(60000);await app.interval(250);
    assert.equal(app.get('match-code').readOnly,false);assert.equal(app.get('match-time').textContent,'OVERTIME');
    app.network.state.phase='cancelled';await app.interval(2000);assert.equal(app.get('match-code').readOnly,true);
});

function teacherState() {
    const state=snapshot();Object.assign(state.participants[1],{code:'private beta draft',revision:0,saved_at:null,submissions:[]});
    state.participants[0].submissions=[{id:1,revision:1,code:'immutable submitted code',submitted_at:'2026-10-29T12:00:00+08:00',verdict:'pending',on_time:true,feedback:null,observed_output:null,download_url:'/download/1'}];
    return state;
}
test('teacher live updates change code while preserving unfinished feedback and observed output',async()=>{
    const app=environment('teacher',teacherState());await settle();
    const list=app.panels.get(1).querySelector('[data-submissions]');
    const inputs=descendants(list);
    const feedback=inputs.find(n=>n.name==='feedback'),output=inputs.find(n=>n.name==='observed_output');
    feedback.value='Feedback I have not submitted';output.value='test output';
    app.network.state.participants[0].code='<script>student code stays text</script>';
    app.network.state.participants[0].submissions[0].verdict='revise';
    app.network.state.participants[0].submissions[0].feedback='Saved in another tab';
    await app.interval(3000);
    assert.equal(feedback.value,'Feedback I have not submitted');assert.equal(output.value,'test output');
    assert.equal(app.panels.get(1).querySelector('[data-live-code]').textContent,'<script>student code stays text</script>');
    assert.equal(descendants(list).find(n=>n.tagName==='pre').textContent,'immutable submitted code');
});
test('teacher award is gated by pending earlier versions and locks after finalization',async()=>{
    const initial=teacherState();initial.candidate_id=1;initial.pending_earlier=1;initial.participants[0].submissions[0].verdict='correct';
    const app=environment('teacher',initial);await settle();assert.equal(app.get('match-award-button').disabled,true);
    app.network.state.pending_earlier=0;await app.interval(3000);assert.equal(app.get('match-award-button').disabled,false);
    app.network.state.phase='awarded';app.network.state.winner='Alpha';app.network.state.xp_awarded=100;await app.interval(3000);
    assert.equal(app.get('match-award').hidden,true);assert.equal(app.get('match-cancel-panel').hidden,true);
    assert.equal(descendants(app.panels.get(1).querySelector('[data-submissions]')).find(n=>n.tagName==='form').hidden,true);
});

test('challenge notices render untrusted messages as text and keep invitations after notices are read',async()=>{
    const nodes=Object.fromEntries(['count','invitations','invitation-link','mark','items','connection'].map(name=>[name,element()]));
    const root={...element(),dataset:{challengeNotices:'/student-challenge-notices',noticeRead:'/read',noticeCsrf:'test'},querySelector:selector=>nodes[selector.slice(13,-1)]};
    const state={unread:1,invitations:1,items:[{id:7,message:'<img src=x onerror=bad()>',time:'2026-10-29T12:00:00+08:00',url:'/coding-challenges/1',unread:true}]};
    const requests=[];
    const context={document:{hidden:false,querySelectorAll:()=>[root],createElement:element,addEventListener(){}},AbortSignal,Date,
        setInterval(){},fetch:async(url,options={})=>{requests.push({url,options});if(url==='/read'){state.unread=0;state.items[0].unread=false;return reply({});}return reply(structuredClone(state));}};
    vm.runInNewContext(source('challenge-notifications.js'),context);await settle();
    assert.equal(nodes.items.children[0].children[0].textContent,'● <img src=x onerror=bad()>');
    assert.equal(nodes['invitation-link'].hidden,false);
    await nodes.mark.fire('click');await settle();
    assert.equal(nodes.count.textContent,'0 unread');assert.equal(nodes.invitations.hidden,false);assert.equal(nodes['invitation-link'].hidden,false);
    assert.deepEqual(JSON.parse(requests.find(r=>r.url==='/read').options.body),{through:7});
});
