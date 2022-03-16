<?php
/**
 * @author    sh1zen
 * @copyright Copyright (C)  2022
 * @license   http://www.gnu.org/licenses/gpl.html GNU/GPL
 */

use FlexySEO\Engine\Txt_Replacer;

/**
 * Return current page breadcrumbs
 *
 * @param string $before
 * @param string $after
 * @param bool $display
 * @param array $args
 * @return string
 */
function wpfs_breadcrumb(string $before, string $after, bool $display = true, array $args = array()): string
{
    return WPFS_Breadcrumb::breadcrumb($before, $after, $display, $args);
}

/**
 *
 */
function wpfs_getDescription($post = null, $default = ''): string
{
    if ($post) {

        $post = get_post($post);

        $description = Txt_Replacer::replace(
            shzn('wpfs')->options->get($post->ID, "description", "customMeta", ""),
            $post,
            'post'
        );
    }
    else {
        $description = wpfseo('generator')->get_description($default);
    }

    if (empty($description)) {
        $description = $default;
    }

    return $description;
}

function wpfs_getMainImageURL($post = null, $size = 'large'): string
{
    return wpfseo('helpers')->images->getPostImage($post, $size, false);
}

/**
 * Replace `%%variable_placeholders%%` with their real value based on the current requested page/post/cpt.
 *
 * @param string $string The string to replace the variables in.
 * @param int $object_id
 * @param string $type
 * @return string
 */
function wpfs_replace_vars(string $string, int $object_id = 0, string $type = 'post'): string
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
function wpfs_add_replacement_rule(string $rule, $replacement, $type = [])
{
    if (defined("WPFS_SEO_ENGINE_LOADED") and WPFS_SEO_ENGINE_LOADED) {
        Txt_Replacer::add_replacer($rule, $replacement, $type);
    }
}