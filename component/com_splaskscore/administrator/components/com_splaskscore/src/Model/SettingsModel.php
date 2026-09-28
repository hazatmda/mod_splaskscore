<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_splaskscore
 */

namespace Hazim\Component\Splaskscore\Administrator\Model;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\FormModel;
use Joomla\Registry\Registry;

final class SettingsModel extends FormModel
{
    private const EDITABLE_FIELDS = [
        'splask_token',
        'appearance_mode',
        'analytics_auto_enabled',
        'analytics_frequency',
        'analytics_collection_time',
        'analytics_duplicate_cooldown',
        'analytics_retention_enabled',
        'analytics_retention_days',
        'analytics_max_rows',
        'analytics_health_retention_days',
        'analytics_rows_per_page',
        'dashboard_title',
        'dashboard_subtitle',
        'analytics_title',
        'analytics_subtitle',
        'button_verification_label',
        'button_history_label',
        'button_refresh_label',
        'button_refresh_loading_label',
        'button_refresh_current_label',
        'button_refresh_failed_label',
        'button_close_label',
        'kpi_mini_trend_label',
        'kpi_check_date_label',
        'kpi_next_check_label',
        'kpi_score_today_label',
        'kpi_lowest_score_label',
        'kpi_total_records_label',
        'kpi_total_records_caption',
        'kpi_rows_per_page_label',
        'graph_mini_trend_aria_label',
        'graph_history_chart_aria_label',
        'history_table_aria_label',
        'history_table_date_label',
        'history_table_time_label',
        'history_table_score_label',
        'history_table_grade_label',
        'history_table_status_label',
        'score_loading_label',
        'status_waiting_label',
        'history_pagination_aria_label',
        'history_previous_label',
        'history_next_label',
        'history_page_status_template',
        'history_page_number_aria_template',
        'history_empty_label',
        'history_loading_label',
        'history_load_failed_label',
        'history_connection_error_label',
    ];

    public function getForm($data = [], $loadData = true): Form
    {
        $form = $this->loadForm(
            'com_splaskscore.settings',
            'settings',
            ['control' => 'jform', 'load_data' => $loadData]
        );

        if (!$form) {
            throw new \RuntimeException('Unable to load SPLaSK Score settings form.');
        }

        return $form;
    }

    protected function loadFormData(): array
    {
        $module = $this->getModule();

        if (!$module) {
            return [];
        }

        $params = new Registry((string) ($module->params ?? '{}'));
        $data = $params->toArray();
        $data['splask_token_status'] = trim((string) $params->get('splask_token', '')) !== ''
            ? Text::_('COM_SPLASHSCORE_TOKEN_CONFIGURED')
            : Text::_('COM_SPLASHSCORE_TOKEN_NOT_CONFIGURED');
        // Never send the stored token back into administrator HTML.
        $data['splask_token'] = '';
        $data['module_id'] = (int) $module->id;
        $data['module_title'] = (string) $module->title;
        $data['dashboard_display_enabled'] = (
            (int) $module->published === 1 && (string) $module->position === 'cpanel'
        ) ? 1 : 0;

        return $data;
    }

