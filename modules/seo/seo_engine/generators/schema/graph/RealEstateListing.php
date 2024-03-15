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
 * RealEstateListing graph class.
 */
class RealEstateListing extends CollectionPage
{
    /**
     * The graph type.
     */
    protected string $type = 'RealEstateListing';

    /**
     * Returns the graph data.
     * @param \FlexySEO\Engine\Helpers\CurrentPage $currentPage
     * @param string $type
     * @param mixed ...$args
     * @return GraphBuilder $data The graph data.
     */
    public function get(CurrentPage $currentPage, string $type = '', ...$args): GraphBuilder
    {
        $schema = parent::get($currentPage, $type, $args);

        if (!$currentPage->get_queried_object() instanceof \WP_Post) {
            return $schema;
        }

        $schema->set('datePosted', mysql2date(DATE_W3C, $currentPage->get_queried_object()->post_date_gmt, false));

        return $schema;
    }
}