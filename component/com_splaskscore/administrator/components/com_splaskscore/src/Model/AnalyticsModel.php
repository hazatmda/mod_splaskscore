<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_splaskscore
 */

namespace Hazim\Component\Splaskscore\Administrator\Model;

defined('_JEXEC') or die;

use Joomla\CMS\MVC\Model\BaseDatabaseModel;

final class AnalyticsModel extends BaseDatabaseModel
{
    /**
     * Return the single component-managed dashboard renderer.
     */
    public function getModule(): ?object
    {
        $db = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('id'),
                $db->quoteName('title'),
                $db->quoteName('published'),
                $db->quoteName('position'),
            ])
            ->from($db->quoteName('#__modules'))
            ->where($db->quoteName('module') . ' = ' . $db->quote('mod_splaskscore'))
            ->where($db->quoteName('client_id') . ' = 1')
            ->order(
                'CASE WHEN ' . $db->quoteName('published') . ' = 1 AND '
                . $db->quoteName('position') . ' = ' . $db->quote('cpanel')
                . ' THEN 0 WHEN ' . $db->quoteName('published') . ' = 1 THEN 1 ELSE 2 END ASC, '
                . $db->quoteName('id') . ' ASC'
            );
        $db->setQuery($query, 0, 1);
        $module = $db->loadObject();

        return $module ?: null;
    }

    /**
     * @return array<string, mixed>
     */
    public function getAnalyticsReport(int $moduleId): array
    {
        $helper = JPATH_ADMINISTRATOR . '/modules/mod_splaskscore/helper.php';

        if (!is_file($helper)) {
            return [
                'success' => false,
                'message' => 'SPLaSK Score module helper is unavailable.',
            ];
        }

        require_once $helper;

        if (!class_exists('ModSplaskscoreHelper') || !method_exists('ModSplaskscoreHelper', 'getComponentAnalyticsReport')) {
            return [
                'success' => false,
                'message' => 'SPLaSK Score analytics provider is unavailable.',
            ];
        }

        return \ModSplaskscoreHelper::getComponentAnalyticsReport($moduleId);
    }
}
