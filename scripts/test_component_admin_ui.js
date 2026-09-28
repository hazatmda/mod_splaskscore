'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const scriptPath = path.join(
  __dirname,
  '..',
  'component',
  'com_splaskscore',
  'media',
  'com_splaskscore',
  'js',
  'admin.js'
);
const source = fs.readFileSync(scriptPath, 'utf8');
const marker = "  document.addEventListener('DOMContentLoaded'";

assert.ok(source.includes(marker), 'Unable to expose component UI helpers for testing');

const instrumented = source.replace(
  marker,
  '  window.__splaskAdminTest = { initialiseTokenControl, initialiseCooldownPreview, initialiseCountdowns, initialiseCronCopy };\n\n' + marker
);

const listeners = {};
let tokenClickListenerCount = 0;
const timeoutCallbacks = [];
const intervalCallbacks = [];
const copiedValues = [];
function attachClassList(target, initial = []) {
  target.classes = new Set(initial);
  target.classList = {
    add(name) {
      target.classes.add(name);
    },
    remove(name) {
      target.classes.delete(name);
    },
    toggle(name, enabled) {
      if (enabled) {
        target.classes.add(name);
      } else {
        target.classes.delete(name);
      }
    }
  };
  return target;
}
const tokenForm = {
  addEventListener(name, callback) {
    listeners['form.' + name] = callback;
  }
};
const tokenInput = attachClassList({
  type: 'password',
  value: '',
  placeholder: '',
  closest(selector) {
    return selector === 'form' ? tokenForm : null;
  }
});
const revealedTokenInput = attachClassList({
  type: 'text',
  value: ''
}, ['d-none']);
const tokenIcon = {
  classes: new Set(['icon-eye']),
  classList: {
    toggle(name, enabled) {
      if (enabled) {
        tokenIcon.classes.add(name);
      } else {
        tokenIcon.classes.delete(name);
      }
    }
  }
};
const tokenLabel = { textContent: '' };
const tokenError = {
  textContent: '',
  hidden: true,
  classList: {
    toggle(name, enabled) {
      if (name === 'd-none') {
        tokenError.hidden = enabled;
      }
    }
  }
};
const tokenButton = {
  disabled: false,
  attributes: {},
  addEventListener(name, callback) {
    if (name === 'click') {
      tokenClickListenerCount += 1;
    }
    listeners['token.' + name] = callback;
  },
  setAttribute(name, value) {
    this.attributes[name] = String(value);
  }
};
const tokenControl = {
  dataset: {
    configured: 'true',
    placeholder: '****************',
    showLabel: 'Show',
    hideLabel: 'Hide',
    loadingLabel: 'Loading',
    errorLabel: 'Unable to reveal token',
    revealUrl: 'index.php?option=com_splaskscore&task=settings.revealToken&format=json',
    moduleId: '258',
    csrfToken: 'csrf_test'
  },
  querySelector(selector) {
    if (selector === '[data-splask-token-input]') {
      return tokenInput;
    }
    if (selector === '[data-splask-token-revealed]') {
      return revealedTokenInput;
    }
    if (selector === '[data-splask-token-toggle]') {
      return tokenButton;
    }
    if (selector === '[data-splask-token-icon]') {
      return tokenIcon;
    }
    if (selector === '[data-splask-token-label]') {
      return tokenLabel;
    }
    return null;
  }
};
const cooldownInput = {
  value: '500',
  addEventListener(name, callback) {
    listeners['cooldown.' + name] = callback;
  }
};
const cooldownPreview = {
  dataset: {
    template: '{minutes} minutes = {hours} hour(s) {remaining} minutes',
    minutesTemplate: '{minutes} minutes'
  },
  textContent: ''
};
const cronInput = {
  value: '*/5 * * * * cd /var/www/joomla && /usr/bin/php cli/joomla.php scheduler:run',
  focus() {},
  select() {}
};
const cronLabel = { textContent: 'Copy' };
const cronButton = {
  dataset: {
    target: 'splaskscore-cron-command',
    copyLabel: 'Copy',
    copiedLabel: 'Copied'
  },
  attributes: {},
  addEventListener(name, callback) {
    listeners['cron.' + name] = callback;
  },
  setAttribute(name, value) {
    this.attributes[name] = String(value);
  },
  querySelector(selector) {
    return selector === '[data-splask-copy-label]' ? cronLabel : null;
  }
};
const documentObject = {
  readyState: 'loading',
  addEventListener() {},
  execCommand() {
    return true;
  },
  getElementById(id) {
    if (id === 'jform_analytics_duplicate_cooldown') {
      return cooldownInput;
    }
    if (id === 'splaskscore-cron-command') {
      return cronInput;
    }
    return null;
  },
  querySelector(selector) {
    if (selector === '[data-splask-token-control]') {
      return tokenControl;
    }
    if (selector === '[data-splask-token-error]') {
      return tokenError;
    }
    if (selector === '[data-splask-cooldown-preview]') {
      return cooldownPreview;
    }
    if (selector === '[data-splask-cron-copy]') {
      return cronButton;
    }
    return null;
  },
  querySelectorAll() {
    return [];
  }
};
const windowObject = {
  async fetch(url, options) {
    assert.match(url, /settings\.revealToken/);
    assert.match(options.body, /module_id=258/);
    assert.match(options.body, /csrf_test=1/);
    return {
      ok: true,
      async text() {
        return JSON.stringify({ success: true, data: { token: 'server-secret-token' } });
      }
    };
  },
  setInterval(callback) {
    intervalCallbacks.push(callback);
    return intervalCallbacks.length;
  },
  setTimeout(callback) {
    timeoutCallbacks.push(callback);
    return timeoutCallbacks.length;
  }
};
const navigatorObject = {
  clipboard: {
    async writeText(value) {
      copiedValues.push(value);
    }
  }
};

