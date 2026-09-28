<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_splaskscore
 */

namespace Hazim\Component\Splaskscore\Administrator\Model;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\Registry\Registry;

final class CollectionlogsModel extends ListModel
{
    private const SCHEDULER_TASK_TYPE = 'splaskscore.analytics.collect';

    /** @var bool */
    private $storageReady = false;

    protected function populateState($ordering = 'h.recorded_at', $direction = 'DESC')
    {
        parent::populateState($ordering, $direction);

        $app = Factory::getApplication();
        $this->setState(
            'filter.search',
            trim((string) $app->getUserStateFromRequest($this->context . '.filter.search', 'filter_search', '', 'string'))
        );
        $this->setState(
            'filter.status',
            (string) $app->getUserStateFromRequest($this->context . '.filter.status', 'filter_status', '', 'cmd')
        );
        $this->setState(
            'filter.source',
            (string) $app->getUserStateFromRequest($this->context . '.filter.source', 'filter_source', '', 'cmd')
        );
    }

    protected function getListQuery()
    {
        $this->ensureStorage();

        $db = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('h.id'),
                $db->quoteName('h.module_id'),
                $db->quoteName('h.source'),
                $db->quoteName('h.status'),
                $db->quoteName('h.message'),
                $db->quoteName('h.recorded_at'),
                $db->quoteName('m.title', 'module_title'),
            ])
            ->from($db->quoteName('#__splaskscore_health', 'h'))
            ->innerJoin(
                $db->quoteName('#__modules', 'm')
                . ' ON ' . $db->quoteName('m.id') . ' = ' . $db->quoteName('h.module_id')
            )
            ->where($db->quoteName('m.module') . ' = ' . $db->quote('mod_splaskscore'))
            ->where($db->quoteName('m.client_id') . ' = 1');

        $status = strtolower((string) $this->getState('filter.status'));
        if (in_array($status, ['success', 'failed', 'skipped'], true)) {
            $query->where($db->quoteName('h.status') . ' = ' . $db->quote($status));
        }

        $source = strtolower((string) $this->getState('filter.source'));
        if (in_array($source, ['scheduler', 'manual'], true)) {
            $query->where($db->quoteName('h.source') . ' = ' . $db->quote($source));
        }

        $search = trim((string) $this->getState('filter.search'));
        if ($search !== '') {
            $needle = $db->quote('%' . $db->escape($search, true) . '%', false);
            $query->where(
                '(' . $db->quoteName('h.message') . ' LIKE ' . $needle
                . ' OR ' . $db->quoteName('h.source') . ' LIKE ' . $needle
                . ' OR ' . $db->quoteName('h.status') . ' LIKE ' . $needle . ')'
            );
        }

        return $query->order($db->quoteName('h.recorded_at') . ' DESC, ' . $db->quoteName('h.id') . ' DESC');
    }

    /**
     * Return site-wide collection attempt totals for the managed renderer.
     *
     * @return array<string, int|string>
     */
    public function getSummary(): array
    {
        $this->ensureStorage();

        $db = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select([
                'COUNT(*) AS ' . $db->quoteName('total_count'),
                'SUM(CASE WHEN ' . $db->quoteName('h.status') . ' = ' . $db->quote('success') . ' THEN 1 ELSE 0 END) AS ' . $db->quoteName('success_count'),
                'SUM(CASE WHEN ' . $db->quoteName('h.status') . ' = ' . $db->quote('failed') . ' THEN 1 ELSE 0 END) AS ' . $db->quoteName('failed_count'),
                'SUM(CASE WHEN ' . $db->quoteName('h.status') . ' = ' . $db->quote('skipped') . ' THEN 1 ELSE 0 END) AS ' . $db->quoteName('skipped_count'),
                'MAX(CASE WHEN ' . $db->quoteName('h.status') . ' = ' . $db->quote('success') . ' THEN ' . $db->quoteName('h.recorded_at') . ' ELSE NULL END) AS ' . $db->quoteName('last_success'),
                'MAX(CASE WHEN ' . $db->quoteName('h.status') . ' = ' . $db->quote('failed') . ' THEN ' . $db->quoteName('h.recorded_at') . ' ELSE NULL END) AS ' . $db->quoteName('last_failed'),
            ])
            ->from($db->quoteName('#__splaskscore_health', 'h'))
            ->innerJoin(
                $db->quoteName('#__modules', 'm')
                . ' ON ' . $db->quoteName('m.id') . ' = ' . $db->quoteName('h.module_id')
            )
            ->where($db->quoteName('m.module') . ' = ' . $db->quote('mod_splaskscore'))
            ->where($db->quoteName('m.client_id') . ' = 1');
        $db->setQuery($query);
        $row = $db->loadAssoc() ?: [];

        return [
            'total_count' => (int) ($row['total_count'] ?? 0),
            'success_count' => (int) ($row['success_count'] ?? 0),
            'failed_count' => (int) ($row['failed_count'] ?? 0),
            'skipped_count' => (int) ($row['skipped_count'] ?? 0),
            'last_success' => (string) ($row['last_success'] ?? ''),
            'last_failed' => (string) ($row['last_failed'] ?? ''),
        ];
    }

    /**
     * Return the read-only Joomla Scheduler status for the SPLaSK task.
     */
    public function getTaskStatus(): ?object
    {
        $this->ensureStorage();
        $db = $this->getDatabase();

        try {
            $query = $db->getQuery(true)
                ->select('*')
                ->from($db->quoteName('#__scheduler_tasks'))
                ->where($db->quoteName('type') . ' = ' . $db->quote(self::SCHEDULER_TASK_TYPE))
                ->order($db->quoteName('id') . ' ASC');
            $db->setQuery($query, 0, 1);
            $task = $db->loadObject();

            if (!$task) {
                return null;
            }

            $taskParams = json_decode((string) ($task->params ?? '{}'), true) ?: [];
            $task->cooldown_minutes = max(
                1,
                (int) ($taskParams['retry_cooldown_minutes'] ?? ($taskParams['duplicate_cooldown_minutes'] ?? 10))
            );
            $task->collection_time = (string) ($taskParams['collection_time'] ?? '06:00');
            $task->next_reason = 'daily';
            $moduleId = (int) ($taskParams['module_id'] ?? 0);

            $latestQuery = $db->getQuery(true)
                ->select([$db->quoteName('status'), $db->quoteName('recorded_at')])
                ->from($db->quoteName('#__splaskscore_health'))
                ->order($db->quoteName('recorded_at') . ' DESC, ' . $db->quoteName('id') . ' DESC');

            if ($moduleId > 0) {
                $latestQuery->where($db->quoteName('module_id') . ' = ' . $moduleId);
            }

            $db->setQuery($latestQuery, 0, 1);
            $latest = $db->loadObject();

            if ($latest && $this->isCurrentSiteDay((string) ($latest->recorded_at ?? ''))) {
                $latestStatus = strtolower((string) ($latest->status ?? ''));
                if (in_array($latestStatus, ['success', 'skipped'], true)) {
                    $task->next_reason = 'completed_today';
                } elseif ($latestStatus === 'failed'
                    && $this->isCurrentSiteDay((string) ($task->next_execution ?? ''))) {
                    $task->next_reason = 'retry';
                }
            }

            $task->overdue_seconds = 0;
            $task->is_overdue = false;
            $nextExecution = (string) ($task->next_execution ?? '');
            if ((int) ($task->state ?? 0) === 1 && empty($task->locked) && $nextExecution !== '') {
                try {
                    $next = new \DateTimeImmutable($nextExecution, new \DateTimeZone('UTC'));
                    $task->overdue_seconds = max(0, time() - $next->getTimestamp());
                    $task->is_overdue = $task->overdue_seconds > 900;
                } catch (\Throwable $exception) {
                    $task->overdue_seconds = 0;
                }
            }

            return $task;
        } catch (\Throwable $exception) {
            return null;
        }
    }

    /**
     * Run an immediate server-side collection and apply the same next-run policy.
     *
     * @return array<string, mixed>
     */
    public function runTestCollection(int $userId): array
    {
        $this->ensureStorage();
        $module = $this->getManagedModule();
        if (!$module) {
            throw new \RuntimeException('SPLaSK Score module is unavailable.');
        }

        $params = new Registry((string) ($module->params ?? '{}'));
        $token = trim((string) $params->get('splask_token', ''));
        if ($token === '') {
            throw new \RuntimeException('Token SPLaSK belum dikonfigurasi.');
        }

        $startedAt = microtime(true);
        $result = \ModSplaskscoreHelper::collectSingleModuleAnalytics(
            (int) $module->id,
            $token,
            'manual',
            'component-test:user-' . max(0, $userId)
        );
        $result['duration_ms'] = (int) round((microtime(true) - $startedAt) * 1000);

        $db = $this->getDatabase();
        $taskQuery = $db->getQuery(true)
            ->select($db->quoteName('id'))
            ->from($db->quoteName('#__scheduler_tasks'))
            ->where($db->quoteName('type') . ' = ' . $db->quote(self::SCHEDULER_TASK_TYPE))
            ->order($db->quoteName('id') . ' ASC');
        $db->setQuery($taskQuery, 0, 1);
        $taskId = (int) $db->loadResult();

        if ($taskId > 0) {
            $result['scheduler'] = \ModSplaskscoreHelper::rescheduleManagedTaskAfterCollection(
                $taskId,
                (int) $module->id,
                !empty($result['success'])
            );
        }

        return $result;
    }

    /**
     * Return every filtered log row for CSV export without pagination.
     *
     * @return array<int, object>
     */
    public function getExportRows(): array
    {
        $db = $this->getDatabase();
        $db->setQuery($this->getListQuery());

        return $db->loadObjectList() ?: [];
    }

    private function getManagedModule(): ?object
    {
        $db = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select([$db->quoteName('id'), $db->quoteName('params')])
            ->from($db->quoteName('#__modules'))
            ->where($db->quoteName('module') . ' = ' . $db->quote('mod_splaskscore'))
            ->where($db->quoteName('client_id') . ' = 1')
            ->order($db->quoteName('id') . ' ASC');
        $db->setQuery($query, 0, 1);
        $module = $db->loadObject();

        return $module ?: null;
    }

    private function isCurrentSiteDay(string $recordedAt): bool
    {
        if ($recordedAt === '') {
            return false;
        }

        try {
            $timezoneName = (string) Factory::getApplication()->get('offset', 'UTC');
            $timezone = new \DateTimeZone($timezoneName !== '' ? $timezoneName : 'UTC');
            $recorded = (new \DateTimeImmutable($recordedAt, new \DateTimeZone('UTC')))->setTimezone($timezone);
            $today = new \DateTimeImmutable('now', $timezone);

            return $recorded->format('Y-m-d') === $today->format('Y-m-d');
        } catch (\Throwable $exception) {
            return false;
        }
    }

    private function ensureStorage(): void
    {
        if ($this->storageReady) {
            return;
        }

        $helper = JPATH_ADMINISTRATOR . '/modules/mod_splaskscore/helper.php';
        if (!is_file($helper)) {
            throw new \RuntimeException('SPLaSK Score module helper is unavailable.');
        }

        require_once $helper;

        if (!class_exists('ModSplaskscoreHelper')
            || !method_exists('ModSplaskscoreHelper', 'ensureAnalyticsHealthStorage')) {
            throw new \RuntimeException('SPLaSK Score collection log provider is unavailable.');
        }

        \ModSplaskscoreHelper::ensureAnalyticsHealthStorage();
        $this->storageReady = true;
    }
}
