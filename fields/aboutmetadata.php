<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  mod_splaskscore
 */

defined('_JEXEC') or die;

use Joomla\CMS\Form\FormField;

require_once dirname(__DIR__) . '/helper.php';

final class JFormFieldAboutMetadata extends FormField
{
    protected $type = 'aboutmetadata';

    protected function getLabel(): string
    {
        return '';
    }

    protected function getInput(): string
    {
        $version = ModSplaskscoreHelper::getEngineVersion();

        return '<div class="card border shadow-sm bg-body text-body">'
            . '<div class="card-body p-4">'
            . '<div class="d-flex flex-column flex-lg-row justify-content-between gap-3 mb-4">'
            . '<div><p class="text-uppercase text-body-secondary fw-semibold small mb-1">Product Information</p>'
            . '<h3 class="h5 mb-1">SPLaSK Score</h3>'
            . '<p class="mb-0 text-body-secondary">Administrator dashboard module with managed Joomla Scheduled Task analytics.</p></div>'
            . '<span class="badge rounded-pill text-bg-success align-self-lg-start">Stable</span></div>'
            . '<div class="row g-3">'
            . '<div class="col-12 col-md-6"><div class="border rounded-3 p-3 h-100 bg-body-tertiary"><div class="small text-body-secondary mb-1">Owner</div><div class="fw-semibold">Muhammad Azizan Hazim</div></div></div>'
            . '<div class="col-12 col-md-6"><div class="border rounded-3 p-3 h-100 bg-body-tertiary"><div class="small text-body-secondary mb-1">Organization</div><div class="fw-semibold">Unit Infrastruktur dan Keselamatan Digital, Bahagian Digital dan Teknologi Maklumat (BDTM)</div></div></div>'
            . '<div class="col-12 col-md-6"><div class="border rounded-3 p-3 h-100 bg-body-tertiary"><div class="small text-body-secondary mb-1">Repository</div><a class="fw-semibold text-decoration-none" href="https://github.com/hazatmda/mod_splaskscore" target="_blank" rel="noopener noreferrer">github.com/hazatmda/mod_splaskscore</a></div></div>'
            . '<div class="col-12 col-md-6"><div class="border rounded-3 p-3 h-100 bg-body-tertiary"><div class="small text-body-secondary mb-1">Issue Tracker</div><a class="fw-semibold text-decoration-none" href="https://github.com/hazatmda/mod_splaskscore/issues" target="_blank" rel="noopener noreferrer">GitHub Issues</a></div></div>'
            . '<div class="col-12 col-md-4"><div class="border rounded-3 p-3 h-100 bg-body-tertiary"><div class="small text-body-secondary mb-1">Version</div><div class="fw-semibold">' . htmlspecialchars($version, ENT_QUOTES, 'UTF-8') . '</div></div></div>'
            . '<div class="col-12 col-md-4"><div class="border rounded-3 p-3 h-100 bg-body-tertiary"><div class="small text-body-secondary mb-1">Joomla Compatibility</div><div class="fw-semibold">Joomla 5.x</div></div></div>'
            . '<div class="col-12 col-md-4"><div class="border rounded-3 p-3 h-100 bg-body-tertiary"><div class="small text-body-secondary mb-1">PHP Compatibility</div><div class="fw-semibold">PHP 8.1+</div></div></div>'
            . '</div></div></div>';
    }
}
