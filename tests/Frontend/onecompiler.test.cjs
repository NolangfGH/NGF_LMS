const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const source = fs.readFileSync(path.join(__dirname, '../../public/js/onecompiler.js'), 'utf8');
const origin = 'https://onecompiler.com';

function element(tagName = 'BUTTON') {
    return {tagName, listeners: {}, value: '', textContent: '', dataset: {}, disabled: false, readOnly: false, maxLength: 20000,
        addEventListener(name, callback) {this.listeners[name] = callback;},
        fire(name, data) {return this.listeners[name]?.(data);},
        setAttribute(name, value) {this[name] = value;}, removeAttribute(name) {delete this[name];},
        focus() {this.focused = true;}, select() {this.selected = true;},
        dispatchEvent(event) {this.dispatched = event.type;},
    };
}
function environment() {
    const editor = element('TEXTAREA'), launcher = element(), frame = element('IFRAME'), dialog = element('DIALOG');
    launcher.dataset.onecompilerEditor = 'code';
    const nodes = new Map([['iframe', frame]]), messages = [], timers = new Map(), window = element();
    const find = selector => {if (!nodes.has(selector)) nodes.set(selector, element()); return nodes.get(selector);};
    dialog.querySelector = find;
    dialog.showModal = () => {dialog.open = true;};
    dialog.close = () => {dialog.open = false; dialog.fire('close');};
    frame.contentWindow = {postMessage(data, target) {messages.push({data: JSON.parse(JSON.stringify(data)), target});}};
    const example = element('PRE');
    const document = {...element(), querySelectorAll: () => [launcher], getElementById: id => id === 'expected' ? example : editor,
        createElement: () => dialog, body: {append() {}}};
    let clock = 0, id = 0;
    class Clock extends Date {static now() {return clock;}}
    const clipboard = {writeText: async code => {clipboard.copied = code;}};
    vm.runInNewContext(source, {document, window, navigator: {clipboard}, Date: Clock,
        Event: class {constructor(type) {this.type = type;}},
        setInterval(callback) {timers.set(++id, callback); return id;}, clearInterval(key) {timers.delete(key);}});
    const click = button => document.fire('click', {target: {closest: () => button}});
    return {editor, launcher, frame, dialog, messages, find, clipboard, example, click,
        open(code = 'int main() { return 0; }') {editor.value = code; click(launcher);},
        receive(code, changes = {}) {window.fire('message', {origin, source: frame.contentWindow, data: {language: 'cpp', files: [{name: 'main.cpp', content: code}]}, ...changes});},
        tick(ms = 500) {clock += ms; for (const callback of [...timers.values()]) callback();},
        timers,
    };
}

test('no external request until click; handoff contains only latest code and uses a fixed origin', () => {
    const app = environment();
    assert.equal(app.frame.src, undefined);
    assert.equal(app.messages.length, 0);
    app.open('int main(){ /* unsaved code <script> */ return 7; }');
    assert.equal(app.dialog.open, true);
    assert.match(app.frame.src, /^https:\/\/onecompiler.com\/embed\/cpp\?listenToEvents=true&codeChangeEvent=true/);
    app.frame.fire('load');
    assert.deepEqual(app.messages[0], {target: origin, data: {eventType: 'populateCode', language: 'cpp', files: [{name: 'main.cpp', content: app.editor.value}]}});
    app.tick();
    assert.equal(app.messages.length, 2);
    app.receive(app.editor.value);
    assert.equal(app.timers.size, 0);
    assert.equal(app.find('[data-oc-apply]').disabled, false);
    app.find('[data-oc-run]').fire('click');
    assert.deepEqual(app.messages.at(-1), {target: origin, data: {eventType: 'triggerRun'}});
});

test('new explanation launchers load their current editable code without an automatic external request', () => {
    const app = environment();
    const added = element();
    added.dataset.onecompilerEditor = 'explanation-3-code';
    assert.equal(app.frame.src, undefined);
    app.editor.value = 'int main() { return 9; }';
    app.click(added);
    app.frame.fire('load');
    assert.equal(app.dialog.open, true);
    assert.equal(app.messages[0].data.files[0].content, app.editor.value);
    assert.equal(app.messages[0].target, origin);
});

test('review launches a captured revision, shows expected output locally and never imports edits', async () => {
    const app = environment();
    const button = element();
    button.dataset = {onecompilerEditor: 'live-code', onecompilerReview: 'true', onecompilerExpected: 'expected'};
    app.editor.tagName = 'PRE';
    delete app.editor.value;
    app.editor.textContent = 'int main() { return 2; }\r\n';
    app.example.textContent = 'Expected output only in LMS';
    app.click(button);
    app.frame.fire('load');
    app.receive('int main() { return 2; }\n');
    assert.equal(app.find('[data-oc-run]').disabled, false);
    assert.equal(app.find('[data-oc-apply]').hidden, true);
    assert.equal(app.find('[data-oc-expected-code]').textContent, app.example.textContent);
    assert.equal(JSON.stringify(app.messages).includes(app.example.textContent), false);
    app.receive('edited code');
    app.editor.textContent = 'newer autosaved code';
    app.find('[data-oc-apply]').fire('click');
    assert.equal(app.editor.textContent, 'newer autosaved code');
    app.find('[data-oc-reload]').fire('click');
    assert.equal(app.messages.at(-1).data.files[0].content, 'int main() { return 2; }\r\n');
    await app.find('[data-oc-copy]').fire('click');
    assert.equal(app.clipboard.copied, 'int main() { return 2; }\r\n');
});

test('spoofed origins and other frame windows cannot replace an LMS draft', () => {
    const app = environment();app.open();app.frame.fire('load');
    app.receive('malicious', {origin: 'https://example.test'});
    app.receive('malicious', {source: {}});
    app.find('[data-oc-apply]').fire('click');
    assert.equal(app.editor.value, 'int main() { return 0; }');
    assert.equal(app.find('[data-oc-apply]').disabled, true);
    app.receive(app.editor.value);
    app.receive('int main() { return 1; }');
    assert.equal(app.editor.value, 'int main() { return 0; }');
    app.find('[data-oc-apply]').fire('click');
    assert.equal(app.editor.value, 'int main() { return 1; }');
    assert.equal(app.editor.dispatched, 'input');
});

test('imports respect closed editors, character limits and concurrently changed drafts', () => {
    for (const reason of ['closed', 'too long', 'concurrent']) {
        const app = environment();app.open('original');app.receive('original');app.receive('updated');
        if (reason === 'closed') app.editor.readOnly = true;
        if (reason === 'too long') app.editor.maxLength = 3;
        if (reason === 'concurrent') app.editor.value = 'new local draft';
        app.find('[data-oc-apply]').fire('click');
        assert.equal(app.editor.value, reason === 'concurrent' ? 'new local draft' : 'original');
        assert.equal(app.editor.dispatched, undefined);
    }
});

test('unavailable services stop retrying and closing cancels transfer and returns focus', () => {
    const app = environment();app.open();app.tick(21000);
    assert.equal(app.timers.size, 0);
    assert.match(app.find('[data-oc-status]').textContent, /not confirmed/);
    app.find('[data-oc-reload]').fire('click');
    assert.equal(app.timers.size, 1);
    app.find('[data-oc-close]').fire('click');
    assert.equal(app.frame.src, undefined);
    assert.equal(app.timers.size, 0);
    assert.equal(app.launcher.focused, true);
    const count = app.messages.length; app.frame.fire('load'); app.receive('later message');
    assert.equal(app.messages.length, count);
});

test('copy fallback uses current code and offers selectable text when clipboard is unavailable', async () => {
    const app = environment();app.open();
    app.editor.value = 'latest LMS code';
    await app.find('[data-oc-copy]').fire('click');
    assert.equal(app.clipboard.copied, 'latest LMS code');
    app.clipboard.writeText = async () => {throw Error('Clipboard unavailable');};
    await app.find('[data-oc-copy]').fire('click');
    assert.equal(app.find('[data-oc-manual]').value, 'latest LMS code');
    assert.equal(app.find('[data-oc-manual]').selected, true);
});
