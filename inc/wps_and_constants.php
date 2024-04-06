<?php
/**
 * @author    sh1zen
 * @copyright Copyright (C) 2024.
 * @license   http://www.gnu.org/licenses/gpl.html GNU/GPL
 */

define("WPFS_ABSPATH", dirname(__DIR__) . '/');
const WPFS_MODULES = WPFS_ABSPATH . 'modules/';
const WPFS_ADMIN = WPFS_ABSPATH . 'admin/';
const WPFS_INC_PATH = WPFS_ABSPATH . 'inc/';

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


define("WPFS_DEBUG", !wps_core()->online);