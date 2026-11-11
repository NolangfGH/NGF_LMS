const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');

function home() {
    const node = () => ({
        listeners: {}, dataset: {}, textContent: '', hidden: false, writes: 0,
        addEventListener(name, handler) { this.listeners[name] = handler; },
        fire(name) { return this.listeners[name]?.(); },
        setAttribute(name, value) { this[name] = value; },
    });
    const names = ['stats','portals','catalog','example'];
    const slots = Object.fromEntries(names.map(name => {
        const slot = { ...node(), content: 'old-' + name, output: name === 'example' ? { open: true } : null,
            querySelector: () => slot.output,
            get innerHTML() { return this.content; },
            set innerHTML(value) { this.content = value; this.writes++; if (this.output) this.output = { open: false }; },
        };
        return [name,slot];
    }));
    const cards = [{ dataset: { search: 'loops repetition' } },{ dataset: { search: 'console input' } }];
    const courses = cards.map(card => {
        const count = node();
        return { hidden: false, count, querySelector: () => count, querySelectorAll: () => [card] };
    });
    const root = { ...node(), dataset: { homeRevision: 'one', homeEndpoint: '/home/status' },
        querySelector(selector) { return slots[selector.match(/"(.+)"/)[1]]; },
        querySelectorAll: selector => selector === '[data-home-course]' ? courses : cards };
    const ids = { 'home-live-root': root, 'home-refresh': node(), 'home-live-status': node(), 'home-status-text': node(),
        'home-search': { ...node(), value: '' }, 'home-result-count': node(), 'home-no-matches': node(), 'home-last-success': node() };
    const year = node();
    const document = { ...node(), hidden: false, getElementById: id => ids[id],
        querySelectorAll: () => [year] };
    const window = node(), navigator = { onLine: true };
    const timers = new Map();
    let timerId = 0;
    const data = revision => ({ available: true, revision, updated_at: '2026-10-29T12:00:00+08:00',
        updated_label: 'Oct 29, 2026 · 12:00 PM (UTC+08:00)',year: 2026,
        fragments: Object.fromEntries(names.map(name => [name,(revision === 'one' ? 'old-' : 'fresh-') + name])) });
    const state = { response: data('one'), failure: false, calls: 0 };
    const fetch = async () => {
        state.calls++;
        if (state.failure) return { ok: false, headers: { get: () => null } };
        return { ok: true, json: async () => state.response };
    };
    vm.runInNewContext(fs.readFileSync(path.join(__dirname,'../../public/js/home.js'),'utf8'), {
        document,window,navigator,fetch,AbortController,
        setTimeout(fn,delay) { timers.set(++timerId,{fn,delay}); return timerId; },
        clearTimeout(id) { timers.delete(id); },
    });
    return { ...state, state, data, ids, root, slots, cards, courses, document, window, navigator, timers, year };
}

test('changed records refresh fragments while preserving course search and example expansion', async () => {
    const h = home();
    h.ids['home-search'].value = 'loops';
    h.ids['home-search'].fire('input');
    assert.equal(h.cards[0].hidden,false);
    assert.equal(h.cards[1].hidden,true);
    assert.equal(h.courses[1].hidden,true);
    assert.equal(h.courses[0].count.textContent,'1 of 1 lessons');
    h.state.response = h.data('two');
    await h.ids['home-refresh'].fire('click');
    assert.equal(h.slots.stats.innerHTML,'fresh-stats');
    assert.equal(h.slots.example.output.open,true);
    assert.equal(h.ids['home-search'].value,'loops');
    assert.equal(h.cards[1].hidden,true);
    assert.equal(h.root.dataset.homeRevision,'two');
    assert.equal(h.year.textContent,'2026');
    assert.equal(h.ids['home-live-status'].dataset.homeState,'live');
});

test('unchanged data refreshes its successful timestamp without replacing focused content', async () => {
    const h = home();
    await h.ids['home-refresh'].fire('click');
    assert.equal(h.slots.portals.writes,0);
    assert.equal(h.slots.catalog.writes,0);
    assert.equal(h.ids['home-last-success'].textContent,h.state.response.updated_label);
    assert.ok([...h.timers.values()].some(timer => timer.delay === 15000));
});

test('failed updates preserve data and successful timestamps and back off before recovery', async () => {
    const h = home();
    await h.ids['home-refresh'].fire('click');
    const last = h.ids['home-last-success'].textContent;
    h.state.failure = true;
    await h.ids['home-refresh'].fire('click');
    assert.equal(h.slots.stats.innerHTML,'old-stats');
    assert.equal(h.ids['home-last-success'].textContent,last);
    assert.equal(h.ids['home-live-status'].dataset.homeState,'error');
    assert.equal(h.ids['home-refresh'].disabled,false);
    assert.ok([...h.timers.values()].some(timer => timer.delay === 30000));
    h.state.failure = false;
    h.state.response = h.data('three');
    await h.ids['home-refresh'].fire('click');
    assert.equal(h.slots.stats.innerHTML,'fresh-stats');
    assert.equal(h.ids['home-live-status'].dataset.homeState,'live');
});

test('hidden pages pause requests and refresh on return; offline state preserves data', async () => {
    const h = home();
    h.document.hidden = true;
    h.document.fire('visibilitychange');
    assert.equal(h.timers.size,0);
    await h.ids['home-refresh'].fire('click');
    assert.equal(h.state.calls,0);
    h.document.hidden = false;
    h.document.fire('visibilitychange');
    await new Promise(resolve => setImmediate(resolve));
    assert.equal(h.state.calls,1);
    h.navigator.onLine = false;
    await h.ids['home-refresh'].fire('click');
    assert.equal(h.state.calls,1);
    assert.equal(h.ids['home-live-status'].dataset.homeState,'offline');
});

test('expired accounts can be replaced by public portal content on the next update', async () => {
    const h = home();
    h.slots.portals.innerHTML = 'private student summary';
    h.state.response = h.data('logged-out');
    h.state.response.fragments.portals = 'public sign-in cards';
    await h.ids['home-refresh'].fire('click');
    assert.equal(h.slots.portals.innerHTML,'public sign-in cards');
    assert.equal(h.root.dataset.homeRevision,'logged-out');
});
