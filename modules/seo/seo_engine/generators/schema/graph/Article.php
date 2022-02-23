<?php
/**
 * @author    sh1zen
 * @copyright Copyright (C)  2022
 * @license   http://www.gnu.org/licenses/gpl.html GNU/GPL
 */

namespace FlexySEO\Engine\Generators\Schema\Graphs;

use FlexySEO\Engine\Generators\CommonGraphs;
use FlexySEO\Engine\Generators\GraphBuilder;
use FlexySEO\Engine\Helpers\CurrentPage;


/**
 * Article graph class.
 */
class Article extends Graph
{
    /**
     * Returns the graph data.
     * @param \FlexySEO\Engine\Helpers\CurrentPage $currentPage
     * @param string $type
     * @param ...$args
     * @return GraphBuilder The graph data.
     */
    public function get(CurrentPage $currentPage, string $type = 'Article', ...$args)
    {
        $post = $currentPage->get_queried_object();

        if (!$post->post_author or $post->post_type === 'page') {
            return new GraphBuilder([]);
        }

        // Get all terms that the post is assigned to.
        $postTerms = [];
        foreach (get_post_taxonomies($post) as $taxonomy) {
            $terms = get_the_terms($post, $taxonomy);
            if ($terms) {
                $postTerms = array_merge($postTerms, wp_list_pluck($terms, 'name'));
            }
        }

        $url = $this->generator->get_permalink();
        $articleID = "{$url}#" . strtolower($type);

        $schema = new GraphBuilder([
            '@type'            => $type,
            '@id'              => $articleID,
            'name'             => $this->generator->generate_title(),
            'description'      => $this->generator->get_description(),
            'inLanguage'       => wpfseo()->language->currentLanguageCodeBCP47(),
            'headline'         => apply_filters('wpfs_post_title', $this->generator->generate_title(), $post),
            'author'           => [
                '@id' => Person::getSchemaID($post->post_author),
            ],
            'datePublished'    => mysql2date(DATE_W3C, $post->post_date_gmt, false),
            'dateModified'     => mysql2date(DATE_W3C, $post->post_modified_gmt, false),
            'articleSection'   => implode(', ', $postTerms),
            'isPartOf'         => [
                '@id' => $articleID
            ],
            "mainEntityOfPage" => [
                '@id' => $articleID
            ],
            'wordCount'        => preg_match_all('~\s+~', "{$post->post_content} "),
            'commentCount'     => wp_count_comments($post->ID)->approved,
        ]);

        if (shzn('wpfs')->settings->get("seo.schema.organization.is", false)) {
            $schema->set(
                'publisher',
                ['@id' => Organization::getSchemaID()]
            );
        }

        $pageNumber = $currentPage->get_page_number();

        if (1 < $pageNumber) {
            $schema->set('pagination', $pageNumber);
        }

        list($imageId, $imageUrl) = wpfseo('helpers')->post->get_first_usable_image('large', false, $post);

        if ($imageUrl) {
            $schema->set('thumbnailUrl', $imageUrl);
        }

        return $schema;
    }
}