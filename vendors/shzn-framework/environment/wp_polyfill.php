<?php
/**
 * @author    sh1zen
 * @copyright Copyright (C)  2022
 * @license   http://www.gnu.org/licenses/gpl.html GNU/GPL
 */

if (!function_exists('wp_doing_ajax')) {
    function wp_doing_ajax()
    {
        return apply_filters('wp_doing_ajax', defined('DOING_AJAX') and DOING_AJAX);
    }
}


if (!function_exists('wp_doing_cron')) {
    function wp_doing_cron()
    {
        return apply_filters('wp_doing_cron', defined('DOING_CRON') and DOING_CRON);
    }
}

