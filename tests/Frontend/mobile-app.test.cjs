const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

function worker() {
    const events = {}, stores = new Map(), network = [], deleted = [];
    let failing = false, claimed = false;
    const response = (body, type = 'text/html') => ({body, ok:true, redirected:false, headers:{get:()=>type}});
    const self = {location:{origin:'https://school.test'}, clients:{claim:async()=>{claimed=true;}}, addEventListener:(name,fn)=>{events[name]=fn;}};
    const caches = {
        open:async name => {
            if (!stores.has(name)) stores.set(name,new Map());
            return {put:async(path,value)=>stores.get(name).set(path,value), match:async path=>stores.get(name).get(path)};
        }, keys:async()=>[...stores.keys()], delete:async name=>{deleted.push(name);stores.delete(name);},
    };
    vm.runInNewContext(fs.readFileSync('public/sw.js','utf8'), {self,caches,URL,Request:class {constructor(path,settings){this.url='https://school.test'+path;Object.assign(this,settings);}},Response:{error:()=>response('error')},fetch:async request=>{
        network.push(request);
        if (failing) throw Error('offline');
        const url=request.url || request;
        return response(url.includes('/images/')?'icon':'live page',url.includes('.png')?'image/png':'text/html');
    }});
    const run = async name=>{let work;events[name]({waitUntil:promise=>{work=promise;}});await work;};
    const request = async (path,method='GET',mode='navigate')=>{let result;events.fetch({request:{url:path.startsWith('https://')?path:'https://school.test'+path,method,mode},respondWith:value=>{result=value;}});return result?await result:undefined;};
    return {run,request,stores,network,deleted,offline:()=>{failing=true;},claimed:()=>claimed};
}

test('service worker stores only the offline screen and public icons, with no credentials',async()=>{
    const app=worker();await app.run('install');
    assert.deepEqual([...app.stores.values()][0].size,3);
    assert.deepEqual(app.network.map(request=>new URL(request.url).pathname),['/offline.html','/images/branding/app-icon-192.png','/images/branding/app-icon-512.png']);
    assert.ok(app.network.every(request=>request.credentials==='omit'));
    await app.request('/quiz?question=secret');await app.request('/teacher-students');await app.request('/student-dashboard');
    assert.equal([...app.stores.values()][0].size,3);
    assert.ok(![...app.stores.values()][0].has('/quiz?question=secret'));
});

test('offline navigation shows a generic screen while API requests, POST submissions and external requests bypass the worker',async()=>{
    const app=worker();await app.run('install');app.offline();
    const fallback=await app.request('/course-assessments/post-test');assert.equal(fallback.body,'live page');
    assert.equal(await app.request('/quiz/1/question','POST','cors'),undefined);
    assert.equal(await app.request('/teacher-dashboard/status','GET','cors'),undefined);
    assert.equal(await app.request('/attendance-photo/1','GET','cors'),undefined);
    assert.equal(await app.request('https://outside.test/quiz','GET','navigate'),undefined);
    assert.equal([...app.stores.values()][0].size,3);
});

test('activation removes only old caches owned by this app',async()=>{
    const app=worker();app.stores.set('ngf-codequest-mobile-v0',new Map());app.stores.set('another-app',new Map());
    await app.run('activate');assert.deepEqual(app.deleted,['ngf-codequest-mobile-v0']);assert.equal(app.claimed(),true);assert.equal(app.stores.has('another-app'),true);
});

