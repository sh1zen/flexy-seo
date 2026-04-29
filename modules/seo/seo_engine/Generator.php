<?php
/**
 * @author    sh1zen
 * @copyright Copyright (C) 2025.
 * @license   http://www.gnu.org/licenses/gpl.html GNU/GPL
 */

namespace FlexySEO\Engine;

use FlexySEO\Engine\Generators\OpenGraph;
use FlexySEO\Engine\Generators\TwitterCard;
use FlexySEO\Engine\Helpers\CurrentPage;
use WPS\core\StringHelper;
use WPS\core\TextReplacer;

class Generator
{
    private static int $instances_created = 0;

    private CurrentPage $current_page;

    private Default_Generator $template;

    private string $cache_key;

    private string $query_type;

    public function __construct(CurrentPage $current_page)
    {
        $this->current_page = $current_page;

        // different caches for different instances
        $this->cache_key = 'generator-' . self::$instances_created++;

        $this->query_type = $this->current_page->get_query_type();
    }

    public function getContext(): CurrentPage
    {
        return $this->current_page;
    }

    public function redirect(): array
    {
        return $this->template->redirect();
    }

    public function load_template(): void
    {
        $current_page = $this->current_page;

        include_once WPFS_SEO_ENGINE . 'generators/template/default-generator.php';

        if ($current_page->is_search()):
            include_once WPFS_SEO_ENGINE . 'generators/template/search-generator.php';
            $generator = new \FlexySEO\Engine\Generators\Templates\Search_Generator($current_page);
        elseif ($current_page->is_404()):
            include_once WPFS_SEO_ENGINE . 'generators/template/E404-generator.php';
            $generator = new \FlexySEO\Engine\Generators\Templates\E404_Generator($current_page);
        elseif ($current_page->is_author_archive()):
            include_once WPFS_SEO_ENGINE . 'generators/template/author-archive-generator.php';
            $generator = new \FlexySEO\Engine\Generators\Templates\AuthorArchive_Generator($current_page);
        elseif ($current_page->is_date_archive()):
            include_once WPFS_SEO_ENGINE . 'generators/template/date-archive-generator.php';
            $generator = new \FlexySEO\Engine\Generators\Templates\DateArchive_Generator($current_page);
        elseif ($current_page->is_homepage()):
            include_once WPFS_SEO_ENGINE . 'generators/template/home-generator.php';
            $generator = new \FlexySEO\Engine\Generators\Templates\Home_Generator($current_page);
        elseif ($current_page->is_simple_page() or $current_page->is_posts_page() or $current_page->is_attachment()):
            include_once WPFS_SEO_ENGINE . 'generators/template/post-type-generator.php';
            $generator = new \FlexySEO\Engine\Generators\Templates\PostType_Generator($current_page);
        elseif ($current_page->is_term_archive()):
            include_once WPFS_SEO_ENGINE . 'generators/template/term-archive-generator.php';
            $generator = new \FlexySEO\Engine\Generators\Templates\TermArchive_Generator($current_page);
        elseif ($current_page->is_post_type_archive()):
            include_once WPFS_SEO_ENGINE . 'generators/template/post-type-archive-generator.php';
            $generator = new \FlexySEO\Engine\Generators\Templates\PostTypeArchive_Generator($current_page);
        else:
            $generator = new Default_Generator($current_page);
        endif;

        $this->template = $generator;
    }

    public function site_verification_codes(): array
    {
        $codes = [];

        if (!empty($code = wps('wpfs')->settings->get('seo.webmaster.google.vercode', ''))) {
            $codes['google-site-verification'] = $code;
        }

        if (!empty($code = wps('wpfs')->settings->get('seo.webmaster.baidu.vercode', ''))) {
            $codes['baidu-site-verification'] = $code;
        }

        if (!empty($code = wps('wpfs')->settings->get('seo.webmaster.bing.vercode', ''))) {
            $codes['msvalidate.01'] = $code;
        }

        if (!empty($code = wps('wpfs')->settings->get('seo.webmaster.yandex.vercode', ''))) {
            $codes['yandex-verification'] = $code;
        }

        return $codes;
    }

    /**
     * Generates the robots value.
     */
    public function get_robots(): array
    {
        $robots = array_merge(
            [
                'index'             => 'index',
                'follow'            => 'follow',
                'max-snippet'       => 'max-snippet:-1',
                'max-image-preview' => 'max-image-preview:large',
                'max-video-preview' => 'max-video-preview:-1',
            ],
            $this->template->get_robots()
        );

        return apply_filters('wpfs_robots_' . $this->current_page->get_page_type(), $robots, $this->current_page);
    }

