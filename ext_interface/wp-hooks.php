<?php
/**
 * @author    sh1zen
 * @copyright Copyright (C)  2022
 * @license   http://www.gnu.org/licenses/gpl.html GNU/GPL
 */

add_action('wpopt_delete_options_cache', function ($id) {
    shzn('wpfs')->options->remove_by_container($id);
}, 10, 1);


