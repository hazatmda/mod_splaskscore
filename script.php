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
use Joomla\Database\DatabaseInterface;

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
            if (!$this->installBundledExtension($packagesPath . '/com_splaskscore.zip')
                || !$this->installBundledPlugin($packagesPath . '/plg_task_splaskscoreanalytics.zip')
                || !$this->installBundledPlugin($packagesPath . '/plg_system_splaskscoreautomation.zip')) {
                return false;
            }
        }

        $this->enablePlugin('task', 'splaskscoreanalytics');
        $this->enablePlugin('system', 'splaskscoreautomation');
        $this->ensureSingleModuleInstance();
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
     * @return  bool
     */
    private function installBundledPlugin(string $pluginZip): bool
    {
        return $this->installBundledExtension($pluginZip);
    }

    /**
     * Install a bundled Joomla extension ZIP after unpacking it to a real installer path.
     *
     * @param   string  $extensionZip  Absolute path to the bundled extension ZIP.
     *
     * @return  bool
     */
    private function installBundledExtension(string $extensionZip): bool
    {
        if (!is_file($extensionZip)) {
            return false;
        }

        $package = InstallerHelper::unpack($extensionZip, true);
        if (!is_array($package)) {
            return false;
        }

        $installPath = isset($package['dir']) ? (string) $package['dir'] : '';
        $installed = false;

        if (!empty($package['type']) && $installPath !== '' && is_dir($installPath)) {
            // Do not reuse Joomla's global installer while its package adapter is
            // still active. A nested install on that singleton overwrites the
            // parent package's manifest and source paths.
            $nestedInstaller = new Installer();
            $nestedInstaller->setDatabase(Factory::getContainer()->get(DatabaseInterface::class));
            $installed = $nestedInstaller->install($installPath);
        }

        $packageFile = isset($package['packagefile']) ? (string) $package['packagefile'] : '';
        $extractDir = isset($package['extractdir']) ? (string) $package['extractdir'] : '';
        if (($packageFile !== '' && is_file($packageFile)) || ($extractDir !== '' && is_dir($extractDir))) {
            InstallerHelper::cleanupInstall($packageFile, $extractDir);
        }

        return $installed;
    }

    /**
     * Ensure the component always has one dashboard module instance to manage.
     *
     * The instance starts outside the cpanel position so installation never
     * changes an administrator dashboard until an operator enables it in the
     * component. It remains published because dashboard visibility and
     * scheduled analytics collection are separate controls.
     *
     * @return  void
     */
    private function ensureSingleModuleInstance(): void
    {
        try {
            $app = Factory::getApplication();
            $component = method_exists($app, 'bootComponent') ? $app->bootComponent('com_splaskscore') : null;

            if (!$component || !method_exists($component, 'getMVCFactory')) {
                throw new \RuntimeException('SPLaSK Score component is unavailable.');
            }

            $model = $component->getMVCFactory()->createModel('Settings', 'Administrator', ['ignore_request' => true]);

            if (!$model || !method_exists($model, 'ensureSingleModuleInstance')) {
                throw new \RuntimeException('SPLaSK Score settings model is unavailable.');
            }

            $model->ensureSingleModuleInstance();
        } catch (\Throwable $exception) {
            // The package remains usable: the component Dashboard can retry
            // creation when the operator first enables dashboard display.
            $app = Factory::getApplication();
            if (method_exists($app, 'enqueueMessage')) {
                $app->enqueueMessage(
                    'SPLaSK Score could not create its managed dashboard instance automatically: ' . $exception->getMessage(),
                    'warning'
                );
            }
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
