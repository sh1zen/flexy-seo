<?php
/**
 * @author    sh1zen
 * @copyright Copyright (C)  2022
 * @license   http://www.gnu.org/licenses/gpl.html GNU/GPL
 */

/**
 * Plugin Name: Flexy SEO
 * Plugin URI: https://github.com/sh1zen/flexy-seo
 * Description: Search Engine Optimization (SEO) plugin, support flex breadcrumbs, schema.org and more.
 * Author: sh1zen
 * Author URI: https://sh1zen.github.io/
 * Text Domain: wpfs
 * Domain Path: /languages
 * Version: 1.5.2
 */

const WPFS_VERSION = '1.5.2';

const WPFS_FILE = __FILE__;
define('WPFS_ABSPATH', dirname(__FILE__) . '/');
const WPFS_MODULES = WPFS_ABSPATH . 'modules/';
const WPFS_ADMIN = WPFS_ABSPATH . 'admin/';
const WPFS_ASSETS = WPFS_ABSPATH . 'assets/';
const WPFS_VENDORS = WPFS_ABSPATH . 'vendors/';

// shzn-framework commons
if (!defined('SHZN_FRAMEWORK')) {
    if (!file_exists(WPFS_VENDORS . 'shzn-framework/loader.php')) {
        return;
    }
    require_once WPFS_VENDORS . 'shzn-framework/loader.php';
}

shzn('wpfs', ['path' => WPFS_MODULES, 'table_name' => "flexy_seo"], [
    'meter'         => false,
    'cron'          => false,
    'cache'         => true,
    'storage'       => true,
    'settings'      => true,
    'moduleHandler' => true,
    'options'       => true
]);

const WPFS_DEBUG = SHZN_DEBUG;

// main class
require_once WPFS_ADMIN . 'PluginInit.class.php';

/**
 * Starts the plugin.
 */
FlexySEO\core\PluginInit::Initialize();

require_once WPFS_ABSPATH . 'ext_interface/wpfs_functions.php';

