<?php
/**
 * @author    sh1zen
 * @copyright Copyright (C) 2025.
 * @license   http://www.gnu.org/licenses/gpl.html GNU/GPL
 */

/**
 * Uninstall Procedure
 */
global $wpdb;

// if uninstall.php is not called by WordPress, die
if (!defined('WP_UNINSTALL_PLUGIN')) {
    die();
}

// setup constants
require_once __DIR__ . '/inc/wps_and_constants.php';

// Leave no trail
$option_names = array('wpfs');

foreach ($option_names as $option_name) {
    delete_option($option_name);
}

delete_option('wpfs_redirects_db_version');
delete_option('wpfs_404_monitor_db_version');

$wpdb->query("DROP TABLE IF EXISTS " . wps('wpfs')->options->table_name());
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}flexy_seo_redirects");
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}flexy_seo_404_logs");

wps_uninstall();
