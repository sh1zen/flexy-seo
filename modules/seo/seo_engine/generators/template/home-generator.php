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

class Home_Generator extends Default_Generator
{
    /**
     * @param CurrentPage $current_page
     */
    public function __construct($current_page)
    {
        parent::__construct($current_page);

        $this->settings_path = "seo.home.";
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
     * Generates the rel prev.
     *
     * @return string The rel prev value.
     */
    public function generate_rel_prev()
    {
        return '';
    }

    /**
     * Generates the rel next.
     *
     * @return string The rel prev next.
     */
    public function generate_rel_next()
    {
        return '';
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
        return parent::get_description(shzn('wpfs')->settings->get($this->settings_path . 'meta_desc', ''));
    }
}