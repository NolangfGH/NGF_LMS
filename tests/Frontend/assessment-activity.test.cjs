const { test } = require('node:test');
const assert = require('node:assert/strict');
const { createActivityMonitor } = require('../../public/js/assessment-activity.js');

function eventTarget(properties = {}) {
    const listeners = new Map();
    return {
        ...properties,
        addEventListener(name, callback) { listeners.set(name, callback); },
        fire(name, event = {}) { return listeners.get(name)?.(event); },
    };
}

function preview({ active = false, reportStatus = 200 } = {}) {
    const reports = [], timeouts = new Map();
    let time = 0, timeoutId = 0;
    const status = {}, warning = { hidden: true }, fullscreen = eventTarget(), note = {};
    const document = eventTarget({ hidden: false, documentElement: {} });
    const browser = eventTarget({
        setTimeout(callback) { timeouts.set(++timeoutId, callback); return timeoutId; },
        clearTimeout(id) { timeouts.delete(id); },
        setInterval() { return 1; },
        clearInterval() {},
    });
    const elements = { status, warning, fullscreen, 'fullscreen-note': note };
    const root = {
        dataset: { assessmentKind: 'quiz', assessmentId: '42', stateUrl: '/status', reportUrl: '/activity', csrf: 'test-token' },
        querySelector(selector) { return elements[selector.slice(17, -1)]; },
    };
    const monitor = createActivityMonitor({
        root, document, browser, clock: () => time,
        fetcher: async (_, options) => {
            if (options.method === 'POST') {
                reports.push(JSON.parse(options.body));
                return { ok: reportStatus === 200, status: reportStatus };
            }
            return { ok: true, status: 200, json: async () => ({ active }) };
        },
    });
    return {
        ...elements, document, browser, monitor, reports,
        start() { active = true; },
        advance(ms) { time += ms; },
        flushBlur() { for (const callback of timeouts.values()) callback(); timeouts.clear(); },
    };
}

const settle = () => new Promise(resolve => setImmediate(resolve));

test('activity starts only after server confirmation and records tab changes with elapsed time', async () => {
    const view = preview();
    await settle();
    view.document.hidden = true;
    view.document.fire('visibilitychange');
    assert.equal(view.reports.length, 0);
    view.document.hidden = false;
    view.start();
    view.document.fire('visibilitychange');
    await settle();
    view.browser.fire('blur');
    view.document.hidden = true;
    view.document.fire('visibilitychange');
    view.flushBlur();
    view.advance(4200);
    view.document.hidden = false;
    view.document.fire('visibilitychange');
    await settle();
    assert.deepEqual(view.reports.map(event => event.signal), ['monitor_started', 'page_hidden', 'page_visible']);
    assert.equal(view.reports[2].away_seconds, 4);
    assert.equal(view.reports[2].id, 42);
    assert.deepEqual(Object.keys(view.reports[2]).sort(), ['away_seconds', 'id', 'kind', 'observed_at', 'request_key', 'signal']);
    assert.equal(view.warning.hidden, false);
    assert.match(view.warning.textContent, /timer has continued/);
});

test('fullscreen support is optional and a closed attempt stops sending events', async () => {
    const view = preview({ active: true, reportStatus: 409 });
    await settle();
    assert.equal(view.fullscreen.disabled, true);
    assert.match(view['fullscreen-note'].textContent, /still take the assessment/);
    view.document.hidden = true;
    view.document.fire('visibilitychange');
    assert.deepEqual(view.reports.map(event => event.signal), ['monitor_started']);
});
