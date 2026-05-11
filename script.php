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
use Joomla\CMS\Installer\Installer;

/**
 * Installer script for module upgrades that need the scheduler plugin.
 */
final class mod_splaskscoreInstallerScript
{
    /**
     * Install or upgrade bundled sub-extensions after the module update.
     *
     * @param   string  $type    Install action.
     * @param   object  $parent  Installer adapter.
     *
     * @return  bool
     */
    public function postflight(string $type, $parent): bool
    {
        $source = method_exists($parent, 'getParent') ? (string) $parent->getParent()->getPath('source') : '';
        $taskPluginZip = $source . '/packages/plg_task_splaskscoreanalytics.zip';
        $systemPluginZip = $source . '/packages/plg_system_splaskscoreautomation.zip';

        foreach ([$taskPluginZip, $systemPluginZip] as $pluginZip) {
            if (is_file($pluginZip)) {
                Installer::getInstance()->install($pluginZip);
            }
        }

        $this->enablePlugin('task', 'splaskscoreanalytics');
        $this->enablePlugin('system', 'splaskscoreautomation');
        $this->syncExistingModuleScheduler();

        return true;
    }

    /**
     * Synchronize existing module instances during install/upgrade.
     *
     * @return  void
     */
    private function syncExistingModuleScheduler(): void
    {
        $helper = JPATH_ADMINISTRATOR . '/modules/mod_splaskscore/helper.php';
        if (is_file($helper)) {
            require_once $helper;
            if (class_exists('ModSplaskscoreHelper')) {
                ModSplaskscoreHelper::synchronizeSchedulerForAllModules();
            }
        }
    }

    /**
     * Keep a bundled plugin enabled so scheduled collection is available.
     *
     * @return  void
     */
    private function enablePlugin(string $folder, string $element): void
    {
        $db = Factory::getDbo();
        $query = $db->getQuery(true)
            ->update($db->quoteName('#__extensions'))
            ->set($db->quoteName('enabled') . ' = 1')
            ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
            ->where($db->quoteName('folder') . ' = ' . $db->quote($folder))
            ->where($db->quoteName('element') . ' = ' . $db->quote($element));
        $db->setQuery($query)->execute();
    }
}
