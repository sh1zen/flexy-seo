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

    /**
     * @var \FlexySEO\Engine\Default_Generator
     */
    private $template;

    public function __construct(CurrentPage $current_page)
    {
        $this->current_page = $current_page;
    }

    public function getContext()
    {
        return $this->current_page;
    }

    public function redirect()
    {
        return $this->template->redirect();
    }

    public function load_template()
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
        return $this->template->get_robots($robots);
    }

    /**
     * Generates the meta keywords.
     *
     * @param string $keywords
     * @return string[] The meta keywords.
     */
    public function get_keywords(string $keywords = '')
    {
        if (($keys = $this->get_cache("keywords")) !== false) {
            return maybe_unserialize($keys);
        }

        $keywords = $this->template->get_keywords($keywords);

        if (is_array($keywords)) {
            $keywords = implode(',', $keywords);
        }

        $keywords = preg_split("/[\s,]+/", trim($keywords, " ,\t\n\r\0\x0B"));

        $keywords = array_filter((array)$keywords);

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
        if (($permalink = $this->get_cache("permalink")) !== false) {
            return $permalink;
        }

        $permalink = $this->template->generate_canonical();

        $this->set_cache("permalink", $permalink);

        if ($permalink) {
            return $permalink;
        }

        return '';
    }

    /**
     * Gets the permalink from the indexable or generates it if dynamic permalinks are enabled.
     *
     * @param int $shift
     * @return string The permalink.
     */
    public function get_permalink($shift = 0)
    {
        return $this->template->get_permalink($shift);
    }

    /**
     * Generates the rel prev.
     *
     * @return string The rel prev value.
     */
    public function generate_rel_prev()
    {
        return $this->template->generate_rel_prev();
    }

    /**
     * Generates the rel next.
     *
     * @return string The rel prev next.
     */
    public function generate_rel_next()
    {
        return $this->template->generate_rel_next();
    }

    /**
     * @return OpenGraph
     */
    public function openGraph()
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
        $og->url($this->template->get_paged_permalink());
        $og->locale();
        $og->siteName(get_bloginfo('name'));

        return $this->template->openGraph($og);
    }

    public function generate_title($title = '')
    {
        if (($_title = $this->get_cache("title")) !== false) {
            return $_title;
        }

        $title = $this->template->generate_title($title);

        $this->set_cache("title", $title);

        return $title;
    }

    public function get_snippet_image($size = 'thumbnail', $use_default = true)
    {
        if (($_image = $this->get_cache("snippet_image")) !== false) {
            return $_image;
        }

        $snippet_data = $this->template->get_snippet_image($size, $use_default);

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

        $description = $this->template->get_description($description);

        $description = strip_tags($description);

        $description = strip_shortcodes($description);

        $description = esc_attr($description);

        $description = trim($description);

        $this->set_cache("description", $description);

        return $description;
    }

    public function twitterCard()
    {
        $tc = new TwitterCard();

        if (shzn('wpfs')->settings->get("seo.social.twitter.card", false)) {

            $tc->add_card(shzn('wpfs')->settings->get("seo.social.twitter.large_images", true) ? 'summary_large_image' : 'summary');

            $image_metadata = $this->get_snippet_image(['medium', 'large', 'full'], true);

            if ($image_metadata and $image_metadata['url']) {
                $tc->add_image($image_metadata['url']);
            }

            $tc->add_title($this->generate_title());

            $tc->add_description($this->get_description());

            preg_match('#(https?://twitter\.com/)?(?<name>[^?]+)(\??.*)#i', shzn('wpfs')->settings->get("seo.social.twitter.url", ''), $m);

            if (!isset($m['name'])) {
                return $tc;
            }

            $twitterName = '@' . trim($m['name'], ' @');

            $tc->add_creator($twitterName);
            $tc->add_site($twitterName);
        }

        return $this->template->twitterCard($tc);
    }
}