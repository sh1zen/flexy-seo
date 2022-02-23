<?php
/**
 * @author    sh1zen
 * @copyright Copyright (C)  2022
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
function shzn_debug_backtrace($level = 2)
{
    $caller = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT, $level + 1);

    if (isset($caller[$level])) {

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

    return var_export($caller, true);
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
    if (!$timestamp) {
        $timestamp = time();
    }

    $timezone = get_option('gmt_offset') * HOUR_IN_SECONDS;

    return $timestamp - $timezone;
}