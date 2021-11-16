<?php
/**
 * @author    sh1zen
 * @copyright Copyright (C)  2021
 * @license   http://www.gnu.org/licenses/gpl.html GNU/GPL
 */

namespace FlexySEO\core;

/**
 * Access to wpopt custom database table options and metadata
 */
class Options
{
    public static function update($obj_id, $option, $value = false, $context = 'core')
    {
        global $wpdb;

        $option = trim($option);

        if (empty($option)) {
            return false;
        }

        if (is_object($value)) {
            $value = clone $value;
        }

        $old_value = self::get($obj_id, $option, $context);

        if ($old_value === false) {
            self::add($obj_id, $option, $value, $context);
        }

        $serialized_value = maybe_serialize($value);

        if ($value === $old_value or $serialized_value === maybe_serialize($old_value)) {
            return false;
        }

        $update_args = array(
            'value'   => $serialized_value,
            'context' => $context,
            'obj_id'  => $obj_id
        );

        $result = $wpdb->update(self::table_name(), $update_args, array('item' => $option));

        if (!$result) {
            return false;
        }

        shzn('wpfs')->cache->set_cache($option, $value, 'options', true);

        return true;
    }

    public static function get($obj_id, $option, $context = 'core', $default = false)
    {
        global $wpdb;

        $option = trim($option);

        if (empty($option)) {
            return false;
        }

        if ($value = shzn('wpfs')->cache->get_cache($option, 'options', false))
            return $value;

        $row = $wpdb->get_row($wpdb->prepare("SELECT value FROM " . self::table_name() . " WHERE obj_id = %d AND item = %s AND context = %s LIMIT 1", $obj_id, $option, $context));

        // Has to be get_row() instead of get_var() because of funkiness with 0, false, null values.
        if (is_object($row)) {
            $value = $row->value;
            $value = maybe_unserialize($value);
            shzn('wpfs')->cache->set_cache($option, $value, 'options');
        }
        else {
            $value = $default;
        }

        return $value;
    }

    private static function table_name()
    {
        global $wpdb;

        return $wpdb->prefix . "flexy_seo";
    }

    public static function add($obj_id, $option, $value = false, $context = 'core')
    {
        global $wpdb;

        $option = trim($option);

        if (empty($option) or !$obj_id) {
            return false;
        }

        if (is_object($value)) {
            $value = clone $value;
        }

        $serialized_value = maybe_serialize($value);

        $result = $wpdb->query($wpdb->prepare("INSERT INTO " . self::table_name() . " (obj_id, context, item, value) VALUES (%d, %s, %s, %s) ON DUPLICATE KEY UPDATE item = VALUES(item), value = VALUES(value)", $obj_id, $context, $option, $serialized_value));

        if (!$result) {
            return false;
        }

        shzn('wpfs')->cache->set_cache($option, $value, 'options', true);

        return true;
    }

    public static function remove($obj_id, $option, $context = 'core')
    {
        global $wpdb;

        $option = trim($option);

        if (empty($option) or !$obj_id) {
            return false;
        }

        $result = $wpdb->query($wpdb->prepare("DELETE FROM " . self::table_name() . " WHERE item = %s AND obj_id = %d AND context = %s", $option, $obj_id, $context));

        if (!$result) {
            return false;
        }

        shzn('wpfs')->cache->delete_cache($option, 'options');

        return true;
    }
}