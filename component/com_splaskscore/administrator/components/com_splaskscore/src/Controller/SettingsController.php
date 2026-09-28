<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_splaskscore
 */

namespace Hazim\Component\Splaskscore\Administrator\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Response\JsonResponse;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;

final class SettingsController extends BaseController
{
    public function save(): void
    {
        $this->persist(false);
    }

    public function apply(): void
    {
        $this->persist(true);
    }

    public function cancel(): void
    {
        $this->setRedirect(Route::_('index.php?option=com_splaskscore&view=analytics', false));
    }

    /**
     * Return the stored token only after an explicit, authorized reveal action.
     */
    public function revealToken(): void
    {
        $app = $this->app;
        $app->setHeader('Content-Type', 'application/json; charset=utf-8', true);
        $app->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate', true);

        try {
            if (!Session::checkToken()) {
                throw new \RuntimeException(Text::_('JINVALID_TOKEN'), 403);
            }

            $moduleId = $this->input->post->getInt('module_id', 0);
            $token = $this->getAuthorizedStoredToken($moduleId);

            echo new JsonResponse(['token' => $token], null, false, true);
        } catch (\Throwable $exception) {
            echo new JsonResponse(null, $exception->getMessage(), true, true);
        }

        $app->close();
    }

    /**
     * Non-JavaScript fallback for the reveal button.
     */
    public function revealTokenPage(): void
    {
        $app = $this->app;

        try {
            if (!Session::checkToken()) {
                throw new \RuntimeException(Text::_('JINVALID_TOKEN'), 403);
            }

            $moduleId = $this->input->post->getInt('module_id', 0);
            $this->getAuthorizedStoredToken($moduleId);
            $app->setUserState($this->getRevealStateKey(), $moduleId);
        } catch (\Throwable $exception) {
            $app->setUserState($this->getRevealStateKey(), null);
            $app->enqueueMessage($exception->getMessage(), 'error');
        }

        $this->setRedirect(Route::_('index.php?option=com_splaskscore&view=settings', false));
    }

    /**
     * Non-JavaScript fallback that removes a previously revealed token page.
     */
    public function hideTokenPage(): void
    {
        if (!Session::checkToken()) {
            throw new \RuntimeException(Text::_('JINVALID_TOKEN'), 403);
        }

        $this->assertCanManageComponent();
        $this->app->setUserState($this->getRevealStateKey(), null);
        $this->setRedirect(Route::_('index.php?option=com_splaskscore&view=settings', false));
    }

    private function persist(bool $stay): void
    {
        if (!Session::checkToken()) {
            throw new \RuntimeException(Text::_('JINVALID_TOKEN'), 403);
        }

        $this->assertCanManageComponent();
        $data = $this->input->post->get('jform', [], 'array');
        $moduleId = max(0, (int) ($data['module_id'] ?? 0));
        $user = $this->app->getIdentity();

        if (!$user->authorise('core.admin')
            && (!$user->authorise('core.edit', 'com_modules.module.' . $moduleId)
                || !$user->authorise('core.edit.state', 'com_modules.module.' . $moduleId))) {
            throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        /** @var \Hazim\Component\Splaskscore\Administrator\Model\SettingsModel $model */
        $model = $this->getModel('Settings');

        try {
            $result = $model->saveSettings($moduleId, $data);
            $this->app->enqueueMessage(Text::_('COM_SPLASHSCORE_SETTINGS_SAVE_SUCCESS'), 'success');

            if (empty($result['scheduler']['success'])) {
                $message = (string) ($result['scheduler']['message'] ?? Text::_('COM_SPLASHSCORE_SCHEDULER_SYNC_WARNING'));
                $this->app->enqueueMessage($message, 'warning');
            }
        } catch (\Throwable $exception) {
            $this->app->enqueueMessage($exception->getMessage(), 'error');
            $stay = true;
        }

        $url = $stay
            ? 'index.php?option=com_splaskscore&view=settings'
            : 'index.php?option=com_splaskscore&view=analytics';

        $this->setRedirect(Route::_($url, false));
    }

    private function assertCanManageComponent(): void
    {
        $user = $this->app->getIdentity();

        if (!$user->authorise('core.admin') && !$user->authorise('core.manage', 'com_splaskscore')) {
            throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }
    }

    private function getAuthorizedStoredToken(int $moduleId): string
    {
        $this->assertCanManageComponent();
        $user = $this->app->getIdentity();

        if (!$user->authorise('core.admin')
            && (!$user->authorise('core.edit', 'com_modules.module.' . $moduleId)
                || !$user->authorise('core.edit.state', 'com_modules.module.' . $moduleId))) {
            throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        /** @var \Hazim\Component\Splaskscore\Administrator\Model\SettingsModel $model */
        $model = $this->getModel('Settings');
        $token = $model->getStoredToken($moduleId);

        if ($token === '') {
            throw new \RuntimeException(Text::_('COM_SPLASHSCORE_TOKEN_NOT_CONFIGURED'), 404);
        }

        return $token;
    }

    private function getRevealStateKey(): string
    {
        return 'com_splaskscore.settings.reveal_token.' . (int) $this->app->getIdentity()->id;
    }
}
