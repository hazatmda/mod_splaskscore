<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  System.Splaskscoreautomation
 */

defined('_JEXEC') or die;

use Joomla\CMS\Plugin\CMSPlugin;

/**
 * Synchronizes the SPLaSK scheduler task whenever module settings are saved.
 */
final class PlgSystemSplaskscoreautomation extends CMSPlugin
{
    /** @var bool */
    protected $autoloadLanguage = true;

    /**
     * Keep the Joomla Scheduled Task synchronized with module parameters.
     *
     * @param   string  $context  Save context.
     * @param   object  $table    Saved table object.
     * @param   bool    $isNew    Whether the record is new.
     * @param   array   $data     Posted data.
     *
     * @return  void
     */
    public function onContentAfterSave($context, $table, $isNew, $data = []): void
    {
        if ($context !== 'com_modules.module') {
            return;
        }

        if ((string) ($table->module ?? '') !== 'mod_splaskscore' || (int) ($table->client_id ?? 0) !== 1) {
            return;
        }

        $moduleId = (int) ($table->id ?? 0);
        if ($moduleId <= 0) {
            return;
        }

        require_once JPATH_ADMINISTRATOR . '/modules/mod_splaskscore/helper.php';
        ModSplaskscoreHelper::synchronizeSchedulerForModule($moduleId);
    }
}
