(function () {
  'use strict';

  const SELECTOR = '[data-splask-widget]';
  const CIRCLE_LENGTH = 377;
  const MALAY_WEEKDAYS = ['Ahad', 'Isnin', 'Selasa', 'Rabu', 'Khamis', 'Jumaat', 'Sabtu'];
  const MALAY_MONTHS = ['Januari', 'Februari', 'Mac', 'April', 'Mei', 'Jun', 'Julai', 'Ogos', 'September', 'Oktober', 'November', 'Disember'];


  function hasDarkAdminSignal(element) {
    if (!element) {
      return false;
    }

    const values = [
      element.dataset ? element.dataset.bsTheme : '',
      element.dataset ? element.dataset.colorScheme : '',
      element.dataset ? element.dataset.theme : '',
      element.getAttribute('data-bs-theme'),
      element.getAttribute('data-color-scheme'),
      element.getAttribute('data-theme'),
      element.className
    ].filter(Boolean).join(' ').toLowerCase();

    return /(^|[\s_-])(dark|dark-mode|atum-dark)([\s_-]|$)/.test(values);
  }

  function resolveAppearance(root) {
    const mode = root.dataset.splaskAppearanceMode || 'light';

    if (mode === 'light' || mode === 'dark') {
      return mode;
    }

    if (hasDarkAdminSignal(document.documentElement) || hasDarkAdminSignal(document.body)) {
      return 'dark';
    }

    if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
      return 'dark';
    }

    return 'light';
  }

  function applyAppearance(root) {
    root.dataset.splaskAppearance = resolveAppearance(root);
  }

  function resolveGrade(score, rules) {
    const numericScore = Number(score);
    return rules.find((rule) => numericScore >= Number(rule.min) && numericScore <= Number(rule.max)) || rules[rules.length - 1];
  }

  function formatScore(score) {
    const numericScore = Number(score);
    if (!Number.isFinite(numericScore)) {
      return '0%';
    }

    return `${numericScore.toFixed(2).replace(/\.?0+$/, '')}%`;
  }

  function parseSplaskDate(dateStr) {
    if (!dateStr) {
      return null;
    }

    const value = String(dateStr).trim();
    const slashMatch = value.match(/^(\d{1,2})\/(\d{1,2})\/(\d{4})(?:\s+(\d{1,2}):(\d{2})(?::(\d{2}))?)?$/);
    if (slashMatch) {
      const [, day, month, year, hour = '0', minute = '0', second = '0'] = slashMatch;
      const parsed = new Date(Date.UTC(Number(year), Number(month) - 1, Number(day), Number(hour), Number(minute), Number(second)));
      return Number.isNaN(parsed.getTime()) ? null : parsed;
    }

    const sqlMatch = value.match(/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})(?::(\d{2}))?$/);
    if (sqlMatch) {
      const [, year, month, day, hour, minute, second = '0'] = sqlMatch;
      const parsed = new Date(Date.UTC(Number(year), Number(month) - 1, Number(day), Number(hour), Number(minute), Number(second)));
      return Number.isNaN(parsed.getTime()) ? null : parsed;
    }

    const parsed = new Date(value);
    return Number.isNaN(parsed.getTime()) ? null : parsed;
  }

  function formatMalayDate(dateStr) {
    const parsed = parseSplaskDate(dateStr);

    if (!parsed) {
      return dateStr || 'Tiada';
    }

    const weekday = MALAY_WEEKDAYS[parsed.getUTCDay()];
    const month = MALAY_MONTHS[parsed.getUTCMonth()];
    const hour = parsed.getUTCHours();
    const ampm = hour >= 12 ? 'PM' : 'AM';
    const hour12 = hour % 12 === 0 ? 12 : hour % 12;
    const minute = String(parsed.getUTCMinutes()).padStart(2, '0');

    return `${weekday} • ${parsed.getUTCDate()} ${month} ${parsed.getUTCFullYear()} • ${hour12}:${minute} ${ampm}`;
  }

  function setText(root, name, value) {
    const element = root.querySelector(`[data-splask-${name}]`);
    if (element) {
      element.textContent = value;
    }
  }

  function applyHealth(root, health) {
    const data = health || {};
    const lastSuccess = data.last_success || root.dataset.splaskLastSuccess || '';
    const missingToday = String(data.missing_today !== undefined ? data.missing_today : root.dataset.splaskMissingToday) === 'true';

    root.dataset.splaskLastSuccess = lastSuccess;
    root.dataset.splaskMissingToday = missingToday ? 'true' : 'false';
  }

  function applyGradeStyles(root, grade, score) {
    root.dataset.splaskGrade = grade.key;
    root.style.setProperty('--splask-grade-color', grade.color);
    root.style.setProperty('--splask-grade-accent', grade.accent);
    root.style.setProperty('--splask-grade-surface', grade.surface);
    root.style.setProperty('--splask-grade-text', grade.text);
    root.style.setProperty('--splask-score-percent', `${score}%`);

    const circle = root.querySelector('[data-splask-progress-circle]');
    if (circle) {
      circle.style.stroke = grade.color;
      circle.style.strokeDashoffset = CIRCLE_LENGTH - (CIRCLE_LENGTH * score / 100);
    }

    const bar = root.querySelector('[data-splask-progress-bar]');
    if (bar) {
      bar.style.width = `${score}%`;
    }
  }

  function buildAjaxParams(root, task, values) {
    const params = new URLSearchParams(values || {});
    params.set('method', task);
    params.set('module_id', root.dataset.splaskModuleId || '0');
    params.set('token', root.dataset.splaskToken || '');
    params.set(root.dataset.splaskCsrfToken || '', '1');

    return params;
  }

  function postModuleAjax(root, task, values) {
    return fetch(root.dataset.splaskAjaxUrl || 'index.php?option=com_ajax&module=splaskscore&format=json', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
      },
      body: buildAjaxParams(root, task, values).toString()
    }).then((response) => response.json());
  }

  function unwrapAjaxResponse(response) {
    if (response && Array.isArray(response.data)) {
      return response.data[0] || {};
    }

    return response && response.data ? response.data : response;
  }

  function saveHistory(root, data, grade, score) {
    if (!root.dataset.splaskAjaxUrl || root.dataset.splaskHistorySaved === 'true') {
      return;
    }

    root.dataset.splaskHistorySaved = 'true';

    postModuleAjax(root, 'saveHistory', {
      score: score,
      grade_key: grade.key,
      grade_label: grade.label,
      status_label: grade.status,
      verification_url: data.verification_url || '',
      source_checked_at: data.last_check || ''
    })
      .then(unwrapAjaxResponse)
      .then((response) => {
        if (response && response.health) {
          applyHealth(root, response.health);
        }
      })
      .catch(() => {
        root.dataset.splaskHistorySaved = 'false';
      });
  }

  function setHistoryLoading(root, message) {
    const body = root.querySelector('[data-splask-history-body]');
    if (body) {
      body.innerHTML = `<div class="splask-history-loading">${message}</div>`;
    }
  }

  function loadHistory(root) {
    const body = root.querySelector('[data-splask-history-body]');
    if (!body || body.dataset.splaskLoaded === 'true') {
      return;
    }

    setHistoryLoading(root, 'Memuatkan sejarah...');

    postModuleAjax(root, 'history', {
      appearance: root.dataset.splaskAppearance || resolveAppearance(root)
    })
      .then(unwrapAjaxResponse)
      .then((data) => {
        if (data && data.success && data.html) {
          body.innerHTML = data.html;
          body.dataset.splaskLoaded = 'true';
          applyHealth(root, data.health);
          return;
        }

        setHistoryLoading(root, (data && data.message) || 'Sejarah tidak dapat dimuatkan.');
      })
      .catch(() => {
        setHistoryLoading(root, 'Ralat sambungan semasa memuatkan sejarah.');
      });
  }

  function setRefreshState(root, loading, message) {
    const label = message || (loading ? 'Refreshing...' : 'Refresh Analytics');
    root.querySelectorAll('[data-splask-refresh-trigger]').forEach((trigger) => {
      trigger.disabled = loading;
      trigger.classList.toggle('is-loading', loading);
      trigger.setAttribute('aria-label', label);
      trigger.setAttribute('title', label);
    });
  }

  function refreshAnalytics(root) {
    if (!root.dataset.splaskAjaxUrl) {
      return;
    }

    const body = root.querySelector('[data-splask-history-body]');
    setRefreshState(root, true, 'Refreshing...');

    postModuleAjax(root, 'refreshAnalytics', {
      appearance: root.dataset.splaskAppearance || resolveAppearance(root)
    })
      .then(unwrapAjaxResponse)
      .then((data) => {
        if (data && data.health) {
          applyHealth(root, data.health);
        }

        if (data && data.success && data.payload) {
          const payload = data.payload;
          updateSuccess(root, {
            status: true,
            final_score: payload.final_score,
            verification_url: payload.verification_url,
            last_check: payload.last_check
          }, JSON.parse(root.dataset.splaskGradeRules || '[]'));
        }

        if (body && data && data.html) {
          body.innerHTML = data.html;
          body.dataset.splaskLoaded = 'true';
        }

        setRefreshState(root, false, (data && data.duplicate) ? 'Already Current' : 'Refresh Analytics');
        window.setTimeout(() => setRefreshState(root, false), 1800);
      })
      .catch(() => {
        setRefreshState(root, false, 'Refresh Failed');
        window.setTimeout(() => setRefreshState(root, false), 1800);
      });
  }

  function bindHistoryModal(root) {
    const modal = root.querySelector('[data-splask-history-modal-shell]');
    const trigger = root.querySelector('[data-splask-history-trigger]');
    const refreshTrigger = root.querySelector('[data-splask-refresh-trigger]');

    if (modal) {
      modal.addEventListener('show.bs.modal', () => loadHistory(root));
    }

    if (trigger) {
      trigger.addEventListener('click', () => loadHistory(root));
    }

    if (refreshTrigger) {
      refreshTrigger.addEventListener('click', () => refreshAnalytics(root));
    }
  }

  function updateSuccess(root, data, rules) {
    const score = Number(data.final_score) || 0;
    const grade = resolveGrade(score, rules);
    const nextCheck = new Date();
    const link = root.querySelector('[data-splask-link]');

    nextCheck.setDate(nextCheck.getDate() + 1);
    applyGradeStyles(root, grade, score);

    setText(root, 'score', formatScore(score));
    setText(root, 'grade', grade.label.toUpperCase());
    setText(root, 'grade-short', grade.shortLabel);
    setText(root, 'status', grade.status);
    setText(root, 'date', formatMalayDate(data.last_check));
    setText(root, 'next', nextCheck.toLocaleDateString('en-GB'));

    if (link && data.verification_url) {
      link.href = data.verification_url;
      link.removeAttribute('aria-disabled');
    }

    saveHistory(root, data, grade, score);
  }

  function updateError(root, message) {
    root.dataset.splaskState = 'error';
    setText(root, 'grade', message);
    setText(root, 'status', 'Sila semak token atau sambungan API.');
  }

  function initWidget(root) {
    if (root.dataset.splaskInitialized === 'true') {
      return;
    }

    root.dataset.splaskInitialized = 'true';
    applyAppearance(root);
    applyHealth(root);
    bindHistoryModal(root);

    let rules = [];
    try {
      rules = JSON.parse(root.dataset.splaskGradeRules || '[]');
    } catch (error) {
      rules = [];
    }

    if (!rules.length) {
      updateError(root, 'RALAT KONFIGURASI GRED');
      return;
    }

    fetch('https://splask-api.jdn.gov.my/api/get_my_score', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({ _token: root.dataset.splaskToken || '' })
    })
      .then((response) => response.json())
      .then((data) => {
        if (data.status) {
          updateSuccess(root, data, rules);
          return;
        }

        updateError(root, 'MARKAH TIDAK DIJUMPAI');
      })
      .catch(() => {
        updateError(root, 'RALAT SAMBUNGAN API');
      });
  }

  function initAll() {
    document.querySelectorAll(SELECTOR).forEach(initWidget);
  }

  if (window.matchMedia) {
    const colorSchemeQuery = window.matchMedia('(prefers-color-scheme: dark)');
    const refreshAutoAppearance = () => {
      document.querySelectorAll(`${SELECTOR}[data-splask-appearance-mode="auto"]`).forEach(applyAppearance);
    };

    if (colorSchemeQuery.addEventListener) {
      colorSchemeQuery.addEventListener('change', refreshAutoAppearance);
    } else if (colorSchemeQuery.addListener) {
      colorSchemeQuery.addListener(refreshAutoAppearance);
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initAll);
  } else {
    initAll();
  }
}());
