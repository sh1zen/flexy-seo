<?php
/**
 * @author    sh1zen
 * @copyright Copyright (C)  2021
 * @license   http://www.gnu.org/licenses/gpl.html GNU/GPL
 */

namespace FlexySEO\Engine;

class Indexable
{
    /**
     * @var int
     */
    public $object_id;

    /**
     * @var string
     */
    public $type;

    /**
     * @var \WP_User | \WP_Post
     */
    public $object;

    public function __construct()
    {

        $this->type = wpfseo()->currentPage->get_page_type();

        $this->object_id = wpfseo()->currentPage->get_queried_object_id();

        $this->object = wpfseo()->currentPage->get_queried_object();
    }

}