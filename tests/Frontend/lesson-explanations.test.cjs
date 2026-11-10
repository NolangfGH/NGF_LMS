const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

function editorFixture(total = 2) {
    const add = {listeners:{}, addEventListener(name,fn){this.listeners[name]=fn;},focus(){this.focused=true;}};
    const notices=[];
    let list;
    const row = index => {
        const fields=['title','text','code','output'].map(key=>({name:`sections[${index}][${key}]`,value:`saved-${index}-${key}`}));
        const number={},code=fields[2],launch={dataset:{}},input={focus(){this.focused=true;}};
        const remove={setAttribute(){},closest:()=>value};
        const value={fields,number,code,launch,input,removeButton:remove,
            querySelector:selector=>({'[data-explanation-number]':number,'[data-explanation-code]':code,'[data-explanation-remove]':remove,input})[selector],
            querySelectorAll:selector=>selector==='[name]'?fields:[launch],
            remove(){list.items.splice(list.items.indexOf(this),1);}};
        return value;
    };
    list={items:Array.from({length:total},(_,index)=>row(index)),listeners:{},querySelectorAll(){return this.items;},append(value){this.items.push(value);},contains(value){return this.items.includes(value);},addEventListener(name,fn){this.listeners[name]=fn;}};
    const status={},template={content:{cloneNode(){return row('__SECTION__');}}};
    const root={querySelector:selector=>({'[data-explanation-list]':list,'[data-explanation-add]':add,'[data-explanation-template]':template,'[data-explanation-count]':status})[selector]};
    vm.runInNewContext(fs.readFileSync('public/js/lesson-explanations.js','utf8'),{document:{querySelector:()=>root},window:{dispatchEvent:event=>notices.push(event)},CustomEvent:class{constructor(type,options){this.type=type;Object.assign(this,options);}}});
    return {list,add,status,notices,remove(index){const button=list.items[index].removeButton;list.listeners.click({target:{closest:()=>button}});}};
}

test('adding explanations creates independent fields and updates the OneCompiler editor target',()=>{
    const app=editorFixture();
    app.add.listeners.click();
    const last=app.list.items.at(-1);
    assert.equal(last.code.name,'sections[2][code]');
    assert.equal(last.launch.dataset.onecompilerEditor,'explanation-2-code');
    assert.equal(last.input.focused,true);
    assert.equal(app.notices[0].type,'ngf:explanation-added');
    assert.equal(app.notices[0].detail,last);
});

test('removing a section preserves the remaining text, code and output while enforcing limits',()=>{
    const app=editorFixture(3),kept=app.list.items[2],values=kept.fields.map(field=>field.value);
    app.remove(1);
    assert.deepEqual(kept.fields.map(field=>field.value),values);
    assert.equal(kept.code.name,'sections[1][code]');
    assert.equal(kept.launch.dataset.onecompilerEditor,'explanation-1-code');
    app.remove(0);app.remove(0);
    assert.equal(app.list.items.length,1);
    for(let count=1;count<14;count++)app.add.listeners.click();
    assert.equal(app.list.items.length,12);
    assert.equal(app.add.disabled,true);
});
