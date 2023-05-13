<?php
/**
 * @author    sh1zen
 * @copyright Copyright (C) 2023.
 * @license   http://www.gnu.org/licenses/gpl.html GNU/GPL
 */

namespace FlexySEO\Engine\Generators\Schema\Graphs;

use FlexySEO\Engine\Generators\GraphBuilder;
use FlexySEO\Engine\Helpers\CurrentPage;

/**
 * BreadcrumbList graph class.
 */
class BreadcrumbList extends Graph
{
    /**
     * Returns the graph data.
     * @param \FlexySEO\Engine\Helpers\CurrentPage $currentPage
     * @param string $type
     * @param ...$args
     * @return GraphBuilder $data The graph data.
     */
    public function get(CurrentPage $currentPage, string $type = '', ...$args): GraphBuilder
    {
        $breadcrumbs = \WPFS_Breadcrumb::export();

        if (empty($breadcrumbs)) {
            return new GraphBuilder();
        }

        $graph = new GraphBuilder(
            'BreadcrumbList',
            $this->generator->get_permalink() . '#breadcrumb'
        );

        foreach ($breadcrumbs as $key => $breadcrumb) {

            $graph->add('itemListElement', [
                '@type'    => 'ListItem',
                'position' => $key + 1,
                'name'     => $breadcrumb['value']['text'],
                'item'     => $breadcrumb['value']['url']
            ]);
        }

        return $graph;
    }
}