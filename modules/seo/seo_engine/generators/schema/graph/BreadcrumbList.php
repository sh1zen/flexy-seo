<?php
/**
 * @author    sh1zen
 * @copyright Copyright (C)  2022
 * @license   http://www.gnu.org/licenses/gpl.html GNU/GPL
 */

namespace FlexySEO\Engine\Generators\Schema\Graphs;

/**
 * BreadcrumbList graph class.
 *
 * @since 1.2.0
 */
class BreadcrumbList extends Graph
{
    /**
     * Returns the graph data.
     * @param \WP_Post $post
     * @param string $type
     * @return array $data The graph data.
     *
     * @since 1.2.0
     */
    public function get($post, string $type = '')
    {
        $breadcrumbs = \WPFS_Breadcrumb::export();

        if (empty($breadcrumbs)) {
            return [];
        }

        $url = $this->generator->get_permalink();

        $data = [
            '@type'           => 'BreadcrumbList',
            '@id'             => $url . '#breadcrumblist',
            'itemListElement' => []
        ];

        $trailLength = count($breadcrumbs);

        foreach ($breadcrumbs as $key => $breadcrumb) {

            $position = $key + 1;

            $listItem = [
                '@type'    => 'ListItem',
                'position' => $position,
                'item'     => [
                    '@id'         => $breadcrumb['value']['url'],
                    'name'        => empty($breadcrumb['value']['text']) ? '' : $breadcrumb['value']['text'],
                    'description' => $position === $trailLength ? $this->generator->get_description() : (empty($breadcrumb['value']['description']) ? '' : $breadcrumb['value']['description']),
                    'url'         => $breadcrumb['value']['url'],
                ]
            ];

            if ($trailLength > $position) {
                $listItem['nextItem'] = $breadcrumbs[$position]['value']['url'] . '#listItem';
            }

            if (1 < $position) {
                $listItem['previousItem'] = $breadcrumbs[$position - 2]['value']['url'] . '#listItem';
            }

            $data['itemListElement'][] = $listItem;
        }
        return $data;
    }
}