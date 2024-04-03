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
     * @return array
     */
    public function get_first_usable_image($size = 'large', bool $useContent = true, $post = null): array
    {
        return wps_get_mainImage($post ?: $this->post, $size, $useContent);
    }
}