<?php

$_wpfs_settings = shzn('wpfs')->settings->get();

if(!isset($_wpfs_settings['ver'])) {
    $_wpfs_settings['ver'] = "1.0.0";
}

SHZN\core\UtilEnv::handle_upgrade($_wpfs_settings['ver'], WPFS_VERSION,  WPFS_ADMIN . "upgrades/");

$_wpfs_settings['ver'] = WPFS_VERSION;

shzn('wpfs')->settings->reset($_wpfs_settings);

unset($_wpfs_settings);