    /**
     * Generates the meta keywords.
     */
    public function get_keywords(): string
    {
        if (($cached = $this->get_cache("keywords")) !== false) {
            return $cached;
        }

        $keywords_pre = $this->template->get_keywords();

        // filter general generation
        $keywords_pre = apply_filters('wpfs_keywords', $keywords_pre, $this->current_page->get_page_type(), $this->current_page);

        // filter typed generation
        $keywords_pre = apply_filters("wpfs_keywords_$this->query_type", $keywords_pre);

        // remove white multiple spaces new lines...
        $keywords = StringHelper::clear($keywords_pre);

        $keywords = TextReplacer::replace(
            $keywords,
            $this->current_page->get_queried_object(),
            $this->query_type
        );

        $keywords = StringHelper::filter_text($keywords, true);

        // keep just words and commas
        $keywords = preg_replace('#\W*,+\W*#', ', ', $keywords);
        $keywords = trim($keywords, ' ,');

        if (empty($keywords)) {
            $keywords = $this->get_context_keywords_fallback();
        }

        $keywords = StringHelper::escape_text($keywords);

        $this->set_cache("keywords", $keywords);

        return $keywords;
    }

    private function get_cache($cacheKey)
    {
        return wps('wpfs')->cache->get($cacheKey, $this->cache_key);
    }

    private function set_cache($cacheKey, $data, $expiration = false): bool
    {
        return wps('wpfs')->cache->set($cacheKey, $data, $this->cache_key, true, $expiration);
    }

    /**
     * Gets the permalink from the Indexable or generates it if dynamic permalinks are enabled.
     */
    public function get_permalink(): string
    {
        if (($permalink = $this->get_cache("get_permalink")) !== false) {
            return $permalink;
        }

        $permalink = $this->template->get_permalink();

        $this->set_cache("get_permalink", $permalink);

        return $permalink;
    }

    /**
     * Generates the rel prev.
     */
    public function generate_rel_prev(): string
    {
        if (($permalink = $this->get_cache("rel_prev")) !== false) {
            return $permalink;
        }

        $permalink = $this->template->generate_rel_prev();

        $permalink = apply_filters('wpfs_relprev', $permalink, $this->current_page->get_page_type(), $this->current_page);

        $this->set_cache("rel_prev", $permalink);

        return $permalink;
    }

    /**
     * Generates the rel next.
     */
    public function generate_rel_next(): string
    {
        if (($permalink = $this->get_cache("rel_next")) !== false) {
            return $permalink;
        }

        $permalink = $this->template->generate_rel_next();

        $permalink = apply_filters('wpfs_relnext', $permalink, $this->current_page->get_page_type(), $this->current_page);

        $this->set_cache("rel_next", $permalink);

        return $permalink;
    }

    public function openGraph(): OpenGraph
    {
        $og = new OpenGraph();

        $og->title($this->generate_title());

        $image_metadata = $this->get_snippet_image(['medium', 'large', 'full'], true);

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
        $og->url($this->generate_canonical());
        $og->locale();
        $og->siteName(get_bloginfo('name'));

        return $this->template->openGraph($og);
    }

    public function generate_title($default = '', $suppress_filters = false): string
    {
        if (($cached = $this->get_cache("title")) !== false) {
            return $cached ?: $default;
        }

        $title_pre = $this->template->generate_title();

        if (!$suppress_filters) {

            // filter general generation
            $title_pre = apply_filters("wpfs_title", $title_pre, $this->current_page->get_page_type(), $this->current_page);

            // filter typed generation
            $title_pre = apply_filters("wpfs_title_$this->query_type", $title_pre);
        }

        $title = TextReplacer::replace(
            $title_pre,
            $this->current_page->get_queried_object(),
            $this->current_page->get_query_type()
        );

        /**
         * Pre-filter title to not have problems in trailing blog name
         */
        $title = $this->normalize_meta_text($title);

        if (empty($title)) {
            $title = wpfs_document_title(wps('wpfs')->settings->get('seo.title.separator', '-'), false);
        }

        // if active force trailing blog name
        if (wps('wpfs')->settings->get('seo.title.blogname', false) and !str_ends_with($title, get_bloginfo('name', 'display'))) {

            $title_sep = wps('wpfs')->settings->get('seo.title.separator', '-');

            $preg_quote = preg_quote(get_bloginfo('name', 'display'), '#');

            $title = preg_replace("#\W*$preg_quote\W*#si", ' ', $title);

            $title .= " $title_sep " . get_bloginfo('name', 'display');

            $title = preg_replace('#\s+#', ' ', $title);
        }

        $title = StringHelper::escape_text($title);

        $this->set_cache("title", $title);

        return $title ?: $default;
    }

    public function get_snippet_image($size = 'thumbnail', $use_default = true)
    {
        if (($cached = $this->get_cache("snippet_image")) !== false) {
            return $cached;
        }

        $snippet_data = $this->template->get_snippet_image($size, $use_default);

        $this->set_cache("snippet_image", $snippet_data);

        return $snippet_data;
    }

