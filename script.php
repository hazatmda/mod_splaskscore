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
        $pluginZip = $source . '/packages/plg_task_splaskscoreanalytics.zip';

        if (is_file($pluginZip)) {
            Installer::getInstance()->install($pluginZip);
            $this->enableSchedulerPlugin();
        }

        return true;
    }

    /**
     * Keep the bundled task plugin enabled so scheduled collection is available.
     *
     * @return  void
     */
    private function enableSchedulerPlugin(): void
    {
        $db = Factory::getDbo();
        $query = $db->getQuery(true)
            ->update($db->quoteName('#__extensions'))
            ->set($db->quoteName('enabled') . ' = 1')
            ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
            ->where($db->quoteName('folder') . ' = ' . $db->quote('task'))
            ->where($db->quoteName('element') . ' = ' . $db->quote('splaskscoreanalytics'));
        $db->setQuery($query)->execute();
    }
}
