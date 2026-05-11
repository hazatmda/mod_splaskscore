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
use Joomla\CMS\Installer\InstallerHelper;

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
        $packagesPath = $this->resolvePackagesPath($parent);

        if ($packagesPath !== '') {
            $this->installBundledPlugin($packagesPath . '/plg_task_splaskscoreanalytics.zip');
            $this->installBundledPlugin($packagesPath . '/plg_system_splaskscoreautomation.zip');
        }

        $this->enablePlugin('task', 'splaskscoreanalytics');
        $this->enablePlugin('system', 'splaskscoreautomation');
        $this->syncExistingModuleScheduler();

        return true;
    }

    /**
     * Resolve the extracted module packages directory before accessing plugin ZIPs.
     *
     * Joomla installer postflight runs while the uploaded package is still in the
     * temporary extraction tree, so bundled plugin ZIPs must be resolved from an
     * existing source path and unpacked before they are passed back to Installer.
     *
     * @param   object  $parent  Installer adapter.
     *
     * @return  string
     */
    private function resolvePackagesPath($parent): string
    {
        if (!method_exists($parent, 'getParent')) {
            return '';
        }

        $installer = $parent->getParent();
        if (!is_object($installer) || !method_exists($installer, 'getPath')) {
            return '';
        }

        $source = (string) $installer->getPath('source');
        if ($source === '') {
            return '';
        }

        $packagesPath = $source . '/packages';

        return is_dir($packagesPath) ? $packagesPath : '';
    }

    /**
     * Install a bundled plugin ZIP after unpacking it to a real installer path.
     *
     * @param   string  $pluginZip  Absolute path to the bundled plugin ZIP.
     *
     * @return  void
     */
    private function installBundledPlugin(string $pluginZip): void
    {
        if (!is_file($pluginZip)) {
            return;
        }

        $package = InstallerHelper::unpack($pluginZip);
        if (!is_array($package)) {
            return;
        }

        $installPath = isset($package['dir']) ? (string) $package['dir'] : '';
        if ($installPath !== '' && is_dir($installPath)) {
            Installer::getInstance()->install($installPath);
        }

        $packageFile = isset($package['packagefile']) ? (string) $package['packagefile'] : '';
        $extractDir = isset($package['extractdir']) ? (string) $package['extractdir'] : '';
        if (($packageFile !== '' && is_file($packageFile)) || ($extractDir !== '' && is_dir($extractDir))) {
            InstallerHelper::cleanupInstall($packageFile, $extractDir);
        }
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
