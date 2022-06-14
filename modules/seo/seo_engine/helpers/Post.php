<?php
/**
 * @author    sh1zen
 * @copyright Copyright (C)  2022
 * @license   http://www.gnu.org/licenses/gpl.html GNU/GPL
 */

namespace FlexySEO\Engine\Helpers;

class Post
{
    public \WP_Post|null $post;

    public function __construct($post)
    {
        if ($post instanceof \WP_Post) {
            $this->post = $post;
        }
        else {
            $this->post = get_post($post);
        }
    }

    /**
     * Gets the post's first usable content image. Null if none is available.
     * @param string|array $size
     * @param bool $useContent
     * @param null $post
     * @return array|mixed|string
     */
    public function get_first_usable_image($size = 'large', $useContent = true, $post = null)
    {
        if ($post) {
            $post = get_post($post);
        }
        else {
            $post = get_post($this->post);
        }

        $mainImage = shzn('wpfs')->options->get($post->ID, "mainImage", "cache", false);

        if ($mainImage) {
            return $mainImage;
        }

        $mediaURL = '';
        $mediaID = get_post_thumbnail_id($post->ID);

        if (!$mediaID) {

            $images = new \WP_Query(array(
                'post_parent'            => $post->ID,
                'post_type'              => 'attachment',
                'post_mime_type'         => 'image',
                'order'                  => 'ASC',
                'orderby'                => 'menu_order',
                'post_status'            => 'inherit',
                'no_found_rows'          => true,
                'cache_results'          => false,
                'update_post_term_cache' => false,
                'update_post_meta_cache' => false,
                'posts_per_page'         => 1,
                'fields'                 => 'ids'
            ));

            if (empty($images->posts)) {
                $mediaID = 0;
            }
            else {
                $mediaID = $images->posts[0];
            }

            unset($images);
        }

        if ($mediaID) {

            $image = wpfseo('helpers')->images->get_image($mediaID, $size);

            $mediaURL = $image ? $image['url'] : '';
        }
        elseif ($useContent) {

            $images = wpfseo()->images->get_images_from_content($post->post_content);

            if ($images) {
                $mediaURL = wpfseo()->images->removeImageDimensions($images[0]);
            }
        }

        shzn('wpfs')->options->add($post->ID, "mainImage", [$mediaID, $mediaURL], "cache", WEEK_IN_SECONDS);

        return [$mediaID, $mediaURL];
    }

    /**
     * Retrieves images from the post content.
     *
     * @return array An array of images found in this post.
     */
    public function get_images_from_content()
    {
        return wpfseo()->images->get_images_from_content($this->post->post_content);
    }

    public function get_thumbnail()
    {
        if (has_post_thumbnail($this->post)) {
            return get_post_thumbnail_id($this->post);
        }

        return false;
    }


}