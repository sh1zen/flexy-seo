<?php
/**
 * @author    sh1zen
 * @copyright Copyright (C)  2021
 * @license   http://www.gnu.org/licenses/gpl.html GNU/GPL
 */

global $wpdb;

$sql = "ALTER TABLE {$wpdb->prefix}flexy_seo MODIFY obj_id varchar(255);";

$wpdb->query($sql);

$sql = "ALTER TABLE {$wpdb->prefix}flexy_seo ADD COLUMN expiration bigint NOT NULL DEFAULT 0;";

$wpdb->query($sql);

unset($sql);