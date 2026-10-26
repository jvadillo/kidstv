const assert = require('node:assert/strict');
const { readFileSync } = require('node:fs');
const { join } = require('node:path');
const { test } = require('node:test');
const vm = require('node:vm');

// YouTube and the clock are substituted so playback events and the four-second
// delay can be checked without network access or waiting in real time.
const source = readFileSync(join(__dirname, '../../public/tv-bridge/youtube.js'), 'utf8');

function playerPage(mode = 'auto') {
    const elements = {};
    for (const id of ['player', 'controls', 'toggle', 'rewind', 'forward', 'progress', 'current', 'duration']) {
        elements[id] = {
            hidden: id === 'controls', disabled: true, value: 0, textContent: '',
            attributes: {}, listeners: {},
            getAttribute(name) { return this.attributes[name] ?? null; },
            setAttribute(name, value) { this.attributes[name] = value; },
            addEventListener(name, listener) { this.listeners[name] = listener; },
        };
    }
    elements.player.attributes = { 'data-video-id': 'aqz-KE-bpKQ', 'data-controls': mode, 'data-origin': 'https://kidstv.example' };
    elements.toggle.attributes = { 'data-play': 'Reproducir', 'data-pause': 'Pausar' };
    const classes = new Set();
    const timers = new Map();
    let clock = 0;
    let nextId = 0;
    function schedule(fn, delay, repeat = false) {
        timers.set(++nextId, { fn, delay, repeat, due: clock + delay });
        return nextId;
    }
    function advance(ms) {
        const until = clock + ms;
        for (;;) {
            const pending = [...timers.entries()].filter(([, timer]) => timer.due <= until).sort((a, b) => a[1].due - b[1].due)[0];
            if (!pending) break;
            const [id, timer] = pending;
            clock = timer.due;
            if (timer.repeat) timer.due += timer.delay;
            else timers.delete(id);
            timer.fn();
        }
        clock = until;
    }
    const messages = [];
    const listeners = {};
    let config;
    const api = {
        state: -1, time: 0, duration: 120, seeks: [],
        getPlayerState() { return this.state; },
        getCurrentTime() { return this.time; },
        getDuration() { return this.duration; },
        playVideo() { this.setState(1); },
        pauseVideo() { this.setState(2); },
        seekTo(time) { this.seeks.push(time); this.time = time; },
        setState(state) { this.state = state; config.events.onStateChange({ data: state }); },
    };
    const window = {
        parent: { postMessage(data) { messages.push(JSON.parse(data)); } },
        addEventListener(event, listener) { listeners[event] = listener; },
        YT: { Player: function (id, options) { config = options; return api; } },
    };
    vm.runInNewContext(source, {
        window,
        document: { getElementById: id => elements[id], body: { classList: { add: value => classes.add(value), remove: value => classes.delete(value) } } },
        setTimeout: (fn, delay) => schedule(fn, delay), clearTimeout: id => timers.delete(id),
        setInterval: (fn, delay) => schedule(fn, delay, true), clearInterval: id => timers.delete(id),
    });
    window.onYouTubeIframeAPIReady();
    return {
        elements, api, config, messages, timers, advance,
        ready: () => config.events.onReady(),
        visible: () => !elements.controls.hidden && classes.has('controls-visible'),
        command: (cmd, value) => listeners.message({ source: window.parent, data: JSON.stringify({ kidstv: 'cmd', cmd, value }) }),
        message: event => listeners.message(event),
        event: name => listeners[name](),
        close: () => listeners.pagehide(),
    };
}

test('automatic controls reappear with remote commands and stay visible while paused', () => {
    const page = playerPage();
    page.command('toggle'); // Ignore input until the API is ready.
    assert.equal(page.api.state, -1);
    page.ready();
    assert.equal(page.visible(), true);
    page.advance(3999);
    assert.equal(page.visible(), true);
    page.advance(1);
    assert.equal(page.visible(), false);
    page.command('showControls');
    assert.equal(page.visible(), true);
    page.advance(4000);
    assert.equal(page.visible(), false);
    page.command('toggle');
    assert.equal(page.api.state, 2);
    page.advance(10000);
    assert.equal(page.visible(), true);
    assert.equal(page.elements.toggle.textContent, 'Reproducir');
    page.command('play');
    assert.equal(page.elements.toggle.textContent, 'Pausar');
    page.advance(4000);
    assert.equal(page.visible(), false);
});

