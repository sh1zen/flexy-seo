<?php
/**
 * @author    sh1zen
 * @copyright Copyright (C) 2025.
 * @license   http://www.gnu.org/licenses/gpl.html GNU/GPL
 */

namespace FlexySEO\Engine\Generators\Templates;

use FlexySEO\Engine\Default_Generator;
use FlexySEO\Engine\Helpers\CurrentPage;

class Home_Generator extends Default_Generator
{
    /**
     * @param CurrentPage $current_page
     */
    public function __construct(CurrentPage $current_page)
    {
        parent::__construct($current_page);

        $this->settings_path = "seo.home.";
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
}