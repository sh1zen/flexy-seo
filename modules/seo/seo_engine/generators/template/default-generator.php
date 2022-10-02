<?php
/**
 * @author    sh1zen
 * @copyright Copyright (C)  2022
 * @license   http://www.gnu.org/licenses/gpl.html GNU/GPL
 */

namespace FlexySEO\Engine;

use FlexySEO\Engine\Generators\OpenGraph;
use FlexySEO\Engine\Generators\TwitterCard;
use FlexySEO\Engine\Helpers\CurrentPage;

class Default_Generator
{
    protected CurrentPage $current_page;

    protected $settings_path = '';

    protected $type = '';

    public function __construct(CurrentPage $current_page)
    {
        $this->current_page = $current_page;
    }

    public function redirect()
    {
        return array(false, 301);
    }

    /**
     * Generates the robots value.
     *
     * @param array $robots
     * @return array The robots value.
     */
    public function get_robots($robots = array())
    {
        //$indexable = shzn('wpfs')->options->get($this->current_page->get_queried_object_id(), "indexable", "customMeta", true);

        $valid = [
            'index'             => 'index', //$indexable ? 'index' : 'noindex',
            'follow'            => 'follow', // 'nofollow'
            'max-snippet'       => 'max-snippet:-1',
            'max-image-preview' => 'max-image-preview:large',
            'max-video-preview' => 'max-video-preview:-1',
        ];

        return array_merge($valid, $robots);
    }

    /**
     * Generates the meta keywords.
     *
     * @param string $keywords
     * @return string[] The meta keywords.
     */
    public function get_keywords(string $keywords = '')
    {
        if ($this->current_page->is_simple_page()) {
            $_keywords = shzn('wpfs')->options->get($this->current_page->get_queried_object_id(), "keywords", "customMeta", "");

            if (!empty($_keywords)) {
                $keywords = $_keywords;
            }
        }

        if (is_array($keywords)) {
            $keywords = implode(',', $keywords);
        }

        return Txt_Replacer::replace(
            $keywords,
            $this->current_page->get_queried_object(),
            $this->current_page->get_query_type()
        );
    }

    protected final function get_cache($cacheKey)
    {
        return shzn('wpfs')->cache->get($cacheKey, "generator");
    }

    protected final function set_cache($cacheKey, $data)
    {
        return shzn('wpfs')->cache->set($cacheKey, $data, "generator", true);
    }

    /**
     * Generates the canonical.
     *
     * @return string The canonical.
     */
    public function generate_canonical()
    {
        $permalink = $this->get_paged_permalink();

        if ($permalink) {
            return $permalink;
        }

        return '';
    }

    public function get_paged_permalink($shift = 0)
    {
        if ($url = $this->get_cache("permalink-{$shift}")) {
            return $url;
        }

        $rewriter = Rewriter::get_instance();

        $page = $this->current_page->get_page_number() + $shift;

        $max_pages = $this->current_page->get_main_query()->max_num_pages;

        if ($max_pages and $page <= $max_pages and $page > 0) {
            $url = $rewriter->get_pagenum_link($page);
        }
        elseif ($shift == 0) {
            $url = $rewriter->get_pagenum_link();
        }
        else {
            $url = '';
        }

        $this->set_cache("permalink-{$shift}", $url);

        return $url;
    }

    /**
     * Gets the permalink from the indexable or generates it if dynamic permalinks are enabled.
     *
     * @param int $shift
     * @return string The permalink.
     */
    public function get_permalink($shift = 0)
    {
        if ($this->current_page->is_attachment()) {
            return wp_get_attachment_url($this->current_page->get_queried_object_id());
        }
        elseif ($this->current_page->is_simple_page()) {
            return get_permalink($this->current_page->get_queried_object_id());
        }
        elseif ($this->current_page->is_homepage()) {
            return home_url('/');
        }
        elseif ($this->current_page->is_term_archive()) {
            $term = shzn_get_term($this->current_page->get_queried_object());

            if ($term === null || is_wp_error($term)) {
                return null;
            }

            return get_term_link($term, $term->taxonomy);
        }
        elseif ($this->current_page->is_search()) {
            return get_search_link();
        }

        elseif ($this->current_page->is_post_type_archive()) {
            return get_post_type_archive_link($this->current_page->get_queried_post_type());
        }
        elseif ($this->current_page->is_author_archive()) {
            return get_author_posts_url($this->current_page->get_queried_object_id());
        }

        return '';
    }

    /**
     * Generates the rel prev.
     *
     * @return string The rel prev value.
     */
    public function generate_rel_prev()
    {
        if ($this->current_page->is_paged() or $this->current_page->get_main_query()->max_num_pages) {
            return $this->get_paged_permalink(-1);
        }

        return '';
    }

    /**
     * Generates the rel next.
     *
     * @return string The rel prev next.
     */
    public function generate_rel_next()
    {
        if ($this->current_page->is_paged() or $this->current_page->get_main_query()->max_num_pages) {
            return $this->get_paged_permalink(1);
        }

        return '';
    }

    /**
     * @return OpenGraph
     */
    public function openGraph(OpenGraph $og)
    {
        $og->type('website');

        return $og;
    }

    public function generate_title($title = '')
    {
        if (($_title = $this->get_cache("title")) !== false) {
            return $_title;
        }

        if (empty($title)) {
            $title = "%%title%%";
        }

        $title = Txt_Replacer::replace(
            $title,
            $this->current_page->get_queried_object(),
            $this->current_page->get_query_type()
        );

        $this->set_cache("title", $title);

        return $title;
    }

    public function get_snippet_image($size = 'thumbnail', $use_default = true)
    {
        if ($this->current_page->is_simple_page()) {

            list($id, $url) = wpfseo('helpers')->post->get_first_usable_image($size);
        }

        if (empty($url) and $use_default) {

            if ($size === 'thumbnail') {
                $url = shzn('wpfs')->settings->get('seo.org.logo_url.small', '');
            }
            else {
                $url = shzn('wpfs')->settings->get('seo.org.logo_url.wide', '');
            }
        }

        if (empty($url)) {
            return false;
        }

        $snippet_data = shzn('wpfs')->options->get($url, "snippet_data", "cache", false);

        if (!$snippet_data) {

            $snippet_data = wpfseo()->images->get_snippet_data($url, $size);

            if (!$snippet_data) {
                return false;
            }

            shzn('wpfs')->options->add($url, "snippet_data", $snippet_data, "cache", WEEK_IN_SECONDS);
        }

        return $snippet_data;
    }

    /**
     * Generates the meta description.
     *
     * @param string $description
     * @return string The meta description.
     */
    public function get_description(string $description = '')
    {
        if ($this->current_page->is_simple_page()) {

            $_description = shzn('wpfs')->options->get($this->current_page->get_queried_object_id(), "description", "customMeta", "");

            if (!empty($_description)) {
                $description = $_description;
            }
        }

        if (empty($description)) {
            $description = '%%description%%';
        }

        return Txt_Replacer::replace(
            $description,
            $this->current_page->get_queried_object(),
            $this->current_page->get_query_type()
        );
    }

    public function twitterCard(TwitterCard $tc)
    {
        return $tc;
    }
}