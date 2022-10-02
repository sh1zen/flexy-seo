<?php
/**
 * @author    sh1zen
 * @copyright Copyright (C)  2022
 * @license   http://www.gnu.org/licenses/gpl.html GNU/GPL
 */

namespace FlexySEO\Engine\Generators\Schema\Graphs;

use FlexySEO\Engine\Generators\GraphBuilder;
use FlexySEO\Engine\Generators\GraphUtility;
use FlexySEO\Engine\Helpers\CurrentPage;

/**
 * Person graph class.
 *
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
    public function get(CurrentPage $currentPage, string $type = '', ...$args)
    {
        return self::build($currentPage->get_queried_object());
    }

    public static function build($user)
    {
        $user = shzn_get_user($user);

        if (!$user) {
            return new GraphBuilder();
        }

        $schema = new GraphBuilder([
            '@type'  => 'Person',
            '@id'    => self::getSchemaID($user),
            'name'   => $user->display_name,
            'sameAs' => GraphUtility::socialUrls($user->ID)
        ]);

        $snippet_data = wpfseo('helpers')->get_user_snippet_image($user->ID, 'full');

        if ($snippet_data) {
            $schema->set('image',
                [
                    '@type'      => 'ImageObject',
                    'url'        => $snippet_data['url'],
                    'width'      => $snippet_data['width'],
                    'height'     => $snippet_data['height'],
                    'caption'    => $user->display_name,
                ]
            );
        }

        return $schema;
    }

    public static function getSchemaID($user)
    {
        $user = shzn_get_user($user);

        if ($user) {
            return shzn()->utility->home_url . '#/schema/person/' . hash('md5', $user->ID . wpfseo('salt'));
        }

        return '';
    }
}