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
