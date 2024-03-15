<?php
/**
 * @author    sh1zen
 * @copyright Copyright (C) 2024.
 * @license   http://www.gnu.org/licenses/gpl.html GNU/GPL
 */

use FlexySEO\Engine\Helpers\XRE_MetaBox;
use WPS\core\Rewriter;

function wpfs_paged_link($pagenum = 0, $url = ''): string
{
    global $wp_rewrite;

    $rewriter = Rewriter::getClone($url);

    $rewriter->remove_query_args();

    if (!$wp_rewrite->using_permalinks()) {

        if ($pagenum > 1) {
            $rewriter->set_query_arg('paged', $pagenum);
        }

        $result = $rewriter->get_uri();
    }
    else {

        $request = $rewriter->get_request_path(true);

        $request = preg_replace("|$wp_rewrite->pagination_base/\d+/?$|", '', $request);
        $request = preg_replace('|^' . preg_quote($wp_rewrite->index, '|') . '|i', '', $request);
        $request = ltrim($request, '/');

        $base = $rewriter->get_base(true);

        if ($wp_rewrite->using_index_permalinks() && ($pagenum > 1 || '' !== $request)) {
            $base .= $wp_rewrite->index . '/';
        }

        if ($pagenum > 1) {
            $request = ((!empty($request)) ? trailingslashit($request) : $request) . user_trailingslashit($wp_rewrite->pagination_base . '/' . $pagenum, 'paged');
        }

        $result = $base . $request;
    }

    return $result;
}

function wpfs_get_term_meta_description($term, $suppress_filter = false, $default = ''): string
{
    $term = wps_get_term($term);

    if (!$term) {
        return '';
    }

    $value = get_metadata_raw('term', $term->term_id, 'wpfs_metaterm_description', true) ?: $default;

    if ($suppress_filter) {
        return $value;
    }

    // verificare l'utilizzo errato all'interno dei tag vedi Generator:251
    return apply_filters('wpfs_term_meta_description', $value, $term);
}

function wpfs_get_term_meta_title($term, $suppress_filter = false, $default = ''): string
{
    $term = wps_get_term($term);

    if (!$term) {
        return '';
    }

    $value = get_metadata_raw('term', $term->term_id, 'wpfs_metaterm_title', true) ?: $default;

    if ($suppress_filter) {
        return $value;
    }

    return apply_filters('wpfs_term_meta_title', $value, $term);
}

function wpfs_get_post_meta_keywords($post, $suppress_filter = false, $default = ''): string
{
    $post = wps_get_post($post);

    if (!$post) {
        return '';
    }

    $value = XRE_MetaBox::get_value($post->ID, "keywords", $default);

    if ($suppress_filter) {
        return $value;
    }

    return apply_filters('wpfs_post_meta_keywords', $value, $post);
}

function wpfs_get_post_meta_description($post, $suppress_filter = false, $default = ''): string
{
    $post = wps_get_post($post);

    if (!$post) {
        return '';
    }

    $value = XRE_MetaBox::get_value($post->ID, "description", $default);

    if ($suppress_filter) {
        return $value;
    }

    return apply_filters('wpfs_post_meta_description', $value, $post);
}

function wpfs_get_post_meta_graphType($post, $suppress_filter = false, $default = ''): string
{
    $post = wps_get_post($post);

    if (!$post) {
        return '';
    }

    $value = XRE_MetaBox::get_value($post->ID, "graphType", $default);

    if ($suppress_filter) {
        return $value;
    }

    return apply_filters('wpfs_post_meta_graphType', $value, $post);
}