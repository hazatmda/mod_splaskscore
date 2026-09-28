<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_splaskscore
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Uri\Uri;

$document = $this->getDocument();
$componentAssetBase = Uri::root(true) . '/media/com_splaskscore';
$moduleAssetBase = Uri::root(true) . '/media/mod_splaskscore';
$assetVersion = class_exists('ModSplaskscoreHelper') ? ModSplaskscoreHelper::getEngineVersion() : '1.9.6';
$document->addStyleSheet($componentAssetBase . '/css/admin.css?v=' . rawurlencode($assetVersion));
$document->addStyleSheet($moduleAssetBase . '/css/splaskscore.css?v=' . rawurlencode($assetVersion));
$document->addScript($moduleAssetBase . '/js/splaskscore.js?v=' . rawurlencode($assetVersion), [], ['defer' => true]);

$analytics = $this->analytics;
$appearance = (string) ($analytics['appearance'] ?? 'light');
$snapshot = is_array($analytics['snapshot'] ?? null) ? $analytics['snapshot'] : ['has_record' => false];
$health = is_array($analytics['health'] ?? null) ? $analytics['health'] : [];
$branding = is_array($analytics['branding'] ?? null) ? $analytics['branding'] : [];
$snapshotJson = htmlspecialchars(json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}', ENT_QUOTES, 'UTF-8');
$ajaxUrl = Uri::base(true) . '/index.php?option=com_ajax&module=splaskscore&format=json';
?>
<div class="com-splaskscore-analytics">
    <section class="splaskscore-component-hero">
        <div>
            <p class="text-uppercase small fw-semibold mb-2"><?php echo Text::_('COM_SPLASHSCORE_COMPONENT_LABEL'); ?></p>
            <h2 class="mb-2"><?php echo Text::_('COM_SPLASHSCORE_ANALYTICS_HEADING'); ?></h2>
            <p class="mb-0"><?php echo Text::_('COM_SPLASHSCORE_ANALYTICS_INTRO'); ?></p>
        </div>
        <span class="badge bg-info text-dark"><?php echo Text::_('COM_SPLASHSCORE_ANALYTICS_PERSISTED'); ?></span>
    </section>

    <?php if (!$this->module) : ?>
        <div class="alert alert-warning mt-4" role="alert">
            <h3 class="h5"><?php echo Text::_('COM_SPLASHSCORE_NO_MODULE_TITLE'); ?></h3>
            <p class="mb-0"><?php echo Text::_('COM_SPLASHSCORE_NO_MODULE_DESC'); ?></p>
        </div>
    <?php else : ?>
        <div class="d-flex justify-content-end mt-4 mb-4">
            <a class="btn btn-outline-secondary" href="<?php echo Route::_('index.php?option=com_splaskscore&view=settings'); ?>">
                <?php echo Text::_('COM_SPLASHSCORE_MANAGE_SETTINGS'); ?>
            </a>
        </div>

        <?php if (empty($analytics['success'])) : ?>
            <div class="alert alert-warning" role="alert">
                <?php echo htmlspecialchars((string) ($analytics['message'] ?? Text::_('COM_SPLASHSCORE_ANALYTICS_UNAVAILABLE')), ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php else : ?>
            <div
                class="splask-widget splask-component-analytics splask-appearance-<?php echo htmlspecialchars($appearance, ENT_QUOTES, 'UTF-8'); ?>"
                style="<?php echo ModSplaskscoreHelper::getGradeThemeStyle(); ?>"
                data-splask-widget
                data-splask-snapshot="<?php echo $snapshotJson; ?>"
                data-splask-grade-rules="<?php echo ModSplaskscoreHelper::getGradeRulesJson(); ?>"
                data-splask-appearance-mode="<?php echo htmlspecialchars($appearance, ENT_QUOTES, 'UTF-8'); ?>"
                data-splask-appearance="<?php echo $appearance === 'auto' ? 'auto' : htmlspecialchars($appearance, ENT_QUOTES, 'UTF-8'); ?>"
                data-splask-module-id="<?php echo (int) $this->module->id; ?>"
                data-splask-ajax-url="<?php echo htmlspecialchars($ajaxUrl, ENT_QUOTES, 'UTF-8'); ?>"
                data-splask-csrf-token="<?php echo htmlspecialchars(Session::getFormToken(), ENT_QUOTES, 'UTF-8'); ?>"
                data-splask-last-success="<?php echo htmlspecialchars((string) ($health['last_success'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                data-splask-last-failed="<?php echo htmlspecialchars((string) ($health['last_failed'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                data-splask-health-status="<?php echo htmlspecialchars((string) ($health['status'] ?? 'UNKNOWN'), ENT_QUOTES, 'UTF-8'); ?>"
                data-splask-missing-today="<?php echo !empty($health['missing_today']) ? 'true' : 'false'; ?>"
                data-splask-labels="<?php echo ModSplaskscoreHelper::getBrandingJson($branding); ?>"
            >
                <?php echo (string) $analytics['html']; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
