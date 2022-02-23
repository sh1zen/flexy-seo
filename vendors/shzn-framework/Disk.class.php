<?php
/**
 * @author    sh1zen
 * @copyright Copyright (C)  2022
 * @license   http://www.gnu.org/licenses/gpl.html GNU/GPL
 */

namespace SHZN\core;

class Disk
{
    private static $wp_filesystem;

    private static bool $suspended = false;

    public static function read($path)
    {
        // writing to system rules file, may be potentially write-protected
        if ($data = @file_get_contents($path))
            return $data;

        return self::wp_filesystem()->get_contents($path);
    }

    /**
     * sdes
     * @return \WP_Filesystem_Direct
     */
    private static function wp_filesystem()
    {
        if (!isset(self::$wp_filesystem)) {
            global $wp_filesystem;

            require_once(ABSPATH . '/wp-admin/includes/file.php');
            WP_Filesystem();

            self::$wp_filesystem = $wp_filesystem;
        }

        return self::$wp_filesystem;
    }

    public static function write($path, $data)
    {
        if (self::$suspended)
            return false;

        // writing to system rules file, may be potentially write-protected
        if (@file_put_contents($path, $data))
            return true;

        return self::wp_filesystem()->put_contents($path, $data);
    }

    public static function count_files($path, $filters = array())
    {

    }

    public static function delete_files($target, $identifier = '')
    {
        self::suspend_cache();

        $target = realpath($target);

        if (is_dir($target)) {

            if (empty($identifier)) {
                /**
                 * get all folders/files (even the hidden ones)
                 * This will prevent listing "." or ".." in the result
                 */
                $identifier = '{,.}[!.,!..]*';
            }

            $target = trailingslashit($target);

            $files = glob($target . $identifier, GLOB_MARK | GLOB_NOSORT | GLOB_BRACE); //GLOB_MARK adds a slash to directories returned

            foreach ($files as $file) {
                self::delete_files($file);
            }

            rmdir($target);
        }
        elseif (is_file($target)) {
            unlink($target);
        }

        self::resume_cache();
    }

    public static function suspend_cache()
    {
        self::$suspended = true;
    }

    public static function resume_cache()
    {
        self::$suspended = false;
    }

    /**
     * @param $directory
     * @return false|int|mixed
     */
    public static function calc_size($directory)
    {
        $bytesTotal = 0;
        $path = realpath($directory);
        if ($path !== false && $path != '' && file_exists($path)) {
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS)) as $object) {
                $bytesTotal += $object->getSize();
            }
        }
        return $bytesTotal;
    }


    public static function make_path($path, $private = true)
    {
        global $is_IIS;

        $plugin_path = __DIR__ . "/utils/";

        $path = UtilEnv::realpath($path);

        if (!$path)
            return false;

        // Create Backup Folder
        $res = wp_mkdir_p($path);

        if ($private and is_dir($path) and wp_is_writable($path)) {

            if ($is_IIS) {
                if (!is_file($path . '/Web.config')) {
                    copy($plugin_path . '/Web.config', $path . '/Web.config');
                }
            }
            else {
                if (!is_file($path . '/.htaccess')) {
                    copy($plugin_path . '/.htaccess', $path . '/.htaccess');
                }
            }
            if (!is_file($path . '/index.php')) {
                file_put_contents($path . '/index.php', '<?php');
            }

            chmod($path, 0750);
        }

        return $res;
    }


    public static function autocomplete($path = '')
    {
        $response = array();

        $abspath = UtilEnv::normalize_path(ABSPATH, true);

        $_search_path = UtilEnv::normalize_path($abspath . $path, true);
        $search_sub_path = false;

        while (!($search_path = realpath($_search_path))) {

            $_search_path = untrailingslashit($_search_path);

            $search_sub_path = substr($_search_path, strrpos($_search_path, '/') + 1);
            $_search_path = substr($_search_path, 0, strrpos($_search_path, '/'));
        }

        $search_path = UtilEnv::normalize_path($search_path, true);

        // to prevent go upper than ABSPATH
        if (!str_contains($search_path, $abspath)) {
            $search_path = $abspath;
        }

        $dir_list = scandir($search_path);

        foreach ($dir_list as $value) {

            if ($value === '.' or $value === '..')
                continue;

            if (is_dir($search_path . $value)) {

                if ($search_sub_path and !str_contains($value, $search_sub_path))
                    continue;

                $response[] = str_replace($abspath, '', $search_path . $value . '/');
            }
        }

        return $response;
    }
}
