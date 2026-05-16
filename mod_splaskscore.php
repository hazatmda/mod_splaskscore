<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  mod_splaskscore
 *
 * @copyright   Copyright (C) 2025 Muhammad Azizan Hazim
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Helper\ModuleHelper;

require_once __DIR__ . '/helper.php';

// Get token parameter securely and escape it for safe JS embedding.
// Phase 1 keeps the existing client-side API behavior for backward compatibility.
$token = $params->get('splask_token', '');
$token_escaped = htmlspecialchars($token, ENT_QUOTES, 'UTF-8');

$layout = ModSplaskscoreHelper::getDesignPreset($params);

require ModuleHelper::getLayoutPath('mod_splaskscore', $layout);
