<?php
/**
 * @author    sh1zen
 * @copyright Copyright (C)  2021
 * @license   http://www.gnu.org/licenses/gpl.html GNU/GPL
 */

namespace FlexySEO\Engine;

use FlexySEO\core\Settings;

class Title
{
    public static function get_format($default = "%%title%%")
    {
        $current_page = \FlexySEO\Engine\Helpers\CurrentPage::get_Instance();

        $title = $default;

        $post_type = $current_page->get_queried_post_type();

        if (is_home() or is_front_page()) {
            $title = shzn('wpfs')->settings->get('seo.home.title', $default);
        }

        // If there is a post
        if (is_attachment() or is_single() or (is_home() and !is_front_page()) or (is_page() and !is_front_page())) {

            if (empty($post_type)) {
                $post_type = get_post_type();
            }

            $title = shzn('wpfs')->settings->get("seo.post_type.{$post_type}.title", $default);
        }

        if (is_archive()) {

            // If there's a custom taxonomy or a category or a tag
            if ($current_page->is_term_archive()) {
                $term = $current_page->get_queried_object();
                if ($term) {
                    $title = shzn('wpfs')->settings->get("seo.tax.{$term->taxonomy}.title", $default);
                }
            }

            // If there's an author
            if (is_author() and !is_post_type_archive()) {
                $title = shzn('wpfs')->settings->get('seo.archives.author.title', $default);
            }

            if (is_date()) {
                $title = shzn('wpfs')->settings->get('seo.archives.date.title', $default);
            }

            if (is_post_type_archive()) {
                $title = shzn('wpfs')->settings->get('seo.archives.' . $post_type . '.title', $default);
            }
        }

        // If it's a search
        if (is_search()) {
            $title = shzn('wpfs')->settings->get('seo.search.title', $default);
        }

        // If it's a 404 page
        if (is_404()) {
            $title = shzn('wpfs')->settings->get('seo.404.title', $default);
        }

        return $title;
    }
}