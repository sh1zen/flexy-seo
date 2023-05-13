<?php
/**
 * @author    sh1zen
 * @copyright Copyright (C) 2023.
 * @license   http://www.gnu.org/licenses/gpl.html GNU/GPL
 */

namespace FlexySEO\Engine\Generators\Schema\Graphs;

use FlexySEO\Engine\Generators\CommonGraphs;
use FlexySEO\Engine\Generators\GraphBuilder;
use FlexySEO\Engine\Helpers\CurrentPage;

/**
 * WebPage graph class.
 */
class WebPage extends Graph
{
    /**
     * The graph type.
     *
     * This value can be overridden by WebPage child graphs that are more specific.
     */
    protected string $type = 'WebPage';

    /**
     * Returns the graph data.
     * @param \FlexySEO\Engine\Helpers\CurrentPage $currentPage
     * @param string $type
     * @param ...$args
     * @return GraphBuilder $data The graph data.
     */
    public function get(CurrentPage $currentPage, string $type = '', ...$args): GraphBuilder
    {
        if (!empty($type)) {
            $this->type = $type;
        }

        $url = $this->generator->get_permalink();

        $schema = new GraphBuilder([
            '@type'           => $this->type,
            '@id'             => $url . '#webpage',
            'url'             => $url,
            'name'            => $this->generator->generate_title(),
            'description'     => $this->generator->get_description(),
            'keywords'        => $this->generator->get_keywords(),
            'inLanguage'      => wpfseo('helpers')->language->currentLanguageCodeBCP47(),
            'isPartOf'        => [
                '@id' => WebSite::getSchemaID()
            ],
            'breadcrumb'      => [
                '@id' => $url . '#breadcrumb'
            ],
            "potentialAction" => [
                [
                    "@type"  => "ReadAction",
                    "target" => [$url]
                ]
            ]
        ]);

        if ($currentPage->is_simple_page()) {

            $image = $this->generator->get_snippet_image('full');

            $schema->set('primaryImageOfPage', CommonGraphs::imageObject($image, 'full', $url . '#primaryimage'));

            $post = $currentPage->get_queried_object();

            $schema->set('datePublished', mysql2date(DATE_W3C, $post->post_date_gmt, false));
            $schema->set('dateModified', mysql2date(DATE_W3C, $post->post_modified_gmt, false));

            if (is_page()) {

                if (wps('wpfs')->settings->get("seo.schema.organization.is", false)) {
                    $schema->set(
                        'publisher',
                        ['@id' => Organization::getSchemaID()]
                    );
                }
            }
            else {

                $schema->set(
                    'author',
                    ['@id' => Person::getSchemaID($post->post_author)]
                );

                $schema->set(
                    'creator',
                    ['@id' => Person::getSchemaID($post->post_author)]
                );
            }
        }

        return $schema;
    }
}