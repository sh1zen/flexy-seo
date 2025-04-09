<?php
/**
 * @author    sh1zen
 * @copyright Copyright (C) 2025.
 * @license   http://www.gnu.org/licenses/gpl.html GNU/GPL
 */

namespace FlexySEO\Engine\Helpers;

use WPS\core\TextReplacer;
use WPS\core\StringHelper;

class Term
{
    public ?\WP_Term $term;

    public function __construct($term)
    {
        $this->term = wps_get_term($term);
    }

    /**
     * Get the WordPress Term description
     */
    public function get_description($term = null, $default = ''): string
    {
        $term = wps_get_term($term ?: $this->term);

        if (!$term) {
            return $default;
        }

        $description = TextReplacer::replace(
            $term->description,
            $term,
            'term'
        );

        if (empty($description)) {
            $description = $default;
        }

        return StringHelper::escape_text($description);
    }
}