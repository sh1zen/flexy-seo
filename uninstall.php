<?php
/**
 * @author    sh1zen
 * @copyright Copyright (C) 2024.
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

$wpdb->query("DROP TABLE IF EXISTS " . wps('wpfs')->options->table_name());

wps_uninstall();