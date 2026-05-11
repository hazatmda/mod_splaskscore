(function () {
  'use strict';

  const SELECTOR = '[data-splask-widget]';
  const CIRCLE_LENGTH = 377;


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

  function formatTo12Hour(dateStr) {
    if (!dateStr || !dateStr.includes('/')) {
      return '---';
    }

    const [day, month, rest] = dateStr.split('/');
    if (!rest || !rest.includes(' ')) {
      return dateStr;
    }

    const [year, time] = rest.split(' ');
    const [hour, minute] = time.split(':');
    const parsedHour = parseInt(hour, 10);

    if (Number.isNaN(parsedHour)) {
      return dateStr;
    }

    const ampm = parsedHour >= 12 ? 'PM' : 'AM';
    const hour12 = parsedHour % 12 === 0 ? 12 : parsedHour % 12;

    return `${hour12}:${minute} ${ampm} ${day}/${month}/${year}`;
  }

  function setText(root, name, value) {
    const element = root.querySelector(`[data-splask-${name}]`);
    if (element) {
      element.textContent = value;
    }
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

  function updateSuccess(root, data, rules) {
    const score = Number(data.final_score) || 0;
    const grade = resolveGrade(score, rules);
    const nextCheck = new Date();
    const link = root.querySelector('[data-splask-link]');

    nextCheck.setDate(nextCheck.getDate() + 1);
    applyGradeStyles(root, grade, score);

    setText(root, 'score', `${score}%`);
    setText(root, 'grade', grade.label.toUpperCase());
    setText(root, 'grade-short', grade.shortLabel);
    setText(root, 'status', grade.status);
    setText(root, 'date', formatTo12Hour(data.last_check));
    setText(root, 'next', nextCheck.toLocaleDateString('en-GB'));

    if (link && data.verification_url) {
      link.href = data.verification_url;
      link.removeAttribute('aria-disabled');
    }
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
