<?php
/**
 * @author    sh1zen
 * @copyright Copyright (C) 2023.
 * @license   http://www.gnu.org/licenses/gpl.html GNU/GPL
 */

namespace FlexySEO\Engine\Generators\Templates;

use FlexySEO\Engine\Default_Generator;
use FlexySEO\Engine\Generators\OpenGraph;
use FlexySEO\Engine\Helpers\CurrentPage;

class E404_Generator extends Default_Generator
{
    /**
     * @param CurrentPage $current_page
     */
    public function __construct($current_page)
    {
        parent::__construct($current_page);

        $this->settings_path = "seo.E404.";
    }

    /**
     * Generates the robots value.
     *
     * @param array $robots
     * @return array The robots value.
     */
    public function get_robots($robots = [])
    {
        return [
            'index'             => 'noindex',
            'follow'            => 'nofollow',
            'max-snippet'       => 'max-snippet:-1',
            'max-image-preview' => 'max-image-preview:large',
        ];
    }

    /**
     * Generates the meta keywords.
     *
     * @param string $keywords
     * @return array The meta keywords.
     */
    public function get_keywords(string $keywords = '')
    {
        return [];
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
     * Generates the title structure.
     *
     * @param string $description
     * @return string The meta description.
     */
    public function get_description(string $description = '')
    {
        return '';
    }

}