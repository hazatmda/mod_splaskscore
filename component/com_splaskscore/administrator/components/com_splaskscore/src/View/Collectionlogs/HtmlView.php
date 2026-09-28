<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_splaskscore
 */

namespace Hazim\Component\Splaskscore\Administrator\View\Collectionlogs;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;

final class HtmlView extends BaseHtmlView
{
    /** @var array<int, object> */
    public array $items = [];

    /** @var array<string, int|string> */
    public array $summary = [];

    public ?object $taskStatus = null;

    public $pagination;

    public $state;

    public function display($tpl = null): void
    {
        $model = $this->getModel();

        try {
            $this->items = $model->getItems() ?: [];
            $this->summary = $model->getSummary();
            $this->taskStatus = $model->getTaskStatus();
            $this->pagination = $model->getPagination();
            $this->state = $model->getState();
        } catch (\Throwable $exception) {
            Factory::getApplication()->enqueueMessage($exception->getMessage(), 'error');
            $this->items = [];
            $this->summary = [
                'total_count' => 0,
                'success_count' => 0,
                'failed_count' => 0,
                'skipped_count' => 0,
                'last_success' => '',
                'last_failed' => '',
            ];
            $this->taskStatus = null;
        }

        ToolbarHelper::title(Text::_('COM_SPLASHSCORE_COLLECTION_LOGS_TITLE'), 'history');

        if (Factory::getApplication()->getIdentity()->authorise('core.admin', 'com_splaskscore')) {
            ToolbarHelper::preferences('com_splaskscore');
        }

        parent::display($tpl);
    }
}
