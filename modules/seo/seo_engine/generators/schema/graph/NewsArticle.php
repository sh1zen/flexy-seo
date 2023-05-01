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
 * News Article graph class.
 */
class NewsArticle extends Article
{
    /**
     * Returns the graph data.
     * @param \FlexySEO\Engine\Helpers\CurrentPage $currentPage
     * @param string $type
     * @param mixed ...$args
     * @return GraphBuilder The graph data.
     */
    public function get(CurrentPage $currentPage, string $type = '', ...$args)
    {
        $schema = parent::get($currentPage, $type, $args);

        // Translators: 1 - The date the article was published on.
        $schema->set('dateline', sprintf(__('Published on %1$s.', 'wpfs'), get_post_time('F j, Y', false, $currentPage->get_queried_object(), true)));

        return $schema;
    }
}