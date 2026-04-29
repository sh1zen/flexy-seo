<?php
/**
 * @author    sh1zen
 * @copyright Copyright (C) 2025.
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

        $url = $this->generator->generate_canonical() ?: $this->generator->get_permalink();

        $graph = new GraphBuilder(
            'BreadcrumbList',
            $url . '#breadcrumb'
        );

        $position = 1;

        foreach ($breadcrumbs as $breadcrumb) {
            $value = $breadcrumb['value'] ?? [];

            if (!empty($value['list'])) {
                foreach ($value as $link) {
                    if (!is_array($link) || empty($link['text']) || !is_string($link['text'])) {
                        continue;
                    }

                    $graph->add('itemListElement', [
                        '@type'    => 'ListItem',
                        'position' => $position++,
                        'name'     => $link['text'],
                        'item'     => $link['url'] ?? ''
                    ]);
                }

                continue;
            }

            if (empty($value['text']) || !is_string($value['text'])) {
                continue;
            }

            $graph->add('itemListElement', [
                '@type'    => 'ListItem',
                'position' => $position++,
                'name'     => $value['text'],
                'item'     => $value['url'] ?? ''
            ]);
        }

        return $graph;
    }
}
