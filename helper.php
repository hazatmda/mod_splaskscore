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

    private const ENGINE_VERSION = '1.6.5';

    private const DEFAULT_DUPLICATE_COOLDOWN_MINUTES = 10;

    private const DEFAULT_RETENTION_ENABLED = false;

    private const DEFAULT_RETENTION_DAYS = 365;

    private const DEFAULT_MAX_HISTORY_ROWS = 500;

    private const DEFAULT_HISTORY_ROWS_PER_PAGE = 7;

    private const HISTORY_CHART_DAY_WINDOW = 30;

    private const MINI_TREND_DAY_WINDOW = 7;

    private const SCHEDULER_TASK_TYPE = 'splaskscore.analytics.collect';

    private const MANUAL_REFRESH_COOLDOWN_SECONDS = 60;

    private const API_RETRY_ATTEMPTS = 3;


    private const DEFAULT_BRANDING = [
        'dashboard_title' => 'Markah Penilaian SPLaSK',
        'dashboard_subtitle' => 'Papan pemuka pemantauan operasi',
        'analytics_title' => 'Sejarah & Analitik SPLaSK',
        'analytics_subtitle' => 'Rekod markah terkini dan trend prestasi.',
        'button_verification_label' => 'Lihat Pengesahan Penuh',
        'button_history_label' => 'Sejarah & Analitik',
        'button_refresh_label' => 'Segar Semula Analitik',
        'button_refresh_loading_label' => 'Menyegar semula...',
        'button_refresh_current_label' => 'Sudah Terkini',
        'button_refresh_failed_label' => 'Segar Semula Gagal',
        'button_close_label' => 'Tutup',
        'kpi_mini_trend_label' => 'Trend 7 Hari',
        'kpi_check_date_label' => 'Tarikh Semakan',
        'kpi_next_check_label' => 'Semakan Seterusnya',
        'kpi_score_today_label' => 'Skor Hari Ini',
        'kpi_lowest_score_label' => 'Skor Terendah',
        'kpi_total_records_label' => 'Jumlah Rekod',
        'kpi_total_records_caption' => 'Semua rekod DB',
        'kpi_rows_per_page_label' => 'Baris Setiap Halaman',
        'graph_mini_trend_aria_label' => 'Graf garis trend operasi tujuh hari',
        'graph_history_chart_aria_label' => 'Carta trend peratus SPLaSK',
        'history_table_aria_label' => 'Senarai sejarah SPLaSK boleh ditatal',
        'history_table_date_label' => 'Tarikh',
        'history_table_time_label' => 'Masa Semakan',
        'history_table_score_label' => 'Markah',
        'history_table_grade_label' => 'Gred',
        'history_table_status_label' => 'Status',
        'score_loading_label' => 'Memuatkan...',
        'status_waiting_label' => 'Menunggu semakan',
        'history_pagination_aria_label' => 'Navigasi halaman sejarah',
        'history_previous_label' => 'Sebelumnya',
        'history_next_label' => 'Seterusnya',
        'history_page_status_template' => 'Memaparkan {start}–{end} daripada {total}',
        'history_page_number_aria_template' => 'Pergi ke halaman sejarah {page}',
        'history_empty_label' => 'Belum ada rekod sejarah. Rekod akan disimpan selepas markah berjaya dimuatkan.',
        'history_loading_label' => 'Memuatkan sejarah...',
        'history_load_failed_label' => 'Sejarah tidak dapat dimuatkan.',
        'history_connection_error_label' => 'Ralat sambungan semasa memuatkan sejarah.',
    ];




    /**
     * Return the current Joomla-configured clock seed for dashboard telemetry.
     *
     * @return  array{timezone: string, epoch: int, display: string}
     */
    public static function getJoomlaClockSeed(): array
    {
        $timezone = 'UTC';

        try {
            $config = \Joomla\CMS\Factory::getConfig();
            $configuredTimezone = (string) $config->get('offset', 'UTC');
            $timezone = $configuredTimezone !== '' ? $configuredTimezone : 'UTC';
            $timezoneObject = new \DateTimeZone($timezone);
        } catch (\Throwable $exception) {
            $timezone = 'UTC';
            $timezoneObject = new \DateTimeZone('UTC');
        }

        try {
            $now = new \Joomla\CMS\Date\Date('now', new \DateTimeZone('UTC'));
            $now->setTimezone($timezoneObject);
        } catch (\Throwable $exception) {
            $now = new \DateTimeImmutable('now', $timezoneObject);
        }

        return [
            'timezone' => $timezone,
            'epoch' => (int) $now->format('U'),
            'display' => self::formatMalayClockDate($now),
        ];
    }

    /**
     * Format a Joomla Date/DateTime object using the compact Malay dashboard clock style.
     *
     * @param   \DateTimeInterface  $date  Date in the desired Joomla timezone.
     *
     * @return  string
     */
    private static function formatMalayClockDate(\DateTimeInterface $date): string
    {
        $weekdays = [
            0 => 'Ahad',
            1 => 'Isnin',
            2 => 'Selasa',
            3 => 'Rabu',
            4 => 'Khamis',
            5 => 'Jumaat',
            6 => 'Sabtu',
        ];

        $hour = (int) $date->format('G');
        $displayHour = $hour % 12 ?: 12;
        $period = $hour >= 12 ? 'PM' : 'AM';
        $month = self::MALAY_MONTHS[(int) $date->format('n')] ?? $date->format('M');

        return sprintf(
            '%s • %d %s %s • %d:%s %s',
            $weekdays[(int) $date->format('w')] ?? $date->format('D'),
            (int) $date->format('j'),
            $month,
            $date->format('Y'),
            $displayHour,
            $date->format('i'),
            $period
        );
    }

    /**
     * Return administrator-configurable dashboard branding labels.
     *
     * @param   object|null  $params  Joomla module parameters registry.
     *
     * @return  array<string, string>
     */
    public static function getBranding($params = null): array
    {
        $branding = self::DEFAULT_BRANDING;

        foreach ($branding as $key => $default) {
            if ($params && method_exists($params, 'get')) {
                $value = trim((string) $params->get($key, $default));
                $branding[$key] = $value !== '' ? self::cleanHistoryText($value, 180) : $default;
            }
        }

        return $branding;
    }

    /**
     * Return branding labels for a module id, falling back to defaults.
     *
     * @param   int  $moduleId  Joomla module id.
     *
     * @return  array<string, string>
     */
    private static function getBrandingForModule(int $moduleId): array
    {
        if ($moduleId <= 0) {
            return self::getBranding(null);
        }

        try {
            $db = \Joomla\CMS\Factory::getDbo();
            $query = $db->getQuery(true)
                ->select($db->quoteName('params'))
                ->from($db->quoteName('#__modules'))
                ->where($db->quoteName('id') . ' = ' . (int) $moduleId)
                ->where($db->quoteName('module') . ' = ' . $db->quote('mod_splaskscore'));
            $db->setQuery($query);
            $rawParams = (string) $db->loadResult();
        } catch (\Throwable $exception) {
            return self::getBranding(null);
        }

        $registry = new \Joomla\Registry\Registry($rawParams ?: '{}');

        return self::getBranding($registry);
    }

    /**
     * Return escaped JSON labels for safe HTML data attributes.
     *
     * @param   array<string, string>  $branding  Branding labels.
     *
     * @return  string
     */
    public static function getBrandingJson(array $branding): string
    {
        return htmlspecialchars(json_encode($branding, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}', ENT_QUOTES, 'UTF-8');
    }

    /**
     * Return the layout names currently supported by the module.
     *
     * @return  string[]
     */
    public static function getAllowedDesignPresets(): array
    {
        return [
            'dashboard_tile',
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
        return 'dashboard_tile';
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
     * Store a normalized daily score snapshot for one module/token pair.
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

        self::normalizeDailyHistoryDuplicates($moduleId, $tokenHash);

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
                'mini_trend' => self::buildDashboardMiniTrendSeries($moduleId, $tokenHash),
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
            self::normalizeDailyHistoryDuplicates($moduleId, $tokenHash);
            $daily = self::getDailyHistoryRecord($moduleId, $tokenHash, $sourceCheckedAt ?: $recordedAt, true);

            if ($daily) {
                $record->id = (int) $daily->id;
                $record->created_at = (string) ($daily->created_at ?? $recordedAt);
                $db->updateObject(self::getHistoryTableName(), $record, ['id']);
                self::recordAnalyticsHealth($moduleId, $tokenHash, $source, 'success', 'Rekod harian dikemaskini.', $recordedAt);
                self::normalizeDailyHistoryDuplicates($moduleId, $tokenHash);
                self::applyRetentionPolicy($moduleId, $tokenHash);
                $db->transactionCommit();
                self::updateModuleLastSuccessfulCollection($moduleId, self::latestSuccessfulCollectionTimestamp($moduleId, $tokenHash));

                return [
                    'success' => true,
                    'saved' => true,
                    'updated' => true,
                    'duplicate' => false,
                    'message' => 'Rekod sejarah harian dikemaskini.',
                    'health' => self::getAnalyticsHealth($moduleId, $tokenHash),
                    'mini_trend' => self::buildDashboardMiniTrendSeries($moduleId, $tokenHash),
                ];
            }

            $db->insertObject(self::getHistoryTableName(), $record);
            self::recordAnalyticsHealth($moduleId, $tokenHash, $source, 'success', '', $recordedAt);
            self::normalizeDailyHistoryDuplicates($moduleId, $tokenHash);
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
            'updated' => false,
            'duplicate' => false,
            'message' => 'Rekod sejarah disimpan.',
            'health' => self::getAnalyticsHealth($moduleId, $tokenHash),
            'mini_trend' => self::buildDashboardMiniTrendSeries($moduleId, $tokenHash),
        ];
    }


    /**
     * Return compact dashboard mini-trend points from the same analytics chart dataset.
     *
     * @param   int     $moduleId   Joomla module id.
     * @param   string  $tokenHash  SHA-256 token hash.
     *
     * @return  array<int, array<string, mixed>>
     */
    public static function getDashboardMiniTrendSeries(int $moduleId, string $tokenHash): array
    {
        if ($moduleId <= 0 || $tokenHash === '') {
            return [];
        }

        try {
            self::ensureHistoryTable();
            self::normalizeDailyHistoryDuplicates($moduleId, $tokenHash);

            return self::buildDashboardMiniTrendSeries($moduleId, $tokenHash);
        } catch (\Throwable $exception) {
            self::logAnalyticsEvent('warning', 'Dashboard mini trend unavailable.', ['module_id' => $moduleId, 'error' => $exception->getMessage()]);

            return [];
        }
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
        $preset = 'dashboard_tile';
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
        self::normalizeDailyHistoryDuplicates($moduleId, $tokenHash);
        $records = self::getHistoryRecords($moduleId, $tokenHash);
        $chartRecords = self::getHistoryChartRecords($moduleId, $tokenHash);
        $totalRecords = self::getHistoryRecordCount($moduleId, $tokenHash);

        return array_merge($result, [
            'html' => self::renderHistoryModal($records, $appearance, self::getAnalyticsHealth($moduleId, $tokenHash), self::getHistoryRowsPerPage($moduleId), $preset, $chartRecords, $totalRecords, self::getBrandingForModule($moduleId)),
            'chart' => self::buildTrendSeries($chartRecords),
            'mini_trend' => self::buildMiniTrendSeriesFromChartRecords($chartRecords),
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
        $preset = 'dashboard_tile';

        if (!$moduleId || !$token) {
            return [
                'success' => false,
                'message' => 'Konfigurasi sejarah tidak lengkap.',
            ];
        }

        self::ensureHistoryTable();

        $tokenHash = hash('sha256', $token);
        self::normalizeDailyHistoryDuplicates($moduleId, $tokenHash);
        $records = self::getHistoryRecords($moduleId, $tokenHash);
        $chartRecords = self::getHistoryChartRecords($moduleId, $tokenHash);
        $totalRecords = self::getHistoryRecordCount($moduleId, $tokenHash);

        $health = self::getAnalyticsHealth($moduleId, $tokenHash);

        return [
            'success' => true,
            'html' => self::renderHistoryModal($records, $appearance, $health, self::getHistoryRowsPerPage($moduleId), $preset, $chartRecords, $totalRecords, self::getBrandingForModule($moduleId)),
            'chart' => self::buildTrendSeries($chartRecords),
            'mini_trend' => self::buildMiniTrendSeriesFromChartRecords($chartRecords),
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
    public static function renderHistoryModal(array $records, string $appearance = 'light', ?array $health = null, ?int $rowsPerPage = null, string $preset = 'dashboard_tile', ?array $chartRecords = null, ?int $totalRecords = null, ?array $branding = null): string
    {
        $appearance = in_array($appearance, self::getAllowedAppearanceModes(), true) ? $appearance : 'light';
        $preset = 'dashboard_tile';
        $modeClass = 'operations-grid';
        $tableRecords = $records;
        $chartRecords = $chartRecords ?? self::getHistoryChartSlice($records);
        $latest = $tableRecords[0] ?? null;
        $lowest = null;
        foreach ($tableRecords as $record) {
            if ($lowest === null || (float) $record->score < (float) $lowest->score) {
                $lowest = $record;
            }
        }
        $health = $health ?? self::buildHealthFromRecords($records);
        $historyCount = $totalRecords ?? count($tableRecords);
        $pageSize = self::normaliseHistoryRowsPerPage($rowsPerPage);
        $branding = array_merge(self::DEFAULT_BRANDING, $branding ?: []);

        ob_start();
        ?>
        <div class="splask-history-content splask-history-<?php echo htmlspecialchars($appearance, ENT_QUOTES, 'UTF-8'); ?> splask-history-mode-<?php echo htmlspecialchars($modeClass, ENT_QUOTES, 'UTF-8'); ?>" data-splask-history-preset="<?php echo htmlspecialchars($preset, ENT_QUOTES, 'UTF-8'); ?>">
            <section class="splask-history-ops-console" aria-label="<?php echo htmlspecialchars($branding['analytics_title'], ENT_QUOTES, 'UTF-8'); ?>">
                <div class="splask-history-ops-rail" aria-label="<?php echo htmlspecialchars($branding['analytics_title'], ENT_QUOTES, 'UTF-8'); ?>">
                    <div class="splask-history-ops-primary"><span><?php echo htmlspecialchars($branding['kpi_score_today_label'], ENT_QUOTES, 'UTF-8'); ?></span><strong><?php echo $latest ? htmlspecialchars(self::formatScorePercent((float) $latest->score), ENT_QUOTES, 'UTF-8') : '--'; ?></strong><small><?php echo $latest ? htmlspecialchars(self::formatHistoryDateOnly((string) ($latest->source_checked_at ?: $latest->created_at)), ENT_QUOTES, 'UTF-8') : 'Tiada'; ?></small></div>
                    <div><span><?php echo htmlspecialchars($branding['kpi_lowest_score_label'], ENT_QUOTES, 'UTF-8'); ?></span><strong><?php echo $lowest ? htmlspecialchars(self::formatScorePercent((float) $lowest->score), ENT_QUOTES, 'UTF-8') : '--'; ?></strong><?php if ($lowest) : ?><small><?php echo htmlspecialchars(self::formatHistoryDateOnly((string) ($lowest->source_checked_at ?: $lowest->created_at)), ENT_QUOTES, 'UTF-8'); ?></small><?php endif; ?></div>
                    <div><span><?php echo htmlspecialchars($branding['kpi_total_records_label'], ENT_QUOTES, 'UTF-8'); ?></span><strong><?php echo $historyCount; ?></strong><small><?php echo htmlspecialchars($branding['kpi_total_records_caption'], ENT_QUOTES, 'UTF-8'); ?></small></div>
                </div>
                <div class="splask-history-ops-main">
                    <div class="splask-history-chart splask-history-ops-chart" data-splask-history-chart aria-label="<?php echo htmlspecialchars($branding['graph_history_chart_aria_label'], ENT_QUOTES, 'UTF-8'); ?>">
                        <?php echo self::renderTrendChart($chartRecords); ?>
                    </div>
                </div>
            </section>

            <div class="splask-history-table-shell" data-splask-history-pagination data-splask-history-page-size="<?php echo $pageSize; ?>" data-splask-labels="<?php echo self::getBrandingJson($branding); ?>">
                <script type="application/json" data-splask-history-records><?php echo htmlspecialchars(json_encode(self::buildHistoryTableRecords($tableRecords), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]', ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></script>
                <div class="splask-history-page-size-control">
                    <label>
                        <span><?php echo htmlspecialchars($branding['kpi_rows_per_page_label'], ENT_QUOTES, 'UTF-8'); ?></span>
                        <input type="number" min="1" max="50" value="<?php echo $pageSize; ?>" data-splask-history-page-size-input aria-label="<?php echo htmlspecialchars($branding['kpi_rows_per_page_label'], ENT_QUOTES, 'UTF-8'); ?>" />
                    </label>
                </div>
                <div class="table-responsive splask-history-table-wrap" tabindex="0" aria-label="<?php echo htmlspecialchars($branding['history_table_aria_label'], ENT_QUOTES, 'UTF-8'); ?>">
                    <table class="table table-sm align-middle splask-history-table">
                    <thead>
                        <tr>
                            <th scope="col"><?php echo htmlspecialchars($branding['history_table_date_label'], ENT_QUOTES, 'UTF-8'); ?></th>
                            <th scope="col"><?php echo htmlspecialchars($branding['history_table_time_label'], ENT_QUOTES, 'UTF-8'); ?></th>
                            <th scope="col"><?php echo htmlspecialchars($branding['history_table_score_label'], ENT_QUOTES, 'UTF-8'); ?></th>
                            <th scope="col"><?php echo htmlspecialchars($branding['history_table_grade_label'], ENT_QUOTES, 'UTF-8'); ?></th>
                            <th scope="col"><?php echo htmlspecialchars($branding['history_table_status_label'], ENT_QUOTES, 'UTF-8'); ?></th>
                        </tr>
                    </thead>
                    <tbody data-splask-history-page-body>
                    </tbody>
                    </table>
                </div>
                <div class="splask-history-pagination" aria-label="<?php echo htmlspecialchars($branding['history_pagination_aria_label'], ENT_QUOTES, 'UTF-8'); ?>">
                    <span data-splask-history-page-status><?php echo htmlspecialchars(str_replace(['{start}', '{end}', '{total}'], ['0', '0', (string) $historyCount], $branding['history_page_status_template']), ENT_QUOTES, 'UTF-8'); ?></span>
                    <div class="splask-history-pagination-actions" data-splask-history-page-actions>
                        <button type="button" class="splask-history-page-button" data-splask-history-page-prev aria-label="<?php echo htmlspecialchars($branding['history_previous_label'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($branding['history_previous_label'], ENT_QUOTES, 'UTF-8'); ?></button>
                        <span class="splask-history-page-numbers" data-splask-history-page-numbers></span>
                        <button type="button" class="splask-history-page-button" data-splask-history-page-next aria-label="<?php echo htmlspecialchars($branding['history_next_label'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($branding['history_next_label'], ENT_QUOTES, 'UTF-8'); ?></button>
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
                'time' => self::formatHistoryTimeOnly((string) ($record->source_checked_at ?: $record->created_at)),
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
    private static function getHistoryRecords(int $moduleId, string $tokenHash, ?int $limit = null): array
    {
        $db = \Joomla\CMS\Factory::getDbo();
        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName(self::getHistoryTableName()))
            ->where($db->quoteName('module_id') . ' = ' . (int) $moduleId)
            ->where($db->quoteName('token_hash') . ' = ' . $db->quote($tokenHash))
            ->order($db->quoteName('recorded_at') . ' DESC, ' . $db->quoteName('created_at') . ' DESC, ' . $db->quoteName('id') . ' DESC');

        $limit === null ? $db->setQuery($query) : $db->setQuery($query, 0, $limit);

        return $db->loadObjectList() ?: [];
    }

    /**
     * Return graph records scoped to the latest 30-day operational window only.
     *
     * @param   int     $moduleId   Joomla module id.
     * @param   string  $tokenHash  SHA-256 token hash.
     *
     * @return  array<int, object>
     */
    private static function getHistoryChartRecords(int $moduleId, string $tokenHash): array
    {
        $db = \Joomla\CMS\Factory::getDbo();
        $cutoff = (new \DateTimeImmutable('today', new \DateTimeZone('UTC')))
            ->modify('-' . (self::HISTORY_CHART_DAY_WINDOW - 1) . ' days')
            ->format('Y-m-d 00:00:00');
        $recordDate = 'COALESCE(' . $db->quoteName('source_checked_at') . ', ' . $db->quoteName('recorded_at') . ', ' . $db->quoteName('created_at') . ')';
        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName(self::getHistoryTableName()))
            ->where($db->quoteName('module_id') . ' = ' . (int) $moduleId)
            ->where($db->quoteName('token_hash') . ' = ' . $db->quote($tokenHash))
            ->where($recordDate . ' >= ' . $db->quote($cutoff))
            ->order($db->quoteName('recorded_at') . ' DESC, ' . $db->quoteName('created_at') . ' DESC, ' . $db->quoteName('id') . ' DESC');
        $db->setQuery($query);

        return $db->loadObjectList() ?: [];
    }

    /**
     * Return the true persisted analytics row count for Jumlah Rekod.
     *
     * @param   int     $moduleId   Joomla module id.
     * @param   string  $tokenHash  SHA-256 token hash.
     *
     * @return  int
     */
    private static function getHistoryRecordCount(int $moduleId, string $tokenHash): int
    {
        $db = \Joomla\CMS\Factory::getDbo();
        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName(self::getHistoryTableName()))
            ->where($db->quoteName('module_id') . ' = ' . (int) $moduleId)
            ->where($db->quoteName('token_hash') . ' = ' . $db->quote($tokenHash));
        $db->setQuery($query);

        return (int) $db->loadResult();
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
            ->order($db->quoteName('recorded_at') . ' DESC, ' . $db->quoteName('created_at') . ' DESC, ' . $db->quoteName('id') . ' DESC');

        $db->setQuery((string) $query . ' LIMIT 1 FOR UPDATE');
        $record = $db->loadObject();

        return $record ?: null;
    }

    /**
     * Return the existing normalized daily record for a module/token/date pair.
     *
     * @param   int     $moduleId    Joomla module id.
     * @param   string  $tokenHash   SHA-256 token hash.
     * @param   string  $recordedAt  Candidate snapshot timestamp.
     * @param   bool    $forUpdate   Lock the row during write transactions.
     *
     * @return  object|null
     */
    private static function getDailyHistoryRecord(int $moduleId, string $tokenHash, string $recordedAt, bool $forUpdate = false): ?object
    {
        $day = self::getHistoryDayKey($recordedAt);
        if ($day === '') {
            return null;
        }

        $db = \Joomla\CMS\Factory::getDbo();
        $recordDate = 'DATE(COALESCE(' . $db->quoteName('source_checked_at') . ', ' . $db->quoteName('recorded_at') . ', ' . $db->quoteName('created_at') . '))';
        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName(self::getHistoryTableName()))
            ->where($db->quoteName('module_id') . ' = ' . (int) $moduleId)
            ->where($db->quoteName('token_hash') . ' = ' . $db->quote($tokenHash))
            ->where($recordDate . ' = ' . $db->quote($day))
            ->order($db->quoteName('recorded_at') . ' DESC, ' . $db->quoteName('created_at') . ' DESC, ' . $db->quoteName('id') . ' DESC');

        if ($forUpdate) {
            $db->setQuery((string) $query . ' LIMIT 1 FOR UPDATE');
        } else {
            $db->setQuery($query, 0, 1);
        }
        $record = $db->loadObject();

        return $record ?: null;
    }

    /**
     * Collapse legacy duplicate daily snapshots so one calendar day keeps one best/latest row.
     *
     * @param   int|null     $moduleId   Optional module id scope.
     * @param   string|null  $tokenHash  Optional token hash scope.
     *
     * @return  void
     */
    private static function normalizeDailyHistoryDuplicates(?int $moduleId = null, ?string $tokenHash = null): void
    {
        $db = \Joomla\CMS\Factory::getDbo();
        $recordDate = 'DATE(COALESCE(' . $db->quoteName('source_checked_at') . ', ' . $db->quoteName('recorded_at') . ', ' . $db->quoteName('created_at') . '))';
        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('id'),
                $db->quoteName('module_id'),
                $db->quoteName('token_hash'),
                $recordDate . ' AS ' . $db->quoteName('history_day'),
            ])
            ->from($db->quoteName(self::getHistoryTableName()))
            ->order($db->quoteName('module_id') . ' ASC, ' . $db->quoteName('token_hash') . ' ASC, history_day DESC, ' . $db->quoteName('recorded_at') . ' DESC, ' . $db->quoteName('created_at') . ' DESC, ' . $db->quoteName('id') . ' DESC');

        if ($moduleId !== null) {
            $query->where($db->quoteName('module_id') . ' = ' . (int) $moduleId);
        }

        if ($tokenHash !== null && $tokenHash !== '') {
            $query->where($db->quoteName('token_hash') . ' = ' . $db->quote($tokenHash));
        }

        $db->setQuery($query);
        $rows = $db->loadObjectList() ?: [];
        $seen = [];
        $deleteIds = [];

        foreach ($rows as $row) {
            $day = (string) ($row->history_day ?? '');
            if ($day === '') {
                continue;
            }

            $key = (int) $row->module_id . '|' . (string) $row->token_hash . '|' . $day;
            if (isset($seen[$key])) {
                $deleteIds[] = (int) $row->id;
                continue;
            }

            $seen[$key] = true;
        }

        if (!$deleteIds) {
            return;
        }

        foreach (array_chunk(array_unique($deleteIds), 500) as $ids) {
            $delete = $db->getQuery(true)
                ->delete($db->quoteName(self::getHistoryTableName()))
                ->where($db->quoteName('id') . ' IN (' . implode(',', array_map('intval', $ids)) . ')');
            $db->setQuery($delete)->execute();
        }
    }

    private static function getHistoryDayKey(string $value): string
    {
        if ($value === '') {
            return '';
        }

        try {
            return (new \DateTimeImmutable($value, new \DateTimeZone('UTC')))
                ->setTimezone(new \DateTimeZone('UTC'))
                ->format('Y-m-d');
        } catch (\Exception $exception) {
            return '';
        }
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

    /* Validation note: Automated analytics collection depends on Joomla Scheduled Tasks being active in the hosting environment. Runtime collection still processes every published enabled module instance; install/upgrade bootstrap synchronizes the first/latest published instance. */

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
                'retention_enabled' => !empty($params['analytics_retention_enabled']),
                'retention_days' => max(1, (int) ($params['analytics_retention_days'] ?? self::DEFAULT_RETENTION_DAYS)),
                'max_history_records' => max(1, (int) ($params['analytics_max_rows'] ?? self::DEFAULT_MAX_HISTORY_ROWS)),
            ];

            $values = [
                'title' => 'SPLaSK Score Analytics Collection',
                'type' => self::SCHEDULER_TASK_TYPE,
                'state' => $enabled ? 1 : 0,
                'execution_rules' => $rules['execution_rules'],
                'params' => $taskParams,
                'note' => 'Diurus secara automatik daripada tetapan modul SPLaSK Score. Masa kutipan harian menggunakan zon masa Joomla yang dikonfigurasi. Kutipan analitik automatik bergantung pada Joomla Scheduled Tasks yang aktif dalam persekitaran hosting. Suntingan manual penjadual dikekalkan hanya sehingga modul disimpan semula.',
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
        $retentionEnabled = (bool) ($params['analytics_retention_enabled'] ?? self::DEFAULT_RETENTION_ENABLED);

        if (!$retentionEnabled) {
            return;
        }

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
        self::normalizeDailyHistoryDuplicates();
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
            $recorded = (string) (($latest->source_checked_at ?? '') ?: ($latest->recorded_at ?? $latest->created_at));
            $missingToday = $recorded < $today;
        }

        $fallbackSuccess = $latest ? (string) (($latest->source_checked_at ?? '') ?: ($latest->recorded_at ?? $latest->created_at)) : '';
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
        $recorded = $latest ? (string) (($latest->source_checked_at ?? '') ?: ($latest->recorded_at ?? $latest->created_at)) : '';

        return [
            'last_success' => $recorded,
            'last_failed' => '',
            'status' => $latest ? 'SUCCESS' : 'UNKNOWN',
            'missing_today' => $recorded === '' || $recorded < $today,
            'source' => $latest ? (string) ($latest->source ?? 'dashboard') : '',
        ];
    }

    private static function getHistoryChartSlice(array $records): array
    {
        $cutoff = (new \DateTimeImmutable('today', new \DateTimeZone('UTC')))
            ->modify('-' . (self::HISTORY_CHART_DAY_WINDOW - 1) . ' days');

        return array_values(array_filter($records, static function ($record) use ($cutoff) {
            $recordDate = self::getHistoryRecordDate($record);

            return $recordDate !== null && $recordDate >= $cutoff;
        }));
    }


    private static function buildDashboardMiniTrendSeries(int $moduleId, string $tokenHash): array
    {
        return self::buildMiniTrendSeriesFromChartRecords(self::getHistoryChartRecords($moduleId, $tokenHash));
    }

    private static function buildMiniTrendSeriesFromChartRecords(array $records): array
    {
        return array_slice(self::buildTrendSeries(self::getMiniTrendSlice($records)), -self::MINI_TREND_DAY_WINDOW);
    }

    private static function getMiniTrendSlice(array $records): array
    {
        $cutoff = (new \DateTimeImmutable('today', new \DateTimeZone('UTC')))
            ->modify('-' . (self::MINI_TREND_DAY_WINDOW - 1) . ' days');

        return array_values(array_filter($records, static function ($record) use ($cutoff) {
            $recordDate = self::getHistoryRecordDate($record);

            return $recordDate !== null && $recordDate >= $cutoff;
        }));
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
        $value = (string) (($record->source_checked_at ?? '') ?: ($record->recorded_at ?? '') ?: ($record->created_at ?? '') ?: '');
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
     * Format a SQL datetime as a 12-hour operational time without date.
     *
     * @param   string  $value  SQL datetime.
     *
     * @return  string
     */
    private static function formatHistoryTimeOnly(string $value): string
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
        $hour = (int) $date->format('G');
        $displayHour = $hour % 12 ?: 12;

        return $displayHour . ':' . $date->format('i') . ' ' . $date->format('A');
    }

    /**
     * Format a SQL datetime for display using Malay month names without time.
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
        $month = self::MALAY_MONTHS[(int) $date->format('n')];

        return $date->format('j') . ' ' . $month . ' ' . $date->format('Y');
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
