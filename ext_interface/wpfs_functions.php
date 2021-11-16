<?php
/**
 * @author    sh1zen
 * @copyright Copyright (C)  2021
 * @license   http://www.gnu.org/licenses/gpl.html GNU/GPL
 */

use FlexySEO\Engine\Txt_Replacer;

/**
 * @param $before
 * @param $after
 * @param bool $display
 * @param array $args
 * @return string
 */
function wpfs_breadcrumb($before, $after, $display = true, $args = array())
{
    return WPFS_Breadcrumb::breadcrumb($before, $after, $display, $args);
}


/**
 * Replace `%%variable_placeholders%%` with their real value based on the current requested page/post/cpt.
 *
 * @param string $string The string to replace the variables in.
 * @param int $object_id
 * @param string $type
 * @return string
 */
function wpfs_replace_vars($string, $object_id = 0, $type = 'post')
{
    return FlexySEO\Engine\Txt_Replacer::replace($string, $object_id, $type);
}

/**
 * Add a custom replacement rule with query type support
 *
 * @param string $rule The rule ex. `%%custom_replace%%`
 * @param String|callable $replacement
 * @param string|string[] $type
 */
function wpfs_add_replacement_rule($rule, $replacement, $type = [])
{
    if(defined("WPFS_SEO_ENGINE_LOADED") and WPFS_SEO_ENGINE_LOADED) {
        Txt_Replacer::add_replacer($rule, $replacement, $type);
    }
}