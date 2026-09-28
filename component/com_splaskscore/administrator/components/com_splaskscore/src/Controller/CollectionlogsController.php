<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_splaskscore
 */

namespace Hazim\Component\Splaskscore\Administrator\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;

final class CollectionlogsController extends BaseController
{
    public function test(): void
    {
        if (!Session::checkToken()) {
            throw new \RuntimeException(Text::_('JINVALID_TOKEN'), 403);
        }

        $this->assertAuthorized();
        $app = $this->app;

        try {
            $model = $this->getModel('Collectionlogs');
            $result = $model->runTestCollection((int) $app->getIdentity()->id);
            $duration = (int) ($result['duration_ms'] ?? 0);
            $message = (string) ($result['message'] ?? '');

            if (!empty($result['success'])) {
                $app->enqueueMessage(
                    Text::sprintf('COM_SPLASHSCORE_COLLECTION_TEST_SUCCESS', $duration, $message),
                    'success'
                );
            } else {
                $app->enqueueMessage(
                    Text::sprintf('COM_SPLASHSCORE_COLLECTION_TEST_FAILED', $duration, $message),
                    'error'
                );
            }
        } catch (\Throwable $exception) {
            $app->enqueueMessage($exception->getMessage(), 'error');
        }

        $this->setRedirect(Route::_('index.php?option=com_splaskscore&view=collectionlogs', false));
    }

    public function export(): void
    {
        if (!Session::checkToken('get')) {
            throw new \RuntimeException(Text::_('JINVALID_TOKEN'), 403);
        }

        $this->assertAuthorized();
        $model = $this->getModel('Collectionlogs');
        $rows = $model->getExportRows();
        $stream = fopen('php://temp', 'w+');

        if ($stream === false) {
            throw new \RuntimeException(Text::_('COM_SPLASHSCORE_COLLECTION_EXPORT_FAILED'));
        }

        fputcsv($stream, [
            Text::_('COM_SPLASHSCORE_COLLECTION_LOGS_DATE'),
            Text::_('COM_SPLASHSCORE_COLLECTION_LOGS_SOURCE'),
            Text::_('COM_SPLASHSCORE_COLLECTION_LOGS_STATUS'),
            Text::_('COM_SPLASHSCORE_COLLECTION_LOGS_MESSAGE'),
            Text::_('COM_SPLASHSCORE_MODULE_TITLE'),
        ], ',', '"', '');

        foreach ($rows as $row) {
            fputcsv($stream, [
                $this->csvSafe((string) ($row->recorded_at ?? '')),
                $this->csvSafe((string) ($row->source ?? '')),
                $this->csvSafe((string) ($row->status ?? '')),
                $this->csvSafe((string) ($row->message ?? '')),
                $this->csvSafe((string) ($row->module_title ?? '')),
            ], ',', '"', '');
        }

        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        $filename = 'splaskscore-collection-records-' . gmdate('Ymd-His') . '.csv';
        $app = $this->app;
        $app->setHeader('Content-Type', 'text/csv; charset=utf-8', true);
        $app->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"', true);
        $app->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate', true);
        $app->setBody("\xEF\xBB\xBF" . (string) $csv);
        $app->sendHeaders();
        echo $app->getBody();
        $app->close();
    }

    private function assertAuthorized(): void
    {
        $user = $this->app->getIdentity();
        if (!$user->authorise('core.admin') && !$user->authorise('core.manage', 'com_splaskscore')) {
            throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }
    }

    private function csvSafe(string $value): string
    {
        return preg_match('/^[\x00-\x20]*[=+\-@]/', $value) ? "'" . $value : $value;
    }
}
