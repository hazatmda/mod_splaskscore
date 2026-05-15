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

// Keep the configured token server-side; AJAX refreshes resolve it from module params.
$token = $params->get('splask_token', '');

$layout = ModSplaskscoreHelper::getDesignPreset($params);

require ModuleHelper::getLayoutPath('mod_splaskscore', $layout);
