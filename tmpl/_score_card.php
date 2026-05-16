<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  mod_splaskscore
 *
 * @copyright   Copyright (C) 2025 Muhammad Azizan Hazim
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Uri\Uri;

$preset = $splaskPreset ?? 'enterprise_kpi';
$presetLabel = $splaskPresetLabel ?? 'Executive Analytics';
$moduleId = isset($module) ? (int) $module->id : 0;
$rootId = 'mod-splaskscore-' . $moduleId . '-' . preg_replace('/[^a-z0-9_-]/i', '-', $preset);
$assetBase = Uri::root(true) . '/media/mod_splaskscore';
$appearanceMode = ModSplaskscoreHelper::getAppearanceMode($params);

$document = Factory::getApplication()->getDocument();
$wa = method_exists($document, 'getWebAssetManager') ? $document->getWebAssetManager() : null;
if ($wa && method_exists($wa, 'useScript')) {
    $wa->useScript('bootstrap.modal');
}
$document->addStyleSheet($assetBase . '/css/splaskscore.css');
$document->addScript($assetBase . '/js/splaskscore.js', ['defer' => true]);
$ajaxUrl = Uri::base(true) . '/index.php?option=com_ajax&module=splaskscore&format=json';
$historyModalId = $rootId . '-history-modal';
$csrfToken = Session::getFormToken();
$initialHealth = $token !== '' ? ModSplaskscoreHelper::getAnalyticsHealth($moduleId, hash('sha256', (string) $token)) : [
    'last_success' => '',
    'last_failed' => '',
    'status' => 'UNKNOWN',
    'missing_today' => true,
];
$analyticsModeLabels = [
    'enterprise_kpi' => 'Executive Analytics',
    'dashboard_tile' => 'Operations Grid',
    'neon_glass' => 'Neon Cyber',
];
$analyticsModeLabel = $analyticsModeLabels[$preset] ?? $presetLabel;
$isExecutiveKpiBoard = $preset === 'enterprise_kpi';
$isOperationsGrid = $preset === 'dashboard_tile';
$isNeonCyber = $preset === 'neon_glass';
?>

<div
  id="<?php echo htmlspecialchars($rootId, ENT_QUOTES, 'UTF-8'); ?>"
  class="splask-widget splask-preset-<?php echo htmlspecialchars($preset, ENT_QUOTES, 'UTF-8'); ?> splask-appearance-<?php echo htmlspecialchars($appearanceMode, ENT_QUOTES, 'UTF-8'); ?> mt-3"
  style="<?php echo ModSplaskscoreHelper::getGradeThemeStyle(); ?>"
  data-splask-widget
  data-splask-token="<?php echo $token_escaped; ?>"
  data-splask-grade-rules="<?php echo ModSplaskscoreHelper::getGradeRulesJson(); ?>"
  data-splask-appearance-mode="<?php echo htmlspecialchars($appearanceMode, ENT_QUOTES, 'UTF-8'); ?>"
  data-splask-appearance="<?php echo $appearanceMode === 'auto' ? 'auto' : htmlspecialchars($appearanceMode, ENT_QUOTES, 'UTF-8'); ?>"
  data-splask-module-id="<?php echo (int) $moduleId; ?>"
  data-splask-preset="<?php echo htmlspecialchars($preset, ENT_QUOTES, 'UTF-8'); ?>"
  data-splask-ajax-url="<?php echo htmlspecialchars($ajaxUrl, ENT_QUOTES, 'UTF-8'); ?>"
  data-splask-csrf-token="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>"
  data-splask-history-modal="<?php echo htmlspecialchars($historyModalId, ENT_QUOTES, 'UTF-8'); ?>"
  data-splask-last-success="<?php echo htmlspecialchars((string) ($initialHealth['last_success'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
  data-splask-last-failed="<?php echo htmlspecialchars((string) ($initialHealth['last_failed'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
  data-splask-health-status="<?php echo htmlspecialchars((string) ($initialHealth['status'] ?? 'UNKNOWN'), ENT_QUOTES, 'UTF-8'); ?>"
  data-splask-missing-today="<?php echo !empty($initialHealth['missing_today']) ? 'true' : 'false'; ?>"
