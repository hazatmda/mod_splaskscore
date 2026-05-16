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

$preset = 'dashboard_tile';
$presetLabel = $splaskPresetLabel ?? '';
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
$analyticsModeLabel = 'Grid Operasi';
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
      <div class="splask-ops-console" aria-label="Papan pemuka pemantauan operasi">
        <div class="splask-ops-score-stream" aria-label="Hierarki skor utama">
          <strong data-splask-score>0%</strong>
          <span data-splask-grade>Memuatkan...</span>
          <em data-splask-status>Menunggu semakan</em>
        </div>
        <div class="splask-ops-grid" aria-label="Grid KPI operasi">
          <div class="splask-ops-cell splask-ops-mini-chart"><span>Trend 7 Hari</span><div class="splask-ops-trend-wrap"><canvas data-splask-mini-trend width="320" height="96" aria-label="Graf garis trend operasi tujuh hari" role="img"></canvas></div></div>
          <div class="splask-ops-cell"><span>Tarikh Semakan</span><strong data-splask-date>Memuatkan...</strong></div>
          <div class="splask-ops-cell"><span>Semakan Seterusnya</span><strong data-splask-next>Memuatkan...</strong></div>
        </div>
      </div>

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
            <button class="splask-icon-button splask-modal-action-button splask-refresh-button" type="button" data-splask-refresh-trigger title="Segar Semula Analitik" aria-label="Segar Semula Analitik">
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
