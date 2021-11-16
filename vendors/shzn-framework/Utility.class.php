<?php
/**
 * @author    sh1zen
 * @copyright Copyright (C)  2021
 * @license   http://www.gnu.org/licenses/gpl.html GNU/GPL
 */

namespace SHZN\core;

class Utility
{
    public $online;

    public $home_url;

    public $cu_id;

    public $wp_upload_dir;

    public function __construct()
    {
        $this->home_url = trailingslashit(home_url());;

        $this->cu_id = get_current_user_id();

        $this->online = $_SERVER["SERVER_ADDR"] !== '127.0.0.1';

        $this->wp_upload_dir = wp_upload_dir(null, false);
    }
}