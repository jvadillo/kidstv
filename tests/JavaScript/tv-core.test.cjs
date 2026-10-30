const assert = require('node:assert/strict');
const { readFileSync } = require('node:fs');
const { join } = require('node:path');
const { test } = require('node:test');
const vm = require('node:vm');

// core.js decides whether the TV app runs on a TV (remote control, animated rows) or on a
// phone, tablet or computer (native scrolling with finger, wheel or trackpad).
const source = readFileSync(join(__dirname, '../../public/tv/js/core.js'), 'utf8');

const ANDROID = 'Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0 Mobile Safari/537.36';
const IPHONE = 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Mobile/15E148 Safari/604.1';
const WEBOS = 'Mozilla/5.0 (Web0S; Linux/SmartTV) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/79.0 Safari/537.36 WebAppManager';
const TIZEN = 'Mozilla/5.0 (SMART-TV; LINUX; Tizen 6.0) AppleWebKit/537.36 (KHTML, like Gecko) 76.0 TV Safari/537.36';
const DESKTOP = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0 Safari/537.36';

function boot({ userAgent, platform = 'web', width = 1920, height = 1080, coarse = false }) {
    const listeners = {};
    const root = { className: '', style: {} };
    const window = {
        NOKOPLAY_CONFIG: { platform },
        innerWidth: width, innerHeight: height,
        matchMedia: (query) => ({ matches: query === '(pointer: coarse)' && coarse }),
        addEventListener(name, fn) { listeners['window:' + name] = fn; },
        localStorage: { getItem: () => null, setItem() {}, removeItem() {} },
    };
    const document = {
        documentElement: root,
        addEventListener(name, fn) { listeners['document:' + name] = fn; },
    };
    vm.runInNewContext(source, { window, document, navigator: { userAgent, maxTouchPoints: coarse ? 5 : 0, platform: '' } });
    return { K: window.NokoPlay, root, listeners };
}

test('TVs keep the remote-control interface and its 1080p scale', () => {
    for (const userAgent of [WEBOS, TIZEN]) {
        const { K, root } = boot({ userAgent });
        assert.equal(K.pointerUi, false);
        assert.equal(root.className, '');
        assert.equal(root.style.fontSize, '16px');
    }
    // The packaged apps never use it, whatever their browser says.
    assert.equal(boot({ userAgent: ANDROID, platform: 'webos' }).K.pointerUi, false);
});

test('phones, tablets and computers get native scrolling and readable sizes', () => {
    const phone = boot({ userAgent: ANDROID, width: 390, height: 844, coarse: true });
    assert.equal(phone.K.pointerUi, true);
    assert.match(phone.root.className, /pointer-ui/);
    assert.equal(phone.K.touching, true);
    assert.equal(phone.root.style.fontSize, '7.5px');

    const landscape = boot({ userAgent: IPHONE, width: 844, height: 390, coarse: true });
    assert.equal(landscape.root.style.fontSize, '7.5px');

    const tablet = boot({ userAgent: ANDROID, width: 820, height: 1180, coarse: true });
    assert.equal(tablet.root.style.fontSize, '10px');

    const desktop = boot({ userAgent: DESKTOP, width: 1920, height: 1080 });
    assert.equal(desktop.K.pointerUi, true);
    assert.equal(desktop.K.touching, false);
    assert.equal(desktop.root.style.fontSize, '16px');
});

test('touching hides the focus frame and the keyboard brings it back', () => {
    const { K, listeners } = boot({ userAgent: DESKTOP });
    const classes = new Set();
    const card = { parentNode: {}, classList: { add: (c) => classes.add(c), remove: (c) => classes.delete(c), contains: (c) => classes.has(c) } };
    K.nav.ensureVisible = () => {};

    K.nav.focus(card);
    assert.ok(classes.has('focused'));
    listeners['document:touchstart']();
    assert.equal(K.touching, true);
    assert.ok(!classes.has('focused'));
    assert.equal(K.recentTouch(), true);

    K.nav.focus(card);
    assert.ok(!classes.has('focused'));
    assert.equal(K.nav.showFocus(), true);
    assert.ok(classes.has('focused'));
    assert.equal(K.nav.showFocus(), false);
});
