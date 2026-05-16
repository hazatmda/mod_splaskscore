<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  mod_splaskscore
 *
 * @copyright   Copyright (C) 2025 Muhammad Azizan Hazim
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

/**
 * Helper methods for the SPLaSK Score administrator module.
 */
final class ModSplaskscoreHelper
{
    private const MALAY_WEEKDAYS = ['Ahad', 'Isnin', 'Selasa', 'Rabu', 'Khamis', 'Jumaat', 'Sabtu'];

    private const MALAY_MONTHS = [
        1 => 'Januari',
        2 => 'Februari',
        3 => 'Mac',
        4 => 'April',
        5 => 'Mei',
        6 => 'Jun',
        7 => 'Julai',
        8 => 'Ogos',
        9 => 'September',
        10 => 'Oktober',
        11 => 'November',
        12 => 'Disember',
    ];

    private const ENGINE_VERSION = '1.4.2';

    private const DEFAULT_DUPLICATE_COOLDOWN_MINUTES = 10;

    private const DEFAULT_RETENTION_DAYS = 365;

    private const DEFAULT_MAX_HISTORY_ROWS = 500;

    private const DEFAULT_HISTORY_ROWS_PER_PAGE = 7;

    private const HISTORY_CHART_DAY_WINDOW = 30;

    private const SCHEDULER_TASK_TYPE = 'splaskscore.analytics.collect';

    private const MANUAL_REFRESH_COOLDOWN_SECONDS = 60;

    private const API_RETRY_ATTEMPTS = 3;

    /**
     * Return the layout names currently supported by the module.
     *
     * @return  string[]
     */
    public static function getAllowedDesignPresets(): array
    {
        return [
            'default',
            'modern_circle',
            'glass_card',
            'minimal_clean',
            'gradient_ring',
            'compact_badge',
            'dashboard_tile',
            'neon_glass',
            'minimal_oled',
            'enterprise_kpi',
            'arc_reactor',
        ];
    }

    /**
     * Resolve the requested design preset to an installed, whitelisted layout.
     *
     * @param   object  $params  Joomla module parameters registry.
     *
     * @return  string
     */
    public static function getDesignPreset($params): string
    {
        $layout = (string) $params->get('design_preset', 'modern_circle');

        return in_array($layout, self::getAllowedDesignPresets(), true) ? $layout : 'modern_circle';
    }

    /**
     * Return the appearance modes supported by the module.
     *
     * @return  string[]
     */
    public static function getAllowedAppearanceModes(): array
    {
        return [
            'light',
            'dark',
            'auto',
        ];
    }

    /**
     * Resolve the requested appearance mode to a supported value.
     *
     * The light fallback preserves the existing module rendering for sites that
     * have not explicitly opted into adaptive dark-mode behavior.
     *
     * @param   object  $params  Joomla module parameters registry.
     *
     * @return  string
     */
    public static function getAppearanceMode($params): string
    {
        $mode = (string) $params->get('appearance_mode', 'light');

        return in_array($mode, self::getAllowedAppearanceModes(), true) ? $mode : 'light';
    }

    /**
     * Return centralized CSS custom properties for each grade.
     *
     * @return  string
     */
    public static function getGradeThemeStyle(): string
    {
        $declarations = [];

        foreach (self::getGradeRules() as $rule) {
            $key = preg_replace('/[^a-z0-9_-]/i', '', (string) $rule['key']);

            foreach (['color', 'accent', 'surface', 'text'] as $token) {
                $declarations[] = sprintf(
                    '--splask-grade-%s-%s:%s',
                    $key,
                    $token,
                    $rule[$token]
                );
            }
        }

        return htmlspecialchars(implode(';', $declarations), ENT_QUOTES, 'UTF-8');
    }

    /**
     * Return centralized grade rules used by every preset.
     *
     * The score thresholds intentionally preserve the existing SPLaSK grading
     * behavior: A is only 100, B is 95-99, C is 91-94, D is 86-90, and
     * anything lower is marked as failed.
     *
     * @return  array<int, array<string, mixed>>
     */
    public static function getGradeRules(): array
    {
        return [
            [
                'key' => 'a',
                'label' => 'Gred A',
                'shortLabel' => 'A',
                'min' => 100,
                'max' => 100,
                'color' => '#198754',
                'accent' => '#20c997',
                'surface' => '#e8f7ef',
                'text' => '#0f5132',
                'status' => 'Cemerlang',
            ],
            [
                'key' => 'b',
                'label' => 'Gred B',
                'shortLabel' => 'B',
                'min' => 95,
                'max' => 99,
                'color' => '#0d6efd',
                'accent' => '#6ea8fe',
                'surface' => '#e7f1ff',
                'text' => '#084298',
                'status' => 'Baik',
            ],
            [
                'key' => 'c',
                'label' => 'Gred C',
                'shortLabel' => 'C',
                'min' => 91,
                'max' => 94,
                'color' => '#f0ad00',
                'accent' => '#ffd666',
                'surface' => '#fff7df',
                'text' => '#664d03',
                'status' => 'Sederhana',
            ],
            [
                'key' => 'd',
                'label' => 'Gred D',
                'shortLabel' => 'D',
                'min' => 86,
                'max' => 90,
                'color' => '#fd7e14',
                'accent' => '#ffb26b',
                'surface' => '#fff0e5',
                'text' => '#653208',
                'status' => 'Lemah',
            ],
            [
                'key' => 'fail',
                'label' => 'Gred GAGAL',
                'shortLabel' => 'F',
                'min' => 0,
                'max' => 85,
                'color' => '#dc3545',
                'accent' => '#ff7b89',
                'surface' => '#fdecef',
                'text' => '#842029',
                'status' => 'Kritikal',
            ],
        ];
    }

    /**
     * Resolve grade style metadata for a numeric score.
     *
     * @param   float|int  $score  Score from 0 to 100.
     *
     * @return  array<string, mixed>
     */
    public static function getGradeStyle($score): array
    {
        $score = (float) $score;

        foreach (self::getGradeRules() as $rule) {
            if ($score >= $rule['min'] && $score <= $rule['max']) {
                return $rule;
            }
        }

        return self::getGradeRules()[count(self::getGradeRules()) - 1];
    }


    /**
     * Return the database table name used for score history.
     *
     * @return  string
     */
    public static function getHistoryTableName(): string
    {
        return '#__splaskscore_history';
    }

