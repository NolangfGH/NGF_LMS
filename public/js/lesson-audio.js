(function () {
    function createNarrationPlayer({ engine, makeUtterance, words, onChange = () => {} }) {
        let position = 0, activeWord = -1, playing = false, paused = false, generation = 0;
        let mode = 'word', rate = 1, voice = null, error = '', notice = '', utterance = null;
        const state = () => ({ position, activeWord, playing, paused, mode, rate, total: words.length, done: position >= words.length, error, notice });
        const notify = () => onChange(state());
        function next(run) {
            if (run !== generation || !playing) return;
            if (position >= words.length) {
                playing = false; paused = false; activeWord = -1; utterance = null; notify(); return;
            }
            const start = position;
            let end = start + 1;
            if (mode === 'natural') {
                let length = words[start].text.length;
                while (end < words.length && words[end].passage === words[start].passage && end - start < 30 && length < 220 && !/[.!?][”’"']?$/.test(words[end - 1].text)) {
                    length += words[end].text.length + 1; end++;
                }
            }
            const offsets = []; let text = '';
            for (let index = start; index < end; index++) {
                if (text) text += ' ';
                offsets.push({ offset: text.length, index }); text += words[index].text;
            }
            const chunkMode = mode;
            let boundarySeen = false;
            utterance = makeUtterance(text);
            utterance.rate = rate;
            utterance.lang = voice?.lang || 'en-US';
            if (voice) utterance.voice = voice;
            utterance.onstart = () => {
                if (run !== generation) return;
                activeWord = chunkMode === 'word' ? start : -1; notify();
            };
            utterance.onboundary = event => {
                if (run !== generation || chunkMode !== 'natural' || (event.name && event.name !== 'word') || !Number.isInteger(event.charIndex) || event.charIndex < 0 || event.charIndex >= text.length) return;
                boundarySeen = true;
                const word = offsets.filter(item => item.offset <= event.charIndex).pop();
                position = word.index; activeWord = word.index; notify();
            };
            utterance.onend = () => {
                if (run !== generation || !playing) return;
                position = end; activeWord = -1;
                if (chunkMode === 'natural' && !boundarySeen) {
                    mode = 'word'; notice = 'This voice does not provide word timing. Continuing in word-by-word mode for precise highlighting.';
                }
                notify(); next(run);
            };
            utterance.onerror = event => {
                if (run !== generation) return;
                playing = false; paused = false; activeWord = -1; utterance = null;
                error = event.error === 'not-allowed' ? 'Your browser blocked audio. Press Play again or choose another reading voice.' : 'The reading voice is unavailable. Choose another voice or check your device’s speech settings, then press Play.';
                notify();
            };
            engine.speak(utterance);
        }
        const player = {
            state,
            play() {
                if (!words.length || playing) return;
                if (position >= words.length) position = 0;
                generation++; engine.cancel(); error = ''; playing = true; paused = false; notify(); next(generation);
            },
            pause() {
                if (!playing) return;
                generation++; playing = false; paused = true; activeWord = Math.min(position, words.length - 1); engine.cancel(); notify();
            },
            stop() {
                generation++; playing = false; paused = false; position = 0; activeWord = -1; error = ''; notice = ''; utterance = null; engine.cancel(); notify();
            },
            seek(index) {
                const wasPlaying = playing;
                generation++; playing = false; paused = false; activeWord = -1;
                position = Math.max(0, Math.min(words.length ? words.length - 1 : 0, Number(index) || 0));
                error = ''; engine.cancel(); notify(); if (wasPlaying) player.play();
            },
            configure(settings) {
                const wasPlaying = playing;
                if (wasPlaying) player.pause();
                if (settings.mode) mode = settings.mode === 'natural' ? 'natural' : 'word';
                if (settings.rate !== undefined) rate = Math.max(0.5, Math.min(2, Number(settings.rate) || 1));
                if (settings.voice !== undefined) voice = settings.voice;
                notice = ''; notify(); if (wasPlaying) player.play();
            },
        };
        return player;
    }

    function initializeLessonAudio(document, browser) {
        const root = document.querySelector('[data-lesson-audio]');
        if (!root) return;
        const control = name => root.querySelector(`[data-speech-${name}]`);
        const status = control('status');
        if (!browser.speechSynthesis || !browser.SpeechSynthesisUtterance) {
            status.textContent = 'Read-aloud is unavailable in this browser. The full lesson remains available to read.';
            return;
        }
        const passages = [...document.querySelectorAll('[data-lesson-speech]')];
        const words = [], elements = [];
        const section = control('section');
        section.replaceChildren();
        passages.forEach((passage, index) => {
            const option = document.createElement('option');
            option.value = String(words.length); option.textContent = passage.dataset.lessonSpeech;
            section.appendChild(option);
            const fragment = document.createDocumentFragment();
            passage.textContent.split(/(\s+)/).filter(Boolean).forEach(part => {
                if (/^\s+$/.test(part)) { fragment.appendChild(document.createTextNode(part)); return; }
                const element = document.createElement('span'); element.textContent = part;
                fragment.appendChild(element); elements.push(element); words.push({ text: part, passage: index });
            });
            passage.replaceChildren(fragment);
        });
        let active = -1, activePassage = -1, voices = [];
        const player = createNarrationPlayer({
            engine: browser.speechSynthesis, makeUtterance: text => new browser.SpeechSynthesisUtterance(text), words,
            onChange(state) {
                if (active !== state.activeWord) {
                    if (elements[active]) elements[active].removeAttribute('data-speech-active');
                    active = state.activeWord;
                    if (elements[active]) elements[active].setAttribute('data-speech-active', '');
                }
                const passage = state.playing || state.paused ? words[state.activeWord >= 0 ? state.activeWord : state.position]?.passage ?? -1 : -1;
                if (passage !== activePassage) {
                    if (passages[activePassage]) passages[activePassage].removeAttribute('data-speech-passage-active');
                    activePassage = passage;
                    if (passages[passage]) {
                        passages[passage].setAttribute('data-speech-passage-active', '');
                        if (control('follow').checked) passages[passage].scrollIntoView({ block: 'center', behavior: 'auto' });
                    }
                }
                control('play').disabled = state.playing || !state.total;
                control('play').textContent = state.paused ? 'Resume lesson' : state.done ? 'Replay lesson' : 'Play lesson';
                control('pause').disabled = !state.playing;
                control('stop').disabled = !state.playing && !state.paused && state.position === 0;
                control('progress').max = Math.max(1, state.total); control('progress').value = state.position;
                control('position').textContent = `${state.position} / ${state.total} words`;
                control('mode').value = state.mode;
                const message = state.error || (state.playing ? 'Reading lesson' : state.paused ? 'Paused · resume from the current word' : state.done ? 'Lesson narration finished' : 'Ready when you are') + (state.notice ? ` · ${state.notice}` : '');
                if (status.textContent !== message) status.textContent = message;
            },
        });
        function loadVoices() {
            const selected = control('voice').value;
            voices = browser.speechSynthesis.getVoices();
            control('voice').replaceChildren();
            const defaultVoice = document.createElement('option'); defaultVoice.value = ''; defaultVoice.textContent = 'Default browser voice'; control('voice').appendChild(defaultVoice);
            voices.forEach((voice, index) => {
                const option = document.createElement('option'); option.value = voice.voiceURI || String(index);
                option.textContent = `${voice.name} · ${voice.lang}${voice.localService ? ' · device' : ' · online voice'}`;
                control('voice').appendChild(option);
            });
            if (voices.some((voice, index) => (voice.voiceURI || String(index)) === selected)) control('voice').value = selected;
        }
        loadVoices();
        browser.speechSynthesis.addEventListener('voiceschanged', loadVoices);
        control('play').addEventListener('click', () => player.play());
        control('pause').addEventListener('click', () => player.pause());
        control('stop').addEventListener('click', () => player.stop());
        control('section').addEventListener('change', () => player.seek(control('section').value));
        control('mode').addEventListener('change', () => player.configure({ mode: control('mode').value }));
        control('rate').addEventListener('change', () => player.configure({ rate: control('rate').value }));
        control('voice').addEventListener('change', () => player.configure({ voice: voices.find((voice, index) => (voice.voiceURI || String(index)) === control('voice').value) || null }));
        document.addEventListener('visibilitychange', () => { if (document.hidden) player.pause(); });
        browser.addEventListener('pagehide', () => player.stop());
        root.addEventListener('keydown', event => { if (event.key === 'Escape') player.stop(); });
        player.configure({ rate: control('rate').value, mode: control('mode').value });
    }
    if (typeof module === 'object' && module.exports) module.exports = { createNarrationPlayer, initializeLessonAudio };
    if (typeof document !== 'undefined' && typeof window !== 'undefined') initializeLessonAudio(document, window);
})();
