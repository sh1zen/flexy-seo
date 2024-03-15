<?php
/**
 * @author    sh1zen
 * @copyright Copyright (C) 2024.
 * @license   http://www.gnu.org/licenses/gpl.html GNU/GPL
 */

namespace FlexySEO\Engine\Helpers;

class Post
{
    public ?\WP_Post $post;

    public function __construct($post)
    {
        $this->post = wps_get_post($post);
    }

    /**
     * Gets the post's first usable content image. Null if none is available.
     * @param string|array $size
     * @param bool $useContent
     * @param null $post
     * @return array|mixed|string
     */
    public function get_first_usable_image($size = 'large', bool $useContent = true, $post = null)
    {
        return self::mainImage($post ?: $this->post, $size, $useContent);
    }

    /**
     * Gets the post's first usable content image. Null if none is available.
     * @param \WP_Post|null $post
     * @param string|array $size
     * @param bool $useContent
     * @return array|mixed|string
     */
    public static function mainImage($post, $size = 'large', bool $useContent = true)
    {
        $post = wps_get_post($post);

        if (is_string($size)) {
            $mainImage = wps('wpfs')->options->get($post->ID, "mainImage-$size", "cache", false);

            if ($mainImage) {
                return $mainImage;
            }
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

            $image = Images::get_image($mediaID, $size);

            $mediaURL = $image ? $image['url'] : '';
        }
        elseif ($useContent) {

            $images = wpfseo('helpers')->images->get_images_from_content($post->post_content);

            if ($images) {
                $mediaURL = wpfseo('helpers')->images->removeImageDimensions($images[0]);
            }
        }

        if (is_string($size)) {
            wps('wpfs')->options->add($post->ID, "mainImage-$size", [$mediaID, $mediaURL], "cache", MONTH_IN_SECONDS);
        }

        return [$mediaID, $mediaURL];
    }
}