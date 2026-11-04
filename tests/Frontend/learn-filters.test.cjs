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
        'lesson-term': control('all'),
        'lesson-result-count': control(''), 'lesson-empty': control(''),
    };
    const header = control('');
    const cards = [
        { dataset: { title: 'Read user input', status: 'not_started', source: 'assigned', term: 'Term1' } },
        { dataset: { title: 'Repeat with loops', status: 'completed', source: 'self', term: 'Term2' } },
        { dataset: { title: 'Loop sentinel', status: 'in_progress', source: 'assigned', term: 'Term2' } },
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

test('term filtering combines with search and clear restores the whole path', () => {
    const { ids, cards } = library();
    ids['lesson-term'].value = 'Term2';
    ids['lesson-term'].fire('change');
    assert.deepEqual(cards.map(card => card.hidden), [true, false, false]);
    ids['lesson-search'].value = 'input';
    ids['lesson-search'].fire('input');
    assert.equal(ids['lesson-empty'].hidden, false);
    ids['lesson-clear'].fire('click');
    assert.equal(ids['lesson-term'].value, 'all');
    assert.deepEqual(cards.map(card => card.hidden), [false, false, false]);
});
