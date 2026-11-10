const { test } = require('node:test');
const assert = require('node:assert/strict');
const { createNarrationPlayer, initializeLessonAudio } = require('../../public/js/lesson-audio.js');

function reader(mode = 'word') {
    const queue = [], states = [];
    const engine = { cancellations: 0, cancel() { this.cancellations++; }, speak(utterance) { queue.push(utterance); } };
    const words = ['Read', 'this.', 'Then', 'build', 'it.'].map((text, index) => ({ text, passage: index < 2 ? 0 : 1 }));
    const player = createNarrationPlayer({ engine, words, makeUtterance: text => ({ text }), onChange: state => states.push(state) });
    player.configure({ mode });
    return { player, queue, states, engine };
}

test('word mode speaks and highlights one real word at a time and finishes at full progress', () => {
    const { player, queue } = reader();
    player.play();
    assert.equal(queue.length, 1);
    assert.equal(queue[0].text, 'Read');
    queue[0].onstart();
    assert.equal(player.state().activeWord, 0);
    for (let index = 0; index < 5; index++) {
        queue[index].onstart();
        assert.equal(player.state().activeWord, index);
        queue[index].onend();
    }
    assert.equal(player.state().playing, false);
    assert.equal(player.state().done, true);
    assert.equal(player.state().position, 5);
    assert.equal(player.state().activeWord, -1);
    player.play();
    assert.equal(queue[5].text, 'Read');
});

test('pause cancels current speech, resumes the same word and ignores late callbacks after stop', () => {
    const { player, queue } = reader();
    player.play(); queue[0].onstart(); queue[0].onend(); queue[1].onstart();
    player.pause();
    assert.equal(player.state().paused, true);
    queue[1].onend();
    assert.equal(player.state().position, 1);
    player.play();
    assert.equal(queue[2].text, 'this.');
    player.stop(); queue[2].onend(); queue[2].onstart();
    assert.equal(player.state().position, 0);
    assert.equal(player.state().activeWord, -1);
    assert.equal(queue.length, 3);
});

test('natural narration maps actual voice character boundaries to words and resumes from the current word', () => {
    const { player, queue } = reader('natural');
    player.play();
    assert.equal(queue[0].text, 'Read this.');
    queue[0].onstart();
    assert.equal(player.state().activeWord, -1);
    queue[0].onboundary({ name: 'word', charIndex: 5 });
    assert.equal(player.state().activeWord, 1);
    player.pause(); player.play();
    assert.equal(queue[1].text, 'this.');
    queue[0].onboundary({ name: 'word', charIndex: 0 });
    assert.equal(player.state().position, 1);
});

test('a voice without natural word timing switches to precise word mode instead of guessing timing', () => {
    const { player, queue } = reader('natural');
    player.play(); queue[0].onstart(); queue[0].onend();
    assert.equal(player.state().mode, 'word');
    assert.match(player.state().notice, /does not provide word timing/);
    assert.equal(queue[1].text, 'Then');
    queue[1].onstart();
    assert.equal(player.state().activeWord, 2);
});

test('voice, speed and passage changes restart safely and speech errors permit retry', () => {
    const { player, queue } = reader();
    player.play();
    const voice = { name: 'Test voice', lang: 'en-GB' };
    player.configure({ voice, rate: 1.25 });
    assert.equal(queue[1].voice, voice);
    assert.equal(queue[1].rate, 1.25);
    assert.equal(queue[1].lang, 'en-GB');
    player.seek(3);
    assert.equal(queue[2].text, 'build');
    queue[2].onerror({ error: 'not-allowed' });
    assert.equal(player.state().playing, false);
    assert.match(player.state().error, /Press Play again/);
    player.play();
    assert.equal(queue[3].text, 'build');
});

test('unsupported browsers retain readable lessons and do not touch lesson text', () => {
    const status = { textContent: '' };
    const document = { querySelector: () => ({ querySelector: () => status }), querySelectorAll() { throw new Error('Should not rewrite a lesson when speech is unavailable'); } };
    initializeLessonAudio(document, {});
    assert.match(status.textContent, /unavailable in this browser/);
});