    /**
     * Return the single component-managed module instance.
     */
    public function getModule(): ?object
    {
        $db = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('id'),
                $db->quoteName('title'),
                $db->quoteName('params'),
                $db->quoteName('published'),
                $db->quoteName('position'),
            ])
            ->from($db->quoteName('#__modules'))
            ->where($db->quoteName('module') . ' = ' . $db->quote('mod_splaskscore'))
            ->where($db->quoteName('client_id') . ' = 1')
            ->order($db->quoteName('id') . ' ASC');
        $db->setQuery($query);
        $modules = $db->loadObjectList() ?: [];
        $selected = null;
        $selectedScore = -1;

        foreach ($modules as $module) {
            $params = new Registry((string) ($module->params ?? '{}'));
            $configured = trim((string) $params->get('splask_token', '')) !== '';
            $published = (int) $module->published === 1;
            $displayed = $published && (string) $module->position === 'cpanel';
            $score = ($configured ? 20 : 0) + ($published ? 10 : 0) + ($displayed ? 20 : 0);

            if ($score > $selectedScore) {
                $selected = $module;
                $selectedScore = $score;
            }
        }

        return $selected;
    }

    /**
     * Return the stored token only to the authorized controller endpoint.
     */
    public function getStoredToken(int $moduleId): string
    {
        $module = $this->getModule();

        if (!$module || (int) $module->id !== $moduleId) {
            throw new \RuntimeException('SPLaSK Score administrator module instance was not found.');
        }

        $params = new Registry((string) ($module->params ?? '{}'));

        return trim((string) $params->get('splask_token', ''));
    }

    /**
     * Build the read-only scheduler, timezone and hosting-cron diagnostics panel.
     *
     * @return array<string, mixed>
     */
    public function getSchedulerDiagnostics(int $moduleId): array
    {
        $module = $this->getModule();
        $timezoneName = (string) Factory::getApplication()->get('offset', 'UTC');

        try {
            $timezone = new \DateTimeZone($timezoneName !== '' ? $timezoneName : 'UTC');
        } catch (\Throwable $exception) {
            $timezoneName = 'UTC';
            $timezone = new \DateTimeZone('UTC');
        }

        $nowLocal = new \DateTimeImmutable('now', $timezone);
        $params = $module && (int) $module->id === $moduleId
            ? new Registry((string) ($module->params ?? '{}'))
            : new Registry();
        $collectionTime = (string) $params->get('analytics_collection_time', '06:00');

        if (!preg_match('/^(\d{1,2}):(\d{2})$/', $collectionTime, $matches)) {
            $collectionTime = '06:00';
            $matches = [null, '06', '00'];
        }

        $dailyTarget = $nowLocal->setTime(
            max(0, min(23, (int) $matches[1])),
            max(0, min(59, (int) $matches[2])),
            0
        );
        if ($dailyTarget <= $nowLocal) {
            $dailyTarget = $dailyTarget->modify('+1 day');
        }

        $task = null;
        $recentStatuses = [];
        $db = $this->getDatabase();

        try {
            $taskQuery = $db->getQuery(true)
                ->select('*')
                ->from($db->quoteName('#__scheduler_tasks'))
                ->where($db->quoteName('type') . ' = ' . $db->quote('splaskscore.analytics.collect'))
                ->order($db->quoteName('id') . ' ASC');
            $db->setQuery($taskQuery, 0, 1);
            $task = $db->loadObject() ?: null;

            $helper = JPATH_ADMINISTRATOR . '/modules/mod_splaskscore/helper.php';
            if (is_file($helper)) {
                require_once $helper;
                if (class_exists('ModSplaskscoreHelper')
                    && method_exists('ModSplaskscoreHelper', 'ensureAnalyticsHealthStorage')) {
                    \ModSplaskscoreHelper::ensureAnalyticsHealthStorage();
                }
            }

            if ($moduleId > 0) {
                $healthQuery = $db->getQuery(true)
                    ->select([$db->quoteName('status'), $db->quoteName('recorded_at')])
                    ->from($db->quoteName('#__splaskscore_health'))
                    ->where($db->quoteName('module_id') . ' = ' . $moduleId)
                    ->order($db->quoteName('recorded_at') . ' DESC, ' . $db->quoteName('id') . ' DESC');
                $db->setQuery($healthQuery, 0, 5);
                $recentStatuses = $db->loadObjectList() ?: [];
            }
        } catch (\Throwable $exception) {
            $task = null;
            $recentStatuses = [];
        }

        $consecutiveFailures = 0;
        foreach ($recentStatuses as $record) {
            if (strtolower((string) ($record->status ?? '')) !== 'failed') {
                break;
            }

            $consecutiveFailures++;
        }

        $overdueMinutes = 0;
        if ($task && (int) ($task->state ?? 0) === 1 && empty($task->locked) && !empty($task->next_execution)) {
            try {
                $next = new \DateTimeImmutable((string) $task->next_execution, new \DateTimeZone('UTC'));
                $overdueMinutes = max(0, (int) floor((time() - $next->getTimestamp()) / 60));
            } catch (\Throwable $exception) {
                $overdueMinutes = 0;
            }
        }

        $health = 'healthy';
        if (!$task) {
            $health = 'missing';
        } elseif ((int) ($task->state ?? 0) !== 1) {
            $health = 'inactive';
        } elseif (empty($task->last_execution)) {
            $health = 'never_run';
        } elseif ($overdueMinutes > 15) {
            $health = 'overdue';
        } elseif ($consecutiveFailures >= 3) {
            $health = 'repeated_failures';
        }

        $phpBinary = (string) PHP_BINARY;
        $binaryName = strtolower(basename($phpBinary));
        if ($phpBinary === ''
            || strpos($binaryName, 'fpm') !== false
            || strpos($binaryName, 'cgi') !== false
            || strpos($binaryName, 'apache') !== false) {
            $phpBinary = '/usr/bin/php';
        }

        return [
            'task' => $task,
            'health' => $health,
            'overdue_minutes' => $overdueMinutes,
            'consecutive_failures' => $consecutiveFailures,
            'timezone_name' => $timezoneName,
            'timezone_offset' => $nowLocal->format('P'),
            'next_daily_utc' => $dailyTarget->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
            'collection_time' => $collectionTime,
            'cron_command' => '*/5 * * * * cd ' . escapeshellarg(JPATH_ROOT)
                . ' && ' . escapeshellarg($phpBinary) . ' cli/joomla.php scheduler:run',
        ];
    }

    /**
     * Create the singleton instance when necessary and retire legacy duplicates.
     */
    public function ensureSingleModuleInstance(): object
    {
        $module = $this->getModule();

        if (!$module) {
            $module = $this->createManagedModuleInstance();
        }

        $db = $this->getDatabase();
        $displayed = (int) $module->published === 1 && (string) $module->position === 'cpanel';
        $primaryUpdate = $db->getQuery(true)
            ->update($db->quoteName('#__modules'))
            ->set($db->quoteName('published') . ' = 1')
            ->set($db->quoteName('position') . ' = ' . $db->quote($displayed ? 'cpanel' : ''))
            ->where($db->quoteName('id') . ' = ' . (int) $module->id);
        $db->setQuery($primaryUpdate)->execute();
        $module->published = 1;
        $module->position = $displayed ? 'cpanel' : '';

        $query = $db->getQuery(true)
            ->select([$db->quoteName('id'), $db->quoteName('params')])
            ->from($db->quoteName('#__modules'))
            ->where($db->quoteName('module') . ' = ' . $db->quote('mod_splaskscore'))
            ->where($db->quoteName('client_id') . ' = 1')
            ->where($db->quoteName('id') . ' <> ' . (int) $module->id);
        $db->setQuery($query);
        $duplicates = $db->loadObjectList() ?: [];
        $duplicateIds = [];

        foreach ($duplicates as $duplicate) {
            $duplicateId = (int) $duplicate->id;
            $duplicateParams = new Registry((string) ($duplicate->params ?? '{}'));
            $duplicateParams->set('splask_token', '');
            $duplicateParams->set('analytics_auto_enabled', '0');
            $duplicateParams->set('analytics_scheduler_status', 'Retired duplicate');

            $update = $db->getQuery(true)
                ->update($db->quoteName('#__modules'))
                ->set($db->quoteName('published') . ' = 0')
                ->set($db->quoteName('position') . ' = ' . $db->quote(''))
                ->set($db->quoteName('params') . ' = ' . $db->quote((string) $duplicateParams))
                ->where($db->quoteName('id') . ' = ' . $duplicateId);
            $db->setQuery($update)->execute();
            $duplicateIds[] = $duplicateId;
        }

        if ($duplicateIds) {
            $delete = $db->getQuery(true)
                ->delete($db->quoteName('#__modules_menu'))
                ->where($db->quoteName('moduleid') . ' IN (' . implode(',', $duplicateIds) . ')');
            $db->setQuery($delete)->execute();
        }

        return $module;
    }

    /**
     * Create the internal dashboard renderer without exposing Add Module setup.
     */
    private function createManagedModuleInstance(): object
    {
        $app = Factory::getApplication();
        $component = method_exists($app, 'bootComponent') ? $app->bootComponent('com_modules') : null;

        if (!$component || !method_exists($component, 'getMVCFactory')) {
            throw new \RuntimeException('Joomla Module Manager is unavailable.');
        }

        $model = $component->getMVCFactory()->createModel('Module', 'Administrator', ['ignore_request' => true]);

        if (!$model || !method_exists($model, 'save')) {
            throw new \RuntimeException('Joomla Module model is unavailable.');
        }

        $data = [
            'title' => 'SPLaSK Score',
            'note' => 'Managed exclusively by com_splaskscore',
            'content' => '',
            'ordering' => 0,
            'position' => '',
            'published' => 1,
            'module' => 'mod_splaskscore',
            'access' => 1,
            'showtitle' => 0,
            'client_id' => 1,
            'language' => '*',
            'assignment' => 0,
            'params' => [
                'appearance_mode' => 'light',
                'analytics_auto_enabled' => '0',
                'analytics_frequency' => 'daily',
                'analytics_collection_time' => '06:00',
                'analytics_duplicate_cooldown' => 10,
                'analytics_retention_enabled' => '0',
                'analytics_retention_days' => 365,
                'analytics_max_rows' => 500,
                'analytics_health_retention_days' => 90,
                'analytics_rows_per_page' => 7,
                'layout' => '_:dashboard_tile',
                'module_tag' => 'div',
                'bootstrap_size' => '12',
                'header_tag' => 'h2',
                'header_class' => '',
                'style' => '0',
            ],
        ];

        if (!$model->save($data)) {
            $error = method_exists($model, 'getError') ? (string) $model->getError() : '';
            throw new \RuntimeException($error !== '' ? $error : 'SPLaSK Score dashboard module could not be created.');
        }

        $module = $this->getModule();

        if (!$module) {
            throw new \RuntimeException('The SPLaSK Score dashboard module could not be loaded after creation.');
        }

        return $module;
    }

    /**
     * @return array{scheduler: array<string, mixed>}
     */
    public function saveSettings(int $moduleId, array $data): array
    {
        $module = $this->getModule();

        if (!$module || (int) $module->id !== $moduleId) {
            throw new \RuntimeException('SPLaSK Score administrator module instance was not found.');
        }

        $form = $this->getForm([], false);
        $filtered = $form->filter($data);

        if (!is_array($filtered) || !$form->validate($filtered)) {
            throw new \RuntimeException('SPLaSK Score settings contain invalid values.');
        }

        $params = new Registry((string) ($module->params ?? '{}'));
        $existingToken = trim((string) $params->get('splask_token', ''));
        $submittedToken = trim((string) ($filtered['splask_token'] ?? ''));

        if ($submittedToken === '' && $existingToken === '') {
            throw new \RuntimeException(Text::_('COM_SPLASHSCORE_TOKEN_REQUIRED'));
        }

        foreach (self::EDITABLE_FIELDS as $field) {
            if ($field === 'splask_token' && $submittedToken === '') {
                continue;
            }

            if (array_key_exists($field, $filtered)) {
                $params->set($field, $filtered[$field]);
            }
        }

        // The automated policy is one successful collection per local day.
        $params->set('analytics_frequency', 'daily');

        $displayEnabled = (int) ($filtered['dashboard_display_enabled'] ?? 0) === 1;
        $db = $this->getDatabase();

        try {
            $db->transactionStart();
            $query = $db->getQuery(true)
                ->update($db->quoteName('#__modules'))
                ->set($db->quoteName('params') . ' = ' . $db->quote((string) $params))
                ->set($db->quoteName('published') . ' = 1')
                ->set($db->quoteName('position') . ' = ' . $db->quote($displayEnabled ? 'cpanel' : ''))
                ->where($db->quoteName('id') . ' = ' . $moduleId)
                ->where($db->quoteName('module') . ' = ' . $db->quote('mod_splaskscore'))
                ->where($db->quoteName('client_id') . ' = 1');
            $db->setQuery($query)->execute();

            if ($displayEnabled) {
                $delete = $db->getQuery(true)
                    ->delete($db->quoteName('#__modules_menu'))
                    ->where($db->quoteName('moduleid') . ' = ' . $moduleId);
                $db->setQuery($delete)->execute();

                $insert = $db->getQuery(true)
                    ->insert($db->quoteName('#__modules_menu'))
                    ->columns($db->quoteName(['moduleid', 'menuid']))
                    ->values($moduleId . ', 0');
                $db->setQuery($insert)->execute();
            }

            $db->transactionCommit();
        } catch (\Throwable $exception) {
            try {
                $db->transactionRollback();
            } catch (\Throwable $rollbackException) {
                // Preserve the original failure.
            }

            throw $exception;
        }

        try {
            Factory::getCache('com_modules')->clean();
            Factory::getCache('mod_splaskscore')->clean();
        } catch (\Throwable $exception) {
            // The committed database state is authoritative.
        }

        $helper = JPATH_ADMINISTRATOR . '/modules/mod_splaskscore/helper.php';
        $scheduler = ['success' => false, 'message' => 'SPLaSK Score module helper is unavailable.'];

        if (is_file($helper)) {
            require_once $helper;
            if (class_exists('ModSplaskscoreHelper')) {
                $scheduler = \ModSplaskscoreHelper::synchronizeSchedulerForModule($moduleId);
            }
        }

        return ['scheduler' => $scheduler, 'display_enabled' => $displayEnabled];
    }
}
