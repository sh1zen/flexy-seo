<?php
/**
 * @author    sh1zen
 * @copyright Copyright (C)  2022
 * @license   http://www.gnu.org/licenses/gpl.html GNU/GPL
 */

namespace FlexySEO\Engine\Helpers;

use FlexySEO\Engine\Txt_Replacer;

class Term
{
    public $term;

    public function __construct($term)
    {
        $this->term = shzn_get_term($term);
    }

    public function get_description($term = null, $default = '')
    {
        $term = shzn_get_term($term ?: $this->term);

        if (!$term instanceof \WP_Term) {
            return $default;
        }

        if (($description = $this->get_cache("term_{$term->term_id}")) !== false) {
            return $description;
        }

        $description = Txt_Replacer::replace(
            $term->description,
            $term,
            'term'
        );

        if (empty($description)) {
            $description = $default;
        }

        $this->set_cache("term_{$term->term_id}", $description);

        return $description;
    }

    protected final function get_cache($cacheKey)
    {
        return shzn('wpfs')->cache->get($cacheKey, "term");
    }

    protected final function set_cache($cacheKey, $data)
    {
        return shzn('wpfs')->cache->set($cacheKey, $data, "term", true);
    }
}