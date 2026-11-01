const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const source = fs.readFileSync(path.join(__dirname, '../../public/js/lesson-content-editor.js'), 'utf8');

function fixture(size = 5) {
    const list = {rows: [], listeners: {}, querySelectorAll: () => list.rows, append: row => list.rows.push(row), contains: row => list.rows.includes(row), addEventListener: (type, callback) => list.listeners[type] = callback};
    function question(index) {
        const number = {};
        const fields = ['prompt', 'options][0', 'options][1', 'options][2', 'correct', 'why'].map(name => ({name: `checks[${index}][${name}]`, value: `${index}:${name}`, focus() { this.focused = true; }}));
        const row = {fields, querySelector: selector => selector === '[data-checkpoint-number]' ? number : selector === 'input' ? fields[0] : remove, querySelectorAll: () => fields, remove: () => list.rows.splice(list.rows.indexOf(row), 1)};
        const remove = {disabled: false, attrs: {}, setAttribute(key, value) { this.attrs[key] = value; }, closest: selector => selector === '[data-checkpoint-remove]' ? remove : row};
        row.removeButton = remove;
        row.number = number;
        return row;
    }
    list.rows = Array.from({length: size}, (_, index) => question(index));
    const add = {listeners: {}, addEventListener(type, callback) { this.listeners[type] = callback; }, focus() { this.focused = true; }};
    const count = {};
    const template = {content: {cloneNode: () => question('__INDEX__')}};
    const elements = {'[data-checkpoint-list]': list, '[data-checkpoint-template]': template, '[data-checkpoint-add]': add, '[data-checkpoint-count]': count};
    const editor = {dataset: {min: '5', max: '20'}, querySelector: selector => elements[selector]};
    vm.runInNewContext(source, {document: {querySelector: () => editor}});
    return {list, add, count, remove: index => list.listeners.click({target: list.rows[index].removeButton})};
}

test('minimum five questions cannot be removed; adding a question focuses its prompt', () => {
    const {list, add, count, remove} = fixture();
    assert.ok(list.rows.every(row => row.removeButton.disabled));
    remove(0);
    assert.equal(list.rows.length, 5);
    add.listeners.click();
    assert.equal(list.rows.length, 6);
    assert.equal(list.rows[5].fields[0].name, 'checks[5][prompt]');
    assert.equal(list.rows[5].fields[0].focused, true);
    assert.ok(list.rows.every(row => !row.removeButton.disabled));
    assert.equal(count.textContent, '6 questions · minimum 5 · maximum 20');
});

test('removing a middle question renumbers fields without changing typed values or correct answers', () => {
    const {list, remove, add} = fixture(7);
    const last = list.rows[6];
    const values = last.fields.map(field => field.value);
    remove(2);
    assert.deepEqual(last.fields.map(field => field.value), values);
    assert.equal(last.fields[4].name, 'checks[5][correct]');
    assert.equal(last.number.textContent, '6');
    assert.equal(last.removeButton.attrs['aria-label'], 'Remove checkpoint question 6');
    assert.equal(add.focused, true);
    remove(0);
    assert.equal(list.rows.length, 5);
    assert.ok(list.rows.every(row => row.removeButton.disabled));
});

test('maximum twenty questions is enforced and removal makes adding available again', () => {
    const {list, add, remove} = fixture(20);
    assert.equal(add.disabled, true);
    add.listeners.click();
    assert.equal(list.rows.length, 20);
    remove(0);
    assert.equal(add.disabled, false);
    add.listeners.click();
    assert.equal(list.rows.length, 20);
    assert.equal(add.disabled, true);
});

test('pages without a checkpoint editor are unaffected', () => {
    assert.doesNotThrow(() => vm.runInNewContext(source, {document: {querySelector: () => null}}));
});
