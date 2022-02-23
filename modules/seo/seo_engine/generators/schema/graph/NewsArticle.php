<?php
/**
 * @author    sh1zen
 * @copyright Copyright (C)  2022
 * @license   http://www.gnu.org/licenses/gpl.html GNU/GPL
 */

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
     * @param \WP_Post $post
     * @param string $type
     * @return array The graph data.
     *
     * @since 1.2.0
     */
    public function get($post, string $type = '')
    {
        $data = parent::get($post, $type);

        if (!$data) {
            return [];
        }

        // Translators: 1 - The date the article was published on.
        $data['dateline'] = sprintf(__('Published on %1$s.', 'wpfs'), get_the_date('F j, Y'));
        return $data;
    }
}