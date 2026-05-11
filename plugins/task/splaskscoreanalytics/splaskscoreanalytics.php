<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  Task.Splaskscoreanalytics
 *
 * @copyright   Copyright (C) 2025 Muhammad Azizan Hazim
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Component\Scheduler\Administrator\Event\ExecuteTaskEvent;
use Joomla\Component\Scheduler\Administrator\Task\Status;
use Joomla\Component\Scheduler\Administrator\Traits\TaskPluginTrait;
use Joomla\Event\SubscriberInterface;

/**
 * Joomla Scheduled Task plugin that records SPLaSK analytics snapshots.
 */
final class PlgTaskSplaskscoreanalytics extends CMSPlugin implements SubscriberInterface
{
    use TaskPluginTrait;

    private const TASK_TYPE = 'splaskscore.analytics.collect';

    /**
     * Joomla Scheduler routine map. Site owners should create this task with a
     * daily execution rule at 6:00 AM in the Joomla Scheduled Tasks UI.
     */
    protected const TASKS_MAP = [
        self::TASK_TYPE => [
            'langConstPrefix' => 'PLG_TASK_SPLASKSCOREANALYTICS_COLLECT',
            'method' => 'collectAnalytics',
            'form' => 'collect',
        ],
    ];

    /** @var bool */
    protected $autoloadLanguage = true;

    /**
     * @return  array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'onTaskOptionsList' => 'advertiseRoutines',
            'onExecuteTask' => 'standardRoutineHandler',
            'onContentPrepareForm' => 'enhanceTaskItemForm',
        ];
    }

    /**
     * Execute the scheduled analytics collection routine.
     *
     * @param   ExecuteTaskEvent  $event  Scheduler execution event.
     *
     * @return  int
     */
    private function collectAnalytics(ExecuteTaskEvent $event): int
    {
        require_once JPATH_ADMINISTRATOR . '/modules/mod_splaskscore/helper.php';

        $result = ModSplaskscoreHelper::collectScheduledAnalytics();

        if (method_exists($event, 'setResultSnapshot')) {
            $event->setResultSnapshot('SPLaSK analytics snapshots processed: ' . (int) ($result['count'] ?? 0));
        }

        return !empty($result['success']) ? Status::OK : Status::KNOCKOUT;
    }
}
