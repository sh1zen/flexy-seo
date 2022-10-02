<?php
/**
 * @author    sh1zen
 * @copyright Copyright (C)  2022
 * @license   http://www.gnu.org/licenses/gpl.html GNU/GPL
 */

namespace FlexySEO\Engine\Generators;

use FlexySEO\Engine\Helpers\Images;

class CommonGraphs
{
    /**
     * Builds the graph data for a given image with a given schema ID.
     *
     * @param int|string $imageUrlId The image ID.
     * @param string $graphId The graph ID.
     * @return array $data    The image graph data.
     */
    public static function imageObject($imageUrlId, string|array $size = 'large', string $graphId = '')
    {
        if (is_array($imageUrlId)) {
            $imageData = array_merge([
                'url'         => '',
                'width'       => 0,
                'height'      => 0,
                'caption'     => '',
                'alt'         => '',
                'description' => '',
            ], $imageUrlId);

            // force if there is data
            $attachmentId = true;
        }
        else {
            $attachmentId = wpfseo()->images->attachmentUrlToPostId($imageUrlId);
            $imageData = Images::get_image($attachmentId, $size);

            if (!$imageData) {

                if (filter_var($imageUrlId, FILTER_VALIDATE_URL)) {
                    $imageData = [
                        'url' => $imageUrlId,
                    ];
                }
                else {
                    return [];
                }
            }
        }

        if ($graphId and !str_contains($graphId, '#')) {
            $graphId = shzn()->utility->home_url . '#' . $graphId;
        }

        $schema = new GraphBuilder(
            'ImageObject',
            [
                '@id'         => strtolower($graphId),
                'url'         => $imageData['url'],
                'inLanguage'  => wpfseo()->language->currentLanguageCodeBCP47()
            ]
        );

        if ($attachmentId) {

            $schema->set('width', $imageData['width']);
            $schema->set('height', $imageData['height']);
            $schema->set('caption', $imageData['caption']);
            $schema->set('alternateName', $imageData['alt']);
            $schema->set('description', $imageData['description']);
        }

        return $schema->export();
    }


}
