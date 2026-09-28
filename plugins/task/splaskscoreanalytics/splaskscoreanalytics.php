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
use Joomla\Event\EventInterface;
use Joomla\Event\SubscriberInterface;

/**
 * Joomla Scheduled Task plugin that records SPLaSK analytics snapshots.
 */
final class PlgTaskSplaskscoreanalytics extends CMSPlugin implements SubscriberInterface
{
    use TaskPluginTrait;

    private const TASK_TYPE = 'splaskscore.analytics.collect';

    /**
     * Joomla Scheduler routine map. The module settings manage creation,
     * enable/disable state, and timing (default 6:00 AM) for this task.
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

    /** @var array<string, mixed> */
    private $collectionResult = [];

    /**
     * @return  array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'onTaskOptionsList' => 'advertiseRoutines',
            'onExecuteTask' => 'standardRoutineHandler',
            'onTaskExecuteSuccess' => 'rescheduleAfterExecution',
            'onTaskExecuteFailure' => 'rescheduleAfterExecution',
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
        $this->collectionResult = $result;

        if (method_exists($event, 'setResultSnapshot')) {
            $event->setResultSnapshot('SPLaSK analytics snapshots processed: ' . (int) ($result['count'] ?? 0));
        }

        return !empty($result['success']) ? Status::OK : Status::KNOCKOUT;
    }

    /**
     * Apply the daily-success/retry schedule after Joomla has released the task lock.
     */
    public function rescheduleAfterExecution(EventInterface $event): void
    {
        $task = $event->getArgument('subject');
        if (!is_object($task) || !method_exists($task, 'get') || $task->get('type') !== self::TASK_TYPE) {
            return;
        }

        require_once JPATH_ADMINISTRATOR . '/modules/mod_splaskscore/helper.php';

        ModSplaskscoreHelper::rescheduleManagedTaskAfterCollection(
            (int) $task->get('id'),
            (int) $task->get('params.module_id', 0),
            !empty($this->collectionResult['success'])
        );
    }
}
