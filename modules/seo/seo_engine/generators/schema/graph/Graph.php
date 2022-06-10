<?php
/**
 * @author    sh1zen
 * @copyright Copyright (C)  2022
 * @license   http://www.gnu.org/licenses/gpl.html GNU/GPL
 */

namespace FlexySEO\Engine\Generators\Schema\Graphs;

use FlexySEO\Engine\Generator;
use FlexySEO\Engine\Helpers\CurrentPage;

/**
 * The base graph class.
 */
abstract class Graph
{
    protected string $type = '';

    protected Generator $generator;

    public function __construct($generator)
    {
        $this->generator = $generator;
    }

    /**
     * Returns the graph data.
     *
     * @param \FlexySEO\Engine\Helpers\CurrentPage $currentPage
     * @param string $type
     * @param ...$args
     */
    abstract public function get(CurrentPage $currentPage, string $type = '', ...$args);

    /**
     * Returns the graph data for the avatar of a given user.
     *
     * @param int $userId The user ID.
     * @param string $graphId The graph ID.
     * @return array           The graph data.
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

        ]);
    }

}