    /**
     * Generates the meta description.
     */
    public function get_description(string $default = ''): string
    {
        if (($cashed = $this->get_cache("description")) !== false) {
            return $cashed ?: $default;
        }

        $description_pre = $this->template->get_description();

        // filter general generation
        $description_pre = apply_filters('wpfs_description', $description_pre, $this->current_page->get_page_type(), $this->current_page);

        // filter typed generation
        $description_pre = apply_filters("wpfs_description_$this->query_type", $description_pre);

        $description = TextReplacer::replace(
            $description_pre,
            $this->current_page->get_queried_object(),
            $this->query_type
        );

        $description = $this->normalize_meta_text($description);

        if (empty($description)) {
            $description = $this->get_context_description_fallback();
        }

        $description = StringHelper::escape_text($description, false, true);

        $this->set_cache("description", $description);

        return $description ?: $default;
    }

    /**
     * Generates the canonical.
     */
    public function generate_canonical(): string
    {
        if (($permalink = $this->get_cache("canonical")) !== false) {
            return $permalink;
        }

        // generate the canonical address from the current without any query args
        $permalink = $this->template->get_paged_permalink();

        $permalink = apply_filters('wpfs_canonical', $permalink, $this->current_page->get_page_type(), $this->current_page);

        $this->set_cache("canonical", $permalink);

        return $permalink;
    }

    public function twitterCard(): TwitterCard
    {
        $tc = new TwitterCard();

        if (wps('wpfs')->settings->get("seo.social.twitter.card", false)) {

            $tc->add_card(wps('wpfs')->settings->get("seo.social.twitter.large_images", true) ? 'summary_large_image' : 'summary');

            $image_metadata = $this->get_snippet_image(['medium', 'large', 'full'], true);

            if ($image_metadata and $image_metadata['url']) {
                $tc->add_image($image_metadata['url']);
            }

            $tc->add_title($this->generate_title());

            $tc->add_description($this->get_description());

            $twitterName = $this->normalize_social_username(wps('wpfs')->settings->get("seo.social.twitter.url", ''));

            if (empty($twitterName)) {
                return $tc;
            }

            $tc->add_creator($twitterName);
            $tc->add_site($twitterName);
        }

        return $this->template->twitterCard($tc);
    }

    private function normalize_meta_text(string $text): string
    {
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, get_bloginfo('charset') ?: 'UTF-8');
        $text = strip_shortcodes($text);
        $text = wp_strip_all_tags($text, true);
        $text = preg_replace('/%%[^%]+%%/', '', $text);
        $text = StringHelper::filter_text($text, true);
        $text = preg_replace('/\s+/u', ' ', $text);

        return trim($text);
    }

    private function get_context_description_fallback(): string
    {
        $description = '';
        $object = $this->current_page->get_queried_object();

        if ($object instanceof \WP_Post) {
            $description = has_excerpt($object) ? $object->post_excerpt : $object->post_content;
            $description = wp_trim_words($description, 32, '');
        }
        elseif ($object instanceof \WP_Term) {
            $description = $object->description;
        }
        elseif ($object instanceof \WP_User) {
            $description = $object->description;
        }
        elseif ($this->current_page->is_search()) {
            $description = sprintf(__('Search results for "%s"', 'wpfs'), get_search_query());
        }

        if (empty($description)) {
            $description = get_bloginfo('description', 'display');
        }

        $description = apply_filters('wpfs_description_fallback', $description, $this->current_page->get_page_type(), $this->current_page);

        return $this->normalize_meta_text($description);
    }

    private function get_context_keywords_fallback(): string
    {
        $keywords = [];
        $object = $this->current_page->get_queried_object();

        if ($object instanceof \WP_Post) {
            foreach (get_post_taxonomies($object) as $taxonomy) {
                $terms = get_the_terms($object, $taxonomy);

                if (!empty($terms) && !is_wp_error($terms)) {
                    $keywords = array_merge($keywords, wp_list_pluck($terms, 'name'));
                }
            }
        }
        elseif ($object instanceof \WP_Term) {
            $keywords[] = $object->name;

            $taxonomy = get_taxonomy($object->taxonomy);

            if ($taxonomy) {
                $keywords[] = $taxonomy->labels->singular_name ?? '';
            }
        }
        elseif ($this->current_page->is_search()) {
            $keywords[] = get_search_query();
        }

        $keywords = array_filter(array_unique(array_map([$this, 'normalize_meta_text'], $keywords)));
        $keywords = array_slice($keywords, 0, 20);

        return implode(', ', $keywords);
    }

    private function normalize_social_username(string $value): string
    {
        $value = trim($value);

        if (empty($value)) {
            return '';
        }

        $path = wp_parse_url($value, PHP_URL_PATH);

        if (!empty($path)) {
            $value = trim($path, '/');
        }

        $value = preg_replace('/[?#].*/', '', $value);
        $value = preg_replace('/^@+/', '', $value);
        $value = strtok($value, '/');
        $value = preg_replace('/[^A-Za-z0-9_]/', '', $value);

        return $value ? '@' . $value : '';
    }
}