    /**
     * Store a score snapshot when it differs from the latest saved record.
     *
     * @param   array<string, mixed>  $payload  Client score payload.
     *
     * @return  array<string, mixed>
     */
    public static function saveHistoryRecord(array $payload): array
    {
        $app = \Joomla\CMS\Factory::getApplication();
        $input = $app->input;

        if (empty($payload['skip_token_check']) && !\Joomla\CMS\Session\Session::checkToken('request')) {
            return [
                'success' => false,
                'message' => 'Token keselamatan tidak sah.',
            ];
        }

        $moduleId = max(0, (int) ($payload['module_id'] ?? $input->getInt('module_id', 0)));
        $plainToken = (string) ($payload['token'] ?? '');
        $tokenHash = hash('sha256', $plainToken);
        $score = max(0, min(100, (float) ($payload['score'] ?? 0)));
        $gradeKey = self::cleanHistoryText((string) ($payload['grade_key'] ?? ''), 32);
        $gradeLabel = self::cleanHistoryText((string) ($payload['grade_label'] ?? ''), 64);
        $statusLabel = self::cleanHistoryText((string) ($payload['status_label'] ?? ''), 64);
        $verificationUrl = filter_var((string) ($payload['verification_url'] ?? ''), FILTER_VALIDATE_URL) ? (string) $payload['verification_url'] : '';
        $sourceCheckedAt = self::normaliseHistoryDate((string) ($payload['source_checked_at'] ?? ''));
        $recordedAt = self::normaliseHistoryDate((string) ($payload['recorded_at'] ?? '')) ?: \Joomla\CMS\Factory::getDate()->toSql();
        $source = self::cleanHistoryText((string) ($payload['source'] ?? 'dashboard'), 32) ?: 'dashboard';
        $triggeredBy = self::cleanHistoryText((string) ($payload['triggered_by'] ?? ''), 128);
        $engineVersion = self::cleanHistoryText((string) ($payload['engine_version'] ?? self::ENGINE_VERSION), 32);
        $signature = (string) ($payload['signature'] ?? self::buildHistorySignature($score, $gradeKey, $statusLabel, $verificationUrl, $sourceCheckedAt));
        $cooldownMinutes = self::getDuplicateCooldownMinutes($moduleId, (int) ($payload['duplicate_cooldown_minutes'] ?? 0));

        if (!$moduleId || !$gradeKey || !$gradeLabel || !$statusLabel || $plainToken === '') {
            return [
                'success' => false,
                'message' => 'Data sejarah tidak lengkap.',
            ];
        }

        self::ensureHistoryTable();

        $latest = self::getLatestHistoryRecord($moduleId, $tokenHash);

        if ($latest && self::isDuplicateHistoryRecord($latest, $signature, $recordedAt, $cooldownMinutes)) {
            self::recordAnalyticsHealth($moduleId, $tokenHash, $source, 'success', 'Rekod pendua diabaikan.', $recordedAt);
            self::updateModuleLastSuccessfulCollection($moduleId, self::latestSuccessfulCollectionTimestamp($moduleId, $tokenHash));

            return [
                'success' => true,
                'saved' => false,
                'duplicate' => true,
                'message' => 'Rekod sejarah terkini sudah wujud dan tidak disimpan semula.',
                'health' => self::getAnalyticsHealth($moduleId, $tokenHash),
            ];
        }

        $db = \Joomla\CMS\Factory::getDbo();
        $record = (object) [
            'module_id' => $moduleId,
            'token_hash' => $tokenHash,
            'score' => $score,
            'grade_key' => $gradeKey,
            'grade_label' => $gradeLabel,
            'status_label' => $statusLabel,
            'verification_url' => $verificationUrl,
            'source_checked_at' => $sourceCheckedAt,
            'source' => $source,
            'recorded_at' => $recordedAt,
            'engine_version' => $engineVersion,
            'signature' => $signature,
            'triggered_by' => $triggeredBy,
            'created_at' => $recordedAt,
        ];

        try {
            $db->transactionStart();
            $latest = self::getLatestHistoryRecord($moduleId, $tokenHash, true);
            if ($latest && self::isDuplicateHistoryRecord($latest, $signature, $recordedAt, $cooldownMinutes)) {
                self::recordAnalyticsHealth($moduleId, $tokenHash, $source, 'success', 'Rekod pendua diabaikan.', $recordedAt);
                $db->transactionCommit();
                self::updateModuleLastSuccessfulCollection($moduleId, self::latestSuccessfulCollectionTimestamp($moduleId, $tokenHash));

                return [
                    'success' => true,
                    'saved' => false,
                    'duplicate' => true,
                    'message' => 'Rekod sejarah terkini sudah wujud dan tidak disimpan semula.',
                    'health' => self::getAnalyticsHealth($moduleId, $tokenHash),
                ];
            }

            $db->insertObject(self::getHistoryTableName(), $record);
            self::recordAnalyticsHealth($moduleId, $tokenHash, $source, 'success', '', $recordedAt);
            self::applyRetentionPolicy($moduleId, $tokenHash);
            $db->transactionCommit();
            self::updateModuleLastSuccessfulCollection($moduleId, self::latestSuccessfulCollectionTimestamp($moduleId, $tokenHash));
        } catch (\Throwable $exception) {
            try {
                $db->transactionRollback();
            } catch (\Throwable $rollbackException) {
                self::logAnalyticsEvent('error', 'History transaction rollback failed.', ['module_id' => $moduleId, 'error' => $rollbackException->getMessage()]);
            }

            self::logAnalyticsEvent('error', 'History transaction failed.', ['module_id' => $moduleId, 'error' => $exception->getMessage()]);

            return [
                'success' => false,
                'message' => 'Rekod sejarah gagal disimpan.',
            ];
        }

        return [
            'success' => true,
            'saved' => true,
            'duplicate' => false,
            'message' => 'Rekod sejarah disimpan.',
            'health' => self::getAnalyticsHealth($moduleId, $tokenHash),
        ];
    }

    /**
     * AJAX endpoint used by com_ajax to save history records.
     *
     * @return  array<string, mixed>
     */
    public static function saveHistoryAjax(): array
    {
        $input = \Joomla\CMS\Factory::getApplication()->input;

        return self::saveHistoryRecord([
            'module_id' => $input->getInt('module_id', 0),
            'token' => $input->getString('token', ''),
            'score' => $input->getFloat('score', 0),
            'grade_key' => $input->getCmd('grade_key', ''),
            'grade_label' => $input->getString('grade_label', ''),
            'status_label' => $input->getString('status_label', ''),
            'verification_url' => $input->getString('verification_url', ''),
            'source_checked_at' => $input->getString('source_checked_at', ''),
            'source' => 'dashboard',
            'triggered_by' => 'dashboard',
        ]);
    }

    /**
     * AJAX endpoint used by com_ajax to force an immediate server-side analytics refresh.
     *
     * @return  array<string, mixed>
     */
    public static function refreshAnalyticsAjax(): array
    {
        $app = \Joomla\CMS\Factory::getApplication();
        $input = $app->input;

        if (!\Joomla\CMS\Session\Session::checkToken('request')) {
            return [
                'success' => false,
                'message' => 'Token keselamatan tidak sah.',
            ];
        }

        $moduleId = $input->getInt('module_id', 0);
        $token = $input->getString('token', '');
        $appearance = $input->getCmd('appearance', 'light');
        $user = \Joomla\CMS\Factory::getUser();

        if (!$user || $user->guest || (!$user->authorise('core.manage', 'com_modules') && !$user->authorise('core.admin'))) {
            return [
                'success' => false,
                'message' => 'Anda tidak dibenarkan menyegarkan analitik secara manual.',
            ];
        }

        if (!self::allowManualRefresh($moduleId, (int) $user->id)) {
            return [
                'success' => false,
                'message' => 'Sila tunggu sebentar sebelum menyegarkan analitik semula.',
                'throttled' => true,
            ];
        }

        $triggeredBy = 'user:' . (int) $user->id;

        $result = self::collectSingleModuleAnalytics($moduleId, $token, 'manual', $triggeredBy);
        $tokenHash = hash('sha256', $token);
        $records = self::getHistoryRecords($moduleId, $tokenHash);

        return array_merge($result, [
            'html' => self::renderHistoryModal($records, $appearance, self::getAnalyticsHealth($moduleId, $tokenHash), self::getHistoryRowsPerPage($moduleId)),
            'chart' => self::buildTrendSeries($records),
            'health' => self::getAnalyticsHealth($moduleId, $tokenHash),
        ]);
    }

    /**
     * AJAX endpoint used by com_ajax to render the history modal content.
     *
     * @return  array<string, mixed>
     */
    public static function historyAjax(): array
    {
        $app = \Joomla\CMS\Factory::getApplication();
        $input = $app->input;

        if (!\Joomla\CMS\Session\Session::checkToken('request')) {
            return [
                'success' => false,
                'message' => 'Token keselamatan tidak sah.',
            ];
        }

        $moduleId = $input->getInt('module_id', 0);
        $token = $input->getString('token', '');
        $appearance = $input->getCmd('appearance', 'light');

        if (!$moduleId || !$token) {
            return [
                'success' => false,
                'message' => 'Konfigurasi sejarah tidak lengkap.',
            ];
        }

        self::ensureHistoryTable();

        $records = self::getHistoryRecords($moduleId, hash('sha256', $token));

        $health = self::getAnalyticsHealth($moduleId, hash('sha256', $token));

        return [
            'success' => true,
            'html' => self::renderHistoryModal($records, $appearance, $health, self::getHistoryRowsPerPage($moduleId)),
            'chart' => self::buildTrendSeries($records),
            'health' => $health,
        ];
    }

