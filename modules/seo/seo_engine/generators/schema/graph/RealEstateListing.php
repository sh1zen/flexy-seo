<?php
/**
 * @author    sh1zen
 * @copyright Copyright (C)  2022
 * @license   http://www.gnu.org/licenses/gpl.html GNU/GPL
 */

namespace FlexySEO\Engine\Generators\Schema\Graphs;

/**
 * RealEstateListing graph class.
 *
 * @since 1.2.0
 */
class RealEstateListing extends WebPage
{
    /**
     * The graph type.
     * @var string
     */
    protected string $type = 'RealEstateListing';

    /**
     * Returns the graph data.
     * @param \WP_Post $post
     * @param string $type
     * @return array $data The graph data.
     * @since 1.2.0
     */
    public function get($post, string $type = '')
    {
        $data = parent::get($post, $type);

        if (!$post) {
            return $data;
        }

        $data['datePosted'] = mysql2date(DATE_W3C, $post->post_date_gmt, false);
        return $data;
    }
}