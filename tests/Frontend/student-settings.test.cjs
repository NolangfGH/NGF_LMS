const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');

function settings() {
    const node = () => ({
        hidden: false, textContent: '', listeners: {},
        addEventListener(type, handler) { this.listeners[type] = handler; },
        fire(type, event = {}) { this.listeners[type]?.(event); },
        scrollIntoView() {},
    });
    const field = (name, value, type = 'text') => ({ name, value, type, checked: false, disabled: false });
    const form = (name, elements) => {
        const initial = elements.map(item => ({ value: item.value, checked: item.checked }));
        const resetButton = node();
        elements.namedItem = key => elements.find(item => item.name === key);
        return { ...node(), elements, dataset: { studentSettingsForm: name }, submits: 0, resetButton,
            querySelector(selector) {
                if (selector === '[data-student-reset]') return resetButton;
                if (selector.includes('high_contrast')) return elements.namedItem('high_contrast');
                if (selector.includes('reduce_motion')) return elements.namedItem('reduce_motion');
            },
            querySelectorAll: () => [],
            requestSubmit() { this.submits++; },
            reset() { elements.forEach((item,index) => Object.assign(item, initial[index])); },
        };
    };
    const profile = form('profile',[field('nickname','MyCoder')]);
    const display = form('display',[field('text_size','medium'),field('high_contrast','1','checkbox'),field('reduce_motion','1','checkbox')]);
    const avatar = { ...field('avatar','Solar','radio'), dataset: { avatarEmoji: '☀️', avatarLabel: 'Solar' }, checked: true };
    const avatarForm = form('avatar',[avatar]);
    const password = form('password',[field('current_password','','password'),field('password','','password')]);
    const visible = { ...field('leaderboard_visible','1','checkbox'), checked: true };
    const privacy = form('privacy',[visible]);
    const ids = {
        'student-settings-unsaved': node(), 'student-settings-dirty-count': node(),
        'student-settings-live-status': node(), 'student-settings-discard': node(), 'student-settings-save-current': node(),
        'student-avatar-preview': node(), 'student-avatar-label': node(), 'student-leaderboard-visible': visible,
        'student-ranking-preview': { ...node(), dataset: { publicName: 'MyCoder', privateAlias: 'Coder-1234' } },
        'student-password-dialog': { ...node(), showModal() { this.open = true; } },
    };
    const openPassword = node();
    const document = {
        documentElement: { dataset: {} }, getElementById: id => ids[id] || null,
        querySelectorAll: () => [profile,display,avatarForm,password,privacy],
        querySelector(selector) {
            return selector.includes('data-student-avatar') ? avatar : selector.includes('data-open-student-password') ? openPassword : null;
        },
    };
    const window = node();
    vm.runInNewContext(fs.readFileSync(path.join(__dirname,'../../public/js/student-settings.js'),'utf8'), {document,window});
    return {profile,display,avatar,password,privacy,visible,ids,document,window,openPassword};
}

test('avatar and leaderboard previews use selected preferences without saving', () => {
    const state = settings();
    assert.equal(state.ids['student-avatar-preview'].textContent,'☀️');
    assert.equal(state.ids['student-avatar-label'].textContent,'Solar');
    state.visible.checked = false;
    state.privacy.fire('change');
    assert.equal(state.ids['student-ranking-preview'].textContent,'Coder-1234');
    assert.equal(state.ids['student-settings-unsaved'].hidden,false);
});

test('display previews update immediately and discarding restores saved defaults', () => {
    const state = settings();
    state.display.elements.namedItem('text_size').value = 'large';
    state.display.elements.namedItem('high_contrast').checked = true;
    state.display.elements.namedItem('reduce_motion').checked = true;
    state.display.fire('change');
    assert.equal(state.document.documentElement.dataset.studentText,'large');
    assert.equal(state.document.documentElement.dataset.studentContrast,'high');
    assert.equal(state.document.documentElement.dataset.studentMotion,'reduce');
    state.ids['student-settings-discard'].fire('click');
    assert.equal(state.document.documentElement.dataset.studentText,'medium');
    assert.equal(state.document.documentElement.dataset.studentContrast,'standard');
    assert.equal(state.ids['student-settings-unsaved'].hidden,true);
});

test('Save current section submits the last edited form and warns for other unsaved sections', () => {
    const state = settings();
    state.profile.elements[0].value = 'Updated';
    state.profile.fire('input');
    state.display.elements[0].value = 'large';
    state.display.fire('change');
    state.ids['student-settings-save-current'].fire('click');
    assert.equal(state.display.submits,1);
    assert.equal(state.profile.submits,0);
    state.display.fire('submit');
    let prevented = false;
    state.window.fire('beforeunload',{ preventDefault() { prevented = true; } });
    assert.equal(prevented,true);
});

test('closing the password dialog clears unsaved credentials', () => {
    const state = settings();
    state.openPassword.fire('click');
    assert.equal(state.ids['student-password-dialog'].open,true);
    state.password.elements[0].value = 'secret';
    state.password.fire('input');
    state.ids['student-password-dialog'].fire('close');
    assert.equal(state.password.elements[0].value,'');
    assert.equal(state.ids['student-settings-unsaved'].hidden,true);
});
