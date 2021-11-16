<?php
/**
 * @author    sh1zen
 * @copyright Copyright (C)  2021
 * @license   http://www.gnu.org/licenses/gpl.html GNU/GPL
 */

namespace FlexySEO\Engine\Generators\Templates;

use FlexySEO\Engine\Generator;
use FlexySEO\Engine\Generators\OpenGraph;
use FlexySEO\Engine\Generators\TwitterCard;
use FlexySEO\Engine\Helpers\CurrentPage;
use FlexySEO\Engine\Rewriter;

class AuthorArchive_Generator extends Generator
{
    /**
     * @param CurrentPage $current_page
     */
    public function __construct($current_page)
    {
        parent::__construct($current_page);

        $this->settings_path = "seo.archives.author.";
    }

    public function redirect()
    {
        if (shzn('wpfs')->settings->get($this->settings_path . "active", true)) {
            return parent::redirect();
        }

        $url = home_url('/');

        if (empty($url)) {
            return parent::redirect();
        }

        return array($url, 301);
    }

    /**
     * Generates the robots value.
     *
     * @param array $robots
     * @return array The robots value.
     */
    public function get_robots($robots = [])
    {
        $robots = [
            'index'             => 'noindex',
            'follow'            => 'follow',
            'max-snippet'       => 'max-snippet:-1',
            'max-image-preview' => 'max-image-preview:large',
        ];

        if (shzn('wpfs')->settings->get($this->settings_path . 'show', true) and count_user_posts($this->current_page->get_queried_object_id(), 'post', true) > 0) {
            $robots['index'] = 'index';
        }

        return $robots;
    }

    /**
     * Generates the meta keywords.
     *
     * @param string $keywords
     * @return string The meta keywords.
     */
    public function get_keywords($keywords = '')
    {
        return parent::get_keywords(shzn('wpfs')->settings->get($this->settings_path . 'keywords', ''));
    }

    /**
     * Generates the title structure.
     *
     * @param string $title
     * @return string The title.
     */
    public function generate_title($title = '')
    {
        return parent::generate_title(shzn('wpfs')->settings->get($this->settings_path . 'title', '%%title%%'));
    }

    /**
     * Gets the permalink from the indexable or generates it if dynamic permalinks are enabled.
     *
     * @param int $shift
     * @return string The permalink.
     */
    public function get_permalink($shift = 0)
    {
        $rewriter = Rewriter::get_instance();

        $url = get_author_posts_url($this->current_page->get_queried_object_id());

        $page = max($this->current_page->get('paged', 0), 1) + $shift;

        $max_pages = $this->current_page->get_main_query()->max_num_pages;

        if ($max_pages) {
            if ($page > $max_pages or $page < 1)
                $url = '';
            else
                $url = $rewriter->get_pagenum_link($page);
        }

        return $url;
    }

    /**
     * @return OpenGraph
     */
    public function openGraph()
    {
        $og = parent::openGraph();

        $og->type('profile');

        $og->profile([
            'username' => $this->current_page->get_queried_object()->display_name,
        ]);

        return $og;
    }

    protected function get_snippet_image($size = 'thumbnail')
    {
        $width = 0;
        $height = 0;

        $url = get_avatar_url($this->current_page->get_queried_object_id());

        if (empty($url))
            $url = "https://secure.gravatar.com/avatar/cb5febbf69fa9e85698bac992b2a4433?s=500&d=mm&r=g";

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

        return array('url' => $url, 'width' => $width, 'height' => $height);
    }

    /**
     * @return TwitterCard
     */
    public function twitterCard()
    {
        return parent::twitterCard();
    }

    /**
     * Generates the title structure.
     *
     * @param string $description
     * @return string The meta description.
     */
    public function get_description($description = '')
    {
        return parent::get_description(shzn('wpfs')->settings->get($this->settings_path . 'meta_desc', ''));
    }
}