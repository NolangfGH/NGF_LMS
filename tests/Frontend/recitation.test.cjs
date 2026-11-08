const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const script = fs.readFileSync(path.join(__dirname, '../../public/js/recitation.js'), 'utf8');
const settle = () => new Promise(resolve => setImmediate(resolve));
function element() {
    return {dataset:{},value:'',textContent:'',listeners:{},disabled:false,readOnly:false,
        addEventListener(name, fn){this.listeners[name]=fn;}, fire(name, event={}){return this.listeners[name]?.(event);},
        focus(){this.focused=true;},select(){this.selected=true;}};
}
function environment() {
    const root=element(), nodes=new Map(), timers=new Map(), intervals=[], requests=[];
    const get=key=>{if(!nodes.has(key))nodes.set(key,element());return nodes.get(key);};
    root.dataset={recitationRole:'student',csrf:'token',revision:'0',locked:'0',awarded:'0',statusUrl:'/status',saveUrl:'/draft',submitUrl:'/submit'};
    root.querySelector=get;
    const window=element();window.location={href:'https://school.test/recitations/1'};
    const document={hidden:false,querySelector:()=>root,querySelectorAll:()=>[],addEventListener(){}};
    const network={state:{status:'open',places_taken:0,xp:100,level:1,award:null},handler:null};
    let nextTimer=0;
    const reply=(data,status=200)=>({ok:status<400,status,json:async()=>data});
    const fetch=async(url,options)=>{
        const request={url,method:options.method,body:options.body?JSON.parse(options.body):undefined};requests.push(request);
        if(network.handler){const result=await network.handler(request);if(result)return result;}
        if(request.method==='PUT')return reply({revision:request.body.revision+1});
        if(request.method==='POST')return reply({correct:true,award:{position:1,score:10,xp:10},xp:110,level:1,message:'Correct! Place 1 · 10 recitation points · 10 XP.'});
        return reply(structuredClone(network.state));
    };
    vm.runInNewContext(script,{document,window,fetch,AbortController,URL,Date,crypto:require('node:crypto'),
        setTimeout(fn,ms){timers.set(++nextTimer,{fn,ms});return nextTimer;},clearTimeout(id){timers.delete(id);},setInterval(fn){intervals.push(fn);}});
    return {get,network,requests,reply,
        input(value){get('[data-recitation-answer]').value=value;get('[data-recitation-answer]').fire('input');},
        async autosave(){const entry=[...timers].find(([,t])=>t.ms===750);assert.ok(entry);timers.delete(entry[0]);await entry[1].fn();await settle();},
        async poll(){for(const fn of intervals)await fn();await settle();},
    };
}

test('autosave serializes changing answers with the latest revision without overwriting typing',async()=>{
    const app=environment();await settle();let release;
    app.network.handler=request=>request.method==='PUT'&&request.body.revision===0?new Promise(resolve=>{release=resolve;}):null;
    app.input('first draft');const first=app.autosave();await settle();app.input('newer draft');const second=app.autosave();await settle();
    assert.equal(app.requests.filter(r=>r.method==='PUT').length,1);
    release(app.reply({revision:1}));await first;await second;
    assert.deepEqual(app.requests.filter(r=>r.method==='PUT').map(r=>[r.body.answer,r.body.revision]),[['first draft',0],['newer draft',1]]);
    assert.equal(app.get('[data-recitation-answer]').value,'newer draft');
    assert.match(app.get('[data-recitation-save]').textContent,/revision 2/);
});

test('correct submissions lock editing and repeated clicks cannot request another reward',async()=>{
    const app=environment();await settle();app.input('cout');
    await app.get('[data-recitation-submit]').fire('click');
    assert.deepEqual(app.requests.filter(r=>r.method!=='GET').map(r=>r.method),['PUT','POST']);
    assert.equal(app.get('[data-recitation-answer]').readOnly,true);
    assert.equal(app.get('[data-recitation-submit]').disabled,true);
    assert.match(app.get('[data-recitation-result]').textContent,/10 recitation points.*10 XP/);
    await app.get('[data-recitation-submit]').fire('click');
    assert.equal(app.requests.filter(r=>r.method==='POST').length,1);
});

test('network retries keep the same request key for the same answer',async()=>{
    const app=environment();await settle();app.input('std::cout');let fail=true;
    app.network.handler=request=>{if(request.method==='POST'&&fail){fail=false;throw Error('Connection lost');}};
    await app.get('[data-recitation-submit]').fire('click');
    assert.equal(app.get('[data-recitation-submit]').disabled,false);
    await app.get('[data-recitation-submit]').fire('click');
    const posts=app.requests.filter(r=>r.method==='POST');
    assert.equal(posts.length,2);assert.equal(posts[0].body.request_key,posts[1].body.request_key);
});

test('closed rounds and draft conflicts preserve unsaved text and prevent further writes',async()=>{
    const app=environment();await settle();app.input('keep my answer');app.network.state.status='completed';app.network.state.places_taken=5;await app.poll();
    await app.autosave();
    assert.equal(app.requests.filter(r=>r.method==='PUT').length,0);
    assert.equal(app.get('[data-recitation-answer]').value,'keep my answer');
    app.get('[data-recitation-copy]').fire('click');assert.equal(app.get('[data-recitation-answer]').selected,true);
    const conflict=environment();await settle();conflict.network.handler=r=>r.method==='PUT'?conflict.reply({message:'Draft conflict'},409):null;
    conflict.input('local answer');await conflict.autosave();await conflict.poll();
    assert.equal(conflict.get('[data-recitation-answer]').readOnly,true);
    assert.equal(conflict.get('[data-recitation-answer]').value,'local answer');
});
