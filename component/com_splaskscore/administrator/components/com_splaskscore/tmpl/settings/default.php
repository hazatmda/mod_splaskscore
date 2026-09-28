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
$assetVersion = class_exists('ModSplaskscoreHelper') ? ModSplaskscoreHelper::getEngineVersion() : '1.9.5';
$document->addStyleSheet($assetBase . '/css/admin.css?v=' . rawurlencode($assetVersion));
$document->addScript($assetBase . '/js/admin.js?v=' . rawurlencode($assetVersion), [], ['defer' => true]);

$diagnostics = $this->schedulerDiagnostics;
$schedulerTask = is_object($diagnostics['task'] ?? null) ? $diagnostics['task'] : null;
$health = (string) ($diagnostics['health'] ?? 'missing');
$healthMap = [
    'healthy' => ['success', 'COM_SPLASHSCORE_SCHEDULER_HEALTHY'],
    'missing' => ['danger', 'COM_SPLASHSCORE_SCHEDULER_MISSING'],
    'inactive' => ['warning', 'COM_SPLASHSCORE_SCHEDULER_INACTIVE'],
    'never_run' => ['warning', 'COM_SPLASHSCORE_SCHEDULER_NEVER_RUN'],
    'overdue' => ['danger', 'COM_SPLASHSCORE_SCHEDULER_OVERDUE'],
    'repeated_failures' => ['danger', 'COM_SPLASHSCORE_SCHEDULER_REPEATED_FAILURES'],
];
$healthMeta = $healthMap[$health] ?? $healthMap['missing'];
$tokenIsRevealed = $this->revealedToken !== '';
$formatDate = static function (?string $date): string {
    if (!$date || $date === '0000-00-00 00:00:00') {
        return Text::_('COM_SPLASHSCORE_COLLECTION_LOGS_NEVER');
    }

    return HTMLHelper::_('date', $date, 'DATE_FORMAT_LC6');
};
$tokenRevealUrl = Route::_('index.php?option=com_splaskscore&task=settings.revealToken&format=json', false);
?>
<?php if (!$this->module || !$this->form) : ?>
    <div class="alert alert-warning" role="alert">
        <h2 class="h5"><?php echo Text::_('COM_SPLASHSCORE_NO_MODULE_TITLE'); ?></h2>
        <p class="mb-0"><?php echo Text::_('COM_SPLASHSCORE_NO_MODULE_DESC'); ?></p>
    </div>
