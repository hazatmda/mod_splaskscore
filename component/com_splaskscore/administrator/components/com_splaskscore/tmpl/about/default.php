<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_splaskscore
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;

$document = $this->getDocument();
$assetBase = Uri::root(true) . '/media/com_splaskscore';
$assetVersion = class_exists('ModSplaskscoreHelper') ? ModSplaskscoreHelper::getEngineVersion() : '1.9.5';
$document->addStyleSheet($assetBase . '/css/admin.css?v=' . rawurlencode($assetVersion));
?>
<div class="com-splaskscore-about">
    <section class="splaskscore-component-hero">
        <div>
            <p class="text-uppercase small fw-semibold mb-2"><?php echo Text::_('COM_SPLASHSCORE_ABOUT_PRODUCT_INFORMATION'); ?></p>
            <h2 class="mb-2"><?php echo Text::_('COM_SPLASHSCORE_ABOUT_HEADING'); ?></h2>
            <p class="mb-0"><?php echo Text::_('COM_SPLASHSCORE_ABOUT_INTRO'); ?></p>
        </div>
        <span class="badge rounded-pill bg-success"><?php echo Text::_('COM_SPLASHSCORE_ABOUT_STATUS_STABLE'); ?></span>
    </section>

    <section class="row g-4 mt-1" aria-label="<?php echo Text::_('COM_SPLASHSCORE_ABOUT_PRODUCT_DETAILS'); ?>">
        <div class="col-12 col-md-6 col-xl-4">
            <div class="card h-100 splaskscore-about-card">
                <div class="card-body">
                    <div class="text-body-secondary small mb-1"><?php echo Text::_('COM_SPLASHSCORE_ABOUT_OWNER'); ?></div>
                    <div class="fw-semibold"><?php echo Text::_('COM_SPLASHSCORE_ABOUT_OWNER_VALUE'); ?></div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-6 col-xl-8">
            <div class="card h-100 splaskscore-about-card">
                <div class="card-body">
                    <div class="text-body-secondary small mb-1"><?php echo Text::_('COM_SPLASHSCORE_ABOUT_ORGANIZATION'); ?></div>
                    <div class="fw-semibold"><?php echo Text::_('COM_SPLASHSCORE_ABOUT_ORGANIZATION_VALUE'); ?></div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="card h-100 splaskscore-about-card">
                <div class="card-body">
                    <div class="text-body-secondary small mb-1"><?php echo Text::_('COM_SPLASHSCORE_ABOUT_VERSION'); ?></div>
                    <div class="fw-semibold">v<?php echo htmlspecialchars($this->version, ENT_QUOTES, 'UTF-8'); ?></div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="card h-100 splaskscore-about-card">
                <div class="card-body">
                    <div class="text-body-secondary small mb-1"><?php echo Text::_('COM_SPLASHSCORE_ABOUT_JOOMLA'); ?></div>
                    <div class="fw-semibold">Joomla 5.x</div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="card h-100 splaskscore-about-card">
                <div class="card-body">
                    <div class="text-body-secondary small mb-1"><?php echo Text::_('COM_SPLASHSCORE_ABOUT_PHP'); ?></div>
                    <div class="fw-semibold">PHP 8.1+</div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-6">
            <div class="card h-100 splaskscore-about-card">
                <div class="card-body">
                    <div class="text-body-secondary small mb-1"><?php echo Text::_('COM_SPLASHSCORE_ABOUT_REPOSITORY'); ?></div>
                    <a class="fw-semibold" href="https://github.com/hazatmda/mod_splaskscore" target="_blank" rel="noopener noreferrer">github.com/hazatmda/mod_splaskscore</a>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-6">
            <div class="card h-100 splaskscore-about-card">
                <div class="card-body">
                    <div class="text-body-secondary small mb-1"><?php echo Text::_('COM_SPLASHSCORE_ABOUT_ISSUES'); ?></div>
                    <a class="fw-semibold" href="https://github.com/hazatmda/mod_splaskscore/issues" target="_blank" rel="noopener noreferrer"><?php echo Text::_('COM_SPLASHSCORE_ABOUT_ISSUES_LINK'); ?></a>
                </div>
            </div>
        </div>
    </section>

    <section class="card mt-4 splaskscore-about-architecture">
        <div class="card-body p-4">
            <h3 class="h5 mb-4"><?php echo Text::_('COM_SPLASHSCORE_ABOUT_ARCHITECTURE'); ?></h3>
            <div class="row g-4">
                <div class="col-12 col-lg-4">
                    <h4 class="h6 mb-2"><?php echo Text::_('COM_SPLASHSCORE_ABOUT_SCOPE'); ?></h4>
                    <p class="mb-0 text-body-secondary"><?php echo Text::_('COM_SPLASHSCORE_ABOUT_SCOPE_DESC'); ?></p>
                </div>
                <div class="col-12 col-lg-4">
                    <h4 class="h6 mb-2"><?php echo Text::_('COM_SPLASHSCORE_ABOUT_DAILY_POLICY'); ?></h4>
                    <p class="mb-0 text-body-secondary"><?php echo Text::_('COM_SPLASHSCORE_ABOUT_DAILY_POLICY_DESC'); ?></p>
                </div>
                <div class="col-12 col-lg-4">
                    <h4 class="h6 mb-2"><?php echo Text::_('COM_SPLASHSCORE_ABOUT_DASHBOARD'); ?></h4>
                    <p class="mb-0 text-body-secondary"><?php echo Text::_('COM_SPLASHSCORE_ABOUT_DASHBOARD_DESC'); ?></p>
                </div>
            </div>
        </div>
    </section>

    <p class="small text-body-secondary mt-4 mb-0">
        <?php echo Text::_('COM_SPLASHSCORE_ABOUT_LICENSE'); ?>:
        <?php echo Text::_('COM_SPLASHSCORE_ABOUT_LICENSE_VALUE'); ?>
    </p>
</div>