    /**
     * Render reusable history analytics markup for the Bootstrap modal body.
     *
     * @param   array<int, object>  $records     Historical score rows.
     * @param   string             $appearance  Current appearance mode.
     *
     * @return  string
     */
    public static function renderHistoryModal(array $records, string $appearance = 'light', ?array $health = null, ?int $rowsPerPage = null): string
    {
        $appearance = in_array($appearance, self::getAllowedAppearanceModes(), true) ? $appearance : 'light';
        $meaningfulRecords = self::getDistinctMeaningfulHistoryRecords($records);
        $latest = $meaningfulRecords[0] ?? null;
        $lowest = null;
        foreach ($meaningfulRecords as $record) {
            if ($lowest === null || (float) $record->score < (float) $lowest->score) {
                $lowest = $record;
            }
        }
        $health = $health ?? self::buildHealthFromRecords($records);
        $historyCount = count($meaningfulRecords);
        $pageSize = self::normaliseHistoryRowsPerPage($rowsPerPage);

        ob_start();
        ?>
        <div class="splask-history-content splask-history-<?php echo htmlspecialchars($appearance, ENT_QUOTES, 'UTF-8'); ?>">
            <div class="splask-history-summary" aria-label="Ringkasan sejarah SPLaSK">
                <div>
                    <span>Rekod Terkini</span>
                    <strong><?php echo $latest ? htmlspecialchars(self::formatScorePercent((float) $latest->score), ENT_QUOTES, 'UTF-8') : 'Tiada'; ?></strong>
                    <?php if ($latest) : ?>
                        <small><?php echo htmlspecialchars(self::formatHistoryDateOnly((string) ($latest->source_checked_at ?: $latest->created_at)), ENT_QUOTES, 'UTF-8'); ?></small>
                    <?php endif; ?>
                </div>
                <div>
                    <span>Markah Terendah</span>
                    <strong><?php echo $lowest ? htmlspecialchars(self::formatScorePercent((float) $lowest->score), ENT_QUOTES, 'UTF-8') : 'Tiada'; ?></strong>
                    <?php if ($lowest) : ?>
                        <small><?php echo htmlspecialchars(self::formatHistoryDateOnly((string) ($lowest->source_checked_at ?: $lowest->created_at)), ENT_QUOTES, 'UTF-8'); ?></small>
                    <?php endif; ?>
                </div>
                <div>
                    <span>Jumlah Rekod</span>
                    <strong><?php echo count($meaningfulRecords); ?></strong>
                </div>
            </div>

            <div class="splask-history-chart" data-splask-history-chart aria-label="Carta trend peratus SPLaSK">
                <?php echo self::renderTrendChart($meaningfulRecords); ?>
            </div>

            <div class="splask-history-table-shell" data-splask-history-pagination data-splask-history-page-size="<?php echo $pageSize; ?>">
                <script type="application/json" data-splask-history-records><?php echo htmlspecialchars(json_encode(self::buildHistoryTableRecords($meaningfulRecords), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]', ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></script>
                <div class="splask-history-page-size-control">
                    <label>
                        <span>Rows Per Page</span>
                        <input type="number" min="1" max="50" value="<?php echo $pageSize; ?>" data-splask-history-page-size-input aria-label="Rows Per Page" />
                    </label>
                </div>
                <div class="table-responsive splask-history-table-wrap" tabindex="0" aria-label="Senarai sejarah SPLaSK boleh ditatal">
                    <table class="table table-sm align-middle splask-history-table">
                    <thead>
                        <tr>
                            <th scope="col">Tarikh</th>
                            <th scope="col">Markah</th>
                            <th scope="col">Gred</th>
                            <th scope="col">Status</th>
                        </tr>
                    </thead>
                    <tbody data-splask-history-page-body>
                    </tbody>
                    </table>
                </div>
                <div class="splask-history-pagination" aria-label="Navigasi halaman sejarah">
                    <span data-splask-history-page-status>Showing 0–0 of <?php echo $historyCount; ?></span>
                    <div class="splask-history-pagination-actions" data-splask-history-page-actions>
                        <button type="button" class="splask-history-page-button" data-splask-history-page-prev aria-label="Previous history page">Previous</button>
                        <span class="splask-history-page-numbers" data-splask-history-page-numbers></span>
                        <button type="button" class="splask-history-page-button" data-splask-history-page-next aria-label="Next history page">Next</button>
                    </div>
                </div>
            </div>
        </div>
        <?php

        return trim((string) ob_get_clean());
    }

    /**
     * Build compact JSON payload used by client-side table pagination.
     *
     * @param   array<int, object>  $records  History rows newest first.
     *
     * @return  array<int, array<string, string>>
     */
    private static function buildHistoryTableRecords(array $records): array
    {
        $tableRecords = [];

        foreach ($records as $record) {
            $tableRecords[] = [
                'date' => self::formatHistoryDateOnly((string) ($record->source_checked_at ?: $record->created_at)),
                'score' => self::formatScorePercent((float) $record->score),
                'gradeKey' => (string) $record->grade_key,
                'gradeLabel' => (string) $record->grade_label,
                'status' => (string) $record->status_label,
            ];
        }

        return $tableRecords;
    }

    private static function getHistoryRowsPerPage(int $moduleId): int
    {
        $params = self::getModuleParams($moduleId);

        return self::normaliseHistoryRowsPerPage($params['analytics_rows_per_page'] ?? null);
    }

    private static function normaliseHistoryRowsPerPage($value): int
    {
        $rowsPerPage = (int) ($value ?? self::DEFAULT_HISTORY_ROWS_PER_PAGE);

        return min(50, max(1, $rowsPerPage ?: self::DEFAULT_HISTORY_ROWS_PER_PAGE));
    }

    /**
     * Create the history table when the SQL installer has not run yet.
     *
     * @return  void
     */
    private static function ensureHistoryTable(): void
    {
        $db = \Joomla\CMS\Factory::getDbo();
        try {
            $columns = $db->getTableColumns(self::getHistoryTableName(), false);
        } catch (\RuntimeException $exception) {
            $columns = [];
        }

        if ($columns) {
            self::migrateHistoryTable($columns);
            self::ensureHealthTable();
            return;
        }

        $queries = explode(';', (string) file_get_contents(__DIR__ . '/sql/install.mysql.utf8.sql'));

        foreach ($queries as $query) {
            $query = trim($query);
            if ($query !== '') {
                $db->setQuery($query)->execute();
            }
        }

        self::ensureHealthTable();
    }

    /**
     * Return recent history records for one module/token pair.
     *
     * @param   int     $moduleId   Joomla module id.
     * @param   string  $tokenHash  SHA-256 token hash.
     * @param   int     $limit      Maximum rows.
     *
     * @return  array<int, object>
     */
    private static function getHistoryRecords(int $moduleId, string $tokenHash, int $limit = 30): array
    {
        $db = \Joomla\CMS\Factory::getDbo();
        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName(self::getHistoryTableName()))
            ->where($db->quoteName('module_id') . ' = ' . (int) $moduleId)
            ->where($db->quoteName('token_hash') . ' = ' . $db->quote($tokenHash))
            ->order($db->quoteName('created_at') . ' DESC, ' . $db->quoteName('id') . ' DESC');

        $db->setQuery($query, 0, $limit);

        return $db->loadObjectList() ?: [];
    }

