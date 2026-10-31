const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');

function report(print = false) {
    const node = () => ({
        hidden: false, listeners: {}, attributes: {},
        addEventListener(name, handler) { this.listeners[name] = handler; },
        fire(name, event = {}) { this.listeners[name]?.(event); },
        setAttribute(name, value) { this.attributes[name] = value; },
    });
    const inputs = [node(), node()];
    const dates = { ...node(), querySelectorAll: () => inputs };
    const range = { ...node(), value: 'all' };
    const menu = node();
    const classes = new Set();
    const sidebar = { classList: {
        toggle(name) { if (classes.has(name)) { classes.delete(name); return false; } classes.add(name); return true; },
        remove(name) { classes.delete(name); },
    }};
    const close = node();
    const link = { ...node(), dataset: { reportInspect: '42' } };
    const fragment = { evidence: 'teacher-scoped evidence' };
    const template = { content: { cloneNode: () => fragment } };
    const detail = { replaceChildren(value) { this.content = value; } };
    const dialog = { ...node(), showModal() { this.open = true; }, close() { this.open = false; } };
    const table = { open: false };
    const ids = { 'report-range': range, 'report-custom-dates': dates, 'report-menu': menu,
        'report-sidebar': sidebar, 'report-student-dialog': dialog, 'report-student-detail': detail,
        'report-evidence-42': template };
    const document = { ...node(), body: { dataset: { printReport: String(print) } }, fonts: { ready: Promise.resolve() },
        getElementById: id => ids[id], querySelector: () => close,
        querySelectorAll: selector => selector === '[data-report-inspect]' ? [link] : [table] };
    const window = { prints: 0, print() { this.prints++; } };
    vm.runInNewContext(fs.readFileSync(path.join(__dirname,'../../public/js/reports.js'),'utf8'), {document, window});
    return { range, dates, inputs, menu, close, link, detail, dialog, table, document, window };
}

test('custom dates become required only when the custom report period is selected', () => {
    const state = report();
    assert.equal(state.dates.hidden, true);
    assert.ok(state.inputs.every(input => input.disabled && !input.required));
    state.range.value = 'custom';
    state.range.fire('change');
    assert.equal(state.dates.hidden, false);
    assert.ok(state.inputs.every(input => !input.disabled && input.required));
    state.range.value = 'month';
    state.range.fire('change');
    assert.equal(state.dates.hidden, true);
    assert.ok(state.inputs.every(input => input.disabled && !input.required));
});

test('student inspection opens saved evidence and the mobile menu can be closed with Escape', () => {
    const state = report();
    let prevented = false;
    state.link.fire('click', { preventDefault() { prevented = true; } });
    assert.equal(prevented, true);
    assert.equal(state.dialog.open, true);
    assert.equal(state.detail.content.evidence, 'teacher-scoped evidence');
    state.dialog.fire('click', { target: state.dialog });
    assert.equal(state.dialog.open, false);
    state.menu.fire('click');
    assert.equal(state.menu.attributes['aria-expanded'], 'true');
    state.document.fire('keydown', { key: 'Escape' });
    assert.equal(state.menu.attributes['aria-expanded'], 'false');
});

test('printing is requested only for the full print view and includes exact chart counts', async () => {
    const normal = report();
    const printed = report(true);
    await new Promise(resolve => setImmediate(resolve));
    assert.equal(normal.window.prints, 0);
    assert.equal(printed.window.prints, 1);
    assert.equal(printed.table.open, true);
});
