<?php
/**
 * @author    sh1zen
 * @copyright Copyright (C)  2022
 * @license   http://www.gnu.org/licenses/gpl.html GNU/GPL
 */

namespace FlexySEO\modules;

use SHZN\modules\Module;
use SHZN\core\UtilEnv;

class Mod_Settings extends Module
{
    public $scopes = array('core-settings', 'admin');

    public function __construct()
    {
        parent::__construct('wpfs');
    }

    public function restricted_access($context = '')
    {
        switch ($context) {

            case 'ajax':
            case 'settings':
                return !current_user_can('manage_options');

            default:
                return false;
        }
    }

    protected function custom_actions()
    {
        return array(
            array(
                'id'           => 'reset_options',
                'value'        => __('Reset Plugin options', 'wpfs'),
                'button_types' => 'button-danger',
                'after'        => '<hr>'
            ),
            array(
                'id'           => 'export_options',
                'value'        => __('Export Plugin options', 'wpfs'),
                'button_types' => 'button-primary',
                'after'        => '<hr>'
            ),
            array(
                'before'       => array(
                    'id'      => 'conf_data',
                    'type'    => 'textarea',
                    'context' => 'block'
                ),
                'id'           => 'import_options',
                'button_types' => 'button-primary',
                'value'        => __('Import Plugin options', 'wpfs'),
            )
        );
    }

    protected function process_custom_actions($action, $options)
    {
        switch ($action) {
            case 'reset_options':
                return shzn('wpfs')->settings->reset();

            case 'export_options':
                if (file_put_contents(WP_CONTENT_DIR . '/wpfs-export.conf', shzn('wpfs')->settings->export())) {
                    UtilEnv::download_file(WP_CONTENT_DIR . '/wpfs-export.conf', true);
                    return true;
                }
                break;

            case 'import_options':
                return shzn('wpfs')->settings->import($options['conf_data']);
        }

        return false;
    }
}

return __NAMESPACE__;