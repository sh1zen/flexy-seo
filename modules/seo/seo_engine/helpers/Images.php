<?php
/**
 * @author    sh1zen
 * @copyright Copyright (C)  2022
 * @license   http://www.gnu.org/licenses/gpl.html GNU/GPL
 */

namespace FlexySEO\Engine\Helpers;

use SHZN\core\Cache;
use SHZN\core\UtilEnv;

/**
 * Image_Utils.
 */
class Images
{
    /**
     * Implements the attachment_url_to_postid with use of WP Cache.
     *
     * @param string $url The attachment URL for which we want to know the Post ID.
     *
     * @return int The Post ID belonging to the attachment, 0 if not found.
     */
    public function attachmentUrlToPostId($url)
    {
        if (is_numeric($url)) {
            return $url;
        }

        $id = shzn('wpfs')->options->get($url, 'attachmentUrlToPostId', 'cache');

        if ($id === 'not_found') {
            return 0;
        }

        // ID is found in cache, return.
        if ($id !== false) {
            return $id;
        }

        /*
        * As get_attachment_by_url won't work on resized versions of images,
        * we strip out the size part of an image URL.
        */
        $url = preg_replace('#(.*)-\d+x\d+\.([a-z]{1,5})$#i', '$1.$2', $url);

        $uploads_base_url = UtilEnv::wp_upload_dir('baseurl');

        // Don't try to do this for external URLs.
        if (!str_starts_with($url, $uploads_base_url)) {
            return 0;
        }

        // Note: We use the WP COM version if we can, see above.
        $id = attachment_url_to_postid($url);

        if (empty($id)) {
            shzn('wpfs')->options->update($url, 'attachmentUrlToPostId', 'not_found', 'cache', WEEK_IN_SECONDS);
            return 0;
        }

        // We have the Post ID, but it's not in the cache yet. We do that here and return.
        shzn('wpfs')->options->update($url, 'attachmentUrlToPostId', $id, 'cache', WEEK_IN_SECONDS);

        return $id;
    }

    /**
     * Retrieve attachment url.
     *
     * @param $attachment_id
     * @param string $size
     * @return array|false
     */
    public function attachmentIdToUrl($attachment_id, $size = 'full')
    {
        if (filter_var($attachment_id, FILTER_VALIDATE_URL)) {
            return $attachment_id;
        }

        if (!($data = $this->get_image($attachment_id, $size))) {
            return '';
        }

        return $data['url'];
    }

    public function getSiteLogoId()
    {
        if (!get_theme_support('custom-logo')) {
            return [];
        }

        return get_theme_mod('custom_logo');
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
    public function filter_usable_dimensions($usable_dimensions, $variations)
    {
        $filtered = [];

        foreach ($variations as $variation) {
            $dimensions = $variation;

            if ($this->has_usable_dimensions($dimensions, $usable_dimensions)) {
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
    private function has_usable_dimensions($dimensions, $usable_dimensions)
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
     * Returns the different image variations for consideration.
     *
     * @param int $attachment_id The attachment to return the variations for.
     *
     * @return array The different variations possible for this attachment ID.
     */
    public function get_variations($attachment_id)
    {
        $variations = [];

        foreach ($this->get_sizes() as $size) {
            $variation = $this->get_image($attachment_id, $size);

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
    public function get_sizes()
    {
        return apply_filters('wpfs_image_sizes', ['thumbnail', 'medium', 'medium_large', 'large', 'full']);
    }

    /**
     * Find the right version of an image based on size.
     *
     * @param int $attachment_id Attachment ID.
     * @param string|array $size Size name.
     *
     * * * @return false|array $image {
     *     Array of image data
     *
     * @type string $id Image's id.
     * @type string $alt Image's alt text.
     * @type string $caption Image's caption text.
     * @type string $description Image's description text.
     * @type string $path Path of image.
     * @type string $file SubPath of image.
     * @type int $width Width of image.
     * @type int $height Height of image.
     * @type string $type Image's MIME type.
     * @type string $size Image's size.
     * @type array $meta Image's meta.
     * @type string $url Image's URL.
     * @type int $filesize The file size in bytes, if already set.
     * }
     */
    public function get_image($attachment_id, string|array $size = 'full', $allowExternal = true)
    {
        if (!$attachment_id) {
            return false;
        }

        $cacheKey = Cache::generate_key($attachment_id, $size);

        $image = shzn('wpfs')->options->get($cacheKey, 'schema.images.get_image', 'cache');

        if ($image !== false) {
            return $image;
        }

        $external = false;

        $attachment = get_post($attachment_id);

        if (!$attachment) {
            return false;
        }

        $metadata = wp_get_attachment_metadata($attachment_id, true);

        if ($metadata) {
            $file_url = wp_get_attachment_url($attachment_id);
        }
        else {
            $file_url = $attachment->guid;
            $external = true;
            $metadata = [
                'file'       => '',
                'width'      => 0,
                'height'     => 0,
                'image_meta' => []
            ];
        }

        if ($external and !$allowExternal) {
            return false;
        }

        $image = [
            'id'          => $attachment_id,
            'alt'         => $this->get_alt_tag($attachment_id),
            'caption'     => $attachment->post_excerpt,
            'description' => $attachment->post_content,
            'size'        => $size,
            'url'         => $file_url,
            'file'        => $metadata['file'],
            'width'       => $metadata['width'],
            'height'      => $metadata['height'],
            'meta'        => $metadata['image_meta']
        ];

        if ($size === 'full') {
            $image['path'] = get_attached_file($attachment_id, true);
            $image['type'] = get_post_mime_type($attachment_id);
        }
        elseif (isset($metadata['sizes'])) {

            if (is_array($size)) {
                foreach ($size as $s) {
                    if (!empty($metadata['sizes'][$s])) {
                        $size = $s;
                        break;
                    }
                }
            }

            if (empty($metadata['sizes'][$size])) {
                return false;
            }

            $image['path'] = path_join(dirname($metadata['file']), $metadata['sizes'][$size]['file']);
            $image['url'] = path_join(dirname($file_url), $metadata['sizes'][$size]['file']);

            $image['file'] = $metadata['sizes'][$size]['file'];
            $image['width'] = $metadata['sizes'][$size]['width'];
            $image['height'] = $metadata['sizes'][$size]['height'];
            $image['type'] = $metadata['sizes'][$size]['mime-type'];
        }

        if ($external) {

            $image['pixels'] = 0;
            $image['filesize'] = 0;
        }
        else {

            // Deals with non-set keys and values being null or false.
            if (empty($image['width']) or empty($image['height'])) {
                return false;
            }

            $image['pixels'] = ((int)$image['width'] * (int)$image['height']);
            $image['filesize'] = @filesize(UtilEnv::url_to_path($image['url']));
        }

        shzn('wpfs')->options->update($cacheKey, 'schema.images.get_image', $image, 'cache', WEEK_IN_SECONDS);

        return $image;
    }


    /**
     * Grabs an image alt text.
     *
     * @param int $attachment_id The attachment ID.
     *
     * @return string The image alt text.
     */
    public function get_alt_tag($attachment_id)
    {
        return (string)get_post_meta($attachment_id, '_wp_attachment_image_alt', true);
    }

    /**
     * @param int|string $object wp_attachment_id, path, url
     * @param string $size
     * @return array|false
     */
    public function get_snippet_data($object, $size = 'thumbnail')
    {
        $width = 0;
        $height = 0;

        $snippet_data = false;

        if (is_numeric($object)) {

            if ($image_data = wp_get_attachment_image_src($object, $size)) {

                $snippet_data = ['url' => $image_data[0], 'width' => $image_data[1], 'height' => $image_data[2]];
            }
        }
        else {

            if (UtilEnv::is_url($object)) {
                $image_path = UtilEnv::url_to_path($object);
            }
            else {
                $image_path = UtilEnv::normalize_path($object);
            }

            if ($image_path) {
                list($width, $height) = wp_getimagesize($image_path);
            }

            $snippet_data = ['url' => $object, 'width' => $width, 'height' => $height];
        }

        return $snippet_data;
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
        if (!str_contains($content, '<img')) {
            return [];
        }

        preg_match_all('#<img[^>]+src="([^">]+)"#', $content, $matches);

        if (isset($matches[1])) {
            return $matches[1];
        }

        return [];
    }

    /**
     * Removes image dimensions from the slug of a URL.
     *
     * @param string $url The image URL.
     * @return string      The formatted image URL.
     *
     */
    public function removeImageDimensions($url)
    {
        return $this->isValidAttachment($url) ? preg_replace('#(-\d*x\d*)#', '', $url) : $url;
    }

    /**
     * Checks whether the given URL is a valid attachment.
     *
     * @param string $url The URL.
     * @return boolean      Whether the URL is a valid attachment.
     *
     */
    public function isValidAttachment($url)
    {
        $uploadDirUrl = wpfseo()->string->escapeRegex(UtilEnv::wp_upload_dir('baseurl'));
        return preg_match("/$uploadDirUrl.*/", $url);
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
        preg_match('#src=(["\'])(.*?)\1#', $image, $matches);
        if (isset($matches[2])) {
            return $matches[2];
        }
        return false;
    }
}
