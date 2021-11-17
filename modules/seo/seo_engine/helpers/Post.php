<?php
/**
 * @author    sh1zen
 * @copyright Copyright (C)  2021
 * @license   http://www.gnu.org/licenses/gpl.html GNU/GPL
 */

namespace FlexySEO\Engine\Helpers;

class Post
{
    public $post;

    public function __construct($post)
    {
        if ($post instanceof \WP_Post)
            $this->post = $post;
        else
            $this->post = get_post($post);
    }

    /**
     * Gets the post's first usable content image. Null if none is available.
     *
     * @param bool $justContent
     * @return string/int
     */
    public static function get_first_usable_image($justContent = false)
    {
        if (!$justContent) {
            $image = wpfseo()->post->get_main_image();

            if ($image) {
                return $image;
            }
        }

        $images = wpfseo()->post->get_images();

        return $images[0];
    }

    /**
     * Retrieves images from the post content.
     *
     * @return array An array of images found in this post.
     */
    public function get_images()
    {
        return wpfseo()->images->get_images_from_content($this->get_post_content());
    }

    /**
     * Retrieves the post content we want to work with.
     *
     * @return string The content of the supplied post.
     */
    public function get_post_content()
    {
        if ($this->post === null) {
            return '';
        }

        /**
         * Filter: 'WPFS_pre_analysis_post_content' - Allow filtering the content before analysis.
         *
         * @api string $post_content The Post content string.
         */
        $content = apply_filters('wpfs_pre_analysis_post_content', $this->post->post_content, $this->post);

        if (!is_string($content)) {
            $content = '';
        }

        return $content;
    }

    public function get_main_image()
    {
        if (has_post_thumbnail($this->post)) {
            return get_post_thumbnail_id();
        }

        return false;
    }


}