<?php
/**
 * @author    sh1zen
 * @copyright Copyright (C)  2022
 * @license   http://www.gnu.org/licenses/gpl.html GNU/GPL
 */

namespace FlexySEO\Engine;

class Indexable
{
    public int $object_id;

    public string $type;

    /**
     * @var \WP_Term|\WP_Post_Type|\WP_Post|\WP_User|null
     */
    public $object;

    public function __construct()
    {
        $this->type = wpfseo()->currentPage->get_page_type();

        $this->object_id = wpfseo()->currentPage->get_queried_object_id();

        $this->object = wpfseo()->currentPage->get_queried_object();
    }

}