<?php

namespace FlexySEO\Engine\Generators\Schema\Graphs;

/**
 * News Article graph class.
 *
 * @since 1.2.0
 */
class NewsArticle extends Article
{
    /**
     * Returns the graph data.
     * @return array The graph data.
     *
     * @since 1.2.0
     */
    public function get($type = '')
    {
        $data = parent::get();
        if (!$data) {
            return [];
        }

        $data['@type'] = 'NewsArticle';
        $data['@id'] = $this->generator->get_permalink() . '#newsarticle';
        // Translators: 1 - The date the article was published on.
        $data['dateline'] = sprintf(__('Published on %1$s.', 'wpfs'), get_the_date('F j, Y'));
        return $data;
    }
}