function page({standalone=false,ios=false,secure=true,menu=false}={}) {
    const events={},registered=[],created=[];
    const node=()=>{
        const classes=new Set();
        return {children:[],attributes:{},listeners:{},textContent:'',hidden:false,disabled:false,
            classList:{add:name=>classes.add(name),remove:name=>classes.delete(name),contains:name=>classes.has(name)},
            setAttribute(name,value){this.attributes[name]=value;},removeAttribute(name){delete this.attributes[name];},
            addEventListener(name,fn){this.listeners[name]=fn;},append(child){this.children.push(child);},prepend(child){this.children.unshift(child);},focus(){document.activeElement=this;},getClientRects(){return [1];}};
    };
    const button=node(),hint=node(),body=node(),header=node(),sidebar=node(),sidebarContent=node();sidebar.firstElementChild=sidebarContent;sidebar.children.push(sidebarContent);
    sidebar.querySelectorAll=()=>[sidebarContent.children[0],{...node(),name:'last'}];
    const media={matches:standalone,addEventListener:(name,fn)=>{events.resize=fn;}};
    const document={body,activeElement:null,querySelectorAll:selector=>selector==='[data-app-install]'?[button]:[hint],querySelector:selector=>menu?(selector.includes('aside')?sidebar:header):null,createElement:tag=>{const value=node();value.tag=tag;created.push(value);return value;},createElementNS:()=>node()};
    const window={matchMedia:query=>query.includes('min-width')?{matches:false,addEventListener:(name,fn)=>{events.resize=fn;}}:media,isSecureContext:secure,addEventListener:(name,fn)=>{events[name]=fn;}};
    const navigator={standalone:false,userAgent:ios?'iPhone Safari':'Chrome',onLine:true,serviceWorker:{register:async(path,options)=>{registered.push({path,options});}}};
    vm.runInNewContext(fs.readFileSync('public/js/mobile-app.js','utf8'),{document,window,navigator});
    return {events,button,hint,body,header,sidebar,sidebarContent,document,created,registered};
}

test('install control uses one browser prompt and keeps cancellation usable',async()=>{
    const app=page();let prompts=0,prevented=0;
    app.events.beforeinstallprompt({preventDefault(){prevented++;},prompt:async()=>{prompts++;},userChoice:Promise.resolve({outcome:'dismissed'})});
    await app.button.listeners.click();assert.equal(prompts,1);assert.equal(prevented,1);assert.equal(app.button.disabled,false);assert.match(app.hint.textContent,/cancelled/);
    await app.button.listeners.click();assert.equal(prompts,1);assert.match(app.hint.textContent,/browser decides/);
    app.events.appinstalled();assert.equal(app.button.disabled,true);
});

test('iPhone guidance and standalone state do not claim a browser prompt exists',async()=>{
    const ios=page({ios:true});await ios.button.listeners.click();assert.match(ios.hint.textContent,/Safari/);assert.match(ios.hint.textContent,/Add to Home Screen/);
    const installed=page({standalone:true});assert.equal(installed.button.disabled,true);assert.match(installed.button.textContent,/installed/);
});

test('connection banner follows connection events and worker registration requires a secure origin',async()=>{
    const app=page();const connection=app.body.children[0];assert.equal(connection.hidden,true);app.events.offline();assert.equal(connection.hidden,false);app.events.online();assert.equal(connection.hidden,true);
    await app.events.load();assert.equal(app.registered[0].path,'/sw.js');assert.equal(app.registered[0].options.updateViaCache,'none');
    const insecure=page({secure:false});assert.equal(insecure.events.load,undefined);
});

test('mobile menu preserves sidebar structure and supports closing, focus return and desktop resize',()=>{
    const app=page({menu:true});const toggle=app.header.children[0],close=app.sidebarContent.children[0];
    assert.equal(app.sidebar.children[0],app.sidebarContent);
    toggle.listeners.click();assert.equal(app.body.classList.contains('ngf-mobile-menu-open'),true);assert.equal(app.sidebar.attributes['aria-modal'],'true');assert.equal(app.document.activeElement,close);
    app.sidebar.listeners.keydown({key:'Escape',preventDefault(){}});assert.equal(app.body.classList.contains('ngf-mobile-menu-open'),false);assert.equal(app.document.activeElement,toggle);
    toggle.listeners.click();app.events.resize({matches:true});assert.equal(app.body.classList.contains('ngf-mobile-menu-open'),false);
});
