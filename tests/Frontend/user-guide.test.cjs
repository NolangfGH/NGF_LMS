const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');

function guide() {
    const control = value => ({ value, listeners: {}, hidden: false, textContent: '',
        addEventListener(event, handler) { this.listeners[event] = handler; },
        fire(event) { this.listeners[event](); }, focus() { this.focused = true; },
        toggleAttribute(name, value) { this[name] = value; },
    });
    const nodes = Object.fromEntries(['search', 'role', 'clear', 'count', 'empty'].map(key => [`[data-guide-${key}]`, control(key === 'role' ? 'all' : '')]));
    const topics = [
        { id: 'start', dataset: { guideRoles: 'student teacher' }, textContent: 'Start with the pre-test explanation' },
        { id: 'review', dataset: { guideRoles: 'teacher principal' }, textContent: 'Review lesson plans' },
        { id: 'family', dataset: { guideRoles: 'parent' }, textContent: 'Link to your child' },
    ];
    topics.forEach(topic => { nodes[`[data-guide-link="${topic.id}"]`] = control(''); });
    vm.runInNewContext(fs.readFileSync(path.join(__dirname, '../../public/js/user-guide.js'), 'utf8'), {
        document: { querySelector: selector => nodes[selector], querySelectorAll: () => topics },
    });
    return { nodes, topics };
}

test('guide combines role and word search and hides nonmatching contents links', () => {
    const { nodes, topics } = guide();
    nodes['[data-guide-role]'].value = 'teacher';
    nodes['[data-guide-role]'].fire('change');
    assert.deepEqual(topics.map(topic => topic.hidden), [false, false, true]);
    nodes['[data-guide-search]'].value = '  LESSON   plans ';
    nodes['[data-guide-search]'].fire('input');
    assert.deepEqual(topics.map(topic => topic.hidden), [true, false, true]);
    assert.equal(nodes['[data-guide-link="start"]'].hidden, true);
    assert.equal(nodes['[data-guide-link="review"]'].hidden, false);
    assert.equal(nodes['[data-guide-count]'].textContent, '1 of 3 help topics');
});

test('guide reports no matches and clear restores topics, links and keyboard focus', () => {
    const { nodes, topics } = guide();
    nodes['[data-guide-search]'].value = 'no matching phrase';
    nodes['[data-guide-search]'].fire('input');
    assert.equal(nodes['[data-guide-empty]'].hidden, false);
    nodes['[data-guide-clear]'].fire('click');
    assert.equal(nodes['[data-guide-role]'].value, 'all');
    assert.deepEqual(topics.map(topic => topic.hidden), [false, false, false]);
    assert.equal(nodes['[data-guide-empty]'].hidden, true);
    assert.equal(nodes['[data-guide-search]'].focused, true);
});

test('short XP search does not match the middle of explanation and punctuation is literal', () => {
    const { nodes } = guide();
    nodes['[data-guide-search]'].value = 'XP';
    nodes['[data-guide-search]'].fire('input');
    assert.equal(nodes['[data-guide-empty]'].hidden, false);
    nodes['[data-guide-search]'].value = 'C++';
    assert.doesNotThrow(() => nodes['[data-guide-search]'].fire('input'));
});
