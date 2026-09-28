<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_splaskscore
 */

namespace Hazim\Component\Splaskscore\Administrator\View\Analytics;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;

final class HtmlView extends BaseHtmlView
{
    public ?object $module = null;

    /** @var array<string, mixed> */
    public array $analytics = [];

    public function display($tpl = null): void
    {
        $model = $this->getModel();
        $this->module = $model->getModule();

        if ($this->module) {
            $this->analytics = $model->getAnalyticsReport((int) $this->module->id);
        }

        ToolbarHelper::title(Text::_('COM_SPLASHSCORE_ANALYTICS_TITLE'), 'chart');

        if (Factory::getApplication()->getIdentity()->authorise('core.admin', 'com_splaskscore')) {
            ToolbarHelper::preferences('com_splaskscore');
        }

        parent::display($tpl);
    }
}
