const test = require('node:test');
const assert = require('node:assert/strict');
const vm = require('node:vm');
const fs = require('node:fs');
const {File} = require('node:buffer');
const source = fs.readFileSync('public/js/blog-activity.js','utf8');
const flush = () => new Promise(setImmediate);

function browser({timed=true, denied=false}={}) {
    const handlers = {};
    const intervals = [];
    const posted = [];
    let clock = 100000;
    let stopped = false;
    let redirect = null;
    let submitCount = 0;
    const element = (values={}) => ({hidden:false,disabled:false,textContent:'',dataset:{},setAttribute(){},...values});
    const textarea = element({value:'',readOnly:false});
    const image = element({files:[]});
    const file = element({files:[],value:'',setCustomValidity(){},reportValidity(){},closest:selector=>selector==='form' ? form : panel,dispatchEvent(){handlers.change({target:file});},matches:selector=>selector==='[data-voice-file]'});
    const voice = {
        '[data-voice-file]':file,'[data-voice-preview]':element(),
        '[data-voice-status]':element(),'[data-voice-record]':element(),
        '[data-voice-stop]':element(),'[data-voice-remove]':element(),
    };
    const panel = {querySelector:selector=>voice[selector],closest:()=>form};
    const fields = element({disabled:timed});
    const nodes = {
        '[data-blog-response-fields]':fields,'[data-blog-start]':element(),
        '[data-blog-time-label]':element(),'[data-blog-time-track]':element(),
        '[data-blog-time-bar]':element({style:{}}),'[data-blog-save-status]':element(),
        '[data-blog-saved-media]':element(),'[data-blog-form-error]':element(),
        'textarea[name="body"]':textarea,'[name="image"]':image,'[name="audio"]':file,
        '[name="_token"]':{value:'csrf-test'},'[data-blog-voice]':panel,
    };
    const form = {
        dataset:{blogTimed:timed ? 'true' : 'false',commentId:'',startUrl:'/start'},isConnected:true,
        querySelector(selector) { return selector==='fieldset:disabled' ? (fields.disabled ? fields : null) : nodes[selector]; },
        closest:()=>null,matches:selector=>selector==='form[enctype="multipart/form-data"]',
        requestSubmit() { submitCount++; },
    };
    class FormDataMock {
        constructor(input) {
            this.values=new Map();
            if(input) { this.values.set('body',textarea.value); if(file.files[0]) this.values.set('audio',file.files[0]); }
        }
        get(key){return this.values.get(key);}
        set(key,value){this.values.set(key,value);}
        delete(key){this.values.delete(key);}
    }
    class Recorder {
        static isTypeSupported(){return true;}
        constructor(){this.state='inactive';this.mimeType='audio/webm';this.events={};}
        addEventListener(kind,callback){this.events[kind]=callback;}
        start(){this.state='recording';}
        stop(){
            this.state='inactive';
            setImmediate(()=>{
                this.events.dataavailable({data:new Blob(['recorded voice'],{type:'audio/webm'})});
                this.events.stop();
            });
        }
    }
    const window = {
        MediaRecorder:Recorder,
        blogActiveAttempt:timed ? {attempt_id:1,comment_id:null,body:'Restored draft',expires_at:new Date(clock+1000).toISOString(),server_now:new Date(clock).toISOString(),duration_seconds:60,finished:false,save_url:'/save',submit_url:'/submit',redirect:'/done'} : null,
        setInterval(callback,ms){intervals.push({callback,ms});return intervals.length;},clearInterval(){},
        setTimeout,clearTimeout,addEventListener(){},alert(){},location:{assign(value){redirect=value;}},
    };
    const document = {addEventListener(kind,handler){handlers[kind]=handler;},querySelectorAll:()=>[form]};
    const context = {
        window,document,navigator:{mediaDevices:{async getUserMedia(){if(denied)throw Error('denied');return {getTracks:()=>[{stop(){stopped=true;}}]};}}},
        MediaRecorder:Recorder,Blob,File,Event,FormData:FormDataMock,
        DataTransfer:class {constructor(){this.files=[];this.items={add:file=>this.files.push(file)};}},
        URL:{createObjectURL:()=>'/blob-preview',revokeObjectURL(){}},Date:class extends Date {static now(){return clock;}},
        fetch:async (url,options)=>{posted.push({url,body:options.body.get('body'),audio:options.body.get('audio')});return {ok:true,json:async()=>({finished:true,redirect:'/done'})};},
    };
    vm.runInNewContext(source,context);
    return {
        handlers,form,textarea,fields,file,voice,nodes,posted,
        get stopped(){return stopped;},get redirect(){return redirect;},get submitCount(){return submitCount;},
        async record(){const button={closest:()=>panel,matches:s=>s==='[data-voice-record]'};handlers.click({target:{closest:()=>button}});await flush();},
        async expire(){clock+=1100;intervals[0].callback();await flush();await flush();await flush();},
    };
}

test('expiry freezes and submits the current typed response, with a decreasing timer bar',async()=>{
    const b=browser();
    assert.equal(b.textarea.value,'Restored draft');
    b.textarea.value='Latest answer typed before zero';
    await b.expire();
    assert.equal(b.posted.length,1);
    assert.equal(b.posted[0].url,'/submit');
    assert.equal(b.posted[0].body,'Latest answer typed before zero');
    assert.equal(b.nodes['[data-blog-time-bar]'].style.width,'0%');
    assert.equal(b.textarea.readOnly,true);
    assert.equal(b.redirect,'/done');
});

test('expiry stops recording, releases the microphone, and includes the voice file in the submission',async()=>{
    const b=browser();
    await b.record();
    assert.equal(b.voice['[data-voice-stop]'].hidden,false);
    await b.expire();
    assert.equal(b.stopped,true);
    assert.equal(b.posted.length,1);
    assert.equal(b.posted[0].audio.name,'voice-message.webm');
    assert.ok(b.posted[0].audio.size>0);
    assert.equal(b.redirect,'/done');
});

test('a teacher submitting during recording waits for the final clip before normal form submission',async()=>{
    const b=browser({timed:false});
    await b.record();
    await b.handlers.submit({target:b.form,preventDefault(){},stopImmediatePropagation(){}});
    assert.equal(b.stopped,true);
    assert.equal(b.submitCount,1);
    assert.equal(b.file.files[0].name,'voice-message.webm');
});

test('denied microphone permission leaves typing and uploading available',async()=>{
    const b=browser({timed:false,denied:true});
    await b.record();
    assert.match(b.voice['[data-voice-status]'].textContent,/Microphone access was not granted/);
    assert.equal(b.voice['[data-voice-record]'].disabled,false);
    assert.equal(b.fields.disabled,false);
    assert.equal(b.posted.length,0);
});
