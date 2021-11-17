<?php

namespace FlexySEO\Engine\Generators\Schema\Graphs;

use FlexySEO\Engine\Generator;

/**
 * WebPage graph class.
 *
 * @since 1.2.0
 */
class WebPage extends Graph
{
    /**
     * The graph type.
     *
     * This value can be overridden by WebPage child graphs that are more specific.
     *
     * @since 1.2.0
     *
     * @var string
     */
    protected $type = 'WebPage';

    /**
     * Returns the graph data.
     * @return array $data The graph data.
     * @since 1.2.0
     *
     */
    public function get($type = '')
    {
        if (!empty($type)) {
            $this->type = $type;
        }

        $homeUrl = shzn()->utility->home_url;

        if (!$this->generator) {
            return [];
        }

        $url = $this->generator->get_permalink();

        $data = [
            '@type'       => $this->type,
            '@id'         => $url . '#' . strtolower($this->type),
            'url'         => $url,
            'name'        => $this->generator->generate_title(),
            'description' => $this->generator->get_description(),
            'inLanguage'  => wpfseo()->language->currentLanguageCodeBCP47(),
            'isPartOf'    => ['@id' => $homeUrl . '#website'],
            'breadcrumb'  => ['@id' => $url . '#breadcrumblist']
        ];

        $queried_object = wpfseo()->currentPage->get_queried_object();

        if ($queried_object) {

            if (is_singular() and !is_page()) {


                $author = get_author_posts_url($queried_object->post_author);
                if (!empty($author)) {
                    $data['author'] = $author . '#author';
                    $data['creator'] = $author . '#author';
                }
            }

            if (is_singular()) {

                if (has_post_thumbnail($queried_object)) {
                    $image = $this->image(get_post_thumbnail_id(), 'mainImage');
                    if ($image) {
                        $data['image'] = $image;
                        $data['primaryImageOfPage'] = [
                            '@id' => $url . '#mainImage'
                        ];
                    }
                }

                $data['datePublished'] = mysql2date(DATE_W3C, $queried_object->post_date_gmt, false);
                $data['dateModified'] = mysql2date(DATE_W3C, $queried_object->post_modified_gmt, false);
                return $data;
            }
        }

        if (is_front_page()) {
            $data['about'] = ['@id' => shzn()->utility->home_url . '#' . (shzn('wpfs')->settings->get('seo.schema.organization', false) ? 'organization' : 'person')];
        }

        return $data;
    }
}