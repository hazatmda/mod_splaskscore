'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

class FakeElement {
  constructor(tagName) {
    this.tagName = tagName;
    this.children = [];
    this.className = '';
    this.attributes = {};
    this.listeners = {};
    this.removed = false;
    this.textContent = '';
    this.classList = {
      add: (...names) => {
        const classes = new Set(this.className.split(/\s+/).filter(Boolean));
        names.forEach((name) => classes.add(name));
        this.className = Array.from(classes).join(' ');
      }
    };
  }

  setAttribute(name, value) {
    this.attributes[name] = String(value);
  }

  appendChild(child) {
    this.children.push(child);
  }

  addEventListener(name, callback) {
    this.listeners[name] = callback;
  }

  remove() {
    this.removed = true;
  }
}

const scriptPath = path.join(__dirname, '..', 'media', 'js', 'splaskscore.js');
const source = fs.readFileSync(scriptPath, 'utf8');
const marker = '  if (window.matchMedia) {';

assert.ok(source.includes(marker), 'Unable to expose showNotification for regression testing');

const instrumented = source.replace(
  marker,
  '  window.__splaskNotificationTest = { showNotification };\n\n' + marker
);

const timers = [];
const windowObject = {
  setTimeout(callback, delay) {
    timers.push({ callback, delay });
    return timers.length;
  }
};
const context = {
  URLSearchParams,
  window: windowObject,
  document: {
    readyState: 'loading',
    addEventListener() {},
    createElement: (tagName) => new FakeElement(tagName)
  }
};

vm.runInNewContext(instrumented, context, { filename: scriptPath });

const modalContent = {
  children: [],
  querySelectorAll(selector) {
    return selector === '.splask-toast'
      ? this.children.filter((child) => child.className.includes('splask-toast') && !child.removed)
      : [];
  },
  prepend(child) {
    this.children.unshift(child);
  }
};
const root = {
  querySelector(selector) {
    return selector === '.modal-content' ? modalContent : null;
  }
};
const showNotification = windowObject.__splaskNotificationTest.showNotification;

showNotification(root, '<strong>Selamat</strong>', 'success');

const successToast = modalContent.children[0];
assert.equal(successToast.className, 'splask-toast splask-toast-success');
assert.equal(successToast.attributes.role, 'status');
assert.equal(successToast.attributes['aria-live'], 'polite');
assert.equal(successToast.children[0].textContent, '<strong>Selamat</strong>');
assert.equal(timers[0].delay, 5000);
console.log('PASS: success notification is prepended with text-only content');

showNotification(root, 'Pelayan tidak tersedia', 'error');

const errorToast = modalContent.children[0];
assert.equal(successToast.removed, true, 'A newer notification must replace the previous toast');
assert.equal(errorToast.className, 'splask-toast splask-toast-error');
assert.equal(errorToast.attributes.role, 'alert');
assert.equal(errorToast.attributes['aria-live'], 'assertive');
console.log('PASS: error notification replaces the previous toast accessibly');

timers[1].callback();
assert.ok(errorToast.className.includes('is-leaving'));
assert.equal(timers[2].delay, 400);
timers[2].callback();
assert.equal(errorToast.removed, true);
console.log('PASS: notification fades after five seconds and is removed');

console.log('Dashboard notification regression checks passed');
