const { test } = require('node:test');
const assert = require('node:assert/strict');
const { readFileSync } = require('node:fs');
const { join } = require('node:path');
const vm = require('node:vm');

const script = readFileSync(join(__dirname, '../../public/js/login-location.js'), 'utf8');

function login() {
    const input = Object.fromEntries(['audit_location_opt_in', 'audit_latitude', 'audit_longitude', 'audit_accuracy_km', 'password'].map(name => [name, { value: name === 'audit_location_opt_in' ? '0' : '' }]));
    const button = () => ({ addEventListener(_, callback) { this.click = callback; } });
    const add = button(), remove = button(), status = {};
    const selectors = { '[data-location-add]': add, '[data-location-remove]': remove, '[data-location-status]': status };
    const requests = [];
    const window = {};
    vm.runInNewContext(script, {
        window,
        document: { querySelectorAll: () => [{ querySelector: selector => selectors[selector] || input[selector.slice(7, -2)] }] },
        navigator: { geolocation: { getCurrentPosition: (...args) => requests.push(args) } },
    });
    const payload = () => JSON.parse(JSON.stringify(window.ngfLoginLocationFields({ elements: { namedItem: name => input[name] } })));
    return { add, remove, status, requests, input, payload };
}

test('explicit location sharing rounds data before sending and removal clears the login payload', () => {
    const view = login();
    assert.equal(view.requests.length, 0);
    assert.deepEqual(view.payload(), {});
    view.input.password.value = 'not part of the location payload';
    view.add.click();
    const [granted, , options] = view.requests[0];
    assert.equal(options.enableHighAccuracy, false);
    granted({ coords: { latitude: 12.345678, longitude: 121.987654, accuracy: 33 } });
    assert.deepEqual(view.payload(), { audit_location_opt_in: '1', audit_latitude: '12.3', audit_longitude: '122.0', audit_accuracy_km: '10' });
    view.remove.click();
    granted({ coords: { latitude: 14.1234, longitude: 121.2345, accuracy: 10 } });
    assert.deepEqual(view.payload(), {});
});

test('denying permission preserves sign-in without sharing location', () => {
    const view = login();
    view.add.click();
    view.requests[0][1]();
    assert.deepEqual(view.payload(), {});
    assert.match(view.status.textContent, /sign in normally/);
    assert.equal(view.add.disabled, false);
});
