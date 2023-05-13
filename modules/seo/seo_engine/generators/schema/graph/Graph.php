<?php
/**
 * @author    sh1zen
 * @copyright Copyright (C) 2023.
 * @license   http://www.gnu.org/licenses/gpl.html GNU/GPL
 */

namespace FlexySEO\Engine\Generators\Schema\Graphs;

use FlexySEO\Engine\Generator;
use FlexySEO\Engine\Generators\GraphBuilder;
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
    abstract public function get(CurrentPage $currentPage, string $type = '', ...$args): GraphBuilder;
}