>
  <section class="splask-card" aria-labelledby="<?php echo htmlspecialchars($rootId, ENT_QUOTES, 'UTF-8'); ?>-title">
    <header class="splask-header">
      <h3 class="splask-title" id="<?php echo htmlspecialchars($rootId, ENT_QUOTES, 'UTF-8'); ?>-title">
        Markah Penilaian SPLaSK
      </h3>
      <span class="splask-grade-pill" data-splask-grade-short aria-label="Gred semasa">…</span>
    </header>

    <div class="splask-body">
      <?php if ($isExecutiveKpiBoard) : ?>
        <div class="splask-exec-board" aria-label="Executive analytics dashboard">
          <div class="splask-exec-primary-strip">
            <span class="splask-mode-label"><?php echo htmlspecialchars($analyticsModeLabel, ENT_QUOTES, 'UTF-8'); ?></span>
            <strong data-splask-score>0%</strong>
            <span data-splask-grade>Memuat...</span>
          </div>
          <div class="splask-kpi-strips" aria-label="Ringkasan KPI eksekutif">
            <div class="splask-kpi-strip splask-kpi-strip-score">
              <span>Strategic Health</span>
              <strong data-splask-score>0%</strong>
              <em class="splask-delta-indicator">7-day board</em>
            </div>
            <div class="splask-kpi-strip">
              <span>Governance Grade</span>
              <strong data-splask-grade>Memuat...</strong>
              <em data-splask-status><?php echo htmlspecialchars($analyticsModeLabel, ENT_QUOTES, 'UTF-8'); ?></em>
            </div>
            <div class="splask-kpi-strip splask-exec-spark-row">
              <span>7-Day Signal</span>
              <strong data-splask-date>Memuat...</strong>
              <em class="splask-sparkline splask-sparkline-exec" aria-hidden="true"><i></i><i></i><i></i><i></i><i></i><i></i><i></i></em>
            </div>
            <div class="splask-kpi-strip">
              <span>Next Review</span>
              <strong data-splask-next>Memuat...</strong>
              <em class="splask-delta-indicator">30d trend</em>
            </div>
          </div>
          <div class="splask-progress-track splask-exec-progress" aria-hidden="true">
            <div class="splask-progress-bar" data-splask-progress-bar></div>
          </div>
        </div>
      <?php elseif ($isOperationsGrid) : ?>
        <div class="splask-ops-console" aria-label="Operations monitoring dashboard">
          <div class="splask-ops-score-stream">
            <span>LIVE SCORE</span>
            <strong data-splask-score>0%</strong>
            <div class="splask-progress-track" aria-hidden="true"><div class="splask-progress-bar" data-splask-progress-bar></div></div>
          </div>
          <div class="splask-ops-grid" aria-label="Operational KPI grid">
            <div class="splask-ops-cell"><span>Grade</span><strong data-splask-grade>Memuat...</strong></div>
            <div class="splask-ops-cell"><span>Status</span><strong data-splask-status><?php echo htmlspecialchars($analyticsModeLabel, ENT_QUOTES, 'UTF-8'); ?></strong></div>
            <div class="splask-ops-cell splask-ops-mini-chart"><span>7D Throughput</span><b aria-hidden="true"><i></i><i></i><i></i><i></i><i></i></b></div>
            <div class="splask-ops-cell"><span>Last Check</span><strong data-splask-date>Memuat...</strong></div>
            <div class="splask-ops-cell"><span>Next Run</span><strong data-splask-next>Memuat...</strong></div>
            <div class="splask-ops-cell splask-ops-stack"><span>Scheduler</span><strong>Baseline</strong><em>preserved</em></div>
          </div>
        </div>
      <?php else : ?>
        <div class="splask-cyber-deck" aria-label="Neon cyber analytics dashboard">
          <div class="splask-cyber-hero">
            <div class="splask-meter splask-cyber-orb" role="img" aria-label="Peratus markah SPLaSK">
              <svg viewBox="0 0 150 150" aria-hidden="true" focusable="false">
                <circle class="splask-meter-bg" cx="75" cy="75" r="60" />
                <circle class="splask-meter-progress" cx="75" cy="75" r="60" stroke-dasharray="377" stroke-dashoffset="377" data-splask-progress-circle />
              </svg>
              <div class="splask-meter-value" data-splask-score>0%</div>
            </div>
            <div class="splask-cyber-status">
              <span class="splask-mode-label"><?php echo htmlspecialchars($analyticsModeLabel, ENT_QUOTES, 'UTF-8'); ?></span>
              <strong data-splask-grade>Memuat...</strong>
              <em data-splask-status><?php echo htmlspecialchars($analyticsModeLabel, ENT_QUOTES, 'UTF-8'); ?></em>
            </div>
          </div>
          <div class="splask-cyber-telemetry">
            <div><span>Pulse</span><strong data-splask-date>Memuat...</strong></div>
            <div><span>Re-scan</span><strong data-splask-next>Memuat...</strong></div>
            <div class="splask-cyber-wave" aria-hidden="true"><i></i><i></i><i></i><i></i></div>
          </div>
        </div>
      <?php endif; ?>

      <div class="splask-actions">
        <a class="splask-link" href="#" target="_blank" rel="noopener noreferrer" data-splask-link aria-disabled="true">
          Lihat Pengesahan Penuh
        </a>
        <button class="splask-link splask-history-button" type="button" data-splask-history-trigger data-bs-toggle="modal" data-bs-target="#<?php echo htmlspecialchars($historyModalId, ENT_QUOTES, 'UTF-8'); ?>">
          Sejarah & Analitik
        </button>
      </div>
    </div>
  </section>

  <div class="modal fade splask-history-modal" id="<?php echo htmlspecialchars($historyModalId, ENT_QUOTES, 'UTF-8'); ?>" tabindex="-1" aria-labelledby="<?php echo htmlspecialchars($historyModalId, ENT_QUOTES, 'UTF-8'); ?>-title" aria-hidden="true" data-splask-history-modal-shell>
    <div class="modal-dialog modal-dialog-centered modal-xl modal-fullscreen-sm-down">
      <div class="modal-content">
        <div class="modal-header">
          <div>
            <h4 class="modal-title" id="<?php echo htmlspecialchars($historyModalId, ENT_QUOTES, 'UTF-8'); ?>-title">Sejarah & Analitik SPLaSK</h4>
            <p class="splask-history-subtitle">Rekod markah terkini dan trend prestasi.</p>
          </div>
          <div class="splask-history-header-actions">
            <button class="splask-icon-button splask-modal-action-button splask-refresh-button" type="button" data-splask-refresh-trigger title="Refresh Analytics" aria-label="Refresh Analytics">
              <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                <path d="M17.7 6.3A7.95 7.95 0 0 0 12 4a8 8 0 1 0 7.45 10.93 1 1 0 0 0-1.86-.74A6 6 0 1 1 16.2 7.8L14 10h6V4l-2.3 2.3Z" />
              </svg>
            </button>
            <button type="button" class="splask-icon-button splask-modal-action-button splask-close-button" data-bs-dismiss="modal" title="Tutup" aria-label="Tutup">
              <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                <path d="M6.4 5 5 6.4 10.6 12 5 17.6 6.4 19 12 13.4 17.6 19 19 17.6 13.4 12 19 6.4 17.6 5 12 10.6 6.4 5Z" />
              </svg>
            </button>
          </div>
        </div>
        <div class="modal-body" data-splask-history-body>
          <div class="splask-history-loading">Memuatkan sejarah...</div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
        </div>
      </div>
    </div>
  </div>
</div>
