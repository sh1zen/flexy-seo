<?php

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
    protected $type = 'RealEstateListing';

    /**
     * Returns the graph data.
     * @return array $data The graph data.
     * @since 1.2.0
     */
    public function get($type = '')
    {
        $data = parent::get();
        $post = wpfseo()->currentPage->get_post();
        if (!$post) {
            return $data;
        }

        $data['datePosted'] = mysql2date(DATE_W3C, $post->post_date_gmt, false);
        return $data;
    }
}