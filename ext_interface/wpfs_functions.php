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
 * @param null|\WP_Post $post
 * @param int $length
 * @param string $more
 * @return string
 */
function wpfs_get_post_excerpt($post = null, $length = 32, $more = '...')
{
    $post = get_post($post);

    $post_excerpt = empty($post->post_excerpt) ? $post->post_content : $post->post_excerpt;

    if ($length) {
        $post_excerpt = wp_trim_words($post_excerpt, $length, $more);
    }

    return $post_excerpt;
}

/**
 * @param null|\WP_Post $post
 * @param string $default
 * @return string
 */
function wpfs_get_the_description($post = null, $default = ''): string
{
    if ($post) {

        $post = get_post($post);

        $_description = shzn('wpfs')->options->get($post->ID, "description", "customMeta", "");

        if (!empty($_description)) {
            $description = $_description;
        }

        if (empty($description)) {
            $description = '%%description%%';
        }

        $description = Txt_Replacer::replace(
            $description,
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

function wpfs_get_mainImageURL($post = null, $size = 'large'): string
{
    list($id, $url) = wpfseo('helpers')->post->get_first_usable_image($size, false, $post);

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

/**
 * todo remove - trailing.it if requested
 */
function wpfs_the_title($filtered = true, $trailingBlogName = true)
{
    $title = '';
    $generator = wpfseo('generator');

    if ($generator) {
        $title = wpfseo('generator')->generate_title();
    }

    if (!$trailingBlogName) {
        $title = trim(str_replace(get_bloginfo('name', 'display'), "", $title), " " . shzn('wpfs')->settings->get('seo.title.separator', '-'));
    }

    return $filtered ? apply_filters('wpfs_title', $title) : $title;
}

function wpfs_document_title($separator = '-', $blogName = true)
{
    global $page, $paged;

    $titleFragments = [];

    if (is_feed()) {

        $post = get_post();

        $titleFragments['title'] = isset($post->post_title) ? $post->post_title : '';

        if (!empty($post->post_password)) {

            $protected_title_format = apply_filters('protected_title_format', __('Protected: %s', 'wpfs'), $post);
            $titleFragments['title'] = sprintf($protected_title_format, $titleFragments);
        }
        elseif (isset($post->post_status) && 'private' === $post->post_status) {

            $private_title_format = apply_filters('private_title_format', __('Private: %s', 'wpfs'), $post);
            $titleFragments['title'] = sprintf($private_title_format, $titleFragments);
        }
    }
    elseif (is_404()) {

        $titleFragments['title'] = __('Page not found', 'wpfs');
    }
    elseif (is_search()) {

        $titleFragments['title'] = sprintf(__('Search Results for &#8220;%s&#8221;', 'wpfs'), get_search_query());
    }
    elseif (is_front_page()) {

        $titleFragments['title'] = get_bloginfo('description', 'display');;
    }
    elseif (is_post_type_archive()) {

        $titleFragments['title'] = post_type_archive_title('', false);
    }
    elseif (is_tax()) {

        $titleFragments['title'] = single_term_title('', false);
    }
    elseif (is_home() || is_singular()) {

        $titleFragments['title'] = single_post_title('', false);
    }
    elseif (is_category() || is_tag()) {

        $titleFragments['title'] = single_term_title('', false);
    }
    elseif (is_author() and get_queried_object()) {

        $titleFragments['title'] = get_queried_object()->display_name;
    }
    elseif (is_year()) {
        $titleFragments['title'] = get_the_date(_x('Y', 'yearly archives date format', 'wpfs'));

    }
    elseif (is_month()) {

        $titleFragments['title'] = get_the_date(_x('F Y', 'monthly archives date format', 'wpfs'));
    }
    elseif (is_day()) {

        $titleFragments['title'] = get_the_date();
    }

    // Add a page number if necessary.
    if (($paged >= 2 || $page >= 2) && !is_404()) {
        $titleFragments['page'] = sprintf(__('Page %s', 'wpfs'), max($paged, $page));
    }

    if ($blogName) {
        $titleFragments['site'] = get_bloginfo('name', 'display');
    }

    $title = implode(" {$separator} ", array_filter($titleFragments));

    $title = trim($title);

    $title = preg_replace('#\s+#', ' ', $title);

    if (str_contains($title, '&')) {
        $title = preg_replace('/&(?!#(?:\d+|x[a-f\d]+);|[a-z1-4]{1,8};)/i', '&#038;', $title);
    }

    return esc_html($title);
}