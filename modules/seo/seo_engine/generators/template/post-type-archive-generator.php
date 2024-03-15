<?php
/**
 * @author    sh1zen
 * @copyright Copyright (C) 2024.
 * @license   http://www.gnu.org/licenses/gpl.html GNU/GPL
 */

namespace FlexySEO\Engine\Generators\Templates;

use FlexySEO\Engine\Default_Generator;
use FlexySEO\Engine\Helpers\CurrentPage;

class PostTypeArchive_Generator extends Default_Generator
{
    public function __construct(CurrentPage $current_page)
    {
        parent::__construct($current_page);

        $post_type = $current_page->get_queried_post_type();

        if (!$post_type) {
            $post_type = 'none';
        }

        $this->settings_path = "seo.archives.$post_type.";
    }

    public function redirect(): array
    {
        if (wps('wpfs')->settings->get($this->settings_path . "active", true)) {
            return parent::redirect();
        }

        return array(home_url('/'), 301);
    }

    /**
     * Generates the robots value.
     *
     * @return array The robots value.
     */
    public function get_robots(): array
    {
        return [
            'index' => wps('wpfs')->settings->get($this->settings_path . 'show', true) ? 'index' : 'noindex'
        ];
    }
}