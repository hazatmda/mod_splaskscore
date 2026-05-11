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
        $db = \Joomla\CMS\Factory::getDbo();
        $input = $app->input;

        if (!\Joomla\CMS\Session\Session::checkToken('request')) {
            return [
                'success' => false,
                'message' => 'Token keselamatan tidak sah.',
            ];
        }

        $moduleId = max(0, (int) ($payload['module_id'] ?? $input->getInt('module_id', 0)));
        $tokenHash = hash('sha256', (string) ($payload['token'] ?? ''));
        $score = max(0, min(100, (float) ($payload['score'] ?? 0)));
        $gradeKey = self::cleanHistoryText((string) ($payload['grade_key'] ?? ''), 32);
        $gradeLabel = self::cleanHistoryText((string) ($payload['grade_label'] ?? ''), 64);
        $statusLabel = self::cleanHistoryText((string) ($payload['status_label'] ?? ''), 64);
        $verificationUrl = filter_var((string) ($payload['verification_url'] ?? ''), FILTER_VALIDATE_URL) ? (string) $payload['verification_url'] : '';
        $sourceCheckedAt = self::normaliseHistoryDate((string) ($payload['source_checked_at'] ?? ''));
        $createdAt = \Joomla\CMS\Factory::getDate()->toSql();

        if (!$moduleId || !$gradeKey || !$gradeLabel || !$statusLabel || empty($payload['token'])) {
            return [
                'success' => false,
                'message' => 'Data sejarah tidak lengkap.',
            ];
        }

        self::ensureHistoryTable();

        $latest = self::getLatestHistoryRecord($moduleId, $tokenHash);

        if ($latest && self::isDuplicateHistoryRecord($latest, $score, $gradeKey, $statusLabel, $sourceCheckedAt)) {
            return [
                'success' => true,
                'saved' => false,
                'message' => 'Rekod sejarah terkini sudah wujud.',
            ];
        }

        $record = (object) [
            'module_id' => $moduleId,
            'token_hash' => $tokenHash,
            'score' => $score,
            'grade_key' => $gradeKey,
            'grade_label' => $gradeLabel,
            'status_label' => $statusLabel,
            'verification_url' => $verificationUrl,
            'source_checked_at' => $sourceCheckedAt,
            'created_at' => $createdAt,
        ];

        $db->insertObject(self::getHistoryTableName(), $record);

        return [
            'success' => true,
            'saved' => true,
            'message' => 'Rekod sejarah disimpan.',
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

        return [
            'success' => true,
            'html' => self::renderHistoryModal($records, $appearance),
            'chart' => self::buildTrendSeries($records),
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
    public static function renderHistoryModal(array $records, string $appearance = 'light'): string
    {
        $appearance = in_array($appearance, self::getAllowedAppearanceModes(), true) ? $appearance : 'light';
        $latest = $records[0] ?? null;
        $previous = $records[1] ?? null;
        $trend = ($latest && $previous) ? ((float) $latest->score - (float) $previous->score) : 0;

        ob_start();
        ?>
        <div class="splask-history-content splask-history-<?php echo htmlspecialchars($appearance, ENT_QUOTES, 'UTF-8'); ?>">
            <div class="splask-history-summary" aria-label="Ringkasan sejarah SPLaSK">
                <div>
                    <span>Rekod Terkini</span>
                    <strong><?php echo $latest ? htmlspecialchars(number_format((float) $latest->score, 0) . '%', ENT_QUOTES, 'UTF-8') : '--'; ?></strong>
                </div>
                <div>
                    <span>Trend</span>
                    <strong class="<?php echo $trend >= 0 ? 'splask-history-positive' : 'splask-history-negative'; ?>">
                        <?php echo $previous ? htmlspecialchars(($trend >= 0 ? '+' : '') . number_format($trend, 0) . '%', ENT_QUOTES, 'UTF-8') : '--'; ?>
                    </strong>
                </div>
                <div>
                    <span>Jumlah Rekod</span>
                    <strong><?php echo count($records); ?></strong>
                </div>
            </div>

            <div class="splask-history-chart" data-splask-history-chart aria-label="Carta trend markah SPLaSK" role="img">
                <?php echo self::renderTrendChart($records); ?>
            </div>

            <div class="table-responsive splask-history-table-wrap">
                <table class="table table-sm align-middle splask-history-table">
                    <thead>
                        <tr>
                            <th scope="col">Tarikh</th>
                            <th scope="col">Markah</th>
                            <th scope="col">Gred</th>
                            <th scope="col">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$records) : ?>
                            <tr><td colspan="4" class="text-center py-4">Belum ada rekod sejarah. Rekod akan disimpan selepas markah berjaya dimuatkan.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($records as $record) : ?>
                            <tr data-splask-history-grade="<?php echo htmlspecialchars((string) $record->grade_key, ENT_QUOTES, 'UTF-8'); ?>">
                                <td><?php echo htmlspecialchars(self::formatHistoryDate((string) ($record->source_checked_at ?: $record->created_at)), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><strong><?php echo htmlspecialchars(number_format((float) $record->score, 0) . '%', ENT_QUOTES, 'UTF-8'); ?></strong></td>
                                <td><span class="splask-history-grade"><?php echo htmlspecialchars((string) $record->grade_label, ENT_QUOTES, 'UTF-8'); ?></span></td>
                                <td><?php echo htmlspecialchars((string) $record->status_label, ENT_QUOTES, 'UTF-8'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php

        return trim((string) ob_get_clean());
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
            return;
        }

        $queries = explode(';', (string) file_get_contents(__DIR__ . '/sql/install.mysql.utf8.sql'));

        foreach ($queries as $query) {
            $query = trim($query);
            if ($query !== '') {
                $db->setQuery($query)->execute();
            }
        }
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
            ->order($db->quoteName('created_at') . ' DESC');

        $db->setQuery($query, 0, $limit);

        return $db->loadObjectList() ?: [];
    }

    /**
     * Return the latest history record for duplicate detection.
     *
     * @param   int     $moduleId   Joomla module id.
     * @param   string  $tokenHash  SHA-256 token hash.
     *
     * @return  object|null
     */
    private static function getLatestHistoryRecord(int $moduleId, string $tokenHash): ?object
    {
        $records = self::getHistoryRecords($moduleId, $tokenHash, 1);

        return $records[0] ?? null;
    }

    /**
     * Decide whether a new API result is already represented by the latest row.
     *
     * @param   object       $latest           Latest row.
     * @param   float        $score            New score.
     * @param   string       $gradeKey         New grade key.
     * @param   string       $statusLabel      New status label.
     * @param   string|null  $sourceCheckedAt  Source timestamp.
     *
     * @return  bool
     */
    private static function isDuplicateHistoryRecord(object $latest, float $score, string $gradeKey, string $statusLabel, ?string $sourceCheckedAt): bool
    {
        $sameCore = abs((float) $latest->score - $score) < 0.01
            && (string) $latest->grade_key === $gradeKey
            && (string) $latest->status_label === $statusLabel;

        if (!$sameCore) {
            return false;
        }

        if ($sourceCheckedAt && (string) $latest->source_checked_at === $sourceCheckedAt) {
            return true;
        }

        return !$sourceCheckedAt;
    }

    /**
     * Return chart-ready records in chronological order.
     *
     * @param   array<int, object>  $records  History rows newest first.
     *
     * @return  array<int, array<string, mixed>>
     */
    private static function buildTrendSeries(array $records): array
    {
        $series = [];

        foreach (array_reverse($records) as $record) {
            $series[] = [
                'label' => self::formatHistoryDate((string) ($record->source_checked_at ?: $record->created_at)),
                'score' => (float) $record->score,
                'grade' => (string) $record->grade_label,
            ];
        }

        return $series;
    }

    /**
     * Render a lightweight inline SVG trend chart without external dependencies.
     *
     * @param   array<int, object>  $records  History rows newest first.
     *
     * @return  string
     */
    private static function renderTrendChart(array $records): string
    {
        $series = self::buildTrendSeries($records);

        if (count($series) < 2) {
            return '<div class="splask-history-empty-chart">Trend akan dipaparkan selepas dua rekod berjaya disimpan.</div>';
        }

        $width = 640;
        $height = 180;
        $padding = 24;
        $usableWidth = $width - ($padding * 2);
        $usableHeight = $height - ($padding * 2);
        $points = [];
        $count = count($series);

        foreach ($series as $index => $point) {
            $x = $padding + (($count === 1 ? 0 : $index / ($count - 1)) * $usableWidth);
            $y = $padding + ((100 - (float) $point['score']) / 100 * $usableHeight);
            $points[] = number_format($x, 2, '.', '') . ',' . number_format($y, 2, '.', '');
        }

        return '<svg class="splask-history-svg" viewBox="0 0 ' . $width . ' ' . $height . '" preserveAspectRatio="none" aria-hidden="true" focusable="false">'
            . '<line x1="24" y1="24" x2="24" y2="156" class="splask-history-axis" />'
            . '<line x1="24" y1="156" x2="616" y2="156" class="splask-history-axis" />'
            . '<polyline class="splask-history-line" points="' . htmlspecialchars(implode(' ', $points), ENT_QUOTES, 'UTF-8') . '" />'
            . '</svg>';
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

        $formats = ['d/m/Y H:i:s', 'd/m/Y H:i', 'Y-m-d H:i:s', DATE_ATOM];

        foreach ($formats as $format) {
            $date = \DateTime::createFromFormat($format, $value);
            if ($date instanceof \DateTime) {
                return $date->format('Y-m-d H:i:s');
            }
        }

        $timestamp = strtotime($value);

        return $timestamp ? gmdate('Y-m-d H:i:s', $timestamp) : null;
    }

    /**
     * Format a SQL datetime for display.
     *
     * @param   string  $value  SQL datetime.
     *
     * @return  string
     */
    private static function formatHistoryDate(string $value): string
    {
        if ($value === '') {
            return '--';
        }

        $timestamp = strtotime($value);

        return $timestamp ? date('d/m/Y h:i A', $timestamp) : $value;
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
