(function () {
  'use strict';

  const SELECTOR = '[data-splask-widget]';
  const CIRCLE_LENGTH = 377;
  const MALAY_MONTHS = ['Januari', 'Februari', 'Mac', 'April', 'Mei', 'Jun', 'Julai', 'Ogos', 'September', 'Oktober', 'November', 'Disember'];
  const MALAY_WEEKDAYS = ['Ahad', 'Isnin', 'Selasa', 'Rabu', 'Khamis', 'Jumaat', 'Sabtu'];

  const DEFAULT_LABELS = {
    button_refresh_label: 'Segar Semula Analitik',
    button_refresh_loading_label: 'Menyegar semula...',
    button_refresh_current_label: 'Sudah Terkini',
    button_refresh_failed_label: 'Segar Semula Gagal',
    history_empty_label: 'Belum ada rekod sejarah. Rekod akan disimpan selepas markah berjaya dimuatkan.',
    history_loading_label: 'Memuatkan sejarah...',
    history_load_failed_label: 'Sejarah tidak dapat dimuatkan.',
    history_connection_error_label: 'Ralat sambungan semasa memuatkan sejarah.',
    history_page_status_template: 'Memaparkan {start}–{end} daripada {total}',
    history_page_number_aria_template: 'Pergi ke halaman sejarah {page}'
  };

  function readLabels(element) {
    const source = element && element.closest ? element.closest('[data-splask-labels]') : element;

    if (source && source._splaskLabels) {
      return source._splaskLabels;
    }

    let labels = {};
    try {
      labels = JSON.parse(source && source.dataset ? source.dataset.splaskLabels || '{}' : '{}');
    } catch (error) {
      labels = {};
    }

    const merged = Object.assign({}, DEFAULT_LABELS, labels || {});
    if (source) {
      source._splaskLabels = merged;
    }

    return merged;
  }

  function label(element, key) {
    const labels = readLabels(element);
    return labels[key] || DEFAULT_LABELS[key] || '';
  }

  function formatLabelTemplate(template, values) {
    return Object.keys(values).reduce((text, key) => text.replace(new RegExp(`\\{${key}\\}`, 'g'), String(values[key])), template || '');
  }


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

    if (dateStr instanceof Date) {
      return Number.isNaN(dateStr.getTime()) ? null : dateStr;
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

  function addDaysPreservingTime(dateStr, days) {
    const parsed = parseSplaskDate(dateStr);

    if (!parsed) {
      return null;
    }

    const nextDate = new Date(parsed.getTime());
    nextDate.setUTCDate(nextDate.getUTCDate() + days);

    return nextDate;
  }


  function formatMalayOperationalDate(dateStr) {
    const parsed = parseSplaskDate(dateStr);

    if (!parsed) {
      return dateStr || 'Tiada';
    }

    const weekday = MALAY_WEEKDAYS[parsed.getUTCDay()];
    const month = MALAY_MONTHS[parsed.getUTCMonth()];

    return `${weekday} • ${parsed.getUTCDate()} ${month} ${parsed.getUTCFullYear()}`;
  }

  function formatMalayOperationalTimestamp(dateStr) {
    const parsed = parseSplaskDate(dateStr);

    if (!parsed) {
      return dateStr || 'Tiada';
    }

    const hours = parsed.getUTCHours();
    const minutes = String(parsed.getUTCMinutes()).padStart(2, '0');
    const period = hours >= 12 ? 'PM' : 'AM';
    const displayHours = hours % 12 || 12;

    return `${formatMalayOperationalDate(parsed)} • ${displayHours}:${minutes} ${period}`;
  }

  function getDatePartsInTimeZone(date, timeZone) {
    const formatter = new Intl.DateTimeFormat('en-GB', {
      day: 'numeric',
      hour: 'numeric',
      hour12: false,
      minute: '2-digit',
      month: 'numeric',
      timeZone,
      weekday: 'short',
      year: 'numeric'
    });
    const values = {};

    formatter.formatToParts(date).forEach((part) => {
      values[part.type] = part.value;
    });

    return {
      day: Number(values.day),
      hour: Number(values.hour === '24' ? '0' : values.hour),
      minute: String(values.minute || '00').padStart(2, '0'),
      month: Number(values.month) - 1,
      weekday: date.getUTCDay(),
      year: Number(values.year)
    };
  }

  function formatMalayClockTimestamp(date, timeZone) {
    let parts;

    try {
      parts = getDatePartsInTimeZone(date, timeZone || 'UTC');
    } catch (error) {
      parts = getDatePartsInTimeZone(date, 'UTC');
    }

    const probe = new Date(Date.UTC(parts.year, parts.month, parts.day));
    const weekday = MALAY_WEEKDAYS[probe.getUTCDay()];
    const month = MALAY_MONTHS[parts.month];
    const period = parts.hour >= 12 ? 'PM' : 'AM';
    const displayHours = parts.hour % 12 || 12;

    return `${weekday} • ${parts.day} ${month} ${parts.year} • ${displayHours}:${parts.minute} ${period}`;
  }

  function startLiveClock(root) {
    const clock = root.querySelector('[data-splask-live-clock]');
    const serverEpoch = Number(root.dataset.splaskClockEpoch || 0);

    if (!clock || !Number.isFinite(serverEpoch) || serverEpoch <= 0) {
      return;
    }

    const timeZone = root.dataset.splaskClockTimezone || 'UTC';
    const browserStart = Date.now();
    const serverStart = serverEpoch * 1000;

    const render = () => {
      const current = new Date(serverStart + (Date.now() - browserStart));
      clock.textContent = formatMalayClockTimestamp(current, timeZone);
    };

    render();
    window.setInterval(render, 1000);
  }

  function setText(root, name, value) {
    root.querySelectorAll(`[data-splask-${name}]`).forEach((element) => {
      element.textContent = value;
    });
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

    root.querySelectorAll('[data-splask-progress-circle]').forEach((circle) => {
      circle.style.stroke = grade.color;
      circle.style.strokeDashoffset = CIRCLE_LENGTH - (CIRCLE_LENGTH * score / 100);
    });

    root.querySelectorAll('[data-splask-progress-bar]').forEach((bar) => {
      bar.style.width = `${score}%`;
    });
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

        if (response && response.mini_trend) {
          applyMiniTrendDataset(root, response.mini_trend);
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

  function scheduleHistoryChartRedraw(scope) {
    redrawHistoryCharts(scope);

    if (window.requestAnimationFrame) {
      window.requestAnimationFrame(() => redrawHistoryCharts(scope));
      window.requestAnimationFrame(() => window.requestAnimationFrame(() => redrawHistoryCharts(scope)));
    }

    [75, 150, 300, 600].forEach((delay) => window.setTimeout(() => redrawHistoryCharts(scope), delay));
  }

  function chartPoints(canvas) {
    try {
      const points = JSON.parse(canvas.dataset.splaskChartPoints || '[]');
      return Array.isArray(points) ? points.filter((point) => Number.isFinite(Number(point.score))) : [];
    } catch (error) {
      return [];
    }
  }

  function resolveCanvasColor(value, fallback) {
    const probe = document.createElement('canvas').getContext('2d');
    if (!probe) {
      return fallback;
    }

    probe.strokeStyle = fallback;
    probe.strokeStyle = value || fallback;

    return probe.strokeStyle || fallback;
  }

  function chartColor(canvas, propertyName, fallback) {
    const root = canvas.closest('[data-splask-widget]') || document.documentElement;
    const canvasStyle = getComputedStyle(canvas);
    const rootStyle = getComputedStyle(root);
    const value = (canvasStyle.getPropertyValue(propertyName) || rootStyle.getPropertyValue(propertyName) || '').trim();

    return resolveCanvasColor(value, fallback);
  }

  function chartDimensions(canvas) {
    const wrapper = canvas.parentElement;
    const rect = wrapper ? wrapper.getBoundingClientRect() : canvas.getBoundingClientRect();
    const cssWidth = wrapper ? wrapper.clientWidth : canvas.clientWidth;
    const cssHeight = wrapper ? wrapper.clientHeight : canvas.clientHeight;
    const width = Math.floor(rect.width || cssWidth || canvas.clientWidth || 0);
    const height = Math.floor(rect.height || cssHeight || canvas.clientHeight || 0);

    return {
      width: Math.max(1, width || 640),
      height: Math.max(220, height || 260),
      visible: width > 0 && height > 0
    };
  }

  function clampScore(score) {
    return Math.max(0, Math.min(100, Number(score)));
  }

  function chartScale(points) {
    const scores = points.map((point) => clampScore(point.score)).filter(Number.isFinite);

    if (!scores.length) {
      return { min: 0, max: 100, ticks: [0, 25, 50, 75, 100] };
    }

    const lowest = Math.min(...scores);
    const highest = Math.max(...scores);
    const spread = highest - lowest;

    if (spread >= 50) {
      return { min: 0, max: 100, ticks: [0, 25, 50, 75, 100] };
    }

    const padding = Math.max(1, spread * 0.2);
    let min = Math.floor(lowest - padding);
    let max = Math.ceil(highest + padding);
    const minimumRange = 4;

    if (max - min < minimumRange) {
      const midpoint = (lowest + highest) / 2;
      min = Math.floor(midpoint - minimumRange / 2);
      max = Math.ceil(midpoint + minimumRange / 2);
    }

    min = Math.max(0, min);
    max = Math.min(101, max);

    if (max - min < minimumRange) {
      if (min === 0) {
        max = Math.min(101, min + minimumRange);
      } else {
        min = Math.max(0, max - minimumRange);
      }
    }

    const range = Math.max(1, max - min);
    const ticks = Array.from({ length: 5 }, (_, index) => min + (range * index / 4));

    return { min, max, ticks };
  }

  function formatChartTick(tick) {
    const rounded = Math.round(tick * 10) / 10;
    return `${rounded.toFixed(1).replace(/\.0$/, '')}%`;
  }

  function drawHistoryChart(canvas) {
    const points = chartPoints(canvas);
    const context = canvas.getContext('2d');
    const dimensions = chartDimensions(canvas);
    const color = chartColor(canvas, '--splask-grade-color', '#2563eb');
    const accent = chartColor(canvas, '--splask-grade-accent', '#60a5fa');
    const muted = chartColor(canvas, '--splask-chart-muted', 'rgba(100, 116, 139, 0.72)');
    const grid = chartColor(canvas, '--splask-chart-grid', 'rgba(148, 163, 184, 0.22)');
    const width = dimensions.width;
    const height = dimensions.height;
    const ratio = window.devicePixelRatio || 1;
    const labelFontSize = width < 520 || points.length > 14 ? 10 : 11;
    const labelFont = `${labelFontSize}px system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif`;

    if (!context || points.length < 2) {
      canvas._splaskChartCoordinates = [];
      return;
    }

    if (!dimensions.visible && !canvas.dataset.splaskHiddenDrawQueued) {
      canvas.dataset.splaskHiddenDrawQueued = 'true';
      window.setTimeout(() => {
        delete canvas.dataset.splaskHiddenDrawQueued;
        drawHistoryChart(canvas);
      }, 150);
    }

    canvas.width = Math.floor(width * ratio);
    canvas.height = Math.floor(height * ratio);
    canvas.style.width = `${width}px`;
    canvas.style.height = `${height}px`;
    context.setTransform(ratio, 0, 0, ratio, 0, 0);
    context.clearRect(0, 0, width, height);
    context.font = labelFont;
    context.lineCap = 'round';
    context.lineJoin = 'round';

    const widestLabel = points.reduce((widest, point) => Math.max(widest, context.measureText(String(point.label || '')).width), 0);
    const labelRotation = -Math.PI / 5.4;
    const labelCos = Math.abs(Math.cos(labelRotation));
    const labelSin = Math.abs(Math.sin(labelRotation));
    const labelProjectedWidth = Math.ceil((widestLabel * labelCos) + (labelFontSize * labelSin));
    const labelProjectedHeight = Math.ceil((widestLabel * labelSin) + (labelFontSize * labelCos));
    const horizontalEdgePadding = Math.ceil(labelProjectedWidth / 2) + 14;
    const labelBaselineOffset = Math.ceil(labelProjectedHeight / 2) + 7;
    const padding = {
      top: 12,
      right: Math.min(64, Math.max(30, horizontalEdgePadding)),
      bottom: Math.min(62, Math.max(44, labelProjectedHeight + 10)),
      left: Math.min(66, Math.max(44, horizontalEdgePadding))
    };
    const plotWidth = Math.max(1, width - padding.left - padding.right);
    const plotHeight = Math.max(1, height - padding.top - padding.bottom);
    const scale = chartScale(points);
    const scaleRange = Math.max(1, scale.max - scale.min);
    const toX = (index) => padding.left + (points.length === 1 ? 0 : (index / (points.length - 1)) * plotWidth);
    const toY = (score) => padding.top + ((scale.max - clampScore(score)) / scaleRange) * plotHeight;

    context.strokeStyle = grid;
    context.fillStyle = muted;
    context.lineWidth = 1;
    scale.ticks.forEach((tick) => {
      const y = padding.top + ((scale.max - tick) / scaleRange) * plotHeight;
      context.beginPath();
      context.moveTo(padding.left, y);
      context.lineTo(width - padding.right, y);
      context.stroke();
      context.fillText(formatChartTick(tick), 8, y + 4);
    });

    context.font = labelFont;
    context.textBaseline = 'middle';
    points.forEach((point, index) => {
      const x = toX(index);
      const label = String(point.label || '');

      context.save();
      context.translate(x, height - labelBaselineOffset);
      context.rotate(labelRotation);
      context.textAlign = 'center';
      context.fillText(label, 0, 0);
      context.restore();
    });

    const coordinates = points.map((point, index) => ({
      x: toX(index),
      y: toY(point.score),
      label: String(point.label || ''),
      score: Number(point.score)
    }));

    context.save();
    context.shadowColor = resolveCanvasColor(accent, color);
    context.shadowBlur = 8;
    context.strokeStyle = color;
    context.lineWidth = 4;
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
    context.restore();

    context.fillStyle = color;
    context.strokeStyle = '#ffffff';
    context.lineWidth = 2;
    coordinates.forEach((point) => {
      context.beginPath();
      context.arc(point.x, point.y, 5, 0, Math.PI * 2);
      context.fill();
      context.stroke();
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
    const edgeGap = 8;
    const caretGap = 12;

    tooltip.replaceChildren(document.createTextNode(nearest.label), document.createElement('br'), document.createTextNode(formatScore(nearest.score)));
    tooltip.hidden = false;
    tooltip.style.left = '0px';
    tooltip.style.top = '0px';
    tooltip.style.maxWidth = `${Math.max(0, Math.floor(rect.width - (edgeGap * 2)))}px`;
    tooltip.dataset.splaskXAlign = 'center';
    tooltip.dataset.splaskYAlign = 'top';

    const tooltipWidth = tooltip.offsetWidth || 0;
    const tooltipHeight = tooltip.offsetHeight || 0;
    const centeredLeft = nearest.x - (tooltipWidth / 2);
    const centeredTop = nearest.y - tooltipHeight - caretGap;
    const minLeft = edgeGap;
    const maxLeft = Math.max(minLeft, rect.width - tooltipWidth - edgeGap);
    const minTop = edgeGap;
    const maxTop = Math.max(minTop, rect.height - tooltipHeight - edgeGap);
    let nextLeft = centeredLeft;
    let nextTop = centeredTop;

    if (centeredLeft < minLeft && nearest.x + caretGap + tooltipWidth <= rect.width - edgeGap) {
      nextLeft = nearest.x + caretGap;
      tooltip.dataset.splaskXAlign = 'left';
    } else if (centeredLeft > maxLeft && nearest.x - caretGap - tooltipWidth >= edgeGap) {
      nextLeft = nearest.x - caretGap - tooltipWidth;
      tooltip.dataset.splaskXAlign = 'right';
    }

    if (centeredTop < minTop && nearest.y + caretGap + tooltipHeight <= rect.height - edgeGap) {
      nextTop = nearest.y + caretGap;
      tooltip.dataset.splaskYAlign = 'bottom';
    } else if (centeredTop > maxTop && nearest.y - caretGap - tooltipHeight >= edgeGap) {
      nextTop = nearest.y - caretGap - tooltipHeight;
      tooltip.dataset.splaskYAlign = 'top';
    }

    tooltip.style.left = `${Math.min(Math.max(nextLeft, minLeft), maxLeft)}px`;
    tooltip.style.top = `${Math.min(Math.max(nextTop, minTop), maxTop)}px`;
  }

  function hideChartTooltip(canvas) {
    const tooltip = canvas.parentElement ? canvas.parentElement.querySelector('[data-splask-chart-tooltip]') : null;
    if (tooltip) {
      tooltip.hidden = true;
    }
  }

  function miniTrendPoints(canvas) {
    try {
      const points = JSON.parse(canvas.dataset.splaskMiniTrendPoints || '[]');
      return Array.isArray(points) ? points.filter((point) => Number.isFinite(Number(point.score))) : [];
    } catch (error) {
      return [];
    }
  }

  function applyMiniTrendDataset(root, points) {
    if (!Array.isArray(points)) {
      return;
    }

    const sanitized = points
      .filter((point) => Number.isFinite(Number(point.score)))
      .slice(-7)
      .map((point) => ({
        label: point.label || '',
        score: clampScore(point.score)
      }));

    root.querySelectorAll('[data-splask-mini-trend]').forEach((canvas) => {
      canvas.dataset.splaskMiniTrendPoints = JSON.stringify(sanitized);
      drawMiniTrendChart(canvas);
    });
  }

  function miniChartDimensions(canvas) {
    const wrapper = canvas.parentElement;
    const rect = wrapper ? wrapper.getBoundingClientRect() : canvas.getBoundingClientRect();
    const cssWidth = wrapper ? wrapper.clientWidth : canvas.clientWidth;
    const cssHeight = wrapper ? wrapper.clientHeight : canvas.clientHeight;
    const width = Math.floor(rect.width || cssWidth || canvas.clientWidth || 0);
    const height = Math.floor(rect.height || cssHeight || canvas.clientHeight || 0);

    return {
      width: Math.max(1, width || 320),
      height: Math.max(72, height || 96),
      visible: width > 0 && height > 0
    };
  }

  function drawMiniTrendChart(canvas) {
    const context = canvas.getContext('2d');
    const points = miniTrendPoints(canvas);
    const dimensions = miniChartDimensions(canvas);
    const color = chartColor(canvas, '--splask-grade-color', '#2563eb');
    const accent = chartColor(canvas, '--splask-grade-accent', '#60a5fa');
    const width = dimensions.width;
    const height = dimensions.height;
    const ratio = window.devicePixelRatio || 1;
    const padding = { top: 14, right: 12, bottom: 14, left: 12 };

    if (!context || !points.length) {
      return;
    }

    if (!dimensions.visible && !canvas.dataset.splaskMiniHiddenDrawQueued) {
      canvas.dataset.splaskMiniHiddenDrawQueued = 'true';
      window.setTimeout(() => {
        delete canvas.dataset.splaskMiniHiddenDrawQueued;
        drawMiniTrendChart(canvas);
      }, 150);
    }

    canvas.width = Math.floor(width * ratio);
    canvas.height = Math.floor(height * ratio);
    canvas.style.width = `${width}px`;
    canvas.style.height = `${height}px`;
    context.setTransform(ratio, 0, 0, ratio, 0, 0);
    context.clearRect(0, 0, width, height);
    context.lineCap = 'round';
    context.lineJoin = 'round';

    const plotWidth = width - padding.left - padding.right;
    const plotHeight = height - padding.top - padding.bottom;
    const scale = chartScale(points);
    const scaleRange = Math.max(1, scale.max - scale.min);
    const drawablePoints = points.length === 1 ? [points[0], points[0]] : points;
    const coordinates = drawablePoints.map((point, index) => ({
      x: padding.left + (index / (drawablePoints.length - 1)) * plotWidth,
      y: padding.top + ((scale.max - clampScore(point.score)) / scaleRange) * plotHeight
    }));

    const gradient = context.createLinearGradient(0, padding.top, 0, height - padding.bottom);
    gradient.addColorStop(0, resolveCanvasColor(accent, color));
    gradient.addColorStop(1, 'rgba(37, 99, 235, 0)');

    context.save();
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
    context.lineTo(coordinates[coordinates.length - 1].x, height - padding.bottom);
    context.lineTo(coordinates[0].x, height - padding.bottom);
    context.closePath();
    context.globalAlpha = 0.18;
    context.fillStyle = gradient;
    context.fill();
    context.restore();

    context.save();
    context.shadowColor = resolveCanvasColor(accent, color);
    context.shadowBlur = 8;
    context.strokeStyle = color;
    context.lineWidth = 4;
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
    context.restore();
  }

  function updateMiniTrendCharts(root) {
    root.querySelectorAll('[data-splask-mini-trend]').forEach((canvas) => {
      drawMiniTrendChart(canvas);
    });
  }

  function initMiniTrendCharts(root) {
    root.querySelectorAll('[data-splask-mini-trend]').forEach((canvas) => {
      if (canvas.dataset.splaskMiniTrendInitialized === 'true') {
        drawMiniTrendChart(canvas);
        return;
      }

      canvas.dataset.splaskMiniTrendInitialized = 'true';
      if (window.ResizeObserver) {
        const observer = new ResizeObserver(() => drawMiniTrendChart(canvas));
        observer.observe(canvas.parentElement || canvas);
        canvas._splaskMiniResizeObserver = observer;
      } else {
        window.addEventListener('resize', () => drawMiniTrendChart(canvas));
      }
      drawMiniTrendChart(canvas);
    });
  }


  function getHistoryRecords(shell) {
    if (Array.isArray(shell._splaskHistoryRecords)) {
      return shell._splaskHistoryRecords;
    }

    const source = shell.querySelector('[data-splask-history-records]');
    try {
      const records = JSON.parse(source ? source.textContent || '[]' : '[]');
      shell._splaskHistoryRecords = Array.isArray(records) ? records : [];
    } catch (error) {
      shell._splaskHistoryRecords = [];
    }

    return shell._splaskHistoryRecords;
  }

  function normaliseHistoryPageSize(value) {
    const size = Number.parseInt(value, 10);
    return Math.min(50, Math.max(1, Number.isFinite(size) ? size : 7));
  }

  function renderHistoryCell(row, value, strong) {
    const cell = document.createElement('td');
    const target = strong ? document.createElement('strong') : cell;
    target.textContent = value || '';

    if (strong) {
      cell.appendChild(target);
    }

    row.appendChild(cell);
  }


  function canEditCatatan(shell) {
    return !!(shell && shell.dataset && shell.dataset.splaskCanEditCatatan === '1');
  }

  function escapeCsvValue(value) {
    const text = String(value == null ? '' : value);
    const safeText = /^[=+\-@]/.test(text) ? `'${text}` : text;
    return `"${safeText.replace(/"/g, '""')}"`;
  }

  function buildHistoryCsv(records) {
    const header = ['Tarikh', 'Masa', 'Skor', 'Gred', 'Status', 'Catatan'];
    const rows = records.map((record) => ([
      record.date || '',
      record.time || '',
      record.score || '',
      record.gradeLabel || '',
      record.status || '',
      record.catatan || ''
    ].map(escapeCsvValue).join(',')));
    return `\uFEFF${header.map(escapeCsvValue).join(',')}\r\n${rows.join('\r\n')}`;
  }

  function exportHistoryCsv(shell) {
    const records = getHistoryRecords(shell);
    if (!records.length) {
      return;
    }

    const csvContent = buildHistoryCsv(records);
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const anchor = document.createElement('a');
    anchor.href = url;
    anchor.download = `splask_analytics_${new Date().toISOString().slice(0, 10)}.csv`;
    document.body.appendChild(anchor);
    anchor.click();
    document.body.removeChild(anchor);
    URL.revokeObjectURL(url);
  }

  function createCatatanDisplayButton(record) {
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'splask-catatan-display';
    button.dataset.splaskCatatanDisplay = '1';

    const text = document.createElement('span');
    text.className = 'splask-catatan-text';
    const hasText = String(record.catatan || '').trim() !== '';
    text.textContent = hasText ? record.catatan : 'Tambah catatan';
    button.appendChild(text);

    const icon = document.createElement('span');
    icon.className = 'splask-catatan-icon';
    icon.setAttribute('aria-hidden', 'true');
    icon.textContent = '✎';
    button.appendChild(icon);

    if (!hasText) {
      button.classList.add('is-empty');
      text.classList.add('visually-hidden');
    }

    button.setAttribute('aria-label', hasText ? 'Edit catatan' : 'Tambah catatan');

    return button;
  }

  function scheduleCatatanSave(widget, record, value, input, status) {
    if (!widget || !record || !record.id) {
      return;
    }

    if (input._splaskSaveTimer) {
      window.clearTimeout(input._splaskSaveTimer);
    }

    input._splaskSaveTimer = window.setTimeout(() => {
      input._splaskSaveTimer = null;
      if (input.dataset.splaskSaving === '1') {
        return;
      }

      if (String(value) === String(record.catatan || '')) {
        return;
      }

      input.dataset.splaskSaving = '1';
      status.textContent = 'Menyimpan…';

      postModuleAjax(widget, 'saveCatatan', { id: record.id, catatan: value })
        .then(unwrapAjaxResponse)
        .then((res) => {
          input.dataset.splaskSaving = '0';
          if (res && res.success) {
            record.catatan = value;
            status.textContent = 'Disimpan';
            window.setTimeout(() => {
              if (status.textContent === 'Disimpan') {
                status.textContent = '';
              }
            }, 1000);
          } else {
            status.textContent = 'Tidak disimpan';
          }
        })
        .catch(() => {
          input.dataset.splaskSaving = '0';
          status.textContent = 'Tidak disimpan';
        });
    }, 400);
  }

  function renderHistoryRows(shell, records) {
    const body = shell.querySelector('[data-splask-history-page-body]');
    if (!body) {
      return;
    }

    body.replaceChildren();

    if (!records.length) {
      const emptyRow = document.createElement('tr');
      const emptyCell = document.createElement('td');
      emptyCell.colSpan = 6;
      emptyCell.className = 'text-center py-4';
      emptyCell.textContent = label(shell, 'history_empty_label');
      emptyRow.appendChild(emptyCell);
      body.appendChild(emptyRow);
      return;
    }

    records.forEach((record) => {
      const row = document.createElement('tr');
      row.dataset.splaskHistoryGrade = record.gradeKey || '';

      renderHistoryCell(row, record.date, false);
      renderHistoryCell(row, record.time, false);
      renderHistoryCell(row, record.score, true);

      const gradeCell = document.createElement('td');
      const grade = document.createElement('span');
      grade.className = 'splask-history-grade';
      grade.textContent = record.gradeLabel || '';
      gradeCell.appendChild(grade);
      row.appendChild(gradeCell);

      renderHistoryCell(row, record.status, false);

      const noteCell = document.createElement('td');
      noteCell.className = 'splask-catatan-cell';
      const editable = canEditCatatan(shell);
      let displayButton = createCatatanDisplayButton(record);
      const editor = document.createElement('textarea');
      editor.className = 'form-control form-control-sm splask-catatan-editor';
      editor.rows = 2;
      editor.value = record.catatan || '';
      editor.hidden = true;

      const status = document.createElement('small');
      status.className = 'splask-catatan-status';

      const openEditor = () => {
        if (!editable) {
          return;
        }

        displayButton.hidden = true;
        editor.hidden = false;
        editor.select();
      };

      const closeEditor = () => {
        editor.hidden = true;
        const refreshed = createCatatanDisplayButton(record);
        displayButton.replaceWith(refreshed);
        displayButton = refreshed;
        displayButton.hidden = false;
        displayButton.addEventListener('click', openEditor);
      };

      if (editable) {
        displayButton.addEventListener('click', openEditor);
      }

      editor.addEventListener('input', () => {
        const widget = shell.closest('[data-splask-widget]');
        scheduleCatatanSave(widget, record, editor.value, editor, status);
      });

      editor.addEventListener('change', () => {
        const widget = shell.closest('[data-splask-widget]');
        scheduleCatatanSave(widget, record, editor.value, editor, status);
      });

      const persistAndCloseEditor = () => {
        if (!editable || editor.hidden) {
          return;
        }

        const widget = shell.closest('[data-splask-widget]');
        scheduleCatatanSave(widget, record, editor.value, editor, status);
        window.setTimeout(closeEditor, 120);
      };

      editor.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
          event.preventDefault();
          persistAndCloseEditor();
        }
      });

      document.addEventListener('mousedown', (event) => {
        if (!editable || editor.hidden) {
          return;
        }

        if (editor.contains(event.target) || displayButton.contains(event.target)) {
          return;
        }

        persistAndCloseEditor();
      });

      if (!editable) {
        const plainText = document.createElement('span');
        plainText.className = 'splask-catatan-text';
        plainText.textContent = String(record.catatan || '').trim() !== '' ? record.catatan : '-';
        noteCell.appendChild(plainText);
      } else {
        noteCell.appendChild(displayButton);
        noteCell.appendChild(editor);
        noteCell.appendChild(status);
      }
      row.appendChild(noteCell);
      body.appendChild(row);
    });
  }

  function renderHistoryPageNumbers(shell, pageCount, current) {
    const numbers = shell.querySelector('[data-splask-history-page-numbers]');
    if (!numbers) {
      return;
    }

    numbers.replaceChildren();

    for (let page = 1; page <= pageCount; page += 1) {
      const button = document.createElement('button');
      button.type = 'button';
      button.className = 'splask-history-page-button splask-history-page-number';
      button.textContent = String(page);
      button.dataset.splaskHistoryPageNumber = String(page);
      button.setAttribute('aria-label', formatLabelTemplate(label(shell, 'history_page_number_aria_template'), { page: page }));

      if (page === current) {
        button.classList.add('is-active');
        button.setAttribute('aria-current', 'page');
      }

      button.addEventListener('click', () => {
        shell.dataset.splaskHistoryPage = String(page);
        updateHistoryPage(shell);
      });

      numbers.appendChild(button);
    }
  }

  function initHistoryTables(scope) {
    const container = scope || document;
    container.querySelectorAll('[data-splask-history-pagination]').forEach((shell) => {
      if (shell.dataset.splaskPaginationInitialized === 'true') {
        updateHistoryPage(shell);
        return;
      }

      shell.dataset.splaskPaginationInitialized = 'true';
      shell.dataset.splaskHistoryPage = '1';
      shell.dataset.splaskHistoryPageSize = String(normaliseHistoryPageSize(shell.dataset.splaskHistoryPageSize || '7'));

      const previous = shell.querySelector('[data-splask-history-page-prev]');
      const next = shell.querySelector('[data-splask-history-page-next]');
      const pageSizeInput = shell.querySelector('[data-splask-history-page-size-input]');
      const exportButton = shell.querySelector('[data-splask-history-export-csv]');

      if (pageSizeInput) {
        pageSizeInput.value = shell.dataset.splaskHistoryPageSize;
        pageSizeInput.addEventListener('change', () => {
          shell.dataset.splaskHistoryPageSize = String(normaliseHistoryPageSize(pageSizeInput.value));
          pageSizeInput.value = shell.dataset.splaskHistoryPageSize;
          shell.dataset.splaskHistoryPage = '1';
          updateHistoryPage(shell);
        });
      }

      if (previous) {
        previous.addEventListener('click', () => {
          const current = Number(shell.dataset.splaskHistoryPage || '1');
          shell.dataset.splaskHistoryPage = String(Math.max(1, current - 1));
          updateHistoryPage(shell);
        });
      }

      if (next) {
        next.addEventListener('click', () => {
          const pageSize = normaliseHistoryPageSize(shell.dataset.splaskHistoryPageSize || '7');
          const records = getHistoryRecords(shell);
          const pageCount = Math.max(1, Math.ceil(records.length / pageSize));
          const current = Number(shell.dataset.splaskHistoryPage || '1');
          shell.dataset.splaskHistoryPage = String(Math.min(pageCount, current + 1));
          updateHistoryPage(shell);
        });
      }

      if (exportButton && canEditCatatan(shell)) {
        exportButton.addEventListener('click', () => {
          exportHistoryCsv(shell);
        });
      }

      updateHistoryPage(shell);
    });
  }

  function updateHistoryPage(shell) {
    const pageSize = normaliseHistoryPageSize(shell.dataset.splaskHistoryPageSize || '7');
    const records = getHistoryRecords(shell);
    const pageCount = Math.max(1, Math.ceil(records.length / pageSize));
    const current = Math.min(pageCount, Math.max(1, Number(shell.dataset.splaskHistoryPage || '1') || 1));
    const start = (current - 1) * pageSize;
    const end = start + pageSize;
    const pageRecords = records.slice(start, end);
    const status = shell.querySelector('[data-splask-history-page-status]');
    const previous = shell.querySelector('[data-splask-history-page-prev]');
    const next = shell.querySelector('[data-splask-history-page-next]');
    const scrollWrap = shell.querySelector('.splask-history-table-wrap');

    shell.dataset.splaskHistoryPage = String(current);
    shell.dataset.splaskHistoryPageSize = String(pageSize);
    renderHistoryRows(shell, pageRecords);
    renderHistoryPageNumbers(shell, pageCount, current);

    if (status) {
      const visibleStart = records.length ? start + 1 : 0;
      const visibleEnd = Math.min(end, records.length);
      status.textContent = formatLabelTemplate(label(shell, 'history_page_status_template'), { start: visibleStart, end: visibleEnd, total: records.length });
    }

    if (previous) {
      previous.disabled = current <= 1;
    }

    if (next) {
      next.disabled = current >= pageCount;
    }

    if (scrollWrap) {
      scrollWrap.scrollTop = 0;
    }
  }

  function setHistoryLoading(root, message) {
    const body = root.querySelector('[data-splask-history-body]');
    if (body) {
      const loading = document.createElement('div');
      loading.className = 'splask-history-loading';
      loading.textContent = message;
      body.replaceChildren(loading);
    }
  }

  function loadHistory(root) {
    const body = root.querySelector('[data-splask-history-body]');
    if (!body || body.dataset.splaskLoaded === 'true') {
      return;
    }

    setHistoryLoading(root, label(root, 'history_loading_label'));

    postModuleAjax(root, 'history', {
      appearance: root.dataset.splaskAppearance || resolveAppearance(root),
      preset: root.dataset.splaskPreset || 'dashboard_tile'
    })
      .then(unwrapAjaxResponse)
      .then((data) => {
        if (data && data.success && data.html) {
          body.innerHTML = data.html;
          body.dataset.splaskLoaded = 'true';
          initHistoryTables(body);
          scheduleHistoryChartRedraw(body);
          applyHealth(root, data.health);
          applyMiniTrendDataset(root, data.mini_trend || data.chart || []);
          return;
        }

        setHistoryLoading(root, (data && data.message) || label(root, 'history_load_failed_label'));
      })
      .catch(() => {
        setHistoryLoading(root, label(root, 'history_connection_error_label'));
      });
  }

  function setRefreshState(root, loading, message) {
    const currentLabel = message || (loading ? label(root, 'button_refresh_loading_label') : label(root, 'button_refresh_label'));
    root.querySelectorAll('[data-splask-refresh-trigger]').forEach((trigger) => {
      trigger.disabled = loading;
      trigger.classList.toggle('is-loading', loading);
      trigger.setAttribute('aria-label', currentLabel);
      trigger.setAttribute('title', currentLabel);
    });
  }

  function refreshAnalytics(root) {
    if (!root.dataset.splaskAjaxUrl) {
      return;
    }

    const body = root.querySelector('[data-splask-history-body]');
    setRefreshState(root, true, label(root, 'button_refresh_loading_label'));

    postModuleAjax(root, 'refreshAnalytics', {
      appearance: root.dataset.splaskAppearance || resolveAppearance(root),
      preset: root.dataset.splaskPreset || 'dashboard_tile'
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

        if (data && (data.mini_trend || data.chart)) {
          applyMiniTrendDataset(root, data.mini_trend || data.chart || []);
        }

        if (body && data && data.html) {
          body.innerHTML = data.html;
          body.dataset.splaskLoaded = 'true';
          initHistoryTables(body);
          scheduleHistoryChartRedraw(body);
        }

        setRefreshState(root, false, (data && data.duplicate) ? label(root, 'button_refresh_current_label') : label(root, 'button_refresh_label'));
        window.setTimeout(() => setRefreshState(root, false), 1800);
      })
      .catch(() => {
        setRefreshState(root, false, label(root, 'button_refresh_failed_label'));
        window.setTimeout(() => setRefreshState(root, false), 1800);
      });
  }

  function bindHistoryModal(root) {
    const modal = root.querySelector('[data-splask-history-modal-shell]');
    const trigger = root.querySelector('[data-splask-history-trigger]');
    const refreshTrigger = root.querySelector('[data-splask-refresh-trigger]');

    if (modal) {
      modal.addEventListener('show.bs.modal', () => loadHistory(root));
      modal.addEventListener('shown.bs.modal', () => scheduleHistoryChartRedraw(modal));
      modal.addEventListener('transitionend', () => scheduleHistoryChartRedraw(modal));
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
    const nextCheck = addDaysPreservingTime(data.last_check, 1);
    const link = root.querySelector('[data-splask-link]');

    applyGradeStyles(root, grade, score);

    setText(root, 'score', formatScore(score));
    setText(root, 'grade', grade.label.toUpperCase());
    setText(root, 'grade-short', grade.shortLabel);
    setText(root, 'status', grade.status);
    setText(root, 'date', formatMalayOperationalDate(data.last_check));
    setText(root, 'next', nextCheck ? formatMalayOperationalDate(nextCheck) : 'Tiada');
    updateMiniTrendCharts(root);

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
    startLiveClock(root);
    applyHealth(root);
    bindHistoryModal(root);
    initHistoryCharts(root);
    initHistoryTables(root);
    initMiniTrendCharts(root);

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