vm.runInNewContext(instrumented, {
  document: documentObject,
  window: windowObject,
  navigator: navigatorObject,
  URLSearchParams,
  Number,
  Object,
  String,
  Array,
  Date
}, { filename: scriptPath });

async function run() {
  const helpers = windowObject.__splaskAdminTest;
  const clickEvent = {
    prevented: false,
    preventDefault() {
      this.prevented = true;
    }
  };

  helpers.initialiseTokenControl();
  helpers.initialiseTokenControl();
  assert.equal(tokenClickListenerCount, 1, 'Token control must not attach duplicate click handlers');
  assert.equal(tokenInput.placeholder, '****************');
  assert.equal(tokenInput.value, '', 'Stored token must not be preloaded into the page');

  await listeners['token.click'](clickEvent);
  assert.equal(clickEvent.prevented, true, 'JavaScript reveal must override the form fallback');
  assert.equal(tokenInput.type, 'password');
  assert.equal(tokenInput.classes.has('d-none'), true);
  assert.equal(revealedTokenInput.classes.has('d-none'), false);
  assert.equal(revealedTokenInput.value, 'server-secret-token');
  assert.equal(tokenLabel.textContent, 'Hide');
  assert.equal(tokenButton.attributes['aria-pressed'], 'true');
  assert.equal(tokenIcon.classes.has('icon-eye-slash'), true);
  assert.equal(tokenError.textContent, '', 'A successful reveal must clear the error message');
  assert.equal(tokenError.hidden, true, 'A successful reveal must keep the error message hidden');

  await listeners['token.click'](clickEvent);
  assert.equal(tokenInput.type, 'password');
  assert.equal(tokenInput.classes.has('d-none'), false);
  assert.equal(revealedTokenInput.classes.has('d-none'), true);
  assert.equal(revealedTokenInput.value, '', 'Hiding a stored token must remove its readable value from the page');
  assert.equal(tokenLabel.textContent, 'Show');

  await listeners['token.click'](clickEvent);
  assert.equal(revealedTokenInput.value, 'server-secret-token');
  listeners['form.submit']();
  assert.equal(revealedTokenInput.value, '', 'Submitting an unchanged revealed token must remove its readable value');
  assert.equal(tokenInput.value, '', 'Submitting an unchanged revealed token must retain the server value');
  console.log('PASS: stored token is fetched only on demand and removed again when hidden or unchanged');

  helpers.initialiseCooldownPreview();
  assert.equal(cooldownPreview.textContent, '500 minutes = 8 hour(s) 20 minutes');
  cooldownInput.value = '60';
  listeners['cooldown.input']();
  assert.equal(cooldownPreview.textContent, '60 minutes = 1 hour(s) 0 minutes');
  cooldownInput.value = '10';
  listeners['cooldown.input']();
  assert.equal(cooldownPreview.textContent, '10 minutes');
  console.log('PASS: failure retry interval preview converts minutes to hours and minutes');

  helpers.initialiseCountdowns();
  assert.equal(intervalCallbacks.length, 0, 'No interval should start when the page has no countdown');
  console.log('PASS: countdown initialization is idle when no scheduler timestamp exists');

  helpers.initialiseCronCopy();
  await listeners['cron.click']();
  assert.deepEqual(copiedValues, [cronInput.value]);
  assert.equal(cronLabel.textContent, 'Copied');
  assert.equal(timeoutCallbacks.length, 1);
  timeoutCallbacks[0]();
  assert.equal(cronLabel.textContent, 'Copy');
  console.log('PASS: generated Joomla scheduler cron command can be copied');

  console.log('Component administrator UI regression checks passed');
}

run().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
