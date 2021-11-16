<?php
/**
 * @author    sh1zen
 * @copyright Copyright (C)  2021
 * @license   http://www.gnu.org/licenses/gpl.html GNU/GPL
 */

namespace FlexySEO\Engine\Helpers;


use FlexySEO\core\Cache;

/**
 * Image_Utils.
 */
class Images
{
    /**
     * Find an attachment ID for a given URL.
     *
     * @param string $url The URL to find the attachment for.
     *
     * @return int The found attachment ID, or 0 if none was found.
     */
    public static function get_attachment_by_url($url)
    {
        /*
         * As get_attachment_by_url won't work on resized versions of images,
         * we strip out the size part of an image URL.
         */
        $url = preg_replace('/(.*)-\d+x\d+\.(jpg|png|gif)$/', '$1.$2', $url);

        static $uploads;

        if ($uploads === null) {
            $uploads = wp_get_upload_dir();
        }

        // Don't try to do this for external URLs.
        if (strpos($url, $uploads['baseurl']) !== 0) {
            return 0;
        }

        return self::attachmentUrlToPostId($url);
    }

    /**
     * Implements the attachment_url_to_postid with use of WP Cache.
     *
     * @param string $url The attachment URL for which we want to know the Post ID.
     *
     * @return int The Post ID belonging to the attachment, 0 if not found.
     */
    public static function attachmentUrlToPostId($url)
    {
        if (is_numeric($url))
            return $url;

        $cache_key = sprintf('attachmentUrlToPostId_%s', md5($url));

        // Set the ID based on the hashed URL in the cache.
        $id = shzn('wpfs')->cache->get_cache($cache_key, 'ImageHelper');

        if ($id === 'not_found') {
            return 0;
        }

        // ID is found in cache, return.
        if ($id !== false) {
            return $id;
        }

        // Note: We use the WP COM version if we can, see above.
        $id = attachment_url_to_postid($url);

        if (empty($id)) {
            shzn('wpfs')->cache->set_cache($cache_key, 'not_found', 'ImageHelper', DAY_IN_SECONDS);
            return 0;
        }

        // We have the Post ID, but it's not in the cache yet. We do that here and return.
        shzn('wpfs')->cache->set_cache($cache_key, $id, 'ImageHelper', DAY_IN_SECONDS);

        return $id;
    }

    /**
     * Get the relative path of the image.
     *
     * @param string $img Image URL.
     *
     * @return string The expanded image URL.
     */
    public static function get_relative_path($img)
    {
        if ($img[0] !== '/') {
            return $img;
        }

        // If it's a relative URL, it's relative to the domain, not necessarily to the WordPress install, we
        // want to preserve domain name and URL scheme (http / https) though.
        $parsed_url = wp_parse_url(home_url());
        $img = $parsed_url['scheme'] . '://' . $parsed_url['host'] . $img;

        return $img;
    }

    /**
     * Returns the different image variations for consideration.
     *
     * @param int $attachment_id The attachment to return the variations for.
     *
     * @return array The different variations possible for this attachment ID.
     */
    public static function get_variations($attachment_id)
    {
        $variations = [];

        foreach (self::get_sizes() as $size) {
            $variation = self::get_image($attachment_id, $size);

            // The get_image function returns false if the size doesn't exist for this attachment.
            if ($variation) {
                $variations[] = $variation;
            }
        }

        return $variations;
    }

    /**
     * Retrieve the internal WP image file sizes.
     *
     * @return array $image_sizes An array of image sizes.
     */
    public static function get_sizes()
    {
        /**
         * Filter: 'wpfs_image_sizes' - Determines which image sizes we'll loop through to get an appropriate image.
         *
         * @api array - The array of image sizes to loop through.
         */
        return apply_filters('wpfs_image_sizes', ['full', 'large', 'medium_large']);
    }

    /**
     * Find the right version of an image based on size.
     *
     * @param int $attachment_id Attachment ID.
     * @param string $size Size name.
     *
     * @return array|false Returns an array with image data on success, false on failure.
     */
    public static function get_image($attachment_id, $size)
    {
        $image = false;
        if ($size === 'full') {
            $image = self::get_full_size_image_data($attachment_id);
        }

        if (!$image) {
            $image = image_get_intermediate_size($attachment_id, $size);
            $image['size'] = $size;
        }

        if (!$image) {
            return false;
        }

        return self::get_data($image, $attachment_id);
    }

    /**
     * Returns the image data for the full size image.
     *
     * @param int $attachment_id Attachment ID.
     *
     * @return array|false Array when there is a full size image. False if not.
     */
    protected static function get_full_size_image_data($attachment_id)
    {
        $image = wp_get_attachment_metadata($attachment_id);
        if (!is_array($image)) {
            return false;
        }

        $image['url'] = wp_get_attachment_image_url($attachment_id, 'full');
        $image['path'] = get_attached_file($attachment_id);
        $image['size'] = 'full';

        return $image;
    }

    /**
     * Retrieves the image data.
     *
     * @param array $image Image array with URL and metadata.
     * @param int $attachment_id Attachment ID.
     *
     * @return false|array $image {
     *     Array of image data
     *
     * @type string $alt Image's alt text.
     * @type string $path Path of image.
     * @type int $width Width of image.
     * @type int $height Height of image.
     * @type string $type Image's MIME type.
     * @type string $size Image's size.
     * @type string $url Image's URL.
     * @type int $filesize The file size in bytes, if already set.
     * }
     */
    public static function get_data($image, $attachment_id)
    {
        if (!is_array($image)) {
            return false;
        }

        // Deals with non-set keys and values being null or false.
        if (empty($image['width']) || empty($image['height'])) {
            return false;
        }

        $image['id'] = $attachment_id;
        $image['alt'] = self::get_alt_tag($attachment_id);
        $image['pixels'] = ((int)$image['width'] * (int)$image['height']);

        if (!isset($image['type'])) {
            $image['type'] = get_post_mime_type($attachment_id);
        }

        /**
         * Filter: 'wpfs_image_data' - Filter image data.
         *
         * Elements with keys not listed in the section will be discarded.
         *
         * @api array {
         *     Array of image data
         *
         * @type int    id       Image's ID as an attachment.
         * @type string alt      Image's alt text.
         * @type string path     Image's path.
         * @type int    width    Width of image.
         * @type int    height   Height of image.
         * @type int    pixels   Number of pixels in the image.
         * @type string type     Image's MIME type.
         * @type string size     Image's size.
         * @type string url      Image's URL.
         * @type int    filesize The file size in bytes, if already set.
         * }
         * @api int  Attachment ID.
         */
        $image = apply_filters('wpfs_image_data', $image, $attachment_id);

        // Keep only the keys we need, and nothing else.
        return array_intersect_key($image, array_flip(['id', 'alt', 'path', 'width', 'height', 'pixels', 'type', 'size', 'url', 'filesize']));
    }

    /**
     * Grabs an image alt text.
     *
     * @param int $attachment_id The attachment ID.
     *
     * @return string The image alt text.
     */
    public static function get_alt_tag($attachment_id)
    {
        return (string)get_post_meta($attachment_id, '_wp_attachment_image_alt', true);
    }

    /**
     * Check original size of image. If original image is too small, return false, else return true.
     *
     * Filters a list of variations by a certain set of usable dimensions.
     *
     * @param array $usable_dimensions {
     *    The parameters to check against.
     *
     * @type int $min_width Minimum width of image.
     * @type int $max_width Maximum width of image.
     * @type int $min_height Minimum height of image.
     * @type int $max_height Maximum height of image.
     * }
     * @param array $variations The variations that should be considered.
     *
     * @return array Whether a variation is fit for display or not.
     */
    public static function filter_usable_dimensions($usable_dimensions, $variations)
    {
        $filtered = [];

        foreach ($variations as $variation) {
            $dimensions = $variation;

            if (self::has_usable_dimensions($dimensions, $usable_dimensions)) {
                $filtered[] = $variation;
            }
        }

        return $filtered;
    }

    /**
     * Checks whether an img sizes up to the parameters.
     *
     * @param array $dimensions The image values.
     * @param array $usable_dimensions The parameters to check against.
     *
     * @return bool True if the image has usable measurements, false if not.
     */
    private static function has_usable_dimensions($dimensions, $usable_dimensions)
    {
        foreach (['width', 'height'] as $param) {
            $minimum = $usable_dimensions['min_' . $param];
            $maximum = $usable_dimensions['max_' . $param];

            $current = $dimensions[$param];
            if (($current < $minimum) || ($current > $maximum)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Filters a list of variations by (disk) file size.
     *
     * @param array $variations The variations to consider.
     *
     * @return array The validations that pass the required file size limits.
     */
    public static function filter_usable_file_size($variations)
    {
        foreach ($variations as $variation) {
            // We return early to prevent measuring the file size of all the variations.
            if (self::has_usable_file_size($variation)) {
                return [$variation];
            }
        }

        return [];
    }

    /**
     * Checks a size version of an image to see if it's not too heavy.
     *
     * @param array $image Image to check the file size of.
     *
     * @return bool True when the image is within limits, false if not.
     */
    public static function has_usable_file_size($image)
    {
        if (!is_array($image) || $image === []) {
            return false;
        }

        /**
         * Filter: 'wpfs_image_image_weight_limit' - Determines what the maximum weight
         * (in bytes) of an image is allowed to be, default is 2 MB.
         *
         * @api int - The maximum weight (in bytes) of an image.
         */
        $max_size = apply_filters('wpfs_image_image_weight_limit', 2097152);

        // We cannot check without a path, so assume it's fine.
        if (!isset($image['path'])) {
            return true;
        }

        return (self::get_file_size($image) <= $max_size);
    }

    /**
     * Get the image file size.
     *
     * @param array $image An image array object.
     *
     * @return int The file size in bytes.
     */
    public static function get_file_size($image)
    {
        if (isset($image['filesize'])) {
            return $image['filesize'];
        }

        // If the file size for the file is over our limit, we're going to go for a smaller version.
        // @todo Save the filesize to the image metadata.
        // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- If file size doesn't properly return, we'll not fail.
        return @filesize(self::get_absolute_path($image['path']));
    }

    /**
     * Finds the full file path for a given image file.
     *
     * @param string $path The relative file path.
     *
     * @return string The full file path.
     */
    public static function get_absolute_path($path)
    {
        static $uploads;

        if ($uploads === null) {
            $uploads = wp_get_upload_dir();
        }

        // Add the uploads basedir if the path does not start with it.
        if (empty($uploads['error']) && strpos($path, $uploads['basedir']) !== 0) {
            return $uploads['basedir'] . DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR);
        }

        return $path;
    }

    /**
     * Grabs the images from the content.
     *
     * @param string $content The post content string.
     *
     * @return array An array of image URLs.
     */
    public function get_images_from_content($content)
    {
        if (!is_string($content)) {
            return [];
        }

        $content_images = $this->get_img_tags_from_content($content);
        $images = array_map([$this, 'get_img_tag_source'], $content_images);
        $images = array_unique($images);
        $images = array_filter($images);

        // Reset the array keys.
        return array_values($images);
    }

    /**
     * Gets the image tags from a given content string.
     *
     * @param string $content The content to search for image tags.
     *
     * @return array An array of `<img>` tags.
     */
    private function get_img_tags_from_content($content)
    {
        if (strpos($content, '<img') === false) {
            return [];
        }

        preg_match_all('`<img [^>]+>`', $content, $matches);
        if (isset($matches[0])) {
            return $matches[0];
        }

        return [];
    }

    /**
     * Retrieves the image URL from an image tag.
     *
     * @param string $image Image HTML element.
     *
     * @return string|bool The image URL on success, false on failure.
     */
    private function get_img_tag_source($image)
    {
        preg_match('`src=(["\'])(.*?)\1`', $image, $matches);
        if (isset($matches[2])) {
            return $matches[2];
        }
        return false;
    }
}
