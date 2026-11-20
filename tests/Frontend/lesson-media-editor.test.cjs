const {test} = require('node:test');
const assert = require('node:assert/strict');
const {previewVideoUrl, mediaClasses} = require('../../public/js/lesson-media-editor.js');

test('video previews use only supported HTTPS providers with bounded start timestamps', () => {
    assert.equal(previewVideoUrl('https://youtu.be/aqz-KE-bpKQ',31),'https://www.youtube-nocookie.com/embed/aqz-KE-bpKQ?rel=0&start=31');
    assert.equal(previewVideoUrl('https://vimeo.com/123456789/abc123',45),'https://player.vimeo.com/video/123456789?h=abc123&dnt=1#t=45s');
    assert.equal(previewVideoUrl('https://www.youtube.com/watch?v=aqz-KE-bpKQ',-1),'https://www.youtube-nocookie.com/embed/aqz-KE-bpKQ?rel=0');
    assert.equal(previewVideoUrl('https://youtu.be/aqz-KE-bpKQ',999999),'https://www.youtube-nocookie.com/embed/aqz-KE-bpKQ?rel=0&start=14400');
    for (const url of ['https://example.com/video','http://youtu.be/aqz-KE-bpKQ','https://youtu.be/invalid','https://vimeo.com/abc']) assert.equal(previewVideoUrl(url),null);
});

test('image and video layouts are independent with allowlisted classes and legacy defaults', () => {
    const settings={image_width:'medium',image_alignment:'right',image_aspect:'square',image_fit:'cover',image_position:'top',image_frame:'border',video_width:'small',video_alignment:'left',video_aspect:'portrait',video_frame:'plain'};
    assert.equal(mediaClasses('image',settings),'lesson-media lesson-media-width-medium lesson-media-align-right lesson-media-aspect-square lesson-media-fit-cover lesson-media-position-top lesson-media-frame-border');
    assert.equal(mediaClasses('video',settings),'lesson-media lesson-media-width-small lesson-media-align-left lesson-media-aspect-portrait lesson-media-frame-plain');
    assert.equal(mediaClasses('image',{image_width:'unrecognized'}),'lesson-media lesson-media-width-full lesson-media-align-center lesson-media-aspect-natural lesson-media-fit-contain lesson-media-position-center lesson-media-frame-card');
    assert.match(mediaClasses('video',{}),/aspect-wide/);
});
