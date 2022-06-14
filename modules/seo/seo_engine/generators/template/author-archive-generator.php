<?php
/**
 * @author    sh1zen
 * @copyright Copyright (C)  2022
 * @license   http://www.gnu.org/licenses/gpl.html GNU/GPL
 */

namespace FlexySEO\Engine\Generators\Templates;

use FlexySEO\Engine\Generator;
use FlexySEO\Engine\Generators\OpenGraph;
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

        if (shzn('wpfs')->settings->get($this->settings_path . 'show', true)) {
            $robots['index'] = 'index';
        }

        return $robots;
    }

    /**
     * Generates the meta keywords.
     *
     * @param string $keywords
     * @return string[] The meta keywords.
     */
    public function get_keywords(string $keywords = '')
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

    /**
     * Generates the title structure.
     *
     * @param string $description
     * @return string The meta description.
     */
    public function get_description(string $description = '')
    {
        return parent::get_description(shzn('wpfs')->settings->get($this->settings_path . 'meta_desc', ''));
    }

    public function get_snippet_image($size = 'thumbnail', $use_default = true)
    {
        return wpfseo('helpers')->get_user_snippet_image($this->current_page->get_queried_object_id(), $size);
    }
}