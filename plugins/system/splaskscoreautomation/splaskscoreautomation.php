<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  System.Splaskscoreautomation
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Router\Route;

/**
 * Synchronizes the SPLaSK scheduler task whenever module settings are saved.
 */
final class PlgSystemSplaskscoreautomation extends CMSPlugin
{
    private const SCHEDULER_TASK_TYPE = 'splaskscore.analytics.collect';

    /** @var bool */
    protected $autoloadLanguage = true;

    /** @var int|null */
    private $moduleExtensionId;

    /** @var int|null */
    private $schedulerTaskId;

    /**
     * Keep module creation and editing inside the singleton component workflow.
     */
    public function onAfterRoute(): void
    {
        $app = Factory::getApplication();

        if (!$app->isClient('administrator')) {
            return;
        }

        $input = $app->input;
        $option = $input->getCmd('option');

        if ($option === 'com_scheduler') {
            $this->redirectManagedSchedulerRequest();

            return;
        }

        if ($option !== 'com_modules') {
            return;
        }

        $task = $input->getCmd('task');
        $extensionId = $input->getInt('eid', 0);
        $moduleId = $input->getInt('id', 0);
        $formData = $input->post->get('jform', [], 'array');
        if ($moduleId <= 0 && is_array($formData)) {
            $moduleId = (int) ($formData['id'] ?? 0);
        }
        $redirect = $task === 'module.add' && $extensionId > 0 && $extensionId === $this->getModuleExtensionId();

        if (!$redirect && $moduleId > 0
            && (strpos($task, 'module.') === 0 || $input->getCmd('view') === 'module')) {
            $redirect = $this->isSplaskscoreModuleId($moduleId);
        }

        if (!$redirect) {
            return;
        }

        $app->enqueueMessage(Text::_('PLG_SYSTEM_SPLASKSCOREAUTOMATION_COMPONENT_ONLY_NOTICE'), 'info');
        $app->redirect(Route::_('index.php?option=com_splaskscore&view=settings', false));
        $app->close();
    }

    /**
     * Hide SPLaSK Score from Joomla's module-type picker while keeping the
     * extension enabled so its managed instance can still render.
     */
    public function onBeforeCompileHead(): void
    {
        $app = Factory::getApplication();

        if (!$app->isClient('administrator')) {
            return;
        }

        $input = $app->input;
        $option = $input->getCmd('option');
        $view = $input->getCmd('view');
        $document = $app->getDocument();

        if ($option === 'com_modules' && $view === 'select') {
            $extensionId = $this->getModuleExtensionId();
            if ($extensionId <= 0) {
                return;
            }

            if (method_exists($document, 'addStyleDeclaration')) {
                $document->addStyleDeclaration(
                    'a.comModulesSelectCard[href$="eid=' . $extensionId . '"]{display:none!important;}'
                );
            }

            return;
        }

        if ($option !== 'com_scheduler') {
            return;
        }

        if ($view === 'select' && method_exists($document, 'addStyleDeclaration')) {
            $document->addStyleDeclaration(
                'a.comSchedulerSelectCard[href*="type=' . self::SCHEDULER_TASK_TYPE . '"]{display:none!important;}'
            );

            return;
        }

        if ($view !== '' && $view !== 'tasks') {
            return;
        }

        $taskId = $this->getManagedSchedulerTaskId();
        if ($taskId <= 0 || !method_exists($document, 'addScriptDeclaration')) {
            return;
        }

        $settingsUrl = Route::_('index.php?option=com_splaskscore&view=settings', false);
        $notice = Text::_('PLG_SYSTEM_SPLASKSCOREAUTOMATION_SCHEDULER_MANAGED_NOTICE');
        $document->addScriptDeclaration(
            '(function(){document.addEventListener("DOMContentLoaded",function(){'
            . 'var checkbox=document.querySelector("input[name=\\"cid[]\\"][value=\\"' . $taskId . '\\"]");'
            . 'if(!checkbox){return;}var row=checkbox.closest("tr");if(!row){return;}'
            . 'checkbox.checked=false;checkbox.disabled=true;checkbox.style.visibility="hidden";'
            . 'var handle=row.querySelector(".sortable-handler");if(handle){handle.classList.add("inactive");handle.style.pointerEvents="none";}'
            . 'var orderInput=row.querySelector("input[name=\\"order[]\\"]");if(orderInput){orderInput.disabled=true;}'
            . 'var cells=row.querySelectorAll("td");var stateCell=cells.length>2?cells[2]:null;'
            . 'if(stateCell){stateCell.querySelectorAll("a,button").forEach(function(control){control.removeAttribute("href");control.removeAttribute("onclick");control.setAttribute("aria-disabled","true");control.setAttribute("title",'
            . json_encode($notice) . ');control.style.pointerEvents="none";});}'
            . 'var titleLink=row.querySelector("a[href*=\\"task.edit\\"]");if(titleLink){titleLink.href='
            . json_encode($settingsUrl) . ';titleLink.title=' . json_encode($notice) . ';}'
            . '});})();'
        );
    }

    /**
     * Enforce one SPLaSK Score module instance per Joomla installation.
     *
     * @return  bool
     */
    public function onContentBeforeSave($context, $table, $isNew, $data = []): bool
    {
        if ($context === 'com_scheduler.task') {
            $app = Factory::getApplication();
            $taskId = (int) ($table->id ?? 0);
            $taskType = (string) ($table->type ?? '');

            if ($app->isClient('administrator')
                && $app->input->getCmd('option') === 'com_scheduler'
                && ($taskType === self::SCHEDULER_TASK_TYPE || $this->isManagedSchedulerTaskId($taskId))) {
                throw new \RuntimeException(Text::_('PLG_SYSTEM_SPLASKSCOREAUTOMATION_SCHEDULER_MANAGED_NOTICE'));
            }

            return true;
        }

        if ($context !== 'com_modules.module'
            || (string) ($table->module ?? '') !== 'mod_splaskscore'
            || (int) ($table->client_id ?? 0) !== 1) {
            return true;
        }

        if (!$isNew && Factory::getApplication()->input->getCmd('option') === 'com_modules') {
            throw new \RuntimeException(Text::_('PLG_SYSTEM_SPLASKSCOREAUTOMATION_COMPONENT_ONLY_NOTICE'));
        }

        if (!$isNew) {
            return true;
        }

        $db = Factory::getDbo();
        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__modules'))
            ->where($db->quoteName('module') . ' = ' . $db->quote('mod_splaskscore'))
            ->where($db->quoteName('client_id') . ' = 1');
        $db->setQuery($query);

        if ((int) $db->loadResult() > 0) {
            throw new \RuntimeException(Text::_('PLG_SYSTEM_SPLASKSCOREAUTOMATION_SINGLETON_ERROR'));
        }

        return true;
    }

    /**
     * Prevent deletion of the component-managed scheduler task from Scheduler Manager.
     *
     * @return  bool
     */
    public function onContentBeforeDelete($context, $table): bool
    {
        if ($context !== 'com_scheduler.task') {
            return true;
        }

        $app = Factory::getApplication();
        $taskId = (int) ($table->id ?? 0);
        $taskType = (string) ($table->type ?? '');

        if ($app->isClient('administrator')
            && $app->input->getCmd('option') === 'com_scheduler'
            && ($taskType === self::SCHEDULER_TASK_TYPE || $this->isManagedSchedulerTaskId($taskId))) {
            throw new \RuntimeException(Text::_('PLG_SYSTEM_SPLASKSCOREAUTOMATION_SCHEDULER_MANAGED_NOTICE'));
        }

        return true;
    }

    /**
     * Keep the Joomla Scheduled Task synchronized with module parameters.
     *
     * @param   string  $context  Save context.
     * @param   object  $table    Saved table object.
     * @param   bool    $isNew    Whether the record is new.
     * @param   array   $data     Posted data.
     *
     * @return  void
     */
    public function onContentAfterSave($context, $table, $isNew, $data = []): void
    {
        if ($context !== 'com_modules.module') {
            return;
        }

        if ((string) ($table->module ?? '') !== 'mod_splaskscore' || (int) ($table->client_id ?? 0) !== 1) {
            return;
        }

        $moduleId = (int) ($table->id ?? 0);
        if ($moduleId <= 0) {
            return;
        }

        require_once JPATH_ADMINISTRATOR . '/modules/mod_splaskscore/helper.php';

        ModSplaskscoreHelper::synchronizeSchedulerForModule($moduleId);
    }

    private function getModuleExtensionId(): int
    {
        if ($this->moduleExtensionId !== null) {
            return $this->moduleExtensionId;
        }

        $db = Factory::getDbo();
        $query = $db->getQuery(true)
            ->select($db->quoteName('extension_id'))
            ->from($db->quoteName('#__extensions'))
            ->where($db->quoteName('type') . ' = ' . $db->quote('module'))
            ->where($db->quoteName('element') . ' = ' . $db->quote('mod_splaskscore'))
            ->where($db->quoteName('client_id') . ' = 1');
        $db->setQuery($query, 0, 1);
        $this->moduleExtensionId = (int) $db->loadResult();

        return $this->moduleExtensionId;
    }

    /**
     * Redirect Scheduler Manager configuration attempts to SPLaSK Score settings.
     */
    private function redirectManagedSchedulerRequest(): void
    {
        $app = Factory::getApplication();
        $input = $app->input;
        $task = strtolower($input->getCmd('task'));
        $view = $input->getCmd('view');
        $formData = $input->post->get('jform', [], 'array');
        $formData = is_array($formData) ? $formData : [];
        $taskId = $input->getInt('id', 0);

        if ($taskId <= 0) {
            $taskId = (int) ($formData['id'] ?? 0);
        }

        $taskType = (string) ($input->getString('type', '') ?: ($formData['type'] ?? ''));
        $redirect = $task === 'task.add' && $taskType === self::SCHEDULER_TASK_TYPE;
        $managedItemActions = [
            'task.edit',
            'task.apply',
            'task.save',
            'task.save2copy',
            'task.save2new',
        ];

        if (!$redirect && in_array($task, $managedItemActions, true)) {
            $redirect = $taskType === self::SCHEDULER_TASK_TYPE || $this->isManagedSchedulerTaskId($taskId);
        }

        if (!$redirect && $view === 'task' && $this->isManagedSchedulerTaskId($taskId)) {
            $redirect = true;
        }

        $managedListActions = [
            'tasks.archive',
            'tasks.checkin',
            'tasks.delete',
            'tasks.orderdown',
            'tasks.orderup',
            'tasks.publish',
            'tasks.saveorderajax',
            'tasks.trash',
            'tasks.unpublish',
        ];

        if (!$redirect && in_array($task, $managedListActions, true)) {
            $selectedIds = array_map('intval', (array) $input->get('cid', [], 'array'));
            $redirect = in_array($this->getManagedSchedulerTaskId(), $selectedIds, true);
        }

        if (!$redirect) {
            return;
        }

        $app->enqueueMessage(Text::_('PLG_SYSTEM_SPLASKSCOREAUTOMATION_SCHEDULER_MANAGED_NOTICE'), 'info');
        $app->redirect(Route::_('index.php?option=com_splaskscore&view=settings', false));
        $app->close();
    }

    private function getManagedSchedulerTaskId(): int
    {
        if ($this->schedulerTaskId !== null) {
            return $this->schedulerTaskId;
        }

        try {
            $db = Factory::getDbo();
            $query = $db->getQuery(true)
                ->select($db->quoteName('id'))
                ->from($db->quoteName('#__scheduler_tasks'))
                ->where($db->quoteName('type') . ' = ' . $db->quote(self::SCHEDULER_TASK_TYPE))
                ->order($db->quoteName('id') . ' ASC');
            $db->setQuery($query, 0, 1);
            $this->schedulerTaskId = (int) $db->loadResult();
        } catch (\Throwable $exception) {
            $this->schedulerTaskId = 0;
        }

        return $this->schedulerTaskId;
    }

    private function isManagedSchedulerTaskId(int $taskId): bool
    {
        return $taskId > 0 && $taskId === $this->getManagedSchedulerTaskId();
    }

    private function isSplaskscoreModuleId(int $moduleId): bool
    {
        $db = Factory::getDbo();
        $query = $db->getQuery(true)
            ->select($db->quoteName('id'))
            ->from($db->quoteName('#__modules'))
            ->where($db->quoteName('id') . ' = ' . $moduleId)
            ->where($db->quoteName('module') . ' = ' . $db->quote('mod_splaskscore'))
            ->where($db->quoteName('client_id') . ' = 1');
        $db->setQuery($query, 0, 1);

        return (int) $db->loadResult() === $moduleId;
    }
}
