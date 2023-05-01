<?php
/**
 * @author    sh1zen
 * @copyright Copyright (C) 2023.
 * @license   http://www.gnu.org/licenses/gpl.html GNU/GPL
 */

$_wpfs_settings = shzn('wpfs')->settings->get();

if(!isset($_wpfs_settings['ver'])) {
    $_wpfs_settings['ver'] = "1.0.0";
}

SHZN\core\UtilEnv::handle_upgrade($_wpfs_settings['ver'], WPFS_VERSION,  WPFS_ADMIN . "upgrades/");

$_wpfs_settings['ver'] = WPFS_VERSION;

shzn('wpfs')->settings->reset($_wpfs_settings);

unset($_wpfs_settings);