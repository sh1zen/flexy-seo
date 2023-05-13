<?php
/**
 * @author    sh1zen
 * @copyright Copyright (C) 2023.
 * @license   http://www.gnu.org/licenses/gpl.html GNU/GPL
 */

namespace FlexySEO\Engine\Generators\Templates;

use FlexySEO\Engine\Default_Generator;
use FlexySEO\Engine\Helpers\CurrentPage;

class E404_Generator extends Default_Generator
{
    public function __construct(CurrentPage $current_page)
    {
        parent::__construct($current_page);

        $this->settings_path = "seo.E404.";
    }

    /**
     * Generates the robots value.
     */
    public function get_robots(): array
    {
        return [
            'index'  => 'noindex',
            'follow' => 'nofollow'
        ];
    }

    public function get_keywords(): string
    {
        return '';
    }

    /**
     * Generates the rel prev.
     */
    public function generate_rel_prev(): string
    {
        return '';
    }

    /**
     * Generates the rel next.
     */
    public function generate_rel_next(): string
    {
        return '';
    }

    /**
     * Generates the description structure.
     */
    public function get_description(): string
    {
        return '';
    }
}