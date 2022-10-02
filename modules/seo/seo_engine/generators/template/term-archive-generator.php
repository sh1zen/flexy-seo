<?php
/**
 * @author    sh1zen
 * @copyright Copyright (C)  2022
 * @license   http://www.gnu.org/licenses/gpl.html GNU/GPL
 */

namespace FlexySEO\Engine\Generators\Templates;

use FlexySEO\Engine\Default_Generator;
use FlexySEO\Engine\Generators\OpenGraph;
use FlexySEO\Engine\Helpers\CurrentPage;

class TermArchive_Generator extends Default_Generator
{
    /**
     * @param CurrentPage $current_page
     */
    public function __construct($current_page)
    {
        parent::__construct($current_page);

        $type = $this->current_page->get_queried_object()->taxonomy;

        if (!$type) {
            $type = 'none';
        }

        $this->settings_path = "seo.tax.{$type}.";
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
        $value = get_metadata_raw('term', wpfseo('helpers')->term->term->term_id, 'wpfs_metaterm_title', true);

        $value = apply_filters('wpfs_term_meta_title', $value, $title);

        if (!empty($value)) {
            return $value;
        }

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
        $value = get_metadata_raw('term', wpfseo('helpers')->term->term->term_id, 'wpfs_metaterm_description', true);

        $value = apply_filters('wpfs_term_meta_description', $value, $description);

        if (!empty($value)) {
            return $value;
        }

        return parent::get_description(shzn('wpfs')->settings->get($this->settings_path . 'meta_desc', $description));
    }
}