    /**
     * Return the latest history record for duplicate detection.
     *
     * @param   int     $moduleId   Joomla module id.
     * @param   string  $tokenHash  SHA-256 token hash.
     * @param   bool    $forUpdate  Lock the latest row during insert transactions.
     *
     * @return  object|null
     */
    private static function getLatestHistoryRecord(int $moduleId, string $tokenHash, bool $forUpdate = false): ?object
    {
        if (!$forUpdate) {
            $records = self::getHistoryRecords($moduleId, $tokenHash, 1);

            return $records[0] ?? null;
        }

        $db = \Joomla\CMS\Factory::getDbo();
        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName(self::getHistoryTableName()))
            ->where($db->quoteName('module_id') . ' = ' . (int) $moduleId)
            ->where($db->quoteName('token_hash') . ' = ' . $db->quote($tokenHash))
            ->order($db->quoteName('created_at') . ' DESC, ' . $db->quoteName('id') . ' DESC');

        $db->setQuery((string) $query . ' LIMIT 1 FOR UPDATE');
        $record = $db->loadObject();

        return $record ?: null;
    }

    /**
     * Decide whether an identical API result is still inside the duplicate cooldown.
     *
     * @param   object  $latest           Latest row.
     * @param   string  $signature        New meaningful snapshot signature.
     * @param   string  $recordedAt       New record timestamp.
     * @param   int     $cooldownMinutes  Duplicate cooldown window in minutes.
     *
     * @return  bool
     */
    private static function isDuplicateHistoryRecord(object $latest, string $signature, string $recordedAt, int $cooldownMinutes): bool
    {
        if ((string) ($latest->signature ?? '') !== $signature) {
            return false;
        }

        $latestRecordedAt = (string) ($latest->recorded_at ?? $latest->created_at ?? '');

        if ($latestRecordedAt !== '' && $latestRecordedAt === $recordedAt) {
            return true;
        }

        if ($cooldownMinutes <= 0) {
            return true;
        }

        try {
            $latestDate = new \DateTimeImmutable($latestRecordedAt, new \DateTimeZone('UTC'));
            $newDate = new \DateTimeImmutable($recordedAt, new \DateTimeZone('UTC'));
        } catch (\Exception $exception) {
            return true;
        }

        return abs($newDate->getTimestamp() - $latestDate->getTimestamp()) <= ($cooldownMinutes * 60);
    }

    /**
     * Collect analytics for one module/token pair using the server-side API workflow.
     *
     * @param   int     $moduleId     Module id.
     * @param   string  $token        SPLaSK token.
     * @param   string  $source       Collection source.
     * @param   string  $triggeredBy  Actor metadata.
     *
     * @return  array<string, mixed>
     */
    public static function collectSingleModuleAnalytics(int $moduleId, string $token, string $source = 'scheduler', string $triggeredBy = 'scheduler'): array
    {
        if ($moduleId <= 0 || $token === '') {
            return [
                'success' => false,
                'message' => 'Konfigurasi analitik tidak lengkap.',
            ];
        }

        self::ensureHistoryTable();
        $tokenHash = hash('sha256', $token);

        try {
            $apiData = self::fetchScoreFromApiWithRetry($token, $moduleId, $source);
            if (empty($apiData['status'])) {
                throw new \RuntimeException('Markah tidak dijumpai daripada API SPLaSK.');
            }

            $score = max(0, min(100, (float) ($apiData['final_score'] ?? 0)));
            $grade = self::getGradeStyle($score);
            $save = self::saveHistoryRecord([
                'skip_token_check' => true,
                'module_id' => $moduleId,
                'token' => $token,
                'score' => $score,
                'grade_key' => (string) $grade['key'],
                'grade_label' => (string) $grade['label'],
                'status_label' => (string) $grade['status'],
                'verification_url' => (string) ($apiData['verification_url'] ?? ''),
                'source_checked_at' => (string) ($apiData['last_check'] ?? ''),
                'source' => $source,
                'triggered_by' => $triggeredBy,
                'engine_version' => self::ENGINE_VERSION,
            ]);

            return array_merge($save, [
                'payload' => [
                    'final_score' => $score,
                    'grade_key' => (string) $grade['key'],
                    'grade_label' => (string) $grade['label'],
                    'grade_short' => (string) $grade['shortLabel'],
                    'status_label' => (string) $grade['status'],
                    'verification_url' => (string) ($apiData['verification_url'] ?? ''),
                    'last_check' => (string) ($apiData['last_check'] ?? ''),
                ],
            ]);
        } catch (\Throwable $exception) {
            self::recordAnalyticsHealth($moduleId, $tokenHash, $source, 'failed', $exception->getMessage());

            return [
                'success' => false,
                'message' => 'Analitik gagal dikemaskini: ' . $exception->getMessage(),
                'health' => self::getAnalyticsHealth($moduleId, $tokenHash),
            ];
        }
    }

    /**
     * Collect analytics for all published administrator module instances.
     *
     * @return  array<string, mixed>
     */
    public static function collectScheduledAnalytics(): array
    {
        $modules = self::getPublishedModuleConfigs();
        $results = [];

        foreach ($modules as $module) {
            $results[] = self::collectSingleModuleAnalytics((int) $module->id, (string) $module->token, 'scheduler', 'joomla-scheduler');
        }

        if (!$modules) {
            self::logAnalyticsEvent('info', 'Scheduler run skipped because no published modules have automatic analytics enabled.', ['source' => 'scheduler']);
        }

        return [
            'success' => !array_filter($results, static fn ($result) => empty($result['success'])),
            'count' => count($results),
            'results' => $results,
        ];
    }

    /**
     * Fetch the current SPLaSK score through Joomla HTTP or a cURL fallback.
     *
     * @param   string  $token  SPLaSK token.
     *
     * @return  array<string, mixed>
     */
    private static function fetchScoreFromApi(string $token): array
    {
        $url = 'https://splask-api.jdn.gov.my/api/get_my_score';
        $body = json_encode(['_token' => $token]);

        if (class_exists('\\Joomla\\CMS\\Http\\HttpFactory')) {
            $http = \Joomla\CMS\Http\HttpFactory::getHttp();
            $response = $http->post($url, $body, ['Content-Type' => 'application/json']);
            $content = (string) ($response->body ?? '');
        } elseif (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_POSTFIELDS => $body,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 30,
            ]);
            $content = (string) curl_exec($ch);
            $error = curl_error($ch);
            curl_close($ch);
            if ($error !== '') {
                throw new \RuntimeException($error);
            }
        } else {
            throw new \RuntimeException('Joomla HTTP client atau cURL tidak tersedia.');
        }

        $data = json_decode($content, true);
        if (!is_array($data)) {
            throw new \RuntimeException('Respons API SPLaSK tidak sah.');
        }

        return $data;
    }

    /**
     * Return published module ids and tokens for scheduler collection.
     *
     * @return  array<int, object>
     */
    private static function getPublishedModuleConfigs(): array
    {
        $db = \Joomla\CMS\Factory::getDbo();
        $query = $db->getQuery(true)
            ->select([$db->quoteName('id'), $db->quoteName('params')])
            ->from($db->quoteName('#__modules'))
            ->where($db->quoteName('module') . ' = ' . $db->quote('mod_splaskscore'))
            ->where($db->quoteName('client_id') . ' = 1')
            ->where($db->quoteName('published') . ' = 1');
        $db->setQuery($query);
        $rows = $db->loadObjectList() ?: [];
        $modules = [];

        foreach ($rows as $row) {
            $params = json_decode((string) $row->params, true) ?: [];
            $token = trim((string) ($params['splask_token'] ?? ''));
            $enabled = (string) ($params['analytics_auto_enabled'] ?? '1') === '1';
            if ($enabled && $token !== '') {
                $modules[] = (object) ['id' => (int) $row->id, 'token' => $token];
            }
        }

        return $modules;
    }

    /**
     * Bootstrap the shared scheduler task from the first/latest SPLaSK module during install/upgrade.
     * Runtime collection still processes every published enabled module instance; this bootstrap
     * only chooses one source of schedule settings so multiple modules do not fight over one
     * Joomla Scheduled Task before an administrator intentionally saves the preferred instance.
     *
     * @return  array<int, array<string, mixed>>
     */
    public static function synchronizeSchedulerForAllModules(): array
    {
        $db = \Joomla\CMS\Factory::getDbo();
        $query = $db->getQuery(true)
            ->select($db->quoteName('id'))
            ->from($db->quoteName('#__modules'))
            ->where($db->quoteName('module') . ' = ' . $db->quote('mod_splaskscore'))
            ->where($db->quoteName('client_id') . ' = 1')
            ->order($db->quoteName('id') . ' DESC');
        $db->setQuery($query);
        $ids = array_map('intval', $db->loadColumn() ?: []);
        $results = [];

        foreach ($ids as $id) {
            $results[] = self::synchronizeSchedulerForModule($id);
            break;
        }

        return $results;
    }

    /**
     * Synchronize Joomla Scheduler with the saved module automation settings.
     *
     * @param   int  $moduleId  Module id that owns the operational settings.
     *
     * @return  array<string, mixed>
     */
    public static function synchronizeSchedulerForModule(int $moduleId): array
    {
        $params = self::getModuleParams($moduleId);
        if ($moduleId <= 0 || !$params) {
            return ['success' => false, 'status' => 'disabled', 'message' => 'Module settings are unavailable.'];
        }

        $enabled = (string) ($params['analytics_auto_enabled'] ?? '1') === '1';
        $frequency = in_array((string) ($params['analytics_frequency'] ?? 'daily'), ['daily', 'hourly'], true) ? (string) $params['analytics_frequency'] : 'daily';
        $time = self::normaliseCollectionTime((string) ($params['analytics_collection_time'] ?? '06:00'));
        $status = $enabled ? 'enabled' : 'disabled';

        try {
            self::ensureSchedulerPluginEnabled();
            $db = \Joomla\CMS\Factory::getDbo();
            $columns = self::getTableColumns('#__scheduler_tasks');

            if (!$columns) {
                self::updateModuleAutomationMetadata($moduleId, 'Scheduler unavailable', '');

                return ['success' => false, 'status' => 'unavailable', 'message' => 'Joomla Scheduler task table is unavailable.'];
            }

            $task = self::getManagedSchedulerTask();
            $now = \Joomla\CMS\Factory::getDate()->toSql();
            $rules = self::buildSchedulerRules($frequency, $time);
            $taskParams = [
                'managed_by' => 'mod_splaskscore',
                'module_id' => $moduleId,
                'frequency' => $frequency,
                'collection_time' => $time,
                'duplicate_cooldown_minutes' => self::getDuplicateCooldownMinutes($moduleId),
                'retention_days' => max(1, (int) ($params['analytics_retention_days'] ?? self::DEFAULT_RETENTION_DAYS)),
                'max_history_records' => max(1, (int) ($params['analytics_max_rows'] ?? self::DEFAULT_MAX_HISTORY_ROWS)),
            ];

            $values = [
                'title' => 'SPLaSK Score Analytics Collection',
                'type' => self::SCHEDULER_TASK_TYPE,
                'state' => $enabled ? 1 : 0,
                'execution_rules' => $rules['execution_rules'],
                'params' => $taskParams,
                'note' => 'Managed automatically from the SPLaSK Score module settings. Daily collection time uses the Joomla configured timezone. Automated analytics collection depends on Joomla Scheduled Tasks being active in the hosting environment. Manual scheduler edits are preserved only until the module is saved again.',
                'priority' => 5,
                'cli_exclusive' => 0,
            ];

            if (!$task || empty($task->id)) {
                $values['created'] = $now;
                $values['created_by'] = 0;
                $values['ordering'] = 0;
                $values['times_executed'] = 0;
                $values['times_failed'] = 0;
            }

            self::saveSchedulerTaskWithJoomlaModel($task, $values, $columns);
            self::clearSchedulerCache();

            $savedTask = self::getManagedSchedulerTask();
            $nextExecution = (string) ($savedTask->next_execution ?? '');
            $lastSuccess = self::getModuleLastSuccessfulCollection($moduleId);
            self::updateModuleAutomationMetadata($moduleId, ucfirst($status) . ' (' . $frequency . ($frequency === 'daily' ? ' at ' . $time : '') . ')', $lastSuccess);
            self::logAnalyticsEvent('info', 'Scheduler synchronized from module settings.', ['module_id' => $moduleId, 'status' => $status, 'frequency' => $frequency, 'time' => $time, 'next_execution' => $nextExecution]);

            return ['success' => true, 'status' => $status, 'frequency' => $frequency, 'time' => $time, 'next_execution' => $nextExecution, 'last_success' => $lastSuccess];
        } catch (\Throwable $exception) {
            try {
                \Joomla\CMS\Factory::getDbo()->transactionRollback();
            } catch (\Throwable $rollbackException) {
                // Ignore rollback failures after non-transactional errors.
            }
            self::logAnalyticsEvent('error', 'Scheduler synchronization failed.', ['module_id' => $moduleId, 'error' => $exception->getMessage()]);
            self::updateModuleAutomationMetadata($moduleId, 'Sync failed', '');

            return ['success' => false, 'status' => 'failed', 'message' => $exception->getMessage()];
        }
    }

    private static function getManagedSchedulerTask(): ?object
    {
        $db = \Joomla\CMS\Factory::getDbo();
        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__scheduler_tasks'))
            ->where($db->quoteName('type') . ' = ' . $db->quote(self::SCHEDULER_TASK_TYPE))
            ->order($db->quoteName('id') . ' ASC');
        $db->setQuery($query, 0, 1);
        $task = $db->loadObject();

        return $task ?: null;
    }

    private static function saveSchedulerTaskWithJoomlaModel(?object $task, array $values, array $columns): void
    {
        $model = self::createSchedulerTaskModel();
        $data = [];

        if ($task && !empty($task->id)) {
            $data['id'] = (int) $task->id;
        }

        foreach ($values as $column => $value) {
            if (isset($columns[$column]) || $column === 'id') {
                $data[$column] = $value;
            }
        }

        if (isset($columns['locked'])) {
            $data['locked'] = null;
        }

        if (!$model->save($data)) {
            $error = method_exists($model, 'getError') ? (string) $model->getError() : '';
            throw new \RuntimeException($error !== '' ? $error : 'Joomla Scheduler task save failed.');
        }
    }

    private static function createSchedulerTaskModel(): object
    {
        $app = \Joomla\CMS\Factory::getApplication();
        $component = method_exists($app, 'bootComponent') ? $app->bootComponent('com_scheduler') : null;

        if (!$component || !method_exists($component, 'getMVCFactory')) {
            throw new \RuntimeException('Joomla Scheduler component is unavailable.');
        }

        $model = $component->getMVCFactory()->createModel('Task', 'Administrator', ['ignore_request' => true]);

        if (!$model || !method_exists($model, 'save')) {
            throw new \RuntimeException('Joomla Scheduler task model is unavailable.');
        }

        return $model;
    }

    private static function buildSchedulerRules(string $frequency, string $time): array
    {
        $basis = \Joomla\CMS\Factory::getDate();
        $execDay = $basis->format('d', true);
        $execTime = $basis->format('H:i', true);

        if ($frequency === 'hourly') {
            return [
                'execution_rules' => ['rule-type' => 'interval-hours', 'interval-hours' => 1, 'exec-day' => $execDay, 'exec-time' => $execTime],
            ];
        }

        [$hour, $minute] = array_map('intval', explode(':', $time));

        return [
            'execution_rules' => ['rule-type' => 'interval-days', 'interval-days' => 1, 'exec-day' => $execDay, 'exec-time' => sprintf('%02d:%02d', $hour, $minute)],
        ];
    }

    private static function normaliseCollectionTime(string $time): string
    {
        if (!preg_match('/^(\\d{1,2}):(\\d{2})$/', $time, $matches)) {
            return '06:00';
        }

        $hour = max(0, min(23, (int) $matches[1]));
        $minute = max(0, min(59, (int) $matches[2]));

        return sprintf('%02d:%02d', $hour, $minute);
    }

    private static function clearSchedulerCache(): void
    {
        if (!class_exists('Joomla\CMS\Factory')) {
            return;
        }

        try {
            \Joomla\CMS\Factory::getCache('com_scheduler')->clean();
        } catch (\Throwable $exception) {
            // Cache cleanup is best-effort; the database row remains the source of truth.
        }
    }

    private static function ensureSchedulerPluginEnabled(): void
    {
        $db = \Joomla\CMS\Factory::getDbo();
        $query = $db->getQuery(true)
            ->update($db->quoteName('#__extensions'))
            ->set($db->quoteName('enabled') . ' = 1')
            ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
            ->where($db->quoteName('folder') . ' = ' . $db->quote('task'))
            ->where($db->quoteName('element') . ' = ' . $db->quote('splaskscoreanalytics'));
        $db->setQuery($query)->execute();
    }

    private static function updateModuleAutomationMetadata(int $moduleId, string $status, string $lastSuccess): void
    {
        $params = self::getModuleParams($moduleId);
        if (!$params) {
            return;
        }

        $params['analytics_scheduler_status'] = $status;
        $params['analytics_last_successful_collection'] = $lastSuccess;
        self::writeModuleParams($moduleId, $params);
    }

    private static function updateModuleLastSuccessfulCollection(int $moduleId, string $lastSuccess): void
    {
        if ($lastSuccess === '') {
            return;
        }

        $params = self::getModuleParams($moduleId);
        if (!$params) {
            return;
        }

        $params['analytics_last_successful_collection'] = $lastSuccess;
        try {
            self::writeModuleParams($moduleId, $params);
        } catch (\Throwable $exception) {
            self::logAnalyticsEvent('warning', 'Unable to update last successful analytics collection metadata.', ['module_id' => $moduleId, 'error' => $exception->getMessage()]);
        }
    }

    private static function writeModuleParams(int $moduleId, array $params): void
    {
        $db = \Joomla\CMS\Factory::getDbo();
        $query = $db->getQuery(true)
            ->update($db->quoteName('#__modules'))
            ->set($db->quoteName('params') . ' = ' . $db->quote(json_encode($params)))
            ->where($db->quoteName('id') . ' = ' . (int) $moduleId)
            ->where($db->quoteName('module') . ' = ' . $db->quote('mod_splaskscore'));
        $db->setQuery($query)->execute();
    }

    private static function getModuleLastSuccessfulCollection(int $moduleId): string
    {
        self::ensureHistoryTable();
        self::ensureHealthTable();

        return self::latestSuccessfulCollectionTimestamp($moduleId);
    }

    private static function latestSuccessfulCollectionTimestamp(int $moduleId, string $tokenHash = ''): string
    {
        $db = \Joomla\CMS\Factory::getDbo();
        $healthQuery = $db->getQuery(true)
            ->select('MAX(' . $db->quoteName('recorded_at') . ')')
            ->from($db->quoteName(self::getHealthTableName()))
            ->where($db->quoteName('module_id') . ' = ' . (int) $moduleId)
            ->where($db->quoteName('status') . ' = ' . $db->quote('success'));

        $historyQuery = $db->getQuery(true)
            ->select('MAX(COALESCE(' . $db->quoteName('recorded_at') . ', ' . $db->quoteName('created_at') . '))')
            ->from($db->quoteName(self::getHistoryTableName()))
            ->where($db->quoteName('module_id') . ' = ' . (int) $moduleId);

        if ($tokenHash !== '') {
            $healthQuery->where($db->quoteName('token_hash') . ' = ' . $db->quote($tokenHash));
            $historyQuery->where($db->quoteName('token_hash') . ' = ' . $db->quote($tokenHash));
        }

        $db->setQuery($healthQuery);
        $healthSuccess = (string) $db->loadResult();
        $db->setQuery($historyQuery);
        $historySuccess = (string) $db->loadResult();

        return strcmp($historySuccess, $healthSuccess) > 0 ? $historySuccess : $healthSuccess;
    }

    private static function fetchScoreFromApiWithRetry(string $token, int $moduleId, string $source): array
    {
        $lastException = null;
        for ($attempt = 1; $attempt <= self::API_RETRY_ATTEMPTS; $attempt++) {
            try {
                return self::fetchScoreFromApi($token);
            } catch (\Throwable $exception) {
                $lastException = $exception;
                self::logAnalyticsEvent('warning', 'SPLaSK API fetch attempt failed.', ['module_id' => $moduleId, 'source' => $source, 'attempt' => $attempt, 'error' => $exception->getMessage()]);
                if ($attempt < self::API_RETRY_ATTEMPTS) {
                    usleep((int) (200000 * $attempt));
                }
            }
        }

        throw $lastException ?: new \RuntimeException('SPLaSK API request failed.');
    }

    private static function allowManualRefresh(int $moduleId, int $userId): bool
    {
        $session = \Joomla\CMS\Factory::getSession();
        $key = 'mod_splaskscore.refresh.' . $moduleId . '.' . $userId;
        $now = time();
        $last = (int) $session->get($key, 0);
        if ($last > 0 && ($now - $last) < self::MANUAL_REFRESH_COOLDOWN_SECONDS) {
            return false;
        }

        $session->set($key, $now);

        return true;
    }

    private static function getTableColumns(string $table): array
    {
        try {
            return \Joomla\CMS\Factory::getDbo()->getTableColumns($table) ?: [];
        } catch (\Throwable $exception) {
            return [];
        }
    }

    private static function logAnalyticsEvent(string $level, string $message, array $context = []): void
    {
        if (!class_exists('\\Joomla\\CMS\\Log\\Log')) {
            return;
        }

        $priority = \Joomla\CMS\Log\Log::INFO;
        if ($level === 'error') {
            $priority = \Joomla\CMS\Log\Log::ERROR;
        } elseif ($level === 'warning') {
            $priority = \Joomla\CMS\Log\Log::WARNING;
        }

        \Joomla\CMS\Log\Log::add($message . ' ' . json_encode($context), $priority, 'mod_splaskscore.analytics');
    }

    /**
     * Build a stable content signature for duplicate protection.
     */
    private static function buildHistorySignature(float $score, string $gradeKey, string $statusLabel, string $verificationUrl, ?string $sourceCheckedAt): string
    {
        return hash('sha256', implode('|', [number_format($score, 2, '.', ''), $gradeKey, $statusLabel, $verificationUrl, (string) $sourceCheckedAt]));
    }

    private static function getDuplicateCooldownMinutes(int $moduleId, int $override = 0): int
    {
        if ($override > 0) {
            return $override;
        }

        $params = self::getModuleParams($moduleId);
        return max(1, (int) ($params['analytics_duplicate_cooldown'] ?? self::DEFAULT_DUPLICATE_COOLDOWN_MINUTES));
    }

    private static function getModuleParams(int $moduleId): array
    {
        if ($moduleId <= 0) {
            return [];
        }

        $db = \Joomla\CMS\Factory::getDbo();
        $query = $db->getQuery(true)
            ->select($db->quoteName('params'))
            ->from($db->quoteName('#__modules'))
            ->where($db->quoteName('id') . ' = ' . (int) $moduleId)
            ->where($db->quoteName('module') . ' = ' . $db->quote('mod_splaskscore'));
        $db->setQuery($query, 0, 1);
        $params = json_decode((string) $db->loadResult(), true);

        return is_array($params) ? $params : [];
    }

    private static function applyRetentionPolicy(int $moduleId, string $tokenHash): void
    {
        $params = self::getModuleParams($moduleId);
        $retentionDays = max(1, (int) ($params['analytics_retention_days'] ?? self::DEFAULT_RETENTION_DAYS));
        $maxRows = max(1, (int) ($params['analytics_max_rows'] ?? self::DEFAULT_MAX_HISTORY_ROWS));
        $db = \Joomla\CMS\Factory::getDbo();
        $cutoff = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->modify('-' . $retentionDays . ' days')->format('Y-m-d H:i:s');

        $query = $db->getQuery(true)
            ->delete($db->quoteName(self::getHistoryTableName()))
            ->where($db->quoteName('module_id') . ' = ' . (int) $moduleId)
            ->where($db->quoteName('token_hash') . ' = ' . $db->quote($tokenHash))
            ->where($db->quoteName('recorded_at') . ' < ' . $db->quote($cutoff));
        $db->setQuery($query)->execute();

        $query = $db->getQuery(true)
            ->select($db->quoteName('id'))
            ->from($db->quoteName(self::getHistoryTableName()))
            ->where($db->quoteName('module_id') . ' = ' . (int) $moduleId)
            ->where($db->quoteName('token_hash') . ' = ' . $db->quote($tokenHash))
            ->order($db->quoteName('recorded_at') . ' DESC');
        $db->setQuery($query, $maxRows, 100000);
        $ids = array_map('intval', $db->loadColumn() ?: []);
        if ($ids) {
            $query = $db->getQuery(true)
                ->delete($db->quoteName(self::getHistoryTableName()))
                ->where($db->quoteName('id') . ' IN (' . implode(',', $ids) . ')');
            $db->setQuery($query)->execute();
        }
    }

    private static function migrateHistoryTable(array $columns): void
    {
        $db = \Joomla\CMS\Factory::getDbo();
        $definitions = [
            'source' => "ALTER TABLE `#__splaskscore_history` ADD `source` varchar(32) NOT NULL DEFAULT 'dashboard' AFTER `source_checked_at`",
            'recorded_at' => 'ALTER TABLE `#__splaskscore_history` ADD `recorded_at` datetime NULL DEFAULT NULL AFTER `source`',
            'engine_version' => "ALTER TABLE `#__splaskscore_history` ADD `engine_version` varchar(32) NOT NULL DEFAULT '' AFTER `recorded_at`",
            'signature' => "ALTER TABLE `#__splaskscore_history` ADD `signature` char(64) NOT NULL DEFAULT '' AFTER `engine_version`",
            'triggered_by' => "ALTER TABLE `#__splaskscore_history` ADD `triggered_by` varchar(128) NOT NULL DEFAULT '' AFTER `signature`",
        ];

        foreach ($definitions as $column => $sql) {
            if (!isset($columns[$column])) {
                $db->setQuery($sql)->execute();
            }
        }

        $db->setQuery("UPDATE `#__splaskscore_history` SET `recorded_at` = `created_at` WHERE `recorded_at` IS NULL")->execute();
        $db->setQuery("UPDATE `#__splaskscore_history` SET `signature` = SHA2(CONCAT(FORMAT(`score`, 2), '|', `grade_key`, '|', `status_label`, '|', `verification_url`, '|', COALESCE(`source_checked_at`, '')), 256) WHERE `signature` = ''")->execute();
    }

    private static function getHealthTableName(): string
    {
        return '#__splaskscore_health';
    }

    private static function ensureHealthTable(): void
    {
        $db = \Joomla\CMS\Factory::getDbo();
        $db->setQuery("CREATE TABLE IF NOT EXISTS `#__splaskscore_health` (\n  `id` int unsigned NOT NULL AUTO_INCREMENT,\n  `module_id` int unsigned NOT NULL DEFAULT 0,\n  `token_hash` char(64) NOT NULL,\n  `source` varchar(32) NOT NULL DEFAULT 'scheduler',\n  `status` varchar(16) NOT NULL DEFAULT 'success',\n  `message` varchar(255) NOT NULL DEFAULT '',\n  `recorded_at` datetime NOT NULL,\n  PRIMARY KEY (`id`),\n  KEY `idx_splaskscore_health_lookup` (`module_id`, `token_hash`, `recorded_at`)\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci")->execute();
    }

    private static function recordAnalyticsHealth(int $moduleId, string $tokenHash, string $source, string $status, string $message = '', ?string $recordedAt = null): void
    {
        self::ensureHealthTable();
        $db = \Joomla\CMS\Factory::getDbo();
        $record = (object) [
            'module_id' => $moduleId,
            'token_hash' => $tokenHash,
            'source' => self::cleanHistoryText($source, 32),
            'status' => self::cleanHistoryText($status, 16),
            'message' => self::cleanHistoryText($message, 255),
            'recorded_at' => $recordedAt ?: \Joomla\CMS\Factory::getDate()->toSql(),
        ];
        $db->insertObject(self::getHealthTableName(), $record);
    }

    public static function getAnalyticsHealth(int $moduleId, string $tokenHash): array
    {
        self::ensureHistoryTable();
        self::ensureHealthTable();
        $records = self::getHistoryRecords($moduleId, $tokenHash, 1);
        $latest = $records[0] ?? null;
        $today = (new \DateTimeImmutable('today', new \DateTimeZone('UTC')))->format('Y-m-d 00:00:00');
        $db = \Joomla\CMS\Factory::getDbo();

        $successQuery = $db->getQuery(true)
            ->select('MAX(' . $db->quoteName('recorded_at') . ')')
            ->from($db->quoteName(self::getHealthTableName()))
            ->where($db->quoteName('module_id') . ' = ' . (int) $moduleId)
            ->where($db->quoteName('token_hash') . ' = ' . $db->quote($tokenHash))
            ->where($db->quoteName('status') . ' = ' . $db->quote('success'));
        $db->setQuery($successQuery);
        $lastSuccess = (string) $db->loadResult();

        $failedQuery = $db->getQuery(true)
            ->select('MAX(' . $db->quoteName('recorded_at') . ')')
            ->from($db->quoteName(self::getHealthTableName()))
            ->where($db->quoteName('module_id') . ' = ' . (int) $moduleId)
            ->where($db->quoteName('token_hash') . ' = ' . $db->quote($tokenHash))
            ->where($db->quoteName('status') . ' = ' . $db->quote('failed'));
        $db->setQuery($failedQuery);
        $lastFailed = (string) $db->loadResult();

        $missingToday = true;
        if ($latest) {
            $recorded = (string) ($latest->recorded_at ?? $latest->created_at);
            $missingToday = $recorded < $today;
        }

        $fallbackSuccess = $latest ? (string) ($latest->recorded_at ?? $latest->created_at) : '';
        $effectiveSuccess = $lastSuccess !== '' ? $lastSuccess : $fallbackSuccess;
        if ($fallbackSuccess !== '' && ($effectiveSuccess === '' || strcmp($fallbackSuccess, $effectiveSuccess) > 0)) {
            $effectiveSuccess = $fallbackSuccess;
        }

        // The dashboard must reflect the newest operational event: a failure
        // after the latest success/history snapshot is release-blocking and
        // must not be masked by an older successful collection.
        $status = 'UNKNOWN';
        if ($lastFailed !== '' && ($effectiveSuccess === '' || strcmp($lastFailed, $effectiveSuccess) > 0)) {
            $status = 'FAILED';
        } elseif ($effectiveSuccess !== '') {
            $status = 'SUCCESS';
        }

        return [
            'last_success' => $effectiveSuccess,
            'last_failed' => $lastFailed,
            'status' => $status,
            'missing_today' => $missingToday,
            'source' => $latest ? (string) ($latest->source ?? 'dashboard') : '',
        ];
    }

    private static function buildHealthFromRecords(array $records): array
    {
        $latest = $records[0] ?? null;
        $today = (new \DateTimeImmutable('today', new \DateTimeZone('UTC')))->format('Y-m-d 00:00:00');
        $recorded = $latest ? (string) ($latest->recorded_at ?? $latest->created_at) : '';

        return [
            'last_success' => $recorded,
            'last_failed' => '',
            'status' => $latest ? 'SUCCESS' : 'UNKNOWN',
            'missing_today' => $recorded === '' || $recorded < $today,
            'source' => $latest ? (string) ($latest->source ?? 'dashboard') : '',
        ];
    }

    /**
     * Return chart-ready records in chronological order.
     *
     * @param   array<int, object>  $records  History rows newest first.
     *
     * @return  array<int, array<string, mixed>>
     */
    private static function getDistinctMeaningfulHistoryRecords(array $records): array
    {
        $distinct = [];
        $seen = [];

        foreach ($records as $record) {
            $key = self::getHistoryMeaningfulKey($record);
            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $distinct[] = $record;
        }

        return $distinct;
    }

    private static function getHistoryMeaningfulKey(object $record): string
    {
        $signature = (string) ($record->signature ?? '');
        if ($signature !== '') {
            return $signature;
        }

        return self::buildHistorySignature(
            (float) ($record->score ?? 0),
            (string) ($record->grade_key ?? ''),
            (string) ($record->status_label ?? ''),
            (string) ($record->verification_url ?? ''),
            (string) ($record->source_checked_at ?? '')
        );
    }

    private static function buildTrendSeries(array $records): array
    {
        $series = [];
        $cutoff = (new \DateTimeImmutable('today', new \DateTimeZone('UTC')))
            ->modify('-' . (self::HISTORY_CHART_DAY_WINDOW - 1) . ' days');
        $recentRecords = [];

        foreach (self::getDistinctMeaningfulHistoryRecords($records) as $record) {
            $recordDate = self::getHistoryRecordDate($record);
            if ($recordDate === null || $recordDate < $cutoff) {
                continue;
            }

            $recentRecords[] = $record;
        }

        foreach (array_reverse($recentRecords) as $record) {
            $series[] = [
                'label' => self::formatHistoryDateOnly((string) ($record->source_checked_at ?: $record->created_at)),
                'score' => (float) $record->score,
            ];
        }

        return $series;
    }

    private static function getHistoryRecordDate(object $record): ?\DateTimeImmutable
    {
        $value = (string) ($record->source_checked_at ?: $record->created_at ?: '');
        if ($value === '') {
            return null;
        }

        try {
            return new \DateTimeImmutable($value, new \DateTimeZone('UTC'));
        } catch (\Exception $exception) {
            return null;
        }
    }

    /**
     * Render a responsive canvas line chart shell hydrated by splaskscore.js.
     *
     * @param   array<int, object>  $records  History rows newest first.
     *
     * @return  string
     */
    private static function renderTrendChart(array $records): string
    {
        $series = self::buildTrendSeries($records);

        if (count($series) < 2) {
            return '<div class="splask-history-empty-chart">Carta akan dipaparkan selepas dua rekod berjaya disimpan.</div>';
        }

        $encodedSeries = htmlspecialchars(json_encode($series, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]', ENT_QUOTES, 'UTF-8');

        return '<div class="splask-history-chart-canvas-wrap">'
            . '<canvas class="splask-history-line-chart" data-splask-line-chart data-splask-chart-points="' . $encodedSeries . '" width="640" height="220" aria-label="Carta garis peratus sejarah SPLaSK untuk trend 30 hari terkini" role="img"></canvas>'
            . '<div class="splask-history-tooltip" data-splask-chart-tooltip hidden></div>'
            . '</div>';
    }

    /**
     * Normalize API date strings to SQL datetime.
     *
     * @param   string  $value  API date value.
     *
     * @return  string|null
     */
    private static function normaliseHistoryDate(string $value): ?string
    {
        if ($value === '') {
            return null;
        }

        $timezone = new \DateTimeZone('UTC');
        $formats = ['!d/m/Y H:i:s', '!d/m/Y H:i', '!Y-m-d H:i:s', DATE_ATOM];

        foreach ($formats as $format) {
            $date = \DateTimeImmutable::createFromFormat($format, $value, $timezone);
            $errors = \DateTimeImmutable::getLastErrors();
            if ($date instanceof \DateTimeImmutable && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) {
                return $date->setTimezone($timezone)->format('Y-m-d H:i:s');
            }
        }

        try {
            $date = new \DateTimeImmutable($value, $timezone);
        } catch (\Exception $exception) {
            return null;
        }

        return $date->setTimezone($timezone)->format('Y-m-d H:i:s');
    }

    /**
     * Format a score percentage without rounding away API decimal precision.
     *
     * @param   float  $score  Score value.
     *
     * @return  string
     */
    private static function formatScorePercent(float $score): string
    {
        $formatted = number_format($score, 2, '.', '');
        $formatted = rtrim(rtrim($formatted, '0'), '.');

        return ($formatted === '' ? '0' : $formatted) . '%';
    }

    /**
     * Format a signed score movement with the same precision as score displays.
     *
     * @param   float  $delta  Score movement.
     *
     * @return  string
     */
    private static function formatSignedScoreDelta(float $delta): string
    {
        return ($delta >= 0 ? '+' : '') . self::formatScorePercent($delta);
    }

    /**
     * Format a SQL datetime as a Malay date without time.
     *
     * @param   string  $value  SQL datetime.
     *
     * @return  string
     */
    private static function formatHistoryDateOnly(string $value): string
    {
        if ($value === '') {
            return 'Tiada';
        }

        try {
            $date = new \DateTimeImmutable($value, new \DateTimeZone('UTC'));
        } catch (\Exception $exception) {
            return $value;
        }

        $date = $date->setTimezone(new \DateTimeZone('UTC'));
        $month = self::MALAY_MONTHS[(int) $date->format('n')];

        return $date->format('j') . ' ' . $month . ' ' . $date->format('Y');
    }

    /**
     * Format a SQL datetime for display using Malay weekday and month names.
     *
     * @param   string  $value  SQL datetime.
     *
     * @return  string
     */
    private static function formatHistoryDate(string $value): string
    {
        if ($value === '') {
            return 'Tiada';
        }

        try {
            $date = new \DateTimeImmutable($value, new \DateTimeZone('UTC'));
        } catch (\Exception $exception) {
            return $value;
        }

        $date = $date->setTimezone(new \DateTimeZone('UTC'));
        $weekday = self::MALAY_WEEKDAYS[(int) $date->format('w')];
        $month = self::MALAY_MONTHS[(int) $date->format('n')];

        return $weekday . ' • ' . $date->format('j') . ' ' . $month . ' ' . $date->format('Y') . ' • ' . $date->format('g:i A');
    }

    /**
     * Clean short text values before database storage/display.
     *
     * @param   string  $value   Raw value.
     * @param   int     $length  Maximum length.
     *
     * @return  string
     */
    private static function cleanHistoryText(string $value, int $length): string
    {
        $value = trim(strip_tags($value));

        return function_exists('mb_substr') ? mb_substr($value, 0, $length) : substr($value, 0, $length);
    }

    /**
     * Return JSON grade rules suitable for safe use in a data attribute.
     *
     * @return  string
     */
    public static function getGradeRulesJson(): string
    {
        return htmlspecialchars(
            json_encode(self::getGradeRules(), JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_HEX_TAG),
            ENT_QUOTES,
            'UTF-8'
        );
    }
}
