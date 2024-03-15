<?php
/**
 * @author    sh1zen
 * @copyright Copyright (C) 2024.
 * @license   http://www.gnu.org/licenses/gpl.html GNU/GPL
 */

namespace FlexySEO\Engine\Generators\Schema\Graphs;

use FlexySEO\Engine\Generators\GraphBuilder;
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
     * @return \FlexySEO\Engine\Generators\GraphBuilder $data The graph data.
     */
    public function get(CurrentPage $currentPage, string $type = '', ...$args): GraphBuilder
    {
        if (!$currentPage->get_queried_object()->post_author) {
            return new GraphBuilder();
        }

        return Person::build($currentPage->get_queried_object()->post_author);
    }
}