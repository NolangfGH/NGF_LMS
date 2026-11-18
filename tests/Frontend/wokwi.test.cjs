const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

function fixture({denied = false, readonly = false} = {}) {
    const editor = {tagName: readonly ? 'PRE' : 'TEXTAREA', value: 'void setup() {}', textContent: 'readonly sketch', maxLength: 20000, dispatchEvent(event) {this.event = event;}};
    const fields = Object.fromEntries(['status', 'source', 'help', 'return'].map(key => [key, {}]));
    const root = {querySelector: selector => fields[selector.match(/data-wokwi-(.*)\]/)[1]]};
    const writes = [];
    let click;
    vm.runInNewContext(fs.readFileSync('public/js/wokwi.js', 'utf8'), {
        WeakMap, Event: class {constructor(type, options) {this.type = type; Object.assign(this, options);}},
        navigator: {clipboard: {async writeText(code) {if (denied) {throw new Error('denied');} writes.push(code);}}},
        document: {getElementById: () => editor, addEventListener(name, callback) {click = callback;}},
    });
    return {editor, fields, writes, async click(action) {
        const button = {dataset: {wokwiEditor: 'code'}, hasAttribute: name => name === 'data-wokwi-'+action, closest: () => root};
        let prevented = false;
        click({target: {closest: () => button}, preventDefault() {prevented = true;}});
        await Promise.resolve(); await Promise.resolve();
        return prevented;
    }};
}

test('launch copies the latest sketch and leaves the standard new-tab link usable', async () => {
    const app = fixture();
    app.editor.value = 'void loop() { delay(100); }';
    assert.equal(await app.click('open'), false);
    assert.deepEqual(app.writes, [app.editor.value]);
    assert.equal(app.fields.source.value, app.editor.value);
    assert.match(app.fields.status.textContent, /Sketch copied/);
});

test('denied clipboard offers a manual copy without losing the source', async () => {
    const app = fixture({denied: true});
    await app.click('open');
    assert.equal(app.fields.help.open, true);
    assert.equal(app.fields.source.value, app.editor.value);
    assert.match(app.fields.status.textContent, /Automatic copying is unavailable/);
});

test('import updates an editable draft and emits input for autosave', async () => {
    const app = fixture();
    await app.click('open');
    app.fields.return.value = 'void setup() { Serial.begin(9600); }';
    await app.click('apply');
    assert.equal(app.editor.value, app.fields.return.value);
    assert.equal(app.editor.event.type, 'input');
    assert.equal(app.editor.event.bubbles, true);
});

test('import preserves newer drafts, readonly submissions and character limits', async () => {
    const app = fixture();
    await app.click('open');
    app.editor.value = 'new unsaved work';
    app.fields.return.value = 'older tested work';
    await app.click('apply');
    assert.equal(app.editor.value, 'new unsaved work');
    assert.match(app.fields.status.textContent, /draft changed/);
    const locked = fixture({readonly: true});
    locked.fields.return.value = 'replacement';
    await locked.click('apply');
    assert.equal(locked.editor.textContent, 'readonly sketch');
    assert.match(locked.fields.status.textContent, /read-only/);
    const short = fixture();
    short.editor.maxLength = 5;
    short.fields.return.value = 'too long';
    await short.click('apply');
    assert.equal(short.editor.value, 'void setup() {}');
    assert.match(short.fields.status.textContent, /character limit/);
});
