<?php
/**
 * @author    sh1zen
 * @copyright Copyright (C) 2024.
 * @license   http://www.gnu.org/licenses/gpl.html GNU/GPL
 */

add_action('wpopt_media_optimized', function ($id) {
    wps('wpfs')->options->remove_by_container($id);
}, 10, 1);