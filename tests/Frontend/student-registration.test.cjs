const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

const initialOptions = [
    {grade: 'Grade 8', section: 'AM'}, {grade: 'Grade 8', section: 'AM'},
    {grade: 'Grade 8', section: 'BP'}, {grade: 'Grade 8', section: 'DB'},
    {grade: 'Grade 9', section: 'AHB'}, {grade: 'Grade 9', section: 'LM'}, {grade: 'Grade 9', section: 'MC'},
];

function page() {
    const node = (value = '') => {
        const classes = new Set();
        return {
            value, textContent: '', disabled: false, options: [], attributes: {},
            classList: {add: name => classes.add(name), remove: name => classes.delete(name), contains: name => classes.has(name), toggle(name, on) { if (on) classes.add(name); else classes.delete(name); }},
            replaceChildren(...options) { this.options = options; this.value = ''; },
            add(option) { this.options.push(option); },
            setAttribute(name, value) { this.attributes[name] = value; },
            removeAttribute(name) { delete this.attributes[name]; },
            focus() {},
        };
    };
    const nodes = Object.fromEntries(['enrollmentOptions', 'gradeLevel', 'section', 'classCode', 'enrollmentForm', 'accountForm', 'feedback', 'sectionHelp', 'refreshClasses', 'success', 'indicator1', 'indicator2', 'indicator3', 'verifiedClass', 'backButton'].map(id => [id, node()]));
    nodes.enrollmentOptions.textContent = JSON.stringify(initialOptions);
    nodes.gradeLevel.value = 'Grade 8';
    nodes.classCode.value = 'TEACHER2';
    nodes.accountForm.classList.add('hidden');
    const verifyButton = node(), accountButton = node();
    nodes.enrollmentForm.querySelector = () => verifyButton;
    nodes.accountForm.querySelector = () => accountButton;
    const root = {dataset: {optionsUrl: '/student-register/options', verifyUrl: '/student-register/verify-class', registerUrl: '/student-register'}};
    const network = {calls: [], redirects: [], ok: true, refreshOk: true, catalog: initialOptions, gate: null};
    class FormData {
        constructor(form) {
            this.values = new Map(Object.entries(form === nodes.enrollmentForm
                ? {first_name: 'Test', surname: 'Learner', grade_level: nodes.gradeLevel.value, section: nodes.section.value, class_code: nodes.classCode.value}
                : {nickname: 'TestCoder', student_id: 'TEST001', password: 'Password123', password_confirmation: 'Password123'}));
        }
        get(key) { return this.values.get(key); }
        set(key, value) { this.values.set(key, String(value)); }
        [Symbol.iterator]() { return this.values[Symbol.iterator](); }
    }
    vm.runInNewContext(fs.readFileSync('public/js/student-registration.js', 'utf8'), {
        document: {getElementById: id => nodes[id], querySelector: selector => selector === '[data-student-registration]' ? root : {content: 'test-csrf'}},
        window: {location: {assign: url => network.redirects.push(url)}},
        Option: class { constructor(label, value) { this.label = label; this.value = value; } },
        FormData,
        fetch: async (url, settings) => {
            network.calls.push({url, ...settings});
            if (url === root.dataset.optionsUrl) return {ok: network.refreshOk, json: async () => ({enrollment_options: network.catalog})};
            if (network.gate) await network.gate;
            return {ok: network.ok, json: async () => network.ok
                ? url === root.dataset.verifyUrl ? {classroom: {id: 88, grade: 'Grade 9', section: 'AHB'}} : {redirect: '/student-registration-status'}
                : {message: 'Code must match the selected grade and section.'}};
        },
    });
    const event = {preventDefault() {}};
    return {nodes, network, verifyButton, accountButton, verify: () => nodes.enrollmentForm.onsubmit(event), register: () => nodes.accountForm.onsubmit(event)};
}

test('each grade displays its own unique sections and changing grades clears the section selection', () => {
    const {nodes} = page();
    assert.deepEqual(nodes.gradeLevel.options.map(option => option.value), ['', 'Grade 8', 'Grade 9']);
    assert.deepEqual(nodes.section.options.map(option => option.value), ['', 'AM', 'BP', 'DB']);
    nodes.section.value = 'AM';
    nodes.gradeLevel.value = 'Grade 9';
    nodes.gradeLevel.onchange();
    assert.deepEqual(nodes.section.options.map(option => option.value), ['', 'AHB', 'LM', 'MC']);
    assert.equal(nodes.section.value, '');
    assert.match(nodes.sectionHelp.textContent, /Grade 9/);
});

test('the verified code response supplies the classroom ID for final student registration', async () => {
    const app = page();
    app.nodes.gradeLevel.value = 'Grade 9';
    app.nodes.gradeLevel.onchange();
    app.nodes.section.value = 'AHB';
    await app.verify();
    assert.equal(app.network.calls[0].body.get('section'), 'AHB');
    assert.equal(app.network.calls[0].body.get('classroom_id'), undefined);
    assert.equal(app.nodes.accountForm.classList.contains('hidden'), false);
    await app.register();
    assert.equal(app.network.calls[1].body.get('classroom_id'), '88');
    assert.equal(app.network.calls[1].body.get('grade_level'), 'Grade 9');
    assert.equal(app.network.calls[1].body.get('section'), 'AHB');
    assert.deepEqual(app.network.redirects, ['/student-registration-status']);
});

test('refresh lists new saved grades and preserves an available grade and section', async () => {
    const {nodes, network} = page();
    nodes.section.value = 'BP';
    network.catalog = [...initialOptions, {grade: 'Grade 10', section: 'A'}];
    await nodes.refreshClasses.onclick();
    assert.deepEqual(nodes.gradeLevel.options.map(option => option.value), ['', 'Grade 8', 'Grade 9', 'Grade 10']);
    assert.equal(nodes.gradeLevel.value, 'Grade 8');
    assert.equal(nodes.section.value, 'BP');
    assert.equal(network.calls[0].cache, 'no-store');
    assert.equal(nodes.refreshClasses.disabled, false);
});

test('failed refresh or invalid code keeps the form usable without opening account setup', async () => {
    const app = page();
    app.nodes.section.value = 'AM';
    app.network.refreshOk = false;
    await app.nodes.refreshClasses.onclick();
    assert.equal(app.nodes.section.value, 'AM');
    assert.equal(app.nodes.refreshClasses.disabled, false);
    app.network.ok = false;
    await app.verify();
    assert.equal(app.nodes.accountForm.classList.contains('hidden'), true);
    assert.equal(app.verifyButton.disabled, false);
    assert.match(app.nodes.feedback.textContent, /Code must match/);
    await app.register();
    assert.deepEqual(app.network.redirects, []);
});

test('changing the selected grade while verification is pending requires verification again', async () => {
    const app = page();
    app.nodes.gradeLevel.value = 'Grade 9';
    app.nodes.gradeLevel.onchange();
    app.nodes.section.value = 'AHB';
    let release;
    app.network.gate = new Promise(resolve => { release = resolve; });
    const pending = app.verify();
    app.nodes.gradeLevel.value = 'Grade 8';
    app.nodes.gradeLevel.onchange();
    release();
    await pending;
    assert.equal(app.nodes.accountForm.classList.contains('hidden'), true);
    assert.match(app.nodes.feedback.textContent, /details changed/);
});
