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
 *
 * Phase 1 intentionally keeps the existing browser-side API behavior and token
 * handling in place. This helper only establishes the layout whitelist needed
 * for the preset architecture foundation.
 */
final class ModSplaskscoreHelper
{
    /**
     * Return the layout names currently supported by the module.
     *
     * Additional presets should be added in later phases after their templates
     * and assets exist.
     *
     * @return  string[]
     */
    public static function getAllowedDesignPresets(): array
    {
        return [
            'modern_circle',
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
}
