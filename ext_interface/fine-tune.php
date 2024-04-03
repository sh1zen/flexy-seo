<?php
/**
 * @author    sh1zen
 * @copyright Copyright (C) 2024.
 * @license   http://www.gnu.org/licenses/gpl.html GNU/GPL
 */


wps_add_replacement_rule("sep", function ($object) {
    return wps('wpfs')->settings->get('seo.title.separator', '-');
});