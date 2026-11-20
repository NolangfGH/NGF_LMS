const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

test('lesson videos load only after Play and repeated clicks keep the same player', () => {
    let click, created = 0;
    const player = {children: [], hidden: true, append(node) {this.children.push(node);}};
    const placeholder = {hidden: false};
    const root = {querySelector: selector => selector === '[data-lesson-video-player]' ? player : placeholder};
    const button = {dataset: {videoSrc: 'https://www.youtube-nocookie.com/embed/aqz-KE-bpKQ?rel=0', videoTitle: 'Program structure'}, closest: () => root};
    vm.runInNewContext(fs.readFileSync('public/js/lesson-media.js', 'utf8'), {document: {
        addEventListener(name, fn) {click = fn;}, createElement(tag) {assert.equal(tag, 'iframe'); created++; return {style: {}};},
    }});
    assert.equal(created, 0);
    click({target: {closest: () => button}});
    assert.equal(created, 1);
    assert.equal(player.hidden, false);
    assert.equal(button.hidden, true);
    assert.equal(placeholder.hidden, true);
    assert.equal(player.children[0].className, 'lesson-video-frame');
    assert.equal(player.children[0].src, button.dataset.videoSrc);
    assert.equal(player.children[0].title, 'Program structure');
    assert.equal(player.children[0].allowFullscreen, true);
    click({target: {closest: () => button}});
    assert.equal(created, 1);
});
