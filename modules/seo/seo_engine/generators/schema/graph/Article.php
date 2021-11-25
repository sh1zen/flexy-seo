<?php

namespace FlexySEO\Engine\Generators\Schema\Graphs;

/**
 * Article graph class.
 *
 * @since 1.2.0
 */
class Article extends Graph
{
    /**
     * Returns the graph data.
     * @return array The graph data.
     *
     * @since 1.2.0
     */
    public function get($type = '')
    {
        // Get all terms that the post is assigned to.
        $post = wpfseo()->currentPage->get_post();
        $postTaxonomies = get_post_taxonomies($post);
        $postTerms = [];
        foreach ($postTaxonomies as $taxonomy) {
            $terms = get_the_terms($post, $taxonomy);
            if ($terms) {
                $postTerms = array_merge($postTerms, wp_list_pluck($terms, 'name'));
            }
        }

        $url = $this->generator->get_permalink();

        $data = [
            '@type'            => 'Article',
            '@id'              => $url . '#article',
            'name'             => $this->generator->generate_title(),
            'description'      => $this->generator->get_description(),
            'inLanguage'       => wpfseo()->language->currentLanguageCodeBCP47(),
            'headline'         => $post->post_title,
            'author'           => ['@id' => get_author_posts_url($post->post_author) . '#author'],
            'publisher'        => ['@id' => shzn()->utility->home_url . '#' . (shzn('wpfs')->settings->get('seo.schema.organization.is', false) ? 'organization' : 'person')],
            'datePublished'    => mysql2date(DATE_W3C, $post->post_date_gmt, false),
            'dateModified'     => mysql2date(DATE_W3C, $post->post_modified_gmt, false),
            'commentCount'     => get_comment_count($post->ID)['approved'],
            'articleSection'   => implode(', ', $postTerms),
            'mainEntityOfPage' => ['@id' => $url . '#webpage'],
            'isPartOf'         => ['@id' => $url . '#webpage'],
        ];

        $pageNumber = $this->generator->get_page_number();
        if (1 < $pageNumber) {
            $data['pagination'] = $pageNumber;
        }

        $image = $this->postImage($post);
        if (!empty($image)) {
            $data['image'] = $image;
        }
        return $data;
    }

    /**
     * Returns the graph data for the post image.
     *
     * @param \WP_Post $post The post object.
     * @return array         The image graph data.
     * @since 1.2.0
     *
     */
    private function postImage($post)
    {
        if (has_post_thumbnail($post)) {
            return $this->image(get_post_thumbnail_id(), 'articleImage');
        }

        $images = wpfseo()->images->get_images_from_content($post->post_content);

        if ($images) {
            $image = wpfseo()->images->removeImageDimensions($images[0]);
            return $this->image($image, 'articleImage');
        }

        if (shzn('wpfs')->settings->get('seo.schema.organization.is', false)) {

            $logo = (new Organization($this->generator))->logo();
            if (!empty($logo)) {
                $logo['@id'] = shzn()->utility->home_url . '#articleImage';
                return $logo;
            }
        }
        else {
            $avatar = $this->avatar($post->post_author, 'articleImage');
            if ($avatar) {
                return $avatar;
            }
        }

        $imageId = wpfseo()->images->getSiteLogoId();

        if ($imageId) {
            return $this->image($imageId, 'articleImage');
        }

        return [];
    }
}