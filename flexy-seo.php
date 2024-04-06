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
 * Version: 1.9.8
 */

const WPFS_VERSION = '1.9.8';
const WPFS_FILE = __FILE__;

require_once __DIR__ . '/inc/wps_and_constants.php';
require_once WPFS_ADMIN . 'PluginInit.class.php';
require_once WPFS_INC_PATH . 'fine-tune.php';

/**
 * Starts the plugin.
 */
FlexySEO\core\PluginInit::Initialize();

require_once WPFS_ABSPATH . 'inc/wpfs_functions.php';
require_once WPFS_ABSPATH . 'inc/wp-hooks.php';
