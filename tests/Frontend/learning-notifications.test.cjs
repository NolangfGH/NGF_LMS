const test=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const vm=require('node:vm');
const source=fs.readFileSync('public/js/learning-notifications.js','utf8');
const settle=async()=>{for(let i=0;i<5;i++) await new Promise(setImmediate);};

function element(tag='div') {
    return {tagName:tag,children:[],childNodes:[],dataset:{},textContent:'',hidden:false,disabled:false,checked:false,open:false,events:{},attrs:{},
        append(...nodes){this.children.push(...nodes);},replaceChildren(...nodes){this.children=nodes;},
        setAttribute(name,value){this.attrs[name]=value;},addEventListener(name,handler){this.events[name]=handler;},
        contains(node){return this===node || this.children.some(child=>child.contains?.(node));},
        async fire(name){await this.events[name]?.();await settle();}};
}
function environment(items,{invitations=0}={}) {
    const nodes=Object.fromEntries(['count','connection','mark','items','invitations','invitation-text','older','panel','unread','refresh'].map(key=>[key,element()]));
    const names={'':'All',assignments:'Assignments',quizzes:'Quizzes',challenges:'Code challenges',articles:'Blog & articles',feedback:'Teacher feedback',lessons:'Lessons'};
    const buttons=Object.entries(names).map(([category,label])=>({...element('button'),dataset:{learningFilter:category},childNodes:[{textContent:label+' '}]}));
    const badges=Object.keys(names).map(category=>({...element('span'),dataset:{learningCategoryCount:category}}));
    const root={...element(),dataset:{learningNotifications:'/notices',learningRead:'/read',learningCsrf:'csrf',learningTimezone:'Asia/Singapore'},
        querySelector:selector=>nodes[selector.match(/data-learning-([^\]]+)/)[1]],
        querySelectorAll:selector=>selector==='[data-learning-filter]' ? buttons : badges};
    const state={items:structuredClone(items),invitations,fail:false,pause:false,pending:null};
    const requests=[];const intervals=[];
    const data=url=>{
        const unread=state.items.filter(item=>item.unread);
        const counts={};for(const item of unread) counts[item.category]=(counts[item.category]||0)+1;
        let rows=state.items.filter(item=>(!url.searchParams.get('category') || item.category===url.searchParams.get('category')) && (!url.searchParams.has('unread_only') || item.unread));
        if(url.searchParams.get('before')) rows=rows.filter(item=>item.id<Number(url.searchParams.get('before')));
        return {unread:unread.length,counts,items:structuredClone(rows.slice(0,20)),through:Math.max(0,...state.items.map(item=>item.id)),next_cursor:rows.length>20 ? rows[19].id : null,invitations:state.invitations};
    };
    const context={document:{hidden:false,activeElement:null,querySelectorAll:()=>[root],createElement:element,addEventListener(){}},
        window:{location:{href:'http://127.0.0.1:8000/student-dashboard',origin:'http://127.0.0.1:8000'},setInterval:fn=>intervals.push(fn)},
        URL,Intl,Date,AbortSignal,Set,
        fetch:async(value,options={})=>{
            const url=new URL(String(value),'http://127.0.0.1:8000');requests.push({url,options});
            if(url.pathname==='/read') {
                const body=JSON.parse(options.body);state.items.forEach(item=>{if(body.ids?.includes(item.id) || item.id<=body.through)item.unread=false;});
                return {ok:true,json:async()=>({})};
            }
            if(state.fail) return {ok:false,status:401};
            const snapshot=data(url);
            if(state.pause) {state.pause=false;return new Promise(resolve=>{state.pending=()=>resolve({ok:true,json:async()=>snapshot});});}
            return {ok:true,json:async()=>snapshot};
        }};
    vm.runInNewContext(source,context);
    return {nodes,buttons,badges,state,requests,async poll(){intervals[0]();await settle();}};
}
const item=(id,category='articles',unread=true)=>({id,category,unread,title:'New article '+id,message:'Classroom update '+id,time:'2026-10-30T01:00:00Z',url:'/blog/'+id});

test('notifications render text safely and reject unsafe notification URLs',async()=>{
    const app=environment([{...item(3),title:'<script>unsafe()</script>',message:'<img src=x onerror=unsafe()>',url:'javascript:unsafe()'},item(2,'quizzes'),item(1,'assignments')]);
    await settle();
    const row=app.nodes.items.children[0];
    assert.equal(row.children[0].children[0].textContent,'● <script>unsafe()</script>');
    assert.equal(row.children[1].textContent,'<img src=x onerror=unsafe()>');
    assert.equal(row.children[3].children[0].tagName,'span');
    assert.equal(app.nodes.count.textContent,'3 unread');
    assert.equal(app.badges.find(b=>b.dataset.learningCategoryCount==='quizzes').textContent,1);
});

test('category, unread, and older-page controls use the saved notification feed',async()=>{
    const rows=Array.from({length:25},(_,i)=>item(25-i,i%2 ? 'assignments' : 'articles',i%3!==0));
    const app=environment(rows);await settle();
    assert.equal(app.nodes.items.children.length,20);
    assert.equal(app.nodes.older.hidden,false);
    await app.nodes.older.fire('click');
    assert.equal(app.nodes.items.children.length,25);
    assert.equal(app.nodes.older.hidden,true);
    await app.buttons.find(button=>button.dataset.learningFilter==='assignments').fire('click');
    assert.equal(app.nodes.panel.open,true);
    assert.equal(app.nodes.items.children.length,12);
    app.nodes.unread.checked=true;await app.nodes.unread.fire('change');
    assert.equal(app.nodes.items.children.length,rows.filter(row=>row.category==='assignments' && row.unread).length);
    assert.equal(app.requests.at(-1).url.searchParams.get('unread_only'),'1');
});

test('mark all uses a captured recipient feed boundary and keeps pending invitations visible',async()=>{
    const app=environment([item(7),item(6,'challenges')],{invitations:1});await settle();
    await app.nodes.mark.fire('click');
    assert.deepEqual(JSON.parse(app.requests.find(request=>request.url.pathname==='/read').options.body),{through:7});
    assert.equal(app.nodes.count.textContent,'0 unread');
    assert.equal(app.nodes.invitations.hidden,false);
    assert.match(app.nodes['invitation-text'].textContent,/1 challenge invitation/);
});

test('a read action during polling queues a fresh read state instead of keeping a stale unread list',async()=>{
    const app=environment([item(4)]);await settle();
    app.state.pause=true;await app.poll();
    await app.nodes.mark.fire('click');
    app.state.pending();await settle();
    assert.equal(app.nodes.count.textContent,'0 unread');
    assert.equal(app.nodes.items.children[0].children[0].children[0].textContent,'New article 4');
});

test('ended sessions stop polling and show a useful sign-in message',async()=>{
    const app=environment([item(1)]);await settle();
    app.state.fail=true;await app.poll();
    const calls=app.requests.length;await app.poll();
    assert.equal(app.requests.length,calls);
    assert.match(app.nodes.connection.textContent,/Sign in again/);
    assert.equal(app.nodes.items.children.length,0);
    assert.equal(app.nodes.count.textContent,'Sign in required');
});
