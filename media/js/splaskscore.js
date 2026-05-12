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


  function initHistoryCharts(scope) {
    const container = scope || document;
    container.querySelectorAll('[data-splask-line-chart]').forEach((canvas) => {
      if (canvas.dataset.splaskChartInitialized === 'true') {
        drawHistoryChart(canvas);
        return;
      }

      canvas.dataset.splaskChartInitialized = 'true';
      canvas.addEventListener('mousemove', (event) => showChartTooltip(canvas, event));
      canvas.addEventListener('mouseleave', () => hideChartTooltip(canvas));

      if (window.ResizeObserver) {
        const observer = new ResizeObserver(() => drawHistoryChart(canvas));
        observer.observe(canvas.parentElement || canvas);
        canvas._splaskResizeObserver = observer;
      } else {
        window.addEventListener('resize', () => drawHistoryChart(canvas));
      }

      drawHistoryChart(canvas);
    });
  }

  function redrawHistoryCharts(scope) {
    initHistoryCharts(scope || document);
  }

  function chartPoints(canvas) {
    try {
      const points = JSON.parse(canvas.dataset.splaskChartPoints || '[]');
      return Array.isArray(points) ? points.filter((point) => Number.isFinite(Number(point.score))) : [];
    } catch (error) {
      return [];
    }
  }

  function drawHistoryChart(canvas) {
    const points = chartPoints(canvas);
    const context = canvas.getContext('2d');
    const wrapper = canvas.parentElement;
    const style = getComputedStyle(canvas);
    const color = getComputedStyle(canvas.closest('[data-splask-widget]') || document.documentElement).getPropertyValue('--splask-grade-color').trim() || '#2563eb';
    const muted = style.getPropertyValue('--splask-chart-muted').trim() || 'rgba(100, 116, 139, 0.72)';
    const grid = style.getPropertyValue('--splask-chart-grid').trim() || 'rgba(148, 163, 184, 0.22)';
    const width = Math.max(320, Math.floor((wrapper || canvas).clientWidth || canvas.clientWidth || 640));
    const height = Math.max(220, Math.floor((wrapper || canvas).clientHeight || canvas.clientHeight || 260));
    const ratio = window.devicePixelRatio || 1;
    const padding = { top: 22, right: 18, bottom: 48, left: 48 };

    if (!context || points.length < 2) {
      return;
    }

    canvas.width = Math.floor(width * ratio);
    canvas.height = Math.floor(height * ratio);
    canvas.style.width = `${width}px`;
    canvas.style.height = `${height}px`;
    context.setTransform(ratio, 0, 0, ratio, 0, 0);
    context.clearRect(0, 0, width, height);
    context.font = '12px system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif';
    context.lineCap = 'round';
    context.lineJoin = 'round';

    const plotWidth = width - padding.left - padding.right;
    const plotHeight = height - padding.top - padding.bottom;
    const toX = (index) => padding.left + (points.length === 1 ? 0 : (index / (points.length - 1)) * plotWidth);
    const toY = (score) => padding.top + ((100 - Math.max(0, Math.min(100, Number(score)))) / 100) * plotHeight;

    context.strokeStyle = grid;
    context.fillStyle = muted;
    context.lineWidth = 1;
    [0, 25, 50, 75, 100].forEach((tick) => {
      const y = toY(tick);
      context.beginPath();
      context.moveTo(padding.left, y);
      context.lineTo(width - padding.right, y);
      context.stroke();
      context.fillText(`${tick}%`, 8, y + 4);
    });

    const maxLabels = width < 520 ? 3 : Math.min(points.length, 6);
    const labelStep = Math.max(1, Math.ceil((points.length - 1) / Math.max(1, maxLabels - 1)));
    points.forEach((point, index) => {
      if (index !== 0 && index !== points.length - 1 && index % labelStep !== 0) {
        return;
      }

      const x = toX(index);
      const label = String(point.label || '');
      context.save();
      context.translate(x, height - 24);
      context.rotate(width < 520 ? -Math.PI / 8 : 0);
      context.textAlign = index === 0 ? 'left' : (index === points.length - 1 ? 'right' : 'center');
      context.fillText(label, 0, 0);
      context.restore();
    });

    const coordinates = points.map((point, index) => ({
      x: toX(index),
      y: toY(point.score),
      label: String(point.label || ''),
      score: Number(point.score)
    }));

    context.strokeStyle = color;
    context.lineWidth = 3.5;
    context.beginPath();
    coordinates.forEach((point, index) => {
      if (index === 0) {
        context.moveTo(point.x, point.y);
        return;
      }

      const previous = coordinates[index - 1];
      const midX = (previous.x + point.x) / 2;
      context.bezierCurveTo(midX, previous.y, midX, point.y, point.x, point.y);
    });
    context.stroke();

    context.fillStyle = color;
    coordinates.forEach((point) => {
      context.beginPath();
      context.arc(point.x, point.y, 4, 0, Math.PI * 2);
      context.fill();
    });

    canvas._splaskChartCoordinates = coordinates;
  }

  function showChartTooltip(canvas, event) {
    const coordinates = canvas._splaskChartCoordinates || [];
    const tooltip = canvas.parentElement ? canvas.parentElement.querySelector('[data-splask-chart-tooltip]') : null;

    if (!coordinates.length || !tooltip) {
      return;
    }

    const rect = canvas.getBoundingClientRect();
    const pointerX = event.clientX - rect.left;
    const nearest = coordinates.reduce((best, point) => (Math.abs(point.x - pointerX) < Math.abs(best.x - pointerX) ? point : best), coordinates[0]);

    tooltip.replaceChildren(document.createTextNode(nearest.label), document.createElement('br'), document.createTextNode(formatScore(nearest.score)));
    tooltip.hidden = false;
    tooltip.style.left = '0px';
    tooltip.style.top = '0px';

    const tooltipWidth = tooltip.offsetWidth || 0;
    const tooltipHeight = tooltip.offsetHeight || 0;
    const horizontalPadding = Math.ceil(tooltipWidth / 2) + 6;
    const verticalPadding = tooltipHeight + 6;
    const maxLeft = Math.max(horizontalPadding, rect.width - horizontalPadding);

    tooltip.style.left = `${Math.min(Math.max(nearest.x, horizontalPadding), maxLeft)}px`;
    tooltip.style.top = `${Math.max(nearest.y - 14, verticalPadding)}px`;
  }

  function hideChartTooltip(canvas) {
    const tooltip = canvas.parentElement ? canvas.parentElement.querySelector('[data-splask-chart-tooltip]') : null;
    if (tooltip) {
      tooltip.hidden = true;
    }
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
          initHistoryCharts(body);
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
          initHistoryCharts(body);
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
      modal.addEventListener('shown.bs.modal', () => redrawHistoryCharts(modal));
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
    initHistoryCharts(root);

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
