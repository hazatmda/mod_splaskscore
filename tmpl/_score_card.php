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
use Joomla\CMS\Uri\Uri;

$preset = $splaskPreset ?? 'modern_circle';
$presetLabel = $splaskPresetLabel ?? 'Modern Circle';
$moduleId = isset($module) ? (int) $module->id : 0;
$rootId = 'mod-splaskscore-' . $moduleId . '-' . preg_replace('/[^a-z0-9_-]/i', '-', $preset);
$assetBase = Uri::root(true) . '/media/mod_splaskscore';
$appearanceMode = ModSplaskscoreHelper::getAppearanceMode($params);

$document = Factory::getApplication()->getDocument();
$document->addStyleSheet($assetBase . '/css/splaskscore.css');
$document->addScript($assetBase . '/js/splaskscore.js', ['defer' => true]);
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
>
  <section class="splask-card" aria-labelledby="<?php echo htmlspecialchars($rootId, ENT_QUOTES, 'UTF-8'); ?>-title">
    <header class="splask-header">
      <h3 class="splask-title" id="<?php echo htmlspecialchars($rootId, ENT_QUOTES, 'UTF-8'); ?>-title">
        Markah Penilaian SPLaSK
      </h3>
      <span class="splask-grade-pill" data-splask-grade-short aria-label="Gred semasa">--</span>
    </header>

    <div class="splask-body">
      <div class="splask-score-row">
        <div class="splask-meter" role="img" aria-label="Peratus markah SPLaSK">
          <svg viewBox="0 0 150 150" aria-hidden="true" focusable="false">
            <circle class="splask-meter-bg" cx="75" cy="75" r="60" />
            <circle
              class="splask-meter-progress"
              cx="75"
              cy="75"
              r="60"
              stroke-dasharray="377"
              stroke-dashoffset="377"
              data-splask-progress-circle
            />
          </svg>
          <div class="splask-meter-value" data-splask-score>0%</div>
        </div>

        <div class="splask-summary">
          <p class="splask-grade-text" data-splask-grade>Memuat...</p>
          <p class="splask-status" data-splask-status><?php echo htmlspecialchars($presetLabel, ENT_QUOTES, 'UTF-8'); ?></p>
          <div class="splask-progress-track" aria-hidden="true">
            <div class="splask-progress-bar" data-splask-progress-bar></div>
          </div>
        </div>
      </div>

      <ul class="splask-meta" aria-label="Maklumat semakan SPLaSK">
        <li><span>Kemaskini Terakhir</span><strong data-splask-date>---</strong></li>
        <li><span>Semakan Seterusnya</span><strong data-splask-next>---</strong></li>
      </ul>

      <a class="splask-link" href="#" target="_blank" rel="noopener noreferrer" data-splask-link aria-disabled="true">
        Lihat Pengesahan Penuh
      </a>
    </div>
  </section>
</div>