test('always-visible mode stays visible and follows playback progress', () => {
    const page = playerPage('always');
    page.ready();
    page.api.time = 30;
    page.advance(30000);
    assert.equal(page.visible(), true);
    assert.equal(page.elements.current.textContent, '0:30');
    assert.equal(page.elements.duration.textContent, '2:00');
    assert.equal(page.elements.progress.value, 250);
    assert.equal(page.config.playerVars.controls, 1);
    page.api.duration = 7200;
    page.api.time = 3661;
    page.advance(500);
    assert.equal(page.elements.current.textContent, '1:01:01');
});

test('hidden mode disables native controls but still accepts the remote', () => {
    const page = playerPage('hidden');
    page.ready();
    page.command('pause');
    page.command('seek', 10);
    page.command('showControls');
    page.advance(10000);
    assert.equal(page.visible(), false);
    assert.equal(page.config.playerVars.controls, 0);
    assert.equal(page.api.state, 2);
    assert.equal(page.api.time, 10);
    assert.equal(page.timers.size, 0);
});

test('remote and pointer seeking stay within the video, with no seeking before metadata', () => {
    const page = playerPage();
    page.api.duration = 0;
    page.ready();
    page.command('seek', 10);
    assert.equal(page.api.seeks.length, 0);
    assert.equal(page.elements.progress.disabled, true);
    assert.equal(page.elements.duration.textContent, '--:--');
    page.api.duration = 120;
    page.advance(500);
    assert.equal(page.elements.progress.disabled, false);
    page.api.time = 5;
    page.command('seek', -30);
    assert.equal(page.api.time, 0);
    page.api.time = 115;
    page.command('seek', 30);
    assert.equal(page.api.time, 120);
    page.elements.rewind.listeners.click();
    assert.equal(page.api.time, 110);
    page.elements.forward.listeners.click();
    assert.equal(page.api.time, 120);
    page.elements.progress.value = 250;
    page.elements.progress.listeners.input();
    assert.equal(page.api.time, 30);
    page.elements.toggle.listeners.click();
    assert.equal(page.api.state, 2);
    page.command('seek', Infinity);
    assert.equal(page.api.time, 30);
});

test('unrelated messages are ignored and the bridge still reports ready, ended and errors', () => {
    const page = playerPage();
    page.ready();
    page.message({ source: {}, data: { kidstv: 'cmd', cmd: 'pause' } });
    page.message({ source: {}, data: 'invalid json' });
    assert.equal(page.api.state, 1);
    page.api.setState(0);
    page.config.events.onError({ data: 150 });
    assert.ok(page.messages.some(message => message.event === 'ready'));
    assert.ok(page.messages.some(message => message.event === 'state' && message.value === 0));
    assert.ok(page.messages.some(message => message.event === 'error' && message.value === 150));
});

test('dragging the progress bar is not interrupted by the timer or playback updates', () => {
    const page = playerPage();
    page.ready();
    page.elements.progress.listeners.mousedown();
    page.elements.progress.value = 600;
    page.api.time = 20;
    page.advance(10000);
    assert.equal(page.visible(), true);
    assert.equal(page.elements.progress.value, 600);
    page.elements.progress.listeners.input();
    assert.equal(page.api.time, 72);
    page.event('mouseup');
    page.advance(4000);
    assert.equal(page.visible(), false);
});

test('closing the bridge cancels updates and ignores subsequent commands', () => {
    const page = playerPage();
    page.ready();
    assert.ok(page.timers.size > 0);
    page.close();
    assert.equal(page.timers.size, 0);
    page.command('seek', 10);
    assert.equal(page.api.time, 0);
});
