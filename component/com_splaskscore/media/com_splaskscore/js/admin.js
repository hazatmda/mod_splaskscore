(function () {
  'use strict';

  function replaceTokens(template, values) {
    return Object.keys(values).reduce(function (result, key) {
      return result.replaceAll('{' + key + '}', String(values[key]));
    }, template);
  }

  function initialiseTokenControl() {
    var control = document.querySelector('[data-splask-token-control]');
    if (!control) {
      return;
    }

    var input = control.querySelector('[data-splask-token-input]');
    var revealedInput = control.querySelector('[data-splask-token-revealed]');
    var button = control.querySelector('[data-splask-token-toggle]');
    var icon = control.querySelector('[data-splask-token-icon]');
    var label = control.querySelector('[data-splask-token-label]');
    var error = document.querySelector('[data-splask-token-error]');
    if (!input || !revealedInput || !button) {
      return;
    }

    if (control.dataset.splaskTokenInitialized === 'true') {
      return;
    }
    control.dataset.splaskTokenInitialized = 'true';

    var configured = control.dataset.configured === 'true';
    var loadedFromServer = control.dataset.serverRevealed === 'true' && revealedInput.value !== '';

    if (configured) {
      input.placeholder = control.dataset.placeholder || '••••••••••••••••';
    }

    var setButtonState = function (showing, loading) {
      var text = loading
        ? (control.dataset.loadingLabel || '')
        : (showing ? control.dataset.hideLabel : control.dataset.showLabel);
      button.disabled = Boolean(loading);
      button.setAttribute('aria-pressed', showing ? 'true' : 'false');
      button.setAttribute('title', text || '');
      if (label) {
        label.textContent = text || '';
      }
      if (icon) {
        icon.classList.toggle('icon-eye', !showing);
        icon.classList.toggle('icon-eye-slash', showing);
      }
    };

    var showError = function (message) {
      if (!error) {
        return;
      }

      error.textContent = typeof message === 'string'
        ? message
        : (control.dataset.errorLabel || '');
      error.classList.toggle('d-none', !error.textContent);
    };

    button.addEventListener('click', async function (event) {
      event.preventDefault();
      showError('');

      if (loadedFromServer) {
        revealedInput.value = '';
        revealedInput.classList.add('d-none');
        input.classList.remove('d-none');
        input.type = 'password';
        loadedFromServer = false;
        setButtonState(false, false);
        return;
      }

      if (input.type === 'text') {
        input.type = 'password';
        setButtonState(false, false);
        return;
      }

      if (input.value !== '' || !configured) {
        input.type = 'text';
        setButtonState(true, false);
        return;
      }

      setButtonState(false, true);

      try {
        var body = new URLSearchParams();
        body.set('module_id', control.dataset.moduleId || '0');
        body.set(control.dataset.csrfToken || '', '1');
        var response = await window.fetch(control.dataset.revealUrl || '', {
          method: 'POST',
          credentials: 'same-origin',
          headers: {
            'Accept': 'application/json',
            'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
            'X-Requested-With': 'XMLHttpRequest'
          },
          body: body.toString()
        });
        var responseText = (await response.text()).replace(/^\uFEFF/, '');
        var payload;

        try {
          payload = JSON.parse(responseText);
        } catch (parseError) {
          throw new Error(control.dataset.errorLabel || 'Token response was not valid JSON');
        }

        var token = payload && payload.data ? String(payload.data.token || '') : '';
        if (!response.ok || !payload || payload.success === false || token === '') {
          throw new Error((payload && payload.message) || control.dataset.errorLabel || 'Token unavailable');
        }

        input.value = '';
        input.type = 'password';
        input.classList.add('d-none');
        revealedInput.value = token;
        revealedInput.classList.remove('d-none');
        loadedFromServer = true;
        showError('');
        setButtonState(true, false);
      } catch (exception) {
        input.value = '';
        input.type = 'password';
        input.classList.remove('d-none');
        revealedInput.value = '';
        revealedInput.classList.add('d-none');
        loadedFromServer = false;
        setButtonState(false, false);
        showError(exception && exception.message ? exception.message : control.dataset.errorLabel);
      }
    });

    var form = input.closest('form');
    if (form) {
      form.addEventListener('submit', function () {
        revealedInput.value = '';
        revealedInput.classList.add('d-none');
        input.classList.remove('d-none');
      });
    }

    setButtonState(loadedFromServer, false);
  }

  function initialiseCronCopy() {
    var button = document.querySelector('[data-splask-cron-copy]');
    if (!button) {
      return;
    }

    button.addEventListener('click', async function () {
      var input = document.getElementById(button.dataset.target || '');
      if (!input) {
        return;
      }

      try {
        if (navigator.clipboard && navigator.clipboard.writeText) {
          await navigator.clipboard.writeText(input.value);
        } else {
          input.focus();
          input.select();
          document.execCommand('copy');
        }

        var label = button.querySelector('[data-splask-copy-label]');
        var copied = button.dataset.copiedLabel || '';
        button.setAttribute('title', copied);
        if (label) {
          label.textContent = copied;
        }
        window.setTimeout(function () {
          var original = button.dataset.copyLabel || '';
          button.setAttribute('title', original);
          if (label) {
            label.textContent = original;
          }
        }, 2000);
      } catch (exception) {
        input.focus();
        input.select();
      }
    });
  }

  function initialiseCooldownPreview() {
    var input = document.getElementById('jform_analytics_duplicate_cooldown');
    var preview = document.querySelector('[data-splask-cooldown-preview]');
    if (!input || !preview) {
      return;
    }

    var render = function () {
      var minutes = Math.max(1, Number.parseInt(input.value, 10) || 1);
      var hours = Math.floor(minutes / 60);
      var remaining = minutes % 60;
      var template = hours > 0
        ? (preview.dataset.template || '{minutes} min = {hours} h {remaining} min')
        : (preview.dataset.minutesTemplate || '{minutes} min');
      preview.textContent = replaceTokens(template, {
        minutes: minutes,
        hours: hours,
        remaining: remaining
      });
    };

    input.addEventListener('input', render);
    render();
  }

  function initialiseCountdowns() {
    var countdowns = Array.from(document.querySelectorAll('[data-splask-countdown]'));
    if (!countdowns.length) {
      return;
    }

    var render = function () {
      var now = Date.now();

      countdowns.forEach(function (node) {
        var target = Date.parse(node.dataset.splaskCountdown || '');
        if (!Number.isFinite(target)) {
          node.textContent = '';
          return;
        }

        var seconds = Math.max(0, Math.floor((target - now) / 1000));
        if (seconds === 0) {
          node.textContent = node.dataset.completeLabel || '';
          return;
        }

        var days = Math.floor(seconds / 86400);
        var hours = Math.floor((seconds % 86400) / 3600);
        var minutes = Math.floor((seconds % 3600) / 60);
        var remainingSeconds = seconds % 60;
        node.textContent = replaceTokens(node.dataset.template || '{days}d {hours}h {minutes}m {seconds}s', {
          days: days,
          hours: hours,
          minutes: minutes,
          seconds: remainingSeconds
        });
      });
    };

    render();
    window.setInterval(render, 1000);
  }

  function initialiseAdministratorUi() {
    initialiseTokenControl();
    initialiseCooldownPreview();
    initialiseCountdowns();
    initialiseCronCopy();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initialiseAdministratorUi, { once: true });
  } else {
    initialiseAdministratorUi();
  }
}());
