<?php
/**
 * @author    sh1zen
 * @copyright Copyright (C)  2021
 * @license   http://www.gnu.org/licenses/gpl.html GNU/GPL
 */

use SHZN\core\shzn_wrapper;

define('SHZN_FRAMEWORK', dirname(__FILE__) . '/');

require_once SHZN_FRAMEWORK . 'back-compat.php';
require_once SHZN_FRAMEWORK . 'functions.php';

require_once SHZN_FRAMEWORK . 'shzn_wrapper.php';

require_once SHZN_FRAMEWORK . 'Utility.class.php';

require_once SHZN_FRAMEWORK . 'UtilEnv.php';

require_once SHZN_FRAMEWORK . 'Cache.class.php';
require_once SHZN_FRAMEWORK . 'Storage.class.php';
require_once SHZN_FRAMEWORK . 'Disk.class.php';
require_once SHZN_FRAMEWORK . 'Settings.class.php';

require_once SHZN_FRAMEWORK . 'Ajax.class.php';
require_once SHZN_FRAMEWORK . 'Cron.class.php';

require_once SHZN_FRAMEWORK . 'Graphic.class.php';

require_once SHZN_FRAMEWORK . 'RuleUtil.php';
require_once SHZN_FRAMEWORK . 'PerformanceMeter.class.php';
require_once SHZN_FRAMEWORK . 'Module.class.php';
require_once SHZN_FRAMEWORK . 'ModuleHandler.class.php';

add_action('admin_enqueue_scripts', 'shzn_admin_enqueue_scripts', 10, 0);

function shzn_admin_enqueue_scripts()
{
    $shzn_assets_url = plugin_dir_url(__FILE__);

    $min = shzn()->utility->online ? '.min' : '';

    wp_register_style('vendor-shzn-css', "{$shzn_assets_url}assets/css/style{$min}.css");
    wp_register_script('vendor-shzn-js', "{$shzn_assets_url}assets/js/core{$min}.js", ['jquery']);
}

function shzn($context = 'common', $args = false, $components = [])
{
    static $cached = [];

    if (!is_string($context)) {
        $fn = shzn_get_calling_function(2);
        trigger_error("SHZN Framework >> not valid context type in {$fn}.", E_USER_ERROR);
    }

    if ($args or !empty($components)) {

        if (!isset($cached[$context]) or !is_object($cached[$context])) {
            $cached[$context] = new shzn_wrapper($context, $args, $components);

            $cached[$context]->setup();
        }
        else {
            $cached[$context]->update_components($components, $args);
        }
    }
    elseif (!isset($cached[$context])) {
        $fn = shzn_get_calling_function(2);
        trigger_error("SHZN Framework >> context '{$context}' not defined yet in {$fn}.", E_USER_WARNING);
        return false;
    }

    return $cached[$context];
}

shzn('common', false, [
    'utility' => true
]);