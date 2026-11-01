const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

function gradebook(print = false) {
    const node = extra => ({listeners: {}, attributes: {},
        addEventListener(type, handler) { this.listeners[type] = handler; },
        fire(type, event = {}) { return this.listeners[type]?.(event); },
        setAttribute(key, value) { this.attributes[key] = value; }, ...extra});
    const scoreInput = node({value: '50', disabled: false});
    const form = node();
    const sourceFields = node({hidden: false});
    const entryMode = node({value: 'automatic', closest: () => assessmentForm, matches: selector => selector === '[data-grading-entry-mode]'});
    const source = node({value: 'assignment:2', selectedOptions: [{dataset: {max: '16', title: 'Loop · challenge', category: 'CH', due: '2026-11-03'}}], closest: () => assessmentForm, matches: selector => selector === '[data-grading-source]'});
    const assessmentForm = node({elements: {title: {value: ''}, category: {value: 'WW'}, max_score: {value: ''}, due_date: {value: ''}},
        querySelector: selector => ({'[data-grading-entry-mode]': entryMode, '[data-grading-source]': source, '[data-grading-source-fields]': sourceFields})[selector]});
    const mode = node({value: 'source', closest: () => ({querySelector: () => scoreInput})});
    const mask = node();
    const refresh = node();
    const status = node({textContent: ''});
    const live = node({innerHTML: 'original records'});
    const links = [{href: 'http://127.0.0.1:8000/grading-sheet?student=1'}, {href: 'http://127.0.0.1:8000/grading-sheet/export'}, {href: 'https://example.test/help'}];
    const root = node({dataset: {print: String(print), masked: 'false', statusUrl: 'http://127.0.0.1:8000/grading-sheet/status?classroom=1'},
        querySelector: selector => ({'[data-grading-status]': status, '[data-grading-mask]': mask, '[data-grading-refresh]': refresh})[selector],
        querySelectorAll: selector => ({a: links, '[data-score-mode]': [mode], '[data-assessment-create]': [assessmentForm]})[selector] || []});
    const document = node({hidden: false, activeElement: null, fonts: {ready: Promise.resolve()},
        querySelector: () => root, getElementById: () => live});
    const window = {location: {href: 'http://127.0.0.1:8000/grading-sheet', origin: 'http://127.0.0.1:8000'}, prints: 0, print() { this.prints++; }};
    const state = {response: {ok: true, status: 200, json: async () => ({html: 'new records'})}, requests: [], poll: null, wait: null};
    const fetch = async (url, options) => { state.requests.push({url, options}); if (state.wait) await state.wait; return state.response; };
    vm.runInNewContext(fs.readFileSync(path.join(__dirname, '../../public/js/grading-sheet.js'), 'utf8'),
        {document, window, URL, fetch, setInterval: (fn, ms) => { state.poll = fn; state.interval = ms; }});
    return {...state, state, root, document, window, scoreInput, form, mode, mask, refresh, status, live, links, assessmentForm, entryMode, source, sourceFields};
}

test('only manual grading enables the score field and edits stop automatic replacement', async () => {
    const page = gradebook();
    assert.equal(page.scoreInput.disabled, true);
    page.mode.value = 'scored';
    const target = {closest: () => page.form, matches: selector => selector === '[data-score-mode]'};
    page.root.fire('change', {target});
    assert.equal(page.scoreInput.disabled, false);
    await page.state.poll();
    assert.equal(page.state.requests.length, 0);
    assert.match(page.status.textContent, /paused while you edit/);
    page.mode.value = 'excused';
    page.root.fire('change', {target});
    assert.equal(page.scoreInput.disabled, true);
});

test('aliases propagate to gradebook navigation and printable/export links', () => {
    const page = gradebook();
    page.mask.fire('click', {currentTarget: page.mask});
    assert.equal(page.root.dataset.masked, 'true');
    assert.match(page.links[0].href, /mask=1/);
    assert.match(page.links[1].href, /mask=1/);
    assert.equal(page.links[2].href, 'https://example.test/help');
    page.mask.fire('click', {currentTarget: page.mask});
    assert.equal(page.root.dataset.masked, 'false');
    assert.doesNotMatch(page.links[0].href, /mask=1/);
});

test('automatic entry requires an activity; manual entry hides and disables the activity selector', () => {
    const page = gradebook();
    assert.equal(page.source.required, true);
    assert.equal(page.source.disabled, false);
    assert.equal(page.sourceFields.hidden, false);
    page.entryMode.value = 'manual';
    page.root.fire('change', {target: page.entryMode});
    assert.equal(page.source.required, false);
    assert.equal(page.source.disabled, true);
    assert.equal(page.sourceFields.hidden, true);
    page.entryMode.value = 'automatic';
    page.root.fire('change', {target: page.entryMode});
    assert.equal(page.source.disabled, false);
    assert.equal(page.source.required, true);
});

test('selecting a linked activity fills its title category maximum and due date without removing title punctuation', () => {
    const page = gradebook();
    page.root.fire('change', {target: page.source});
    assert.equal(page.assessmentForm.elements.title.value, 'Loop · challenge');
    assert.equal(page.assessmentForm.elements.category.value, 'CH');
    assert.equal(page.assessmentForm.elements.max_score.value, '16');
    assert.equal(page.assessmentForm.elements.due_date.value, '2026-11-03');
    delete page.source.selectedOptions[0].dataset.due;
    page.root.fire('change', {target: page.source});
    assert.equal(page.assessmentForm.elements.due_date.value, '');
});

test('polling refreshes saved evidence and preserves edits started during a request', async () => {
    const page = gradebook();
    assert.equal(page.state.interval, 15000);
    await page.state.poll();
    assert.equal(page.live.innerHTML, 'new records');
    let release;
    page.state.wait = new Promise(resolve => { release = resolve; });
    page.state.response = {ok: true, status: 200, json: async () => ({html: 'stale records'})};
    const pending = page.state.poll();
    page.root.fire('input', {target: {closest: () => page.form}});
    release();
    await pending;
    assert.equal(page.live.innerHTML, 'new records');
});

test('hidden pages skip polling and expired sessions stop requests', async () => {
    const page = gradebook();
    page.document.hidden = true;
    await page.state.poll();
    assert.equal(page.state.requests.length, 0);
    page.document.hidden = false;
    page.state.response = {ok: false, status: 403};
    await page.state.poll();
    assert.match(page.status.textContent, /session has ended/);
    await page.state.poll();
    assert.equal(page.state.requests.length, 1);
});

test('print view prints once after fonts and starts no refresh timer', async () => {
    const page = gradebook(true);
    await new Promise(resolve => setImmediate(resolve));
    assert.equal(page.window.prints, 1);
    assert.equal(page.state.poll, null);
});
