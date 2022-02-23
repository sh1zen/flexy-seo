<?php
/**
 * @author    sh1zen
 * @copyright Copyright (C)  2022
 * @license   http://www.gnu.org/licenses/gpl.html GNU/GPL
 */

namespace FlexySEO\Engine\Generators\Schema\Graphs;

use FlexySEO\Engine\Helpers\CurrentPage;

/**
 * Person Author graph class.
 *
 * This a secondary Person graph for post authors and BuddyPress profile pages.
 */
class PersonAuthor extends Person
{
    /**
     * Returns the graph data.
     * @param \FlexySEO\Engine\Helpers\CurrentPage $currentPage
     * @param string $type
     * @param ...$args
     * @return array $data The graph data.
     */
    public function get(CurrentPage $currentPage, string $type = '', ...$args)
    {
        if (!$currentPage->get_queried_object()->post_author) {
            return [];
        }

        return Person::build($currentPage->get_queried_object()->post_author);
    }
}