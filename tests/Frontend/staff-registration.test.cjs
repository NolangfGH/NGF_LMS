const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

function registrationPage() {
    const nodes = new Map();
    const node = id => {
        if (!nodes.has(id)) nodes.set(id, {value: '', textContent: '', hidden: false, disabled: false, children: [], listeners: {},
            addEventListener(type, callback) { this.listeners[type] = callback; }, replaceChildren() { this.children = []; }, append(child) { this.children.push(child); }, focus() {}});
        return nodes.get(id);
    };
    node('staff-role').value = 'teacher';
    node('registration').action = '/register';
    node('staff-role-data').textContent = JSON.stringify({roles: {teacher: 'Teacher', principal: 'Principal', school_admin: 'System Admin'},
        rules: {teacher: ['Own classes only'], principal: ['Read-only summaries'], school_admin: ['Approve staff access']}});
    const network = {calls: 0, ok: true, result: {status: 'pending', redirect: '/staff-registration-status'}, redirects: []};
    vm.runInNewContext(fs.readFileSync('public/js/staff-registration.js', 'utf8'), {document: {getElementById: node, createElement: () => ({textContent: ''}), querySelector: () => ({content: 'csrf-token'})},
        FormData: class {}, window: {location: {assign: url => network.redirects.push(url)}},
        fetch: async () => { network.calls++; return {ok: network.ok, json: async () => network.result}; }});
    return {node, network, choose(role) { node('staff-role').value = role; node('staff-role').listeners.change(); }, submit: () => node('registration').listeners.submit({preventDefault() {}})};
}

test('Principal and System Admin requests disable classroom requirements; Teacher restores them', () => {
    const app = registrationPage();
    for (const role of ['principal', 'school_admin']) {
        app.choose(role);
        assert.equal(app.node('teaching-setup').hidden, true);
        assert.equal(app.node('teaching-setup').disabled, true);
        assert.match(app.node('role-approval').textContent, /must approve/);
        assert.match(app.node('register-button').textContent, /access request/);
    }
    app.choose('teacher');
    assert.equal(app.node('teaching-setup').disabled, false);
    assert.equal(app.node('teaching-setup').hidden, false);
    assert.equal(app.node('role-rules').children[0].textContent, 'Own classes only');
});

test('successful role request follows the private status redirect and prevents duplicate submissions', async () => {
    const app = registrationPage();
    app.choose('principal');
    await Promise.all([app.submit(), app.submit()]);
    assert.equal(app.network.calls, 1);
    assert.deepEqual(app.network.redirects, ['/staff-registration-status']);
});

test('registration validation failure preserves the selected role and enables a corrected submission', async () => {
    const app = registrationPage();
    app.choose('school_admin');
    app.network.ok = false;
    app.network.result = {errors: {email: ['This school email is already registered.']}};
    await app.submit();
    assert.equal(app.node('feedback').textContent, 'This school email is already registered.');
    assert.equal(app.node('register-button').disabled, false);
    assert.equal(app.node('staff-role').value, 'school_admin');
    assert.deepEqual(app.network.redirects, []);
});
