<?php
/**
 * @author    sh1zen
 * @copyright Copyright (C) 2025.
 * @license   http://www.gnu.org/licenses/gpl.html GNU/GPL
 */

namespace FlexySEO\Engine\Generators\Schema\Graphs;

use FlexySEO\Engine\Generators\CommonGraphs;
use FlexySEO\Engine\Generators\GraphBuilder;
use FlexySEO\Engine\Helpers\CurrentPage;

/**
 * News Article graph class.
 */
class CollectionPage extends WebPage
{
    /**
     * Returns the graph data.
     * @param \FlexySEO\Engine\Helpers\CurrentPage $currentPage
     * @param string $type
     * @param mixed ...$args
     * @return GraphBuilder The graph data.
     */
    public function get(CurrentPage $currentPage, string $type = '', ...$args): GraphBuilder
    {
        $schema = parent::get($currentPage, $type, $args);

        $schema->set('primaryImageOfPage', CommonGraphs::imageObject($this->generator->get_snippet_image('full'), 'full', $schema->get('url') . '#primaryimage'));

        if ($currentPage->get_queried_object()) {
            $schema->set('lastReviewed', mysql2date(DATE_W3C, $currentPage->get_queried_object()->post_date_gmt, false));
        }

        $schema->set('nextPage', $this->generator->generate_rel_next());
        $schema->set('previousPage', $this->generator->generate_rel_prev());

        if (!$currentPage->is_homepage()) {

            $subSchema = new GraphBuilder('ItemList');
            $index = 0;

            foreach ($currentPage->get_queried_posts() as $index => $post) {
                $subSchema->add('itemListElement', [
                    // todo get for each post type the corresponding type: RealEstateListing per annunci
                    "@type"    => "ListItem",
                    "url"      => get_permalink($post),
                    "position" => $index + 1
                ]);
            }

            $subSchema->set('numberOfItems', $index + 1);

            $schema->set('mainEntity', $subSchema);
        }

        return $schema;
    }
}