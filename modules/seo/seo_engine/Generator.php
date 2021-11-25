<?php
/**
 * @author    sh1zen
 * @copyright Copyright (C)  2021
 * @license   http://www.gnu.org/licenses/gpl.html GNU/GPL
 */

namespace FlexySEO\Engine;


use FlexySEO\core\Options;
use FlexySEO\Engine\Generators\OpenGraph;
use FlexySEO\Engine\Generators\Schema;
use FlexySEO\Engine\Generators\TwitterCard;
use FlexySEO\Engine\Helpers\CurrentPage;

class Generator
{
    /**
     * @var CurrentPage
     */
    protected $current_page;

    protected $settings_path;

    protected $type;

    /**
     * @param CurrentPage $current_page
     */
    public function __construct($current_page)
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

        if (!empty($code = shzn('wpfs')->settings->get('seo.webmaster.google.vercode', '')))
            $codes['google-site-verification'] = $code;

        if (!empty($code = shzn('wpfs')->settings->get('seo.webmaster.baidu.vercode', '')))
            $codes['baidu-site-verification'] = $code;

        if (!empty($code = shzn('wpfs')->settings->get('seo.webmaster.bing.vercode', '')))
            $codes['msvalidate.01'] = $code;

        if (!empty($code = shzn('wpfs')->settings->get('seo.webmaster.yandex.vercode', '')))
            $codes['yandex-verification'] = $code;

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
        //$indexable = Options::get($this->current_page->get_queried_object_id(), "indexable", "customMeta", true);

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
    public function get_keywords($keywords = '')
    {
        if ($keywords = $this->get_cache("keywords"))
            return maybe_unserialize($keywords);

        if ($this->current_page->is_simple_page()) {
            $_keywords = Options::get($this->current_page->get_queried_object_id(), "keywords", "customMeta", false);

            if (!empty($_keywords))
                $keywords = $_keywords;
        }

        if (is_array($keywords))
            $keywords = implode(',', $keywords);

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
        return shzn('wpfs')->cache->get_cache($cacheKey, "generator");
    }

    protected final function set_cache($cacheKey, $data)
    {
        return shzn('wpfs')->cache->set_cache($cacheKey, $data, "generator", true);
    }

    public function schema()
    {
        $schema = new Schema($this);

        $schema->build();

        return $schema->export();
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
        if ($url = $this->get_cache("permalink-{$shift}"))
            return $url;

        $url = '';

        $rewriter = Rewriter::get_instance();

        $page = $this->get_page_number() + $shift;

        $max_pages = $this->current_page->get_main_query()->max_num_pages;

        if ($max_pages and $page <= $max_pages and $page > 0) {
            $url = $rewriter->get_pagenum_link($page);
        }
        elseif ($shift == 0) {
            $url = $rewriter->get_pagenum_link();
        }

        $this->set_cache("permalink-{$shift}", $url);

        return $url;
    }

    public function get_page_number()
    {
        $page = get_query_var('page');
        $paged = get_query_var('paged');
        return !empty($page) ? $page : (!empty($paged) ? $paged : 1);
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
        elseif ($this->current_page->is_home()) {
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

        foreach (['medium', 'thumbnail'] as $size) {
            $image_metadata = $this->get_snippet_image($size);

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
        }

        $og->description($this->get_description());
        $og->url($this->get_paged_permalink());
        $og->locale();
        $og->siteName(get_bloginfo('name'));

        return $og;
    }

    public function generate_title($title = '')
    {
        if ($_title = $this->get_cache("title"))
            return $_title;

        if (empty($title))
            $title = "%%title%%";

        $title = Txt_Replacer::replace(
            $title,
            $this->current_page->get_queried_object(),
            $this->current_page->get_query_type()
        );

        $title = trim($title);
        $title = convert_chars($title);

        $title = esc_html($title);

        $title = apply_filters("wpfs_title", $title);

        $this->set_cache("title", $title);

        return $title;
    }

    protected function get_snippet_image($size = 'thumbnail')
    {
        if ($_image = $this->get_cache("snippet_image"))
            return $_image;

        $width = 0;
        $height = 0;

        if ($size === 'thumbnail') {
            $url = shzn('wpfs')->settings->get('seo.org.logo_url.small', '');
        }
        else {
            $url = shzn('wpfs')->settings->get('seo.org.logo_url.wide', '');
        }

        if (empty($url))
            return false;

        $path = parse_url($url, PHP_URL_PATH);

        $image_path = realpath(ABSPATH . $path);

        if ($image_path) {
            list($width, $height) = wp_getimagesize($image_path);
        }

        if ($this->current_page->is_simple_page()) {

            if ($post_thumbnail_id = get_post_thumbnail_id($this->current_page->get_queried_object())) {

                if ($image_data = wp_get_attachment_image_src($post_thumbnail_id, $size)) {
                    $url = $image_data[0];
                    $width = $image_data[1];
                    $height = $image_data[2];
                }
            }
        }

        $snippet_data = array('url' => $url, 'width' => $width, 'height' => $height);

        $this->set_cache("snippet_image", $snippet_data);

        return $snippet_data;
    }

    /**
     * Generates the meta description.
     *
     * @param string $description
     * @return string The meta description.
     */
    public function get_description($description = '')
    {
        if ($dsc = $this->get_cache("description"))
            return $dsc;

        if ($this->current_page->is_simple_page()) {
            $_description = Options::get($this->current_page->get_queried_object_id(), "description", "customMeta", "");

            if (!empty($_description))
                $description = $_description;
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

            preg_match('/(https?:\/\/twitter\.com\/)?(?<name>[^\?]+)(\??.*)?/i', shzn('wpfs')->settings->get("seo.social.twitter.url", ''), $m);

            if(!isset($m['name']))
                return $tc;

            $twitterName = '@' . trim($m['name'], ' @');

            $tc->add_creator($twitterName);
            $tc->add_site($twitterName);
        }

        return $tc;
    }
}