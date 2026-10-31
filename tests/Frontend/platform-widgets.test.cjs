const {test}=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const vm=require('node:vm');
const path=require('node:path');
const source=name=>fs.readFileSync(path.join(__dirname,'../../public/js',name),'utf8');
const node=()=>({children:[],style:{cssText:''},attrs:{},listeners:{},append(...items){this.children.push(...items);},prepend(item){this.children.unshift(item);},before(){},setAttribute(key,value){this.attrs[key]=value;},replaceChildren(){this.children=[];},addEventListener(key,fn){this.listeners[key]=fn;},remove(){this.removed=true;}});

test('syntax highlighting renders C++ as text, preserves hostile strings and follows editor scrolling',()=>{
    const editor={...node(),value:'#include <iostream>\nint main(){ /* comment */ std::cout << "<img onerror=evil>" << 0; }',scrollTop:28,scrollLeft:17};
    const created=[];
    const document={getElementById:()=>editor,createElement:tag=>{const item={...node(),tag};created.push(item);return item;},createTextNode:text=>({textContent:text})};
    vm.runInNewContext(source('code-editor-highlight.js'),{document});
    const highlight=created.find(item=>item.tag==='pre');
    assert.equal(highlight.attrs['aria-hidden'],'true');
    assert.equal(highlight.scrollTop,28);
    assert.equal(highlight.children.map(item=>item.textContent).join(''),editor.value+'\n');
    assert.equal(created.some(item=>item.tag==='img'),false);
    editor.scrollLeft=40;editor.listeners.scroll();assert.equal(highlight.scrollLeft,40);
    editor.value='return 0;';editor.listeners.input();assert.equal(highlight.children.map(item=>item.textContent).join(''),'return 0;\n');
});

test('logic ordering supports Up/Down buttons and drag moves while notifying quiz autosave',()=>{
    let changes=0;
    const list={...node(),dataset:{},closest:()=>({dispatchEvent:()=>changes++})};
    const move=(item,target,after=false)=>{list.children=list.children.filter(row=>row!==item);list.children.splice(list.children.indexOf(target)+(after?1:0),0,item);};
    function row(name){const item={name,before:moved=>move(moved,item),after:moved=>move(moved,item,true)};Object.defineProperty(item,'previousElementSibling',{get:()=>list.children[list.children.indexOf(item)-1]});Object.defineProperty(item,'nextElementSibling',{get:()=>list.children[list.children.indexOf(item)+1]});return item;}
    const first=row('Read'),second=row('Calculate'),third=row('Print');list.children=[first,second,third];
    const document={querySelectorAll:()=>[list]};
    vm.runInNewContext(source('quiz-ordering.js'),{document,Event:class{constructor(type){this.type=type;}}});
    const target=(item,direction)=>({closest:selector=>selector==='[data-order-step]'?item:selector===`[data-order-${direction}]`?{}:null});
    list.listeners.click({target:target(second,'up')});assert.deepEqual(list.children.map(row=>row.name),['Calculate','Read','Print']);
    list.listeners.click({target:target(second,'down')});assert.deepEqual(list.children.map(row=>row.name),['Read','Calculate','Print']);
    list.listeners.dragstart({target:target(third),dataTransfer:{setData(){}}});
    list.listeners.drop({target:target(first),preventDefault(){}});assert.deepEqual(list.children.map(row=>row.name),['Print','Read','Calculate']);assert.equal(changes,3);
});

test('celebrations respect reduced motion and avoid repeated overlapping announcements',()=>{
    const body=node(),timers=[],events={};let animations=0;
    const document={body,documentElement:{dataset:{studentMotion:'reduce'}},createElement:()=>({...node(),animate(){animations++;}})};
    const window={addEventListener:(type,callback)=>events[type]=callback};
    vm.runInNewContext(source('celebrations.js'),{document,window,matchMedia:()=>({matches:false}),setTimeout:fn=>timers.push(fn)});
    events['codequest:celebrate']();events['codequest:celebrate']();
    assert.equal(body.children.length,1);assert.equal(body.children[0].attrs.role,'status');assert.equal(animations,0);
    timers.shift()();assert.equal(body.children[0].removed,true);
    document.documentElement.dataset.studentMotion='standard';events['codequest:celebrate']();assert.equal(animations,30);
});
