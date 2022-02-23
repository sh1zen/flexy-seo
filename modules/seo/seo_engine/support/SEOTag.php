<?php
/**
 * @author    sh1zen
 * @copyright Copyright (C)  2022
 * @license   http://www.gnu.org/licenses/gpl.html GNU/GPL
 */

namespace FlexySEO\Engine\Helpers;

class SEOTag
{
    public $name;

    public $value;

    public $template;

    public function __construct($name, $value, $template = 'meta')
    {
        $this->name = $name;
        $this->value = $value;
        $this->template = $template;
    }
}