const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');

function settings(clipboard = true) {
    const node = () => ({
        listeners: {}, textContent: '', hidden: false,
        addEventListener(type, listener) { this.listeners[type] = listener; },
        fire(type, event = {}) { this.listeners[type]?.(event); },
    });
    const field = (name, value, type = 'text') => ({ name, value, type, checked: false, files: [], disabled: false });
    const makeForm = (name, fields) => {
        const original = fields.map(item => ({ value: item.value, checked: item.checked }));
        const reset = node();
        const submit = { disabled: false };
        fields.namedItem = name => fields.find(item => item.name === name);
        return { ...node(), dataset: { settingsForm: name }, elements: fields, resets: 0, submits: 0, submit,
            querySelector(selector) {
                if (selector === '[data-settings-reset]') return reset;
                if (selector.includes('high_contrast')) return fields.namedItem('high_contrast');
                if (selector.includes('reduce_motion')) return fields.namedItem('reduce_motion');
            },
            querySelectorAll: () => [submit],
            reset() { this.resets++; fields.forEach((item, index) => Object.assign(item, original[index])); },
            requestSubmit() { this.submits++; },
            resetButton: reset,
        };
    };
    const account = makeForm('account',[field('first_name','Teacher')]);
    const preferences = makeForm('preferences',[field('text_size','standard'),field('high_contrast','1','checkbox'),field('reduce_motion','1','checkbox')]);
    const ids = { 'settings-unsaved': node(), 'settings-dirty-count': node(), 'settings-discard': node(),
        'settings-save-current': node(), 'settings-live-status': node(), 'teacher-bio': field('bio','C++ teacher') };
    const code = { value: 'CODE1234', focus() { this.focused = true; }, select() { this.selected = true; } };
    ids['join-code-1'] = code;
    const copy = { ...node(), dataset: { copyTarget: 'join-code-1' } };
    const bioCount = node();
    const document = {
        documentElement: { dataset: {} }, getElementById: id => ids[id] || null,
        querySelector: () => bioCount,
        querySelectorAll: selector => selector === 'form[data-settings-form]' ? [account,preferences] : [copy],
    };
    const window = node();
    const navigator = { clipboard: clipboard ? { async writeText(value) { navigator.copied = value; } } : undefined };
    vm.runInNewContext(fs.readFileSync(path.join(__dirname,'../../public/js/teacher-settings.js'),'utf8'), { document, window, navigator });
    return { account, preferences, ids, document, window, navigator, copy, code };
}

test('edits show the unsaved bar and Save current section targets the last edited form', () => {
    const state = settings();
    state.account.elements[0].value = 'Updated';
    state.account.fire('input');
    assert.equal(state.ids['settings-unsaved'].hidden, false);
    state.preferences.elements.namedItem('text_size').value = 'large';
    state.preferences.fire('change');
    assert.equal(state.ids['settings-dirty-count'].textContent, 'in 2 sections');
    state.ids['settings-save-current'].fire('click');
    assert.equal(state.preferences.submits, 1);
    assert.equal(state.account.submits, 0);
});

test('accessibility previews are immediate and discarding restores the saved form values', () => {
    const state = settings();
    state.preferences.elements.namedItem('high_contrast').checked = true;
    state.preferences.elements.namedItem('reduce_motion').checked = true;
    state.preferences.fire('change');
    assert.equal(state.document.documentElement.dataset.teacherContrast, 'high');
    assert.equal(state.document.documentElement.dataset.teacherMotion, 'reduce');
    state.ids['settings-discard'].fire('click');
    assert.equal(state.document.documentElement.dataset.teacherContrast, 'standard');
    assert.equal(state.document.documentElement.dataset.teacherMotion, 'standard');
    assert.equal(state.ids['settings-unsaved'].hidden, true);
});

test('navigation warns about unsaved changes but allows submitting the only edited section', () => {
    const state = settings();
    state.account.elements[0].value = 'Updated';
    state.account.fire('input');
    let warned = false;
    state.window.fire('beforeunload', { preventDefault() { warned = true; } });
    assert.equal(warned, true);
    state.account.fire('submit');
    warned = false;
    state.window.fire('beforeunload', { preventDefault() { warned = true; } });
    assert.equal(warned, false);
    assert.equal(state.account.submit.disabled, true);
    assert.equal(state.ids['settings-live-status'].textContent, 'Saving your changes…');
});

test('join-code copying supports the clipboard and an accessible manual-copy fallback', async () => {
    const copied = settings();
    copied.copy.fire('click');
    await new Promise(resolve => setImmediate(resolve));
    assert.equal(copied.navigator.copied, 'CODE1234');
    const manual = settings(false);
    manual.copy.fire('click');
    await new Promise(resolve => setImmediate(resolve));
    assert.equal(manual.code.selected, true);
    assert.equal(manual.code.focused, true);
    assert.match(manual.ids['settings-live-status'].textContent, /Ctrl\+C/);
});
