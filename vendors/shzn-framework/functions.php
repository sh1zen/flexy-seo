<?php
/**
 * @author    sh1zen
 * @copyright Copyright (C)  2021
 * @license   http://www.gnu.org/licenses/gpl.html GNU/GPL
 */

function shzn_module_panel_url($module = '', $panel = '')
{
    return admin_url("admin.php?page={$module}#{$panel}");
}

function shzn_module_setting_url($context, $panel = '')
{
    return admin_url("admin.php?page={$context}-modules-settings#settings-{$panel}");
}

function shzn_setting_panel_url($context, $panel = '')
{
    return admin_url("admin.php?page={$context}-settings#settings-{$panel}");
}

function shzn_var_dump(...$vars)
{
    foreach ($vars as $var => $var_data)
        highlight_string("<?php\n$var =\n" . var_export($var_data, true) . ";\n?>");
    echo '</br></br>';
}

/**
 * @return string
 */
function shzn_get_calling_function($level = 2)
{
    $caller = debug_backtrace();
    $caller = $caller[$level];
    $r = $caller['function'] . '()';
    if (isset($caller['class'])) {
        $r .= ' in ' . $caller['class'];
    }
    if (isset($caller['object'])) {
        $r .= ' (' . get_class($caller['object']) . ')';
    }
    return $r;
}


function shzn_timestr2seconds($time = '')
{
    if (!$time)
        return 0;

    list($hour, $minute) = explode(':', $time);

    return $hour * HOUR_IN_SECONDS + $minute * MINUTE_IN_SECONDS;
}

function shzn_add_timezone($timestamp = false)
{
    if (!$timestamp)
        $timestamp = time();

    $timezone = get_option('gmt_offset') * HOUR_IN_SECONDS;

    return $timestamp - $timezone;
}