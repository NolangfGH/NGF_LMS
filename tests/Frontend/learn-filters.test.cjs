const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');

function library() {
    const control = value => ({
        value, listeners: {}, hidden: false, textContent: '',
        addEventListener(event, handler) { this.listeners[event] = handler; },
        setAttribute() {},
        fire(event) { this.listeners[event](); },
    });
    const ids = {
        'lesson-search': control(''), 'lesson-status': control('all'),
        'lesson-source': control('all'), 'lesson-clear': control(''),
        'lesson-result-count': control(''), 'lesson-empty': control(''),
    };
    const header = control('');
    const cards = [
        { dataset: { title: 'Read user input', status: 'not_started', source: 'assigned' } },
        { dataset: { title: 'Repeat with loops', status: 'completed', source: 'self' } },
        { dataset: { title: 'Loop sentinel', status: 'in_progress', source: 'assigned' } },
    ];
    const document = { getElementById: id => ids[id], querySelectorAll: () => cards, querySelector: () => header };
    vm.runInNewContext(fs.readFileSync(path.join(__dirname,'../../public/js/learn.js'),'utf8'),{document});
    return { ids, cards, header };
}

test('combined topic, progress and source filters show matching lessons and handle an empty result', () => {
    const { ids, cards } = library();
    ids['lesson-search'].value = '  LOOP ';
    ids['lesson-search'].fire('input');
    assert.equal(cards[0].hidden,true);
    assert.equal(cards[1].hidden,false);
    assert.equal(cards[2].hidden,false);
    ids['lesson-source'].value = 'assigned';
    ids['lesson-source'].fire('change');
    assert.equal(cards[1].hidden,true);
    assert.equal(cards[2].hidden,false);
    ids['lesson-status'].value = 'completed';
    ids['lesson-status'].fire('change');
    assert.equal(ids['lesson-empty'].hidden,false);
    assert.equal(ids['lesson-result-count'].textContent,'0 of 3 lessons shown');
});

test('header search filters the library and clear restores all lessons and controls', () => {
    const { ids, cards, header } = library();
    header.value = 'input';
    header.fire('input');
    assert.equal(ids['lesson-search'].value,'input');
    assert.deepEqual(cards.map(card => card.hidden),[false,true,true]);
    ids['lesson-clear'].fire('click');
    assert.equal(header.value,'');
    assert.equal(ids['lesson-search'].value,'');
    assert.equal(ids['lesson-source'].value,'all');
    assert.equal(ids['lesson-status'].value,'all');
    assert.deepEqual(cards.map(card => card.hidden),[false,false,false]);
    assert.equal(ids['lesson-empty'].hidden,true);
});
