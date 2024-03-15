<?php
/**
 * @author    sh1zen
 * @copyright Copyright (C) 2024.
 * @license   http://www.gnu.org/licenses/gpl.html GNU/GPL
 */

namespace FlexySEO\Engine\Helpers;

class SEOScriptTag
{
    public $content;

    public $template;

    public $attributes;

    public function __construct($content, $template, $attributes = [])
    {
        $this->attributes = $attributes;
        $this->content = $content;
        $this->template = $template;
    }
}