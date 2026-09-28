<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_splaskscore
 */

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Uri\Uri;

$document = $this->getDocument();
$assetBase = Uri::root(true) . '/media/com_splaskscore';
$assetVersion = class_exists('ModSplaskscoreHelper') ? ModSplaskscoreHelper::getEngineVersion() : '1.9.6';
$document->addStyleSheet($assetBase . '/css/admin.css?v=' . rawurlencode($assetVersion));
$document->addScript($assetBase . '/js/admin.js?v=' . rawurlencode($assetVersion), [], ['defer' => true]);
$summary = $this->summary;
$task = $this->taskStatus;
$statusFilter = (string) ($this->state ? $this->state->get('filter.status', '') : '');
$sourceFilter = (string) ($this->state ? $this->state->get('filter.source', '') : '');
$searchFilter = (string) ($this->state ? $this->state->get('filter.search', '') : '');
$formToken = Session::getFormToken();
$exportUrl = Route::_(
    'index.php?option=com_splaskscore&task=collectionlogs.export'
    . '&filter_search=' . rawurlencode($searchFilter)
    . '&filter_status=' . rawurlencode($statusFilter)
    . '&filter_source=' . rawurlencode($sourceFilter)
    . '&' . $formToken . '=1'
);
$nextExecutionIso = '';

if ($task && !empty($task->next_execution)) {
    try {
        $nextExecutionIso = (new DateTimeImmutable((string) $task->next_execution, new DateTimeZone('UTC')))
            ->format(DateTimeInterface::ATOM);
    } catch (Throwable $exception) {
        $nextExecutionIso = '';
    }
}

$nextReason = '';
if ($task) {
    $reason = (string) ($task->next_reason ?? 'daily');
    if ($reason === 'completed_today') {
        $nextReason = Text::_('COM_SPLASHSCORE_COLLECTION_NEXT_REASON_COMPLETED');
    } elseif ($reason === 'retry') {
        $nextReason = Text::sprintf(
            'COM_SPLASHSCORE_COLLECTION_NEXT_REASON_RETRY',
            (int) ($task->cooldown_minutes ?? 10)
        );
    } else {
        $nextReason = Text::sprintf(
            'COM_SPLASHSCORE_COLLECTION_NEXT_REASON_DAILY',
            (string) ($task->collection_time ?? '06:00')
        );
    }
}

$formatDate = static function (?string $date): string {
    if (!$date || $date === '0000-00-00 00:00:00') {
        return Text::_('COM_SPLASHSCORE_COLLECTION_LOGS_NEVER');
    }

    return HTMLHelper::_('date', $date, 'DATE_FORMAT_LC6');
};

$sourceLabel = static function (string $source): string {
    if ($source === 'scheduler') {
        return Text::_('COM_SPLASHSCORE_COLLECTION_LOGS_SOURCE_SCHEDULER');
    }

    if ($source === 'manual') {
        return Text::_('COM_SPLASHSCORE_COLLECTION_LOGS_SOURCE_MANUAL');
    }

    return $source !== '' ? ucfirst($source) : Text::_('COM_SPLASHSCORE_COLLECTION_LOGS_SOURCE_UNKNOWN');
};
?>
<div class="com-splaskscore-collectionlogs">
    <section class="splaskscore-component-hero">
        <div>
            <p class="text-uppercase small fw-semibold mb-2"><?php echo Text::_('COM_SPLASHSCORE_COMPONENT_LABEL'); ?></p>
            <h2 class="mb-2"><?php echo Text::_('COM_SPLASHSCORE_COLLECTION_LOGS_HEADING'); ?></h2>
            <p class="mb-0"><?php echo Text::_('COM_SPLASHSCORE_COLLECTION_LOGS_INTRO'); ?></p>
        </div>
        <div class="d-flex flex-column align-items-stretch align-items-lg-end gap-2">
            <?php if ($task) : ?>
                <span class="badge <?php echo (int) ($task->state ?? 0) === 1 ? 'bg-success' : 'bg-secondary'; ?> align-self-lg-end">
                    <?php echo (int) ($task->state ?? 0) === 1
                        ? Text::_('COM_SPLASHSCORE_COLLECTION_LOGS_TASK_ACTIVE')
                        : Text::_('COM_SPLASHSCORE_COLLECTION_LOGS_TASK_INACTIVE'); ?>
                </span>
            <?php endif; ?>
            <div class="d-flex flex-wrap gap-2">
                <form action="<?php echo Route::_('index.php?option=com_splaskscore'); ?>" method="post">
                    <button class="btn btn-light" type="submit">
                        <?php echo Text::_('COM_SPLASHSCORE_COLLECTION_TEST_NOW'); ?>
                    </button>
                    <input type="hidden" name="task" value="collectionlogs.test">
                    <?php echo HTMLHelper::_('form.token'); ?>
                </form>
                <a class="btn btn-outline-light" href="<?php echo $exportUrl; ?>">
                    <?php echo Text::_('COM_SPLASHSCORE_COLLECTION_EXPORT_CSV'); ?>
                </a>
            </div>
        </div>
    </section>

    <?php if (!$task) : ?>
        <div class="alert alert-warning mt-4" role="alert">
            <?php echo Text::_('COM_SPLASHSCORE_COLLECTION_LOGS_TASK_MISSING'); ?>
        </div>
    <?php else : ?>
        <?php if (!empty($task->is_overdue)) : ?>
            <div class="alert alert-danger mt-4 mb-0" role="alert">
                <strong><?php echo Text::_('COM_SPLASHSCORE_COLLECTION_SCHEDULER_OVERDUE_TITLE'); ?></strong>
                <?php echo Text::sprintf(
                    'COM_SPLASHSCORE_COLLECTION_SCHEDULER_OVERDUE_DESC',
                    max(1, (int) ceil(((int) $task->overdue_seconds) / 60))
                ); ?>
            </div>
        <?php endif; ?>

        <div class="splaskscore-collection-summary mt-4 mb-4">
            <div>
                <div class="card h-100"><div class="card-body">
                    <div class="text-body-secondary small mb-1"><?php echo Text::_('COM_SPLASHSCORE_COLLECTION_LOGS_LAST_RUN'); ?></div>
                    <div class="fw-semibold"><?php echo htmlspecialchars($formatDate((string) ($task->last_execution ?? '')), ENT_QUOTES, 'UTF-8'); ?></div>
                </div></div>
            </div>
            <div>
                <div class="card h-100"><div class="card-body">
                    <div class="text-body-secondary small mb-1"><?php echo Text::_('COM_SPLASHSCORE_COLLECTION_LOGS_NEXT_RUN'); ?></div>
                    <div class="fw-semibold"><?php echo htmlspecialchars($formatDate((string) ($task->next_execution ?? '')), ENT_QUOTES, 'UTF-8'); ?></div>
                    <?php if ($nextReason !== '') : ?>
                        <div class="small text-body-secondary mt-2"><?php echo htmlspecialchars($nextReason, ENT_QUOTES, 'UTF-8'); ?></div>
                    <?php endif; ?>
                    <?php if ($nextExecutionIso !== '') : ?>
                        <div class="small fw-semibold mt-1" aria-live="polite"
                            data-splask-countdown="<?php echo htmlspecialchars($nextExecutionIso, ENT_QUOTES, 'UTF-8'); ?>"
                            data-template="<?php echo htmlspecialchars(Text::_('COM_SPLASHSCORE_COLLECTION_COUNTDOWN'), ENT_QUOTES, 'UTF-8'); ?>"
                            data-complete-label="<?php echo htmlspecialchars(Text::_('COM_SPLASHSCORE_COLLECTION_COUNTDOWN_DUE'), ENT_QUOTES, 'UTF-8'); ?>"></div>
                    <?php endif; ?>
                </div></div>
            </div>
            <div>
                <div class="card h-100"><div class="card-body">
                    <div class="text-body-secondary small mb-1"><?php echo Text::_('COM_SPLASHSCORE_COLLECTION_LOGS_SUCCESS_TOTAL'); ?></div>
                    <div class="display-6 fw-semibold text-success"><?php echo (int) ($summary['success_count'] ?? 0); ?></div>
                </div></div>
            </div>
            <div>
                <div class="card h-100"><div class="card-body">
                    <div class="text-body-secondary small mb-1"><?php echo Text::_('COM_SPLASHSCORE_COLLECTION_LOGS_FAILED_TOTAL'); ?></div>
                    <div class="display-6 fw-semibold <?php echo (int) ($summary['failed_count'] ?? 0) > 0 ? 'text-danger' : 'text-body'; ?>"><?php echo (int) ($summary['failed_count'] ?? 0); ?></div>
                </div></div>
            </div>
            <div>
                <div class="card h-100"><div class="card-body">
                    <div class="text-body-secondary small mb-1"><?php echo Text::_('COM_SPLASHSCORE_COLLECTION_LOGS_SKIPPED_TOTAL'); ?></div>
                    <div class="display-6 fw-semibold text-secondary"><?php echo (int) ($summary['skipped_count'] ?? 0); ?></div>
                </div></div>
            </div>
        </div>
    <?php endif; ?>

    <form action="<?php echo Route::_('index.php?option=com_splaskscore&view=collectionlogs'); ?>" method="get" name="adminForm" id="adminForm">
        <div class="card mb-4 splaskscore-collectionlogs-filter">
            <div class="card-body">
                <div class="row g-3 align-items-end">
                    <div class="col-12 col-lg-5">
                        <label class="form-label" for="filter_search"><?php echo Text::_('JSEARCH_FILTER'); ?></label>
                        <input class="form-control" type="search" id="filter_search" name="filter_search" value="<?php echo htmlspecialchars($searchFilter, ENT_QUOTES, 'UTF-8'); ?>" placeholder="<?php echo Text::_('COM_SPLASHSCORE_COLLECTION_LOGS_SEARCH_HINT'); ?>">
                    </div>
                    <div class="col-12 col-sm-6 col-lg-2">
                        <label class="form-label" for="filter_status"><?php echo Text::_('COM_SPLASHSCORE_COLLECTION_LOGS_STATUS'); ?></label>
                        <select class="form-select" id="filter_status" name="filter_status">
                            <option value=""><?php echo Text::_('JALL'); ?></option>
                            <option value="success" <?php echo $statusFilter === 'success' ? 'selected' : ''; ?>><?php echo Text::_('COM_SPLASHSCORE_COLLECTION_LOGS_SUCCESS'); ?></option>
                            <option value="failed" <?php echo $statusFilter === 'failed' ? 'selected' : ''; ?>><?php echo Text::_('COM_SPLASHSCORE_COLLECTION_LOGS_FAILED'); ?></option>
                            <option value="skipped" <?php echo $statusFilter === 'skipped' ? 'selected' : ''; ?>><?php echo Text::_('COM_SPLASHSCORE_COLLECTION_LOGS_SKIPPED'); ?></option>
                        </select>
                    </div>
                    <div class="col-12 col-sm-6 col-lg-3">
                        <label class="form-label" for="filter_source"><?php echo Text::_('COM_SPLASHSCORE_COLLECTION_LOGS_SOURCE'); ?></label>
                        <select class="form-select" id="filter_source" name="filter_source">
                            <option value=""><?php echo Text::_('JALL'); ?></option>
                            <option value="scheduler" <?php echo $sourceFilter === 'scheduler' ? 'selected' : ''; ?>><?php echo Text::_('COM_SPLASHSCORE_COLLECTION_LOGS_SOURCE_SCHEDULER'); ?></option>
                            <option value="manual" <?php echo $sourceFilter === 'manual' ? 'selected' : ''; ?>><?php echo Text::_('COM_SPLASHSCORE_COLLECTION_LOGS_SOURCE_MANUAL'); ?></option>
                        </select>
                    </div>
                    <div class="col-12 col-lg-2 d-flex gap-2">
                        <button class="btn btn-primary flex-grow-1" type="submit"><?php echo Text::_('JFILTER'); ?></button>
                        <a class="btn btn-outline-secondary" href="<?php echo Route::_('index.php?option=com_splaskscore&view=collectionlogs&filter_search=&filter_status=&filter_source='); ?>"><?php echo Text::_('JCLEAR'); ?></a>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                <span class="fw-semibold"><?php echo Text::_('COM_SPLASHSCORE_COLLECTION_LOGS_TABLE_TITLE'); ?></span>
                <span class="badge bg-secondary"><?php echo Text::sprintf('COM_SPLASHSCORE_COLLECTION_LOGS_TOTAL', (int) ($summary['total_count'] ?? 0)); ?></span>
            </div>
            <div class="table-responsive">
                <table class="table table-striped align-middle mb-0">
                    <thead>
                        <tr>
                            <th scope="col"><?php echo Text::_('COM_SPLASHSCORE_COLLECTION_LOGS_DATE'); ?></th>
                            <th scope="col"><?php echo Text::_('COM_SPLASHSCORE_COLLECTION_LOGS_SOURCE'); ?></th>
                            <th scope="col"><?php echo Text::_('COM_SPLASHSCORE_COLLECTION_LOGS_STATUS'); ?></th>
                            <th scope="col"><?php echo Text::_('COM_SPLASHSCORE_COLLECTION_LOGS_MESSAGE'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!$this->items) : ?>
                        <tr><td colspan="4" class="text-center text-body-secondary py-5"><?php echo Text::_('COM_SPLASHSCORE_COLLECTION_LOGS_EMPTY'); ?></td></tr>
                    <?php else : ?>
                        <?php foreach ($this->items as $item) : ?>
                            <?php
                            $itemStatus = strtolower((string) $item->status);
                            $successful = $itemStatus === 'success';
                            $skipped = $itemStatus === 'skipped';
                            ?>
                            <tr>
                                <td class="text-nowrap"><?php echo htmlspecialchars($formatDate((string) $item->recorded_at), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><span class="badge bg-info text-dark"><?php echo htmlspecialchars($sourceLabel((string) $item->source), ENT_QUOTES, 'UTF-8'); ?></span></td>
                                <td>
                                    <span class="badge <?php echo $successful ? 'bg-success' : ($skipped ? 'bg-secondary' : 'bg-danger'); ?>">
                                        <?php echo $successful
                                            ? Text::_('COM_SPLASHSCORE_COLLECTION_LOGS_SUCCESS')
                                            : ($skipped ? Text::_('COM_SPLASHSCORE_COLLECTION_LOGS_SKIPPED') : Text::_('COM_SPLASHSCORE_COLLECTION_LOGS_FAILED')); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars((string) ($item->message ?: ($successful ? Text::_('COM_SPLASHSCORE_COLLECTION_LOGS_SUCCESS_DEFAULT') : '')), ENT_QUOTES, 'UTF-8'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($this->pagination) : ?>
                <div class="card-footer"><?php echo $this->pagination->getListFooter(); ?></div>
            <?php endif; ?>
        </div>

        <input type="hidden" name="option" value="com_splaskscore">
        <input type="hidden" name="view" value="collectionlogs">
    </form>
</div>
