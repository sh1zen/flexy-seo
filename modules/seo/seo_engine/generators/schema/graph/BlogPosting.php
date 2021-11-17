<?php

namespace FlexySEO\Engine\Generators\Schema\Graphs;

/**
 * Blog Posting graph class.
 *
 * @since 1.2.0
 */
class BlogPosting extends Article
{
    /**
     * Returns the graph data.
     * @return array The graph data.
     * @since 1.2.0
     *
     */
    public function get($type = '')
    {
        $data = parent::get();

        if (!$data) {
            return [];
        }

        $data['@type'] = 'BlogPosting';
        $data['@id'] = $this->generator->get_permalink() . '#blogposting';
        return $data;
    }
}