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

class Generator
{
    protected CurrentPage $current_page;

    protected $settings_path;

    protected $type;

    public function __construct(CurrentPage $current_page)
    {
        $this->current_page = $current_page;
        $this->settings_path = '';
        $this->type = '';
    }

    public function redirect()
    {
        return array(false, 301);
    }

    public function site_verification_codes()
    {
        $codes = [];

        if (!empty($code = shzn('wpfs')->settings->get('seo.webmaster.google.vercode', ''))) {
            $codes['google-site-verification'] = $code;
        }

        if (!empty($code = shzn('wpfs')->settings->get('seo.webmaster.baidu.vercode', ''))) {
            $codes['baidu-site-verification'] = $code;
        }

        if (!empty($code = shzn('wpfs')->settings->get('seo.webmaster.bing.vercode', ''))) {
            $codes['msvalidate.01'] = $code;
        }

        if (!empty($code = shzn('wpfs')->settings->get('seo.webmaster.yandex.vercode', ''))) {
            $codes['yandex-verification'] = $code;
        }

        return $codes;
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
     * @return string The meta keywords.
     */
    public function get_keywords(string $keywords = '')
    {
        if (($keys = $this->get_cache("keywords")) !== false) {
            return maybe_unserialize($keys);
        }

        if ($this->current_page->is_simple_page()) {
            $_keywords = shzn('wpfs')->options->get($this->current_page->get_queried_object_id(), "keywords", "customMeta", "");

            if (!empty($_keywords)) {
                $keywords = $_keywords;
            }
        }

        if (is_array($keywords)) {
            $keywords = implode(',', $keywords);
        }

        $keywords = Txt_Replacer::replace(
            $keywords,
            $this->current_page->get_queried_object(),
            $this->current_page->get_query_type()
        );

        $keywords = preg_split("/[\s,]+/", $keywords);

        $this->set_cache("keywords", maybe_serialize($keywords));

        return $keywords;
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
            $term = get_term($this->current_page->get_queried_object_id());

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
    public function openGraph()
    {
        $og = new OpenGraph();

        $og->title($this->generate_title());

        $image_metadata = $this->get_snippet_image(['medium', 'large'], false);

        if ($image_metadata and $image_metadata['url']) {
            $attributes = [];

            if ($image_metadata['width']) {
                $attributes = [
                    'width'  => $image_metadata['width'],
                    'height' => $image_metadata['height'],
                ];
            }
            $og->image($image_metadata['url'], $attributes);
        }

        $og->description($this->get_description());
        $og->url($this->get_paged_permalink());
        $og->locale();
        $og->siteName(get_bloginfo('name'));

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

        $title = trim($title);

        if (str_contains($title, '&')) {
            $title = preg_replace('/&([^#])(?![a-z1-4]{1,8};)/Ui', '&#038;$1', $title);
        }

        $title = esc_html($title);

        $this->set_cache("title", $title);

        return $title;
    }

    public function get_snippet_image($size = 'thumbnail')
    {
        if (is_array($size)) {
            foreach ($size as $s) {
                if ($im = $this->get_snippet_image($s)) {
                    return $im;
                }
            }

            return false;
        }

        if (($_image = $this->get_cache("snippet_image")) !== false) {
            return $_image;
        }

        if ($this->current_page->is_simple_page()) {

            list($id, $url) = wpfseo('helpers')->post->get_first_usable_image($size);
        }

        if (empty($url)) {

            if ($size === 'thumbnail') {
                $url = shzn('wpfs')->settings->get('seo.org.logo_url.small', '');
            }
            else {
                $url = shzn('wpfs')->settings->get('seo.org.logo_url.wide', '');
            }

            if (empty($url)) {
                return false;
            }
        }

        $snippet_data = shzn('wpfs')->options->get($url, "snippet_data", "cache", false);

        if (!$snippet_data) {

            $snippet_data = wpfseo()->images->get_snippet_data($url, $size);

            if (!$snippet_data) {
                return false;
            }

            shzn('wpfs')->options->add($url, "snippet_data", $snippet_data, "cache", WEEK_IN_SECONDS);
        }

        $this->set_cache("snippet_image", $snippet_data);

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
        if (($dsc = $this->get_cache("description")) !== false) {
            return $dsc;
        }

        if ($this->current_page->is_simple_page()) {
            $_description = shzn('wpfs')->options->get($this->current_page->get_queried_object_id(), "description", "customMeta", "");

            if (!empty($_description)) {
                $description = $_description;
            }
        }

        $description = Txt_Replacer::replace(
            $description,
            $this->current_page->get_queried_object(),
            $this->current_page->get_query_type()
        );

        $this->set_cache("description", $description);

        return $description;
    }

    public function twitterCard()
    {
        $tc = new TwitterCard();

        if (shzn('wpfs')->settings->get("seo.social.twitter.card", false)) {
            $tc->add_card(shzn('wpfs')->settings->get("seo.social.twitter.large_images", true) ? 'summary_large_image' : 'summary');

            preg_match('#(https?://twitter\.com/)?(?<name>[^?]+)(\??.*)#i', shzn('wpfs')->settings->get("seo.social.twitter.url", ''), $m);

            if (!isset($m['name'])) {
                return $tc;
            }

            $twitterName = '@' . trim($m['name'], ' @');

            $tc->add_creator($twitterName);
            $tc->add_site($twitterName);
        }

        return $tc;
    }
}