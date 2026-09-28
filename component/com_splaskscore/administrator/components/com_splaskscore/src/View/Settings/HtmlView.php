<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_splaskscore
 */

namespace Hazim\Component\Splaskscore\Administrator\View\Settings;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;

final class HtmlView extends BaseHtmlView
{
    public ?Form $form = null;

    public ?object $module = null;

    public bool $canEdit = false;

    public bool $tokenConfigured = false;

    public string $revealedToken = '';

    /** @var array<string, mixed> */
    public array $schedulerDiagnostics = [];

    public function display($tpl = null): void
    {
        $model = $this->getModel();
        $app = Factory::getApplication();
        $user = $app->getIdentity();
        $this->module = $model->getModule();

        if ($user->authorise('core.admin')
            || ($user->authorise('core.create', 'com_modules')
                && $user->authorise('core.edit.state', 'com_modules'))) {
            try {
                $this->module = $model->ensureSingleModuleInstance();
            } catch (\Throwable $exception) {
                $app->enqueueMessage($exception->getMessage(), 'error');
            }
        }

        if ($this->module) {
            $this->form = $model->getForm();
            $this->tokenConfigured = $model->getStoredToken((int) $this->module->id) !== '';
            $this->schedulerDiagnostics = $model->getSchedulerDiagnostics((int) $this->module->id);
            $this->canEdit = $user->authorise('core.admin')
                || ($user->authorise('core.edit', 'com_modules.module.' . (int) $this->module->id)
                    && $user->authorise('core.edit.state', 'com_modules.module.' . (int) $this->module->id));

            $revealStateKey = 'com_splaskscore.settings.reveal_token.' . (int) $user->id;
            $revealModuleId = (int) $app->getUserState($revealStateKey, 0);
            $app->setUserState($revealStateKey, null);

            if ($this->canEdit && $revealModuleId === (int) $this->module->id) {
                $this->revealedToken = $model->getStoredToken((int) $this->module->id);
                $app->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate', true);
            }
        }

        ToolbarHelper::title(Text::_('COM_SPLASHSCORE_SETTINGS_TITLE'), 'cog');

        if ($user->authorise('core.admin', 'com_splaskscore')) {
            ToolbarHelper::preferences('com_splaskscore');
        }

        if ($this->module && $this->canEdit) {
            ToolbarHelper::apply('settings.apply');
            ToolbarHelper::save('settings.save');
        }

        ToolbarHelper::cancel('settings.cancel', 'JTOOLBAR_CLOSE');

        parent::display($tpl);
    }
}
