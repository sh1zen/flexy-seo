<?php
/**
 * Plugin Name: Flexy SEO
 * Plugin URI: https://github.com/sh1zen/flexy-seo
 * Description: Search Engine Optimization (SEO) plugin, support flex breadcrumbs, schema.org and more.
 * Author: sh1zen
 * Author URI: https://sh1zen.github.io/
 * Text Domain: wpfs
 * Domain Path: /languages
 * Version: 1.2.0
 */

const WPFS_FILE = __FILE__;
define('WPFS_ABSPATH', dirname(__FILE__) . '/');
const WPFS_INCPATH = WPFS_ABSPATH . 'inc/';
const WPFS_MODULES = WPFS_ABSPATH . 'modules/';
const WPFS_ADMIN = WPFS_ABSPATH . 'admin/';
const WPFS_ASSETS = WPFS_ABSPATH . 'assets/';
const WPFS_EXTENSIONS = WPFS_ABSPATH . 'extensions/';
const WPFS_VENDORS = WPFS_ABSPATH . 'vendors/';

// setup constants
require_once WPFS_INCPATH . 'constants.php';

// essential
require_once WPFS_ADMIN . 'Options.class.php';

// main class
require_once WPFS_ADMIN . 'PluginInit.class.php';

// shzn-framework commons
if (!defined('SHZN_FRAMEWORK')) {
    require_once WPFS_VENDORS . 'shzn-framework/loader.php';
}

shzn('wpfs', ['path' => WPFS_MODULES], [
    'meter'         => false,
    'cron'          => false,
    'cache'         => true,
    'storage'       => true,
    'settings'      => true,
    'moduleHandler' => true,
]);

/**
 * Starts the plugin.
 */
FlexySEO\core\PluginInit::Initialize();


require_once WPFS_ABSPATH . 'ext_interface/wpfs_functions.php';

