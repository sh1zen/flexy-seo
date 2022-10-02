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
        $this->post = shzn_get_post($post);
    }

    public static function get_url($post)
    {
        global $pagenow;

        $post = shzn_get_post($post);

        if (!$post) {
            return false;
        }

        $url = false;

        $file = get_post_meta($post->ID, '_wp_attached_file', true);

        if ($file) {
            // Get upload directory.
            $uploads = wp_get_upload_dir();
            if ($uploads && false === $uploads['error']) {
                // Check that the upload base exists in the file location.
                if (str_starts_with($file, $uploads['basedir'])) {
                    // Replace file location with url location.
                    $url = str_replace($uploads['basedir'], $uploads['baseurl'], $file);
                }
                elseif (str_contains($file, 'wp-content/uploads')) {
                    // Get the directory name relative to the basedir (back compat for pre-2.7 uploads).
                    $url = trailingslashit($uploads['baseurl'] . '/' . _wp_get_attachment_relative_path($file)) . wp_basename($file);
                }
                else {
                    // It's a newly-uploaded file, therefore $file is relative to the basedir.
                    $url = $uploads['baseurl'] . "/$file";
                }
            }
        }

        /*
         * If any of the above options failed, Fallback on the GUID as used pre-2.7,
         * not recommended to rely upon this.
         */
        if (!$url) {
            $url = get_the_guid($post->ID);
        }

        // On SSL front end, URLs should be HTTPS.
        if (is_ssl() && !is_admin() && 'wp-login.php' !== $pagenow) {
            $url = set_url_scheme($url);
        }

        return $url;
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
        return self::mainImage($post ?: $this->post, $size, $useContent);
    }

    /**
     * Gets the post's first usable content image. Null if none is available.
     * @param \WP_Post|null $post
     * @param string|array $size
     * @param bool $useContent
     * @return array|mixed|string
     */
    public static function mainImage($post, $size = 'large', $useContent = true)
    {
        $post = shzn_get_post($post);

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

            $image = Images::get_image($mediaID, $size);

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

    public function get_thumbnail()
    {
        if (has_post_thumbnail($this->post)) {
            return get_post_thumbnail_id($this->post);
        }

        return false;
    }
}