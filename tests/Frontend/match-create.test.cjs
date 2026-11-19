const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

function fixture(enrolled = 10) {
    const category = {value: '2', addEventListener(name, fn) {this.change = fn;}};
    const rows = Array.from({length: 10}, () => ({}));
    const selectors = rows.map(() => ({value: '', options: [{value: ''}, ...Array.from({length: 10}, (_, i) => ({value: String(i+1), dataset: {room: 'A'}}))], addEventListener(name, fn) {this.change = fn;}, setCustomValidity(text) {this.invalid = text;}}));
    const filters = rows.map((_, i) => ({value: '', dataset: {rosterFilter: String(i)}, addEventListener(name, fn) {this.change = fn;}}));
    const status = {}, button = {};
    const form = {dataset: {enrolled: String(enrolled)}, querySelectorAll(selector) {return {'[data-match-slot]': rows, '[data-roster-student]': selectors, '[data-roster-filter]': filters}[selector];}, querySelector(selector) {
        return {'[data-match-count]': category, '[data-match-roster-status]': status, '[data-match-invite]': button}[selector] || filters[Number(selector.match(/"(\d+)"/)?.[1])];
    }};
    vm.runInNewContext(fs.readFileSync('public/js/match-create.js', 'utf8'), {document: {querySelector: () => form}});
    return {category, rows, selectors, button, status};
}

test('two and ten coder categories enable the matching slots and reject repeated students', () => {
    const app = fixture();
    assert.equal(app.rows.filter(row => !row.disabled).length, 2);
    app.category.value = '10'; app.category.change();
    assert.equal(app.rows.filter(row => !row.disabled).length, 10);
    app.selectors[0].value = '1'; app.selectors[1].value = '1'; app.selectors[0].change();
    assert.match(app.selectors[1].invalid, /different coder/);
    app.selectors[1].value = '2'; app.selectors[1].change();
    assert.equal(app.selectors[1].invalid, '');
    app.category.value = '2'; app.category.change();
    assert.equal(app.rows[9].hidden, true);
});

test('a ten-student match cannot be assigned from a roster with fewer than ten learners', () => {
    const app = fixture(7);
    app.category.value = '10'; app.category.change();
    assert.equal(app.button.disabled, true);
    assert.match(app.status.textContent, /7 are currently enrolled/);
    app.category.value = '2'; app.category.change();
    assert.equal(app.button.disabled, false);
});
