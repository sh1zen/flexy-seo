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
    list($id, $url) =  wpfseo('helpers')->post->get_first_usable_image($size, false, $post);

    return $url;
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

function wpfs_document_title()
{
    // If it's a 404 page, use a "Page not found" title.
    if (is_404()) {
        $title = __('Page not found');

        // If it's a search, use a dynamic search results title.
    }
    elseif (is_search()) {
        /* translators: %s: Search query. */
        $title = sprintf(__('Search Results for &#8220;%s&#8221;'), get_search_query());

        // If on the front page, use the site title.
    }
    elseif (is_front_page()) {
        $title = get_bloginfo('name', 'display');

        // If on a post type archive, use the post type archive title.
    }
    elseif (is_post_type_archive()) {
        $title = post_type_archive_title('', false);

        // If on a taxonomy archive, use the term title.
    }
    elseif (is_tax()) {
        $title = single_term_title('', false);

        /*
        * If we're on the blog page that is not the homepage
        * or a single post of any post type, use the post title.
        */
    }
    elseif (is_home() || is_singular()) {
        $title = single_post_title('', false);

        // If on a category or tag archive, use the term title.
    }
    elseif (is_category() || is_tag()) {
        $title = single_term_title('', false);

        // If on an author archive, use the author's display name.
    }
    elseif (is_author() and get_queried_object()) {
        $author = get_queried_object();
        $title = $author->display_name;

        // If it's a date archive, use the date as the title.
    }
    elseif (is_year()) {
        $title = get_the_date(_x('Y', 'yearly archives date format'));

    }
    elseif (is_month()) {
        $title = get_the_date(_x('F Y', 'monthly archives date format'));

    }
    elseif (is_day()) {
        $title = get_the_date();
    }
    else {
        $title = '';
    }

    return $title;
}