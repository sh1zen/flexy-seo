<?php
/**
 * @author    sh1zen
 * @copyright Copyright (C)  2022
 * @license   http://www.gnu.org/licenses/gpl.html GNU/GPL
 */

use FlexySEO\core\Options;

global $wpdb;

$wpdb->query("ALTER TABLE " . Options::table_name() . " ADD UNIQUE speeder (context, item, obj_id) USING BTREE;");

