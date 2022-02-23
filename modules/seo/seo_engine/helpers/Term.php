<?php
/**
 * @author    sh1zen
 * @copyright Copyright (C)  2022
 * @license   http://www.gnu.org/licenses/gpl.html GNU/GPL
 */

namespace FlexySEO\Engine\Helpers;

class Term
{
    public $term;

    public function __construct($term)
    {
        if ($term instanceof \WP_Term)
            $this->term = $term;
        else
            $this->term = get_term($term);
    }
}