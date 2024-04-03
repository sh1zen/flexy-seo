<?php
/**
 * @author    sh1zen
 * @copyright Copyright (C) 2024.
 * @license   http://www.gnu.org/licenses/gpl.html GNU/GPL
 */

namespace FlexySEO\Engine;

use FlexySEO\Engine\Generators\OpenGraph;
use FlexySEO\Engine\Generators\TwitterCard;
use FlexySEO\Engine\Helpers\CurrentPage;

class Default_Generator
{
    protected CurrentPage $current_page;

    protected string $settings_path = '';

    protected string $type = '';

    /**
     * @var \WP_Post|\WP_Post_Type|\WP_Term|\WP_User|null
     */
    protected $queried_object;

    public function __construct(CurrentPage $current_page)
    {
        $this->current_page = $current_page;
        $this->queried_object = $this->current_page->get_queried_object();
    }

    public function redirect(): array
    {
        return array(false, 301);
    }

    /**
     * Generates the robots value.
     *
     * @return array The robots value.
     */
    public function get_robots(): array
    {
        return [];
    }

    /**
     * Generates the meta keywords string.
     */
    public function get_keywords(): string
    {
        return wps('wpfs')->settings->get($this->settings_path . 'keywords', '');
    }

    public function get_paged_permalink($shift = 0): string
    {
        $page = $this->current_page->get_page_number() + $shift;
        $max_pages = $this->current_page->get_query()->max_num_pages;

        if ($max_pages and $page <= $max_pages and $page > 0) {
            return wpfs_paged_link($page);
        }
        elseif ($shift == 0) {
            return wpfs_paged_link(0);
        }

        return '';
    }

    /**
     * Gets the permalink from the indexable or generates it if dynamic permalinks are enabled.
     */
    public function get_permalink(): string
    {
        if ($this->current_page->is_attachment()) {
            $permalink = wp_get_attachment_url($this->current_page->get_queried_object_id());
        }
        elseif ($this->current_page->is_simple_page()) {
            $permalink = get_permalink($this->current_page->get_queried_object_id());
        }
        elseif ($this->current_page->is_homepage()) {
            $permalink = home_url('/');
        }
        elseif ($this->current_page->is_term_archive()) {
            $term = wps_get_term($this->current_page->get_queried_object());

            if ($term === null || is_wp_error($term)) {
                return '';
            }

            $permalink = get_term_link($term, $term->taxonomy);
        }
        elseif ($this->current_page->is_search()) {
            $permalink = get_search_link();
        }
        elseif ($this->current_page->is_author_archive()) {
            $permalink = get_author_posts_url($this->current_page->get_queried_object_id());
        }
        elseif ($this->current_page->is_post_type_archive()) {
            $permalink = get_post_type_archive_link($this->current_page->get_queried_post_type());
        }
        else {
            $permalink = '';
        }

        return $permalink;
    }

    /**
     * Generates the rel prev.
     */
    public function generate_rel_prev(): string
    {
        if ($this->current_page->is_paged() or $this->current_page->get_query()->max_num_pages) {
            return $this->get_paged_permalink(-1);
        }

        return '';
    }

    /**
     * Generates the rel next.
     */
    public function generate_rel_next(): string
    {
        if ($this->current_page->is_paged() or $this->current_page->get_query()->max_num_pages) {
            return $this->get_paged_permalink(1);
        }

        return '';
    }

    public function openGraph(OpenGraph $og): OpenGraph
    {
        $og->type('website');

        return $og;
    }

    public function generate_title(): string
    {
        return wps('wpfs')->settings->get($this->settings_path . 'title', '%%title%%');
    }

    public function get_snippet_image($size = 'thumbnail', $use_default = true)
    {
        if ($this->current_page->is_simple_page()) {

            list($id, $url) = wpfseo('helpers')->post->get_first_usable_image($size);
        }

        if (empty($url) and $use_default) {

            if ($size === 'thumbnail') {
                $url = wps('wpfs')->settings->get('seo.org.logo_url.small', '');
            }
            else {
                $url = wps('wpfs')->settings->get('seo.org.logo_url.wide', '');
            }
        }

        if (empty($url)) {
            return false;
        }

        return wps_get_snippet_data($url, $size);
    }

    /**
     * Generates the description structure.
     */
    public function get_description(): string
    {
        return wps('wpfs')->settings->get($this->settings_path . 'meta_desc', '%%description%%');
    }

    public function twitterCard(TwitterCard $tc): TwitterCard
    {
        return $tc;
    }
}