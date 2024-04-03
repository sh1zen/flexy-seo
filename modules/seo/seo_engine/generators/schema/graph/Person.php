<?php
/**
 * @author    sh1zen
 * @copyright Copyright (C) 2024.
 * @license   http://www.gnu.org/licenses/gpl.html GNU/GPL
 */

namespace FlexySEO\Engine\Generators\Schema\Graphs;

use FlexySEO\Engine\Generators\GraphBuilder;
use FlexySEO\Engine\Generators\GraphUtility;
use FlexySEO\Engine\Helpers\CurrentPage;
use WPS\core\StringHelper;
use WPS\core\Images;

/**
 * Person graph class.
 * This is the main Person graph that can be set to represent the site.
 */
class Person extends Graph
{
    /**
     * Returns the graph data.
     * @param \FlexySEO\Engine\Helpers\CurrentPage $currentPage
     * @param string $type
     * @param ...$args
     * @return GraphBuilder The graph data.
     */
    public function get(CurrentPage $currentPage, string $type = '', ...$args): GraphBuilder
    {
        return self::build($currentPage->get_queried_object());
    }

    public static function build($user): GraphBuilder
    {
        $user = wps_get_user($user);

        if (!$user) {
            return new GraphBuilder();
        }

        $schema = new GraphBuilder([
            '@type'       => 'Person',
            '@id'         => self::getSchemaID($user),
            'name'        => $user->display_name,
            'description' => StringHelper::truncate(StringHelper::escape_text($user->description), 300, '...'),
            'url'         => $user->user_url,
            'email'       => $user->user_email,
            'sameAs'      => GraphUtility::socialUrls($user->ID)
        ]);

        $snippet_data = Images::get_user_snippet_image($user->ID, 'full');

        if ($snippet_data) {
            $schema->set('image',
                [
                    '@type'  => 'ImageObject',
                    'url'    => $snippet_data['url'],
                    'width'  => $snippet_data['width'],
                    'height' => $snippet_data['height'],
                    //'caption' => $user->display_name,
                ]
            );
        }

        return $schema;
    }

    public static function getSchemaID($user): string
    {
        $user = wps_get_user($user);

        if ($user) {
            return wps_core()->home_url . '#/schema/person/' . md5($user->ID . wpfseo('salt'));
        }

        return '';
    }
}