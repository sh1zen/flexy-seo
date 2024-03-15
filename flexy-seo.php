<?php
/**
 * @author    sh1zen
 * @copyright Copyright (C) 2024.
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
 * Version: 1.9.4
 */

const WPFS_VERSION = '1.9.4';

const WPFS_FILE = __FILE__;
const WPFS_ABSPATH = __DIR__ . '/';
const WPFS_MODULES = WPFS_ABSPATH . 'modules/';
const WPFS_ADMIN = WPFS_ABSPATH . 'admin/';


// wps-framework commons
if (!defined('WPS_FRAMEWORK')) {

    if (defined('WPS_FRAMEWORK_SOURCE') and file_exists(WPS_FRAMEWORK_SOURCE . 'loader.php')) {
        require_once WPS_FRAMEWORK_SOURCE . 'loader.php';
    }
    else {
        if (!file_exists(WPFS_ABSPATH . 'vendors/wps-framework/loader.php')) {
            return;
        }
        require_once WPFS_ABSPATH . 'vendors/wps-framework/loader.php';
    }
}

wps(
    'wpfs',
    [
        'modules_path' => WPFS_MODULES,
        'table_name'   => "wp_flexy_seo",
    ],
    [
        'meter'         => false,
        'cron'          => false,
        'cache'         => true,
        'storage'       => true,
        'settings'      => true,
        'moduleHandler' => true,
        'options'       => true
    ]
);

define("WPFS_DEBUG", $_SERVER["SERVER_ADDR"] === '127.0.0.1');

// main class
require_once WPFS_ADMIN . 'PluginInit.class.php';

/**
 * Starts the plugin.
 */
FlexySEO\core\PluginInit::Initialize();

require_once WPFS_ABSPATH . 'ext_interface/wpfs_functions.php';
require_once WPFS_ABSPATH . 'ext_interface/wp-hooks.php';