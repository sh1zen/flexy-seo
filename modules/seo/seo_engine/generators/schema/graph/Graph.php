<?php
/**
 * @author    sh1zen
 * @copyright Copyright (C)  2021
 * @license   http://www.gnu.org/licenses/gpl.html GNU/GPL
 */

namespace FlexySEO\Engine\Generators\Schema\Graphs;

/**
 * The base graph class.
 *
 * @since 1.2.0
 */
abstract class Graph
{
    protected $type = '';

    /**
     * @var \FlexySEO\Engine\Generator $generator
     */
    protected $generator;

    public function __construct($generator)
    {
        $this->generator = $generator;
    }

    /**
     * Returns the graph data.
     *
     * @since 1.2.0
     */
    abstract public function get($type = '');

    /**
     * Builds the graph data for a given image with a given schema ID.
     *
     * @param int $imageId The image ID.
     * @param string $graphId The graph ID.
     * @return array $data    The image graph data.
     *
     * @since 1.2.0
     */
    protected function image($imageId, $graphId)
    {
        $attachmentId = wpfseo()->images->attachmentUrlToPostId($imageId);

        if (!$attachmentId) {
            return [];
        }

        $imageUrl = wp_get_attachment_image_url($attachmentId, 'full');

        $data = [
            '@type' => 'ImageObject',
            '@id'   => shzn()->utility->home_url . '#' . $graphId,
            'url'   => $imageUrl ? $imageUrl : $imageId,
        ];

        $metaData = wp_get_attachment_metadata($attachmentId);
        if ($metaData) {
            $data['width'] = $metaData['width'];
            $data['height'] = $metaData['height'];
        }

        $caption = wp_get_attachment_caption($attachmentId);
        if (!empty($caption)) {
            $data['caption'] = $caption;
        }
        return $data;
    }

    /**
     * Returns the graph data for the avatar of a given user.
     *
     * @param int $userId The user ID.
     * @param string $graphId The graph ID.
     * @return array           The graph data.
     *
     * @since 1.2.0
     */
    protected function avatar($userId, $graphId)
    {
        if (!get_option('show_avatars')) {
            return [];
        }

        $avatar = get_avatar_data($userId);
        if (!$avatar['found_avatar']) {
            return [];
        }

        return array_filter([
            '@type'   => 'ImageObject',
            '@id'     => $this->generator->get_permalink() . "#$graphId",
            'url'     => $avatar['url'],
            'width'   => $avatar['width'],
            'height'  => $avatar['height'],
            'caption' => get_the_author_meta('display_name', $userId)
        ]);
    }

    /**
     * Returns the social media URLs for the author.
     *
     * @param int $authorId The author ID.
     * @return array $socialUrls The social media URLs.
     *
     * @since 1.2.0
     */
    protected function socialUrls($authorId = false)
    {
        $socialUrls = [];

        $urls = [
            'facebook'   => "https://facebook.com/%s",
            'twitter'    => "https://twitter.com/%s",
            'instagram'  => "https://instagram.com/%s",
            'pinterest'  => "https://pinterest.com/%s",
            'youtube'    => "https://youtube.com/%s",
            'linkedin'   => "https://linkedin.com/in/%s",
            'tumblr'     => "https://%s.tumblr.com",
            'yelp'       => "https://yelp.com/biz/%s",
            'soundcloud' => "https://soundcloud.com/%s",
            'wikipedia'  => "https://en.wikipedia.org/wiki/%s"
        ];

        $sameUsername = false;

        if ($authorId) {

        }
        else {

            if (shzn('wpfs')->settings->get("seo.social.sameUsername.enable", false)) {
                $sameUsername = shzn('wpfs')->settings->get("seo.social.sameUsername.username", '');
                if (empty($sameUsername)) {
                    return $socialUrls;
                }
            }

            foreach ($urls as $name => $value) {

                if ($sameUsername) {
                    $username = shzn('wpfs')->settings->get("seo.social.{$name}.enable", true) ? $sameUsername : '';
                }
                else {
                    $username = shzn('wpfs')->settings->get("seo.social.{$name}.url", '');
                }

                if (!empty($username)) {

                    if (strpos($username, "/") === false) {
                        $socialUrls[] = sprintf($value, $username);
                    }
                    else {
                        $socialUrls[] = $username;
                    }
                }
            }
        }

        return array_values(array_filter($socialUrls));
    }
}