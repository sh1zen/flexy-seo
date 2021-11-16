<?php
/**
 * @author    sh1zen
 * @copyright Copyright (C)  2021
 * @license   http://www.gnu.org/licenses/gpl.html GNU/GPL
 */

namespace FlexySEO\Engine\Generators\Schema;

use FlexySEO\Engine\Helpers\Helpers;

/**
 * The base graph class.
 *
 * @since 1.2.0
 */
abstract class Graph
{
    protected $properties = [];

    protected $context;

    protected $retriver;

    protected $type = '';

    public function __construct()
    {
        $this->context = array_merge([
            'name'        => '',
            'description' => '',
            'url'         => '',
            'breadcrumb'  => '',
            'object'      => '',
            'type'        => ''
        ], []);

        //$this->retriver =
    }

    /**
     * Returns the graph data.
     *
     * @since 1.2.0
     */
    abstract public function get();

    /**
     * Builds the graph data for a given image with a given schema ID.
     *
     * @param int $imageId The image ID.
     * @param string $graphId The graph ID.
     * @return array $data    The image graph data.
     * @since 4.0.0
     *
     */
    protected function image($imageId, $graphId)
    {
        $attachmentId = (is_string($imageId) and !is_numeric($imageId)) ? Helpers::attachmentUrlToPostId($imageId) : $imageId;
        $imageUrl = wp_get_attachment_image_url($attachmentId, 'full');

        $data = [
            '@type' => 'ImageObject',
            '@id'   => trailingslashit(home_url()) . '#' . $graphId,
            'url'   => $imageUrl ? $imageUrl : $imageId,
        ];

        if (!$attachmentId) {
            return $data;
        }

        $metaData = wp_get_attachment_metadata($attachmentId);
        if ($metaData) {
            $data['width'] = $metaData['width'];
            $data['height'] = $metaData['height'];
        }

        $caption = wp_get_attachment_caption($attachmentId);
        if (false !== $caption || !empty($caption)) {
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
     * @since 4.0.0
     *
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
            '@id'     => $this->context['url'] . "#$graphId",
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
     * @since 4.0.0
     *
     */
    protected function socialUrls($authorId = false)
    {
        /*$socialUrls = [];
        if ( aioseo()->options->social->profiles->sameUsername->enable ) {
            $username = aioseo()->options->social->profiles->sameUsername->username;
            $urls = [
                'facebookPageUrl' => "https://facebook.com/$username",
                'twitterUrl'      => "https://twitter.com/$username",
                'instagramUrl'    => "https://instagram.com/$username",
                'pinterestUrl'    => "https://pinterest.com/$username",
                'youtubeUrl'      => "https://youtube.com/$username",
                'linkedinUrl'     => "https://linkedin.com/in/$username",
                'tumblrUrl'       => "https://$username.tumblr.com",
                'yelpPageUrl'     => "https://yelp.com/biz/$username",
                'soundCloudUrl'   => "https://soundcloud.com/$username",
                'wikipediaUrl'    => "https://en.wikipedia.org/wiki/$username",
                'myspaceUrl'      => "https://myspace.com/$username"
            ];

            $included = aioseo()->options->social->profiles->sameUsername->included;
            foreach ( $urls as $name => $value ) {
                if ( in_array( $name, $included, true ) ) {
                    $socialUrls[ $name ] = $value;
                } else {
                    $notIncluded = aioseo()->options->social->profiles->urls->$name;
                    if ( ! empty( $notIncluded ) ) {
                        $socialUrls[ $name ] = $notIncluded;
                    }
                }
            }
        } else {
            $socialUrls = [
                'facebookPageUrl' => aioseo()->options->social->profiles->urls->facebookPageUrl,
                'twitterUrl'      => aioseo()->options->social->profiles->urls->twitterUrl,
                'instagramUrl'    => aioseo()->options->social->profiles->urls->instagramUrl,
                'pinterestUrl'    => aioseo()->options->social->profiles->urls->pinterestUrl,
                'youtubeUrl'      => aioseo()->options->social->profiles->urls->youtubeUrl,
                'linkedinUrl'     => aioseo()->options->social->profiles->urls->linkedinUrl,
                'tumblrUrl'       => aioseo()->options->social->profiles->urls->tumblrUrl,
                'yelpPageUrl'     => aioseo()->options->social->profiles->urls->yelpPageUrl,
                'soundCloudUrl'   => aioseo()->options->social->profiles->urls->soundCloudUrl,
                'wikipediaUrl'    => aioseo()->options->social->profiles->urls->wikipediaUrl,
                'myspaceUrl'      => aioseo()->options->social->profiles->urls->myspaceUrl
            ];
        }

        if ( ! $authorId ) {
            return array_values( array_filter( $socialUrls ) );
        }

        if ( aioseo()->options->social->facebook->general->showAuthor ) {
            $meta = get_the_author_meta( 'aioseo_facebook', $authorId );
            if ( $meta ) {
                $socialUrls['facebookPageUrl'] = $meta;
            }
        } else {
            $socialUrls['facebookPageUrl'] = '';
        }

        if ( aioseo()->options->social->twitter->general->showAuthor ) {
            $meta = get_the_author_meta( 'aioseo_twitter', $authorId );
            if ( $meta ) {
                $socialUrls['twitterUrl'] = $meta;
            }
        } else {
            $socialUrls['twitterUrl'] = '';
        }
        return array_values( array_filter( $socialUrls ) );*/

        return [];
    }
}