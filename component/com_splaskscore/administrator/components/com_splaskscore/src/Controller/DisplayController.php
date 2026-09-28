<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_splaskscore
 */

namespace Hazim\Component\Splaskscore\Administrator\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;

final class DisplayController extends BaseController
{
    protected $default_view = 'analytics';

    public function display($cachable = false, $urlparams = []): BaseController
    {
        $user = $this->app->getIdentity();

        if (!$user->authorise('core.admin') && !$user->authorise('core.manage', 'com_splaskscore')) {
            throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        $view = $this->input->getCmd('view', $this->default_view);

        if (!in_array($view, ['analytics', 'collectionlogs', 'settings', 'about'], true)) {
            $view = $this->default_view;
        }

        $this->input->set('view', $view);

        return parent::display($cachable, $urlparams);
    }
}