<?php else : ?>
    <form action="<?php echo Route::_('index.php?option=com_splaskscore&view=settings'); ?>" method="post" name="adminForm" id="adminForm" class="com-splaskscore-settings">
        <div class="alert alert-info" role="status">
            <?php echo Text::sprintf('COM_SPLASHSCORE_EDITING_MODULE', htmlspecialchars((string) $this->module->title, ENT_QUOTES, 'UTF-8'), (int) $this->module->id); ?>
        </div>

        <?php if (!$this->canEdit) : ?>
            <div class="alert alert-warning" role="alert"><?php echo Text::_('COM_SPLASHSCORE_READ_ONLY_NOTICE'); ?></div>
        <?php endif; ?>

        <?php foreach ($this->form->getFieldsets() as $fieldset) : ?>
            <fieldset class="card mb-4">
                <legend class="card-header h5 mb-0"><?php echo Text::_($fieldset->label); ?></legend>
                <div class="card-body">
                    <?php foreach ($this->form->getFieldset($fieldset->name) as $field) : ?>
                        <?php if ($field->hidden) : ?>
                            <?php echo $field->input; ?>
                        <?php else : ?>
                            <div class="control-group mb-3">
                                <div class="control-label"><?php echo $field->label; ?></div>
                                <div class="controls">
                                    <?php if ($field->fieldname === 'splask_token') : ?>
                                        <div class="input-group splaskscore-token-control" data-splask-token-control
                                            data-configured="<?php echo $this->tokenConfigured ? 'true' : 'false'; ?>"
                                            data-server-revealed="<?php echo $tokenIsRevealed ? 'true' : 'false'; ?>"
                                            data-reveal-url="<?php echo htmlspecialchars($tokenRevealUrl, ENT_QUOTES, 'UTF-8'); ?>"
                                            data-module-id="<?php echo (int) $this->module->id; ?>"
                                            data-csrf-token="<?php echo htmlspecialchars(Session::getFormToken(), ENT_QUOTES, 'UTF-8'); ?>"
                                            data-placeholder="••••••••••••••••"
                                            data-show-label="<?php echo htmlspecialchars(Text::_('COM_SPLASHSCORE_TOKEN_SHOW'), ENT_QUOTES, 'UTF-8'); ?>"
                                            data-hide-label="<?php echo htmlspecialchars(Text::_('COM_SPLASHSCORE_TOKEN_HIDE'), ENT_QUOTES, 'UTF-8'); ?>"
                                            data-loading-label="<?php echo htmlspecialchars(Text::_('COM_SPLASHSCORE_TOKEN_LOADING'), ENT_QUOTES, 'UTF-8'); ?>"
                                            data-error-label="<?php echo htmlspecialchars(Text::_('COM_SPLASHSCORE_TOKEN_REVEAL_FAILED'), ENT_QUOTES, 'UTF-8'); ?>">
                                            <input
                                                class="form-control<?php echo $tokenIsRevealed ? ' d-none' : ''; ?>"
                                                type="password"
                                                name="<?php echo htmlspecialchars($field->name, ENT_QUOTES, 'UTF-8'); ?>"
                                                id="<?php echo htmlspecialchars($field->id, ENT_QUOTES, 'UTF-8'); ?>"
                                                value=""
                                                autocomplete="new-password"
                                                spellcheck="false"
                                                placeholder="<?php echo $this->tokenConfigured ? '••••••••••••••••' : ''; ?>"
                                                data-splask-token-input
                                                <?php echo $this->canEdit ? '' : 'disabled'; ?>>
                                            <input
                                                class="form-control font-monospace<?php echo $tokenIsRevealed ? '' : ' d-none'; ?>"
                                                type="text"
                                                id="jform_splask_token_revealed"
                                                value="<?php echo htmlspecialchars($this->revealedToken, ENT_QUOTES, 'UTF-8'); ?>"
                                                readonly
                                                autocomplete="off"
                                                spellcheck="false"
                                                aria-label="<?php echo htmlspecialchars(Text::_('COM_SPLASHSCORE_TOKEN_REVEALED_LABEL'), ENT_QUOTES, 'UTF-8'); ?>"
                                                data-splask-token-revealed>
                                            <button class="btn btn-outline-secondary" type="submit" form="splaskscore-token-reveal-form" data-splask-token-toggle aria-controls="<?php echo htmlspecialchars($field->id, ENT_QUOTES, 'UTF-8'); ?> jform_splask_token_revealed" aria-pressed="<?php echo $tokenIsRevealed ? 'true' : 'false'; ?>" title="<?php echo Text::_($tokenIsRevealed ? 'COM_SPLASHSCORE_TOKEN_HIDE' : 'COM_SPLASHSCORE_TOKEN_SHOW'); ?>" <?php echo $this->canEdit ? '' : 'disabled'; ?>>
                                                <span class="<?php echo $tokenIsRevealed ? 'icon-eye-slash' : 'icon-eye'; ?>" aria-hidden="true" data-splask-token-icon></span>
                                                <span class="visually-hidden" data-splask-token-label><?php echo Text::_($tokenIsRevealed ? 'COM_SPLASHSCORE_TOKEN_HIDE' : 'COM_SPLASHSCORE_TOKEN_SHOW'); ?></span>
                                            </button>
                                        </div>
                                        <div class="form-text text-danger d-none" role="alert" data-splask-token-error></div>
                                    <?php else : ?>
                                        <?php echo $field->input; ?>
                                    <?php endif; ?>

                                    <?php if ($field->fieldname === 'analytics_duplicate_cooldown') : ?>
                                        <div class="form-text" aria-live="polite" data-splask-cooldown-preview
                                            data-template="<?php echo htmlspecialchars(Text::_('COM_SPLASHSCORE_COOLDOWN_PREVIEW'), ENT_QUOTES, 'UTF-8'); ?>"
                                            data-minutes-template="<?php echo htmlspecialchars(Text::_('COM_SPLASHSCORE_COOLDOWN_PREVIEW_MINUTES'), ENT_QUOTES, 'UTF-8'); ?>"></div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <?php if ($field->fieldname === 'analytics_duplicate_cooldown') : ?>
                                <section class="splaskscore-scheduler-panel mt-4 mb-3" aria-labelledby="splaskscore-scheduler-title">
                                    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
                                        <div>
                                            <h3 class="h5 mb-1" id="splaskscore-scheduler-title"><?php echo Text::_('COM_SPLASHSCORE_SCHEDULER_PANEL_TITLE'); ?></h3>
                                            <p class="text-body-secondary mb-0"><?php echo Text::_('COM_SPLASHSCORE_SCHEDULER_PANEL_DESC'); ?></p>
                                        </div>
                                        <span class="badge bg-<?php echo htmlspecialchars($healthMeta[0], ENT_QUOTES, 'UTF-8'); ?>">
                                            <?php echo Text::_($healthMeta[1]); ?>
                                        </span>
                                    </div>

                                    <div class="row g-3 mb-3">
                                        <div class="col-12 col-md-6 col-xl-3">
                                            <div class="splaskscore-diagnostic-item">
                                                <span><?php echo Text::_('COM_SPLASHSCORE_SCHEDULER_TIMEZONE'); ?></span>
                                                <strong><?php echo htmlspecialchars(
                                                    (string) ($diagnostics['timezone_name'] ?? 'UTC')
                                                    . ' (UTC' . (string) ($diagnostics['timezone_offset'] ?? '+00:00') . ')',
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ); ?></strong>
                                            </div>
                                        </div>
                                        <div class="col-12 col-md-6 col-xl-3">
                                            <div class="splaskscore-diagnostic-item">
                                                <span><?php echo Text::_('COM_SPLASHSCORE_SCHEDULER_NEXT_DAILY'); ?></span>
                                                <strong><?php echo htmlspecialchars(
                                                    $formatDate((string) ($diagnostics['next_daily_utc'] ?? '')),
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ); ?></strong>
                                            </div>
                                        </div>
                                        <div class="col-12 col-md-6 col-xl-3">
                                            <div class="splaskscore-diagnostic-item">
                                                <span><?php echo Text::_('COM_SPLASHSCORE_COLLECTION_LOGS_LAST_RUN'); ?></span>
                                                <strong><?php echo htmlspecialchars(
                                                    $formatDate($schedulerTask ? (string) ($schedulerTask->last_execution ?? '') : ''),
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ); ?></strong>
                                            </div>
                                        </div>
                                        <div class="col-12 col-md-6 col-xl-3">
                                            <div class="splaskscore-diagnostic-item">
                                                <span><?php echo Text::_('COM_SPLASHSCORE_COLLECTION_LOGS_NEXT_RUN'); ?></span>
                                                <strong><?php echo htmlspecialchars(
                                                    $formatDate($schedulerTask ? (string) ($schedulerTask->next_execution ?? '') : ''),
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ); ?></strong>
                                            </div>
                                        </div>
                                    </div>

                                    <?php if ($health === 'missing') : ?>
                                        <div class="alert alert-danger py-2" role="alert">
                                            <?php echo Text::_('COM_SPLASHSCORE_SCHEDULER_MISSING_DETAIL'); ?>
                                        </div>
                                    <?php elseif ($health === 'inactive') : ?>
                                        <div class="alert alert-warning py-2" role="alert">
                                            <?php echo Text::_('COM_SPLASHSCORE_SCHEDULER_INACTIVE_DETAIL'); ?>
                                        </div>
                                    <?php elseif ($health === 'never_run') : ?>
                                        <div class="alert alert-warning py-2" role="alert">
                                            <?php echo Text::_('COM_SPLASHSCORE_SCHEDULER_NEVER_RUN_DETAIL'); ?>
                                        </div>
                                    <?php elseif ($health === 'overdue') : ?>
                                        <div class="alert alert-danger py-2" role="alert">
                                            <?php echo Text::sprintf(
                                                'COM_SPLASHSCORE_SCHEDULER_OVERDUE_DETAIL',
                                                (int) ($diagnostics['overdue_minutes'] ?? 0)
                                            ); ?>
                                        </div>
                                    <?php elseif ($health === 'repeated_failures') : ?>
                                        <div class="alert alert-danger py-2" role="alert">
                                            <?php echo Text::sprintf(
                                                'COM_SPLASHSCORE_SCHEDULER_FAILURE_DETAIL',
                                                (int) ($diagnostics['consecutive_failures'] ?? 0)
                                            ); ?>
                                        </div>
                                    <?php endif; ?>

                                    <div class="alert alert-info py-2 mb-3" role="note">
                                        <?php echo Text::_('COM_SPLASHSCORE_SCHEDULER_POLICY_NOTE'); ?>
                                    </div>

                                    <div class="mb-2 fw-semibold"><?php echo Text::_('COM_SPLASHSCORE_CRON_GUIDE_TITLE'); ?></div>
                                    <p class="small text-body-secondary mb-2"><?php echo Text::_('COM_SPLASHSCORE_CRON_GUIDE_DESC'); ?></p>
                                    <div class="input-group">
                                        <input class="form-control font-monospace" id="splaskscore-cron-command" type="text" readonly value="<?php echo htmlspecialchars((string) ($diagnostics['cron_command'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                                        <button class="btn btn-outline-secondary" type="button" data-splask-cron-copy data-target="splaskscore-cron-command"
                                            data-copy-label="<?php echo htmlspecialchars(Text::_('COM_SPLASHSCORE_CRON_COPY'), ENT_QUOTES, 'UTF-8'); ?>"
                                            data-copied-label="<?php echo htmlspecialchars(Text::_('COM_SPLASHSCORE_CRON_COPIED'), ENT_QUOTES, 'UTF-8'); ?>"
                                            title="<?php echo Text::_('COM_SPLASHSCORE_CRON_COPY'); ?>">
                                            <span class="icon-copy" aria-hidden="true"></span>
                                            <span class="visually-hidden" data-splask-copy-label><?php echo Text::_('COM_SPLASHSCORE_CRON_COPY'); ?></span>
                                        </button>
                                    </div>
                                    <div class="d-flex flex-wrap justify-content-between gap-2 mt-2">
                                        <small class="text-body-secondary"><?php echo Text::_('COM_SPLASHSCORE_CRON_GUIDE_NOTE'); ?></small>
                                        <a href="<?php echo Route::_('index.php?option=com_scheduler&view=tasks'); ?>">
                                            <?php echo Text::_('COM_SPLASHSCORE_OPEN_SCHEDULED_TASKS'); ?>
                                        </a>
                                    </div>
                                </section>
                            <?php endif; ?>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </fieldset>
        <?php endforeach; ?>

        <input type="hidden" name="task" value="" />
        <?php echo HTMLHelper::_('form.token'); ?>
    </form>

    <form action="<?php echo Route::_('index.php?option=com_splaskscore'); ?>" method="post" id="splaskscore-token-reveal-form" class="d-none">
        <input type="hidden" name="module_id" value="<?php echo (int) $this->module->id; ?>">
        <input type="hidden" name="task" value="settings.<?php echo $tokenIsRevealed ? 'hideTokenPage' : 'revealTokenPage'; ?>">
        <?php echo HTMLHelper::_('form.token'); ?>
    </form>
<?php endif; ?>
