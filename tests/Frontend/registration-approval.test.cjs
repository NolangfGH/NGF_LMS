const {test} = require('node:test');
const assert = require('node:assert/strict');
const vm = require('node:vm');
const fs = require('node:fs');

function page(script, initial) {
    const nodes = new Map();
    const intervals = [];
    const node = id => {
        if (!nodes.has(id)) nodes.set(id, {textContent: '', hidden: false, innerHTML: 'original review', dataset: {}, listeners: {}, contains: () => false,
            addEventListener(event, callback) { this.listeners[event] = callback; }});
        return nodes.get(id);
    };
    const result = {data: initial, requests: 0, ok: true};
    const document = {getElementById: node, hidden: false, activeElement: null, addEventListener() {}};
    node('registration-status').dataset.checkUrl = '/private-status';
    node('registration-queue').dataset.statusUrl = '/queue';
    vm.runInNewContext(fs.readFileSync('public/js/' + script, 'utf8'), {document, setInterval: callback => intervals.push(callback),
        fetch: async () => { result.requests++; return {ok: result.ok, json: async () => result.data}; }});
    return {node, result, refresh: intervals[0]};
}

test('student status changes from waiting to approved without granting a login session', async () => {
    const app = page('student-registration-status.js', {status: 'approved', note: '', reviewed_at: 'Nov 1, 2026', archived: false});
    await app.refresh();
    assert.equal(app.node('review-heading').textContent, 'Registration approved');
    assert.match(app.node('review-description').textContent, /Sign in with your Student ID and password/);
    assert.equal(app.node('review-note-panel').hidden, true);
    app.result.data = {status: 'rejected', note: '<img src=x onerror=alert(1)>', archived: true};
    await app.refresh();
    assert.equal(app.node('review-note').textContent, '<img src=x onerror=alert(1)>');
    assert.equal(app.node('review-note').innerHTML, 'original review');
    assert.equal(app.node('review-archived').hidden, false);
});

test('private status errors ask for sign in and preserve the last confirmed status', async () => {
    const app = page('student-registration-status.js', {status: 'pending'});
    await app.refresh();
    app.result.ok = false;
    await app.refresh();
    assert.equal(app.node('review-heading').textContent, 'Awaiting teacher approval');
    assert.match(app.node('review-live').textContent, /Sign in again/);
});

test('live queue refresh preserves an administrator’s unsaved review note', async () => {
    const app = page('registration-queue.js', {html: 'updated requests', updated_at: 'just now'});
    await app.refresh();
    assert.equal(app.node('registration-queue').innerHTML, 'updated requests');
    app.node('registration-queue').listeners.input();
    app.result.data.html = 'replacement that would lose the note';
    await app.refresh();
    assert.equal(app.result.requests, 1);
    assert.equal(app.node('registration-queue').innerHTML, 'updated requests');
    assert.match(app.node('registration-live').textContent, /preserve your review note/);
});
