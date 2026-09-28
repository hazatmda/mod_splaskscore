<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_splaskscore
 */

namespace Hazim\Component\Splaskscore\Administrator\View\About;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;

final class HtmlView extends BaseHtmlView
{
    public string $version = '';

    public function display($tpl = null): void
    {
        $helper = JPATH_ADMINISTRATOR . '/modules/mod_splaskscore/helper.php';

        if (is_file($helper)) {
            require_once $helper;
        }

        $this->version = class_exists('ModSplaskscoreHelper')
            ? \ModSplaskscoreHelper::getEngineVersion()
            : '1.9.0';

        ToolbarHelper::title(Text::_('COM_SPLASHSCORE_ABOUT_TITLE'), 'info-circle');

        if (Factory::getApplication()->getIdentity()->authorise('core.admin', 'com_splaskscore')) {
            ToolbarHelper::preferences('com_splaskscore');
        }

        parent::display($tpl);
    }
}
