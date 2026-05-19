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
$miniTrendSeries = $token !== '' ? ModSplaskscoreHelper::getDashboardMiniTrendSeries($moduleId, hash('sha256', (string) $token)) : [];
$miniTrendJson = htmlspecialchars(json_encode($miniTrendSeries, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]', ENT_QUOTES, 'UTF-8');
$branding = ModSplaskscoreHelper::getBranding($params);
$brandingJson = ModSplaskscoreHelper::getBrandingJson($branding);
$clockSeed = ModSplaskscoreHelper::getJoomlaClockSeed();
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
  data-splask-labels="<?php echo $brandingJson; ?>"
  data-splask-clock-timezone="<?php echo htmlspecialchars($clockSeed['timezone'], ENT_QUOTES, 'UTF-8'); ?>"
  data-splask-clock-epoch="<?php echo (int) $clockSeed['epoch']; ?>"
>
  <section class="splask-card" aria-labelledby="<?php echo htmlspecialchars($rootId, ENT_QUOTES, 'UTF-8'); ?>-title">
    <header class="splask-header">
      <div class="splask-heading">
        <h3 class="splask-title" id="<?php echo htmlspecialchars($rootId, ENT_QUOTES, 'UTF-8'); ?>-title">
          <?php echo htmlspecialchars($branding['dashboard_title'], ENT_QUOTES, 'UTF-8'); ?>
        </h3>
        <?php if ($branding['dashboard_subtitle'] !== '') : ?>
          <p class="splask-subtitle"><?php echo htmlspecialchars($branding['dashboard_subtitle'], ENT_QUOTES, 'UTF-8'); ?></p>
        <?php endif; ?>
      </div>
      <div class="splask-header-telemetry" aria-label="Masa semasa Joomla">
        <span class="splask-live-clock" data-splask-live-clock><?php echo htmlspecialchars($clockSeed['display'], ENT_QUOTES, 'UTF-8'); ?></span>
        <span class="splask-grade-pill" data-splask-grade-short aria-label="Gred semasa">…</span>
      </div>
    </header>

    <div class="splask-body">
      <div class="splask-ops-console" aria-label="<?php echo htmlspecialchars($branding['dashboard_subtitle'], ENT_QUOTES, 'UTF-8'); ?>">
        <div class="splask-ops-score-stream" aria-label="Hierarki skor utama">
          <strong data-splask-score>0%</strong>
          <span data-splask-grade><?php echo htmlspecialchars($branding['score_loading_label'], ENT_QUOTES, 'UTF-8'); ?></span>
          <em data-splask-status><?php echo htmlspecialchars($branding['status_waiting_label'], ENT_QUOTES, 'UTF-8'); ?></em>
        </div>
        <div class="splask-ops-grid" aria-label="Grid KPI operasi">
          <div class="splask-ops-cell splask-ops-mini-chart"><span><?php echo htmlspecialchars($branding['kpi_mini_trend_label'], ENT_QUOTES, 'UTF-8'); ?></span><div class="splask-ops-trend-wrap"><canvas data-splask-mini-trend data-splask-mini-trend-points="<?php echo $miniTrendJson; ?>" width="320" height="96" aria-label="<?php echo htmlspecialchars($branding['graph_mini_trend_aria_label'], ENT_QUOTES, 'UTF-8'); ?>" role="img"></canvas></div></div>
          <div class="splask-ops-cell splask-ops-date-cell"><span><?php echo htmlspecialchars($branding['kpi_check_date_label'], ENT_QUOTES, 'UTF-8'); ?></span><strong data-splask-date><?php echo htmlspecialchars($branding['score_loading_label'], ENT_QUOTES, 'UTF-8'); ?></strong></div>
          <div class="splask-ops-cell splask-ops-next-cell"><span><?php echo htmlspecialchars($branding['kpi_next_check_label'], ENT_QUOTES, 'UTF-8'); ?></span><strong data-splask-next><?php echo htmlspecialchars($branding['score_loading_label'], ENT_QUOTES, 'UTF-8'); ?></strong></div>
        </div>
      </div>

      <div class="splask-actions">
        <a class="splask-link" href="#" target="_blank" rel="noopener noreferrer" data-splask-link aria-disabled="true">
          <?php echo htmlspecialchars($branding['button_verification_label'], ENT_QUOTES, 'UTF-8'); ?>
        </a>
        <button class="splask-link splask-history-button" type="button" data-splask-history-trigger data-bs-toggle="modal" data-bs-target="#<?php echo htmlspecialchars($historyModalId, ENT_QUOTES, 'UTF-8'); ?>">
          <?php echo htmlspecialchars($branding['button_history_label'], ENT_QUOTES, 'UTF-8'); ?>
        </button>
      </div>
    </div>
  </section>

  <div class="modal fade splask-history-modal" id="<?php echo htmlspecialchars($historyModalId, ENT_QUOTES, 'UTF-8'); ?>" tabindex="-1" aria-labelledby="<?php echo htmlspecialchars($historyModalId, ENT_QUOTES, 'UTF-8'); ?>-title" aria-hidden="true" data-splask-history-modal-shell>
    <div class="modal-dialog modal-dialog-centered modal-xl modal-fullscreen-sm-down">
      <div class="modal-content">
        <div class="modal-header">
          <div>
            <h4 class="modal-title" id="<?php echo htmlspecialchars($historyModalId, ENT_QUOTES, 'UTF-8'); ?>-title"><?php echo htmlspecialchars($branding['analytics_title'], ENT_QUOTES, 'UTF-8'); ?></h4>
            <p class="splask-history-subtitle"><?php echo htmlspecialchars($branding['analytics_subtitle'], ENT_QUOTES, 'UTF-8'); ?></p>
          </div>
          <div class="splask-history-header-actions">
            <button class="splask-icon-button splask-modal-action-button splask-refresh-button" type="button" data-splask-refresh-trigger title="<?php echo htmlspecialchars($branding['button_refresh_label'], ENT_QUOTES, 'UTF-8'); ?>" aria-label="<?php echo htmlspecialchars($branding['button_refresh_label'], ENT_QUOTES, 'UTF-8'); ?>">
              <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                <path d="M17.7 6.3A7.95 7.95 0 0 0 12 4a8 8 0 1 0 7.45 10.93 1 1 0 0 0-1.86-.74A6 6 0 1 1 16.2 7.8L14 10h6V4l-2.3 2.3Z" />
              </svg>
            </button>
            <button type="button" class="splask-icon-button splask-modal-action-button splask-close-button" data-bs-dismiss="modal" title="<?php echo htmlspecialchars($branding['button_close_label'], ENT_QUOTES, 'UTF-8'); ?>" aria-label="<?php echo htmlspecialchars($branding['button_close_label'], ENT_QUOTES, 'UTF-8'); ?>">
              <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                <path d="M6.4 5 5 6.4 10.6 12 5 17.6 6.4 19 12 13.4 17.6 19 19 17.6 13.4 12 19 6.4 17.6 5 12 10.6 6.4 5Z" />
              </svg>
            </button>
          </div>
        </div>
        <div class="modal-body" data-splask-history-body>
          <div class="splask-history-loading"><?php echo htmlspecialchars($branding['history_loading_label'], ENT_QUOTES, 'UTF-8'); ?></div>
        </div>
      </div>
    </div>
  </div>
</div>
