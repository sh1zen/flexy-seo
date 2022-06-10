<?php
/**
 * @author    sh1zen
 * @copyright Copyright (C)  2022
 * @license   http://www.gnu.org/licenses/gpl.html GNU/GPL
 */

namespace SHZN\core;

class UtilEnv
{
    public static function handle_upgrade($ver_start, $ver_to, $upgrade_path)
    {
        $upgrades = array_filter(
            array_map(function ($fname) {
                return basename($fname, ".php");
            }, array_diff(
                    scandir($upgrade_path), array('.', '..'))
            ),
            function ($ver) use ($ver_start, $ver_to) {
                return version_compare($ver, $ver_start, '>') and version_compare($ver, $ver_to, '<=');
            });

        usort($upgrades, 'version_compare');

        $current_ver = $ver_start;

        while (!empty($upgrades)) {

            self::rise_time_limit(30);

            $next_ver = array_shift($upgrades);

            require_once $upgrade_path . "{$next_ver}.php";

            $current_ver = $next_ver;
        }

        return $current_ver;
    }

    public static function rise_time_limit($time = 30)
    {
        if (absint(ini_get('max_execution_time')) === 0) {
            return true;
        }

        return function_exists('set_time_limit') and set_time_limit($time);
    }

    public static function db_create($table_name, $args)
    {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        if (!str_starts_with($table_name, $wpdb->prefix)) {
            $table_name = $wpdb->prefix . $table_name;
        }

        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} ( ";

        foreach ($args['fields'] as $key => $value) {
            $sql .= " {$key} {$value}, ";
        }

        $sql .= " PRIMARY KEY  ({$args['primary_key']})";

        $sql .= " ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        return dbDelta($sql);
    }

    public static function db_search_replace($search, $replace, $table, $column, $where = [])
    {
        global $wpdb;

        $_where = '';

        if (!empty($where)) {

            $conditions = [];

            foreach ($where as $field => $value) {
                $conditions[] = "{$field} = {$value}";
            }

            $_where = "WHERE " . implode(' AND ', $conditions);
        }

        return $wpdb->query($wpdb->prepare("UPDATE {$table} SET {$column} = REPLACE({$column}, '%s', '%s') {$_where};", $search, $replace));
    }

    /**
     * @param $items
     * @param bool|string $keep_key_format string, numeric, bool, object
     * @return array
     */
    public static function array_flatter($items, $keep_key_format = false)
    {
        if (!is_array($items)) {
            return array($items);
        }

        if ($keep_key_format and !in_array($keep_key_format, array('string', 'numeric', 'bool', 'object'), true))
            $keep_key_format = false;

        if ($keep_key_format) {
            $res = array();
            foreach ($items as $key => $value) {
                if (!call_user_func("is_{$keep_key_format}", $key))
                    $key = array();
                $res = array_merge($res, (array)$key, self::array_flatter($value, $keep_key_format));
            }
            return $res;
        }

        return array_reduce($items, function ($carry, $item) {
            return array_merge($carry, self::array_flatter($item));
        }, array());
    }

    /**
     * Standardize whitespace in a string.
     *
     * Replace line breaks, carriage returns, tabs with a space, then remove double spaces.
     *
     * @param string $string String input to standardize.
     *
     * @return string
     */
    public static function sanitize_whitespace($string)
    {
        return \trim(\str_replace('  ', ' ', \str_replace(["\t", "\n", "\r", "\f"], ' ', $string)));
    }

    /**
     * Returns true if server is Apache
     *
     * @return boolean
     */
    public static function is_apache()
    {
        // assume apache when unknown, since most common
        if (empty($_SERVER['SERVER_SOFTWARE']))
            return true;

        return stristr($_SERVER['SERVER_SOFTWARE'], 'Apache') !== false;
    }

    /**
     * Check whether server is LiteSpeed
     *
     * @return bool
     */
    public static function is_litespeed()
    {
        return isset($_SERVER['SERVER_SOFTWARE']) and stristr($_SERVER['SERVER_SOFTWARE'], 'LiteSpeed') !== false;
    }

    /**
     * Returns true if server is nginx
     *
     * @return boolean
     */
    public static function is_nginx()
    {
        return isset($_SERVER['SERVER_SOFTWARE']) and stristr($_SERVER['SERVER_SOFTWARE'], 'nginx') !== false;
    }

    /**
     * Returns true if server is nginx
     *
     * @return boolean
     */
    public static function is_iis()
    {
        return isset($_SERVER['SERVER_SOFTWARE']) and stristr($_SERVER['SERVER_SOFTWARE'], 'IIS') !== false;
    }

    /**
     * Memoized version of wp_upload_dir.
     */
    public static function wp_upload_dir($part = '')
    {
        static $values_by_blog = array();

        $blog_id = get_current_blog_id();

        if (!isset($values_by_blog[$blog_id])) {
            $values_by_blog[$blog_id] = wp_upload_dir();
        }

        if ($part) {
            return $values_by_blog[$blog_id][$part];
        }

        return $values_by_blog[$blog_id];
    }

    /**
     * Returns if there is multisite mode
     *
     * @return boolean
     */
    public static function is_wpmu()
    {
        static $wpmu = null;

        if ($wpmu === null) {
            $wpmu = (file_exists(ABSPATH . 'wpmu-settings.php') ||
                (defined('MULTISITE') and MULTISITE) ||
                defined('SUNRISE') ||
                self::is_wpmu_subdomain());
        }

        return $wpmu;
    }

    /**
     * Returns true if WPMU uses vhosts
     *
     * @return boolean
     */
    public static function is_wpmu_subdomain()
    {
        return ((defined('SUBDOMAIN_INSTALL') and SUBDOMAIN_INSTALL) ||
            (defined('VHOST') and VHOST == 'yes'));
    }

    /**
     * Returns true if current connection is secure
     *
     * @return boolean
     */
    public static function is_https()
    {
        switch (true) {
            case (isset($_SERVER['HTTPS']) and
                self::to_boolean($_SERVER['HTTPS'])):
            case (isset($_SERVER['SERVER_PORT']) and
                (int)$_SERVER['SERVER_PORT'] == 443):
            case (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) and
                $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https'):
                return true;
        }

        return false;
    }

    /**
     * Converts value to boolean
     *
     * @param mixed $value
     * @return boolean
     */
    public static function to_boolean($value)
    {
        if (is_string($value)) {
            switch (strtolower($value)) {
                case '+':
                case '1':
                case 'y':
                case 'on':
                case 'yes':
                case 'true':
                case 'enabled':
                    return true;

                case '-':
                case '0':
                case 'n':
                case 'no':
                case 'off':
                case 'false':
                case 'disabled':
                    return false;
            }
        }

        return (boolean)$value;
    }

    /**
     * Check if URL is valid
     *
     * @param string $url
     * @return boolean
     */
    public static function is_url($url)
    {
        return preg_match('~^(https?:)?//~', $url);
    }

    /**
     * Returns URL regexp from URL
     *
     * @param string $url
     * @return string
     */
    public static function get_url_regexp($url)
    {
        $url = preg_replace('~(https?:)?//~i', '', $url);
        $url = preg_replace('~^www\.~i', '', $url);

        return '(https?:)?//(www\.)?' . self::preg_quote($url);
    }

    /**
     * Quotes regular expression string
     *
     * @param string $string
     * @param string $delimiter
     * @return string
     */
    public static function preg_quote($string, $delimiter = '~')
    {
        $string = preg_quote($string, $delimiter);
        return strtr($string, array(
            ' ' => '\ '
        ));
    }

    /**
     * Returns real path of given path
     *
     * @param string $path
     * @return string
     */
    public static function realpath($path, $exist = false)
    {
        $path = self::normalize_path($path);

        if ($exist) {
            $absolutes = realpath($path);
        }
        else {
            $absolutes = array();

            $parts = explode('/', $path);

            foreach ($parts as $part) {
                if ('.' == $part) {
                    continue;
                }
                if ('..' == $part) {
                    array_pop($absolutes);
                }
                else {
                    $absolutes[] = $part;
                }
            }

            $absolutes = implode('/', $absolutes);
        }

        return $absolutes;
    }

    /**
     * Converts win path to unix
     *
     * @param string $path
     * @param bool $trailing_slash
     * @return string
     */
    public static function normalize_path($path, $trailing_slash = false)
    {
        $wrapper = '';

        // Remove the trailing slash
        if (!$trailing_slash) {
            $path = rtrim($path, '/');
        }
        else {
            $path .= '/';
        }

        if (wp_is_stream($path)) {
            list($wrapper, $path) = explode('://', $path, 2);

            $wrapper .= '://';
        }
        else {
            // Windows paths should uppercase the drive letter.
            if (':' === substr($path, 1, 1)) {
                $path = ucfirst($path);
            }
        }

        // Standardise all paths to use '/' and replace multiple slashes down to a singular.
        $path = preg_replace('#(?<=.)[/\\\]+#', '/', $path);

        return $wrapper . $path;
    }

    public static function path_to_url($path, $file = false)
    {
        $base_dir = self::normalize_path(ABSPATH, false);

        return site_url(str_replace($base_dir, '', self::normalize_path($path, !$file)));
    }

    /**
     * Get the attachment absolute path from its url
     *
     * @param string $url the attachment url to get its absolute path
     *
     * @return bool|string It returns the absolute path of an attachment
     */
    public static function url_to_path(string $url)
    {
        if (!is_string($url)) {
            return false;
        }

        $parsed_url_path = parse_url($url, PHP_URL_PATH);

        if (empty($parsed_url_path)) {
            return false;
        }

        return realpath($_SERVER['DOCUMENT_ROOT'] . $parsed_url_path);
    }

    public static function plugin_basename($file)
    {
        $plugin_dir = self::normalize_path(WP_PLUGIN_DIR);
        $mu_plugin_dir = self::normalize_path(WPMU_PLUGIN_DIR);

        // Get relative path from plugins directory.
        $file = preg_replace('#^' . preg_quote($plugin_dir, '#') . '/|^' . preg_quote($mu_plugin_dir, '#') . '/#', '', self::normalize_path($file));
        return trim($file, '/');
    }

    public static function change_file_extension($file, $extension)
    {
        return str_replace(pathinfo($file, PATHINFO_EXTENSION), $extension, $file);
    }

    /**
     * Removes all query strings from url
     * @param $url
     * @return false|string
     */
    public static function remove_query_all($url)
    {
        $pos = strpos($url, '?');
        if ($pos === false)
            return $url;

        return substr($url, 0, $pos);
    }

    /**
     * Copy of wordpress get_home_path, but accessible not only for wp-admin
     * Get the absolute filesystem path to the root of the WordPress installation
     * (i.e. filesystem path of siteurl)
     *
     * @return string Full filesystem path to the root of the WordPress installation
     */
    public static function site_path()
    {
        $home = set_url_scheme(get_option('home'), 'http');
        $siteurl = set_url_scheme(get_option('siteurl'), 'http');

        if (!empty($home) && 0 !== strcasecmp($home, $siteurl)) {
            $wp_path_rel_to_home = str_ireplace($home, '', $siteurl); /* $siteurl - $home */
            $pos = strripos(str_replace('\\', '/', $_SERVER['SCRIPT_FILENAME']), trailingslashit($wp_path_rel_to_home));
            $home_path = substr($_SERVER['SCRIPT_FILENAME'], 0, $pos);
            $home_path = trailingslashit($home_path);
        }
        else {
            $home_path = ABSPATH;
        }

        return str_replace('\\', '/', $home_path);
    }

    /**
     * Removes WP query string from URL
     * @param $url
     * @return string|string[]|null
     */
    public static function remove_query($url)
    {
        return preg_replace('~(\?|&amp;|&#038;|&)+ver=[a-z\d-_.]+~i', '', $url);
    }

    /**
     * Redirects to URL
     *
     * @param string $url
     * @param array $params
     */
    public static function redirect($url = '', $params = array())
    {
        $url = self::url_format($url, $params);

        @header('Location: ' . $url);
        exit();
    }

    /**
     * Formats URL
     *
     * @param string $url
     * @param array $params
     * @param boolean $skip_empty
     * @param string $separator
     * @return string
     */
    public static function url_format(string $url = '', array $params = array(), bool $skip_empty = false, string $separator = '&')
    {
        if ($url != '') {
            $parse_url = @parse_url($url);
            $url = '';

            if (!empty($parse_url['scheme'])) {
                $url .= $parse_url['scheme'] . '://';

                if (!empty($parse_url['user'])) {
                    $url .= $parse_url['user'];

                    if (!empty($parse_url['pass'])) {
                        $url .= ':' . $parse_url['pass'];
                    }
                }

                if (!empty($parse_url['host'])) {
                    $url .= $parse_url['host'];
                }

                if (!empty($parse_url['port']) and $parse_url['port'] != 80) {
                    $url .= ':' . (int)$parse_url['port'];
                }
            }

            if (!empty($parse_url['path'])) {
                $url .= $parse_url['path'];
            }

            if (!empty($parse_url['query'])) {
                $old_params = array();
                parse_str($parse_url['query'], $old_params);

                $params = array_merge($old_params, $params);
            }

            $query = self::url_query($params);

            if ($query != '') {
                $url .= '?' . $query;
            }

            if (!empty($parse_url['fragment'])) {
                $url .= '#' . $parse_url['fragment'];
            }
        }
        else {
            $query = self::url_query($params, $skip_empty, $separator);

            if ($query != '') {
                $url = '?' . $query;
            }
        }

        return $url;
    }

    /**
     * Formats query string
     *
     * @param array $params
     * @param boolean $skip_empty
     * @param string $separator
     * @return string
     */
    public static function url_query($params = array(), $skip_empty = false, $separator = '&')
    {
        $str = '';
        static $stack = array();

        foreach ((array)$params as $key => $value) {
            if ($skip_empty === true and empty($value)) {
                continue;
            }

            $stack[] = $key;

            if (is_array($value)) {
                if (count($value)) {
                    $str .= ($str != '' ? '&' : '') .
                        self::url_query($value, $skip_empty, $key);
                }
            }
            else {
                $name = '';
                foreach ($stack as $key) {
                    $name .= ($name != '' ? '[' . $key . ']' : $key);
                }
                $str .= ($str != '' ? $separator : '') . $name . '=' . rawurlencode($value);
            }

            array_pop($stack);
        }

        return $str;
    }

    /**
     * Returns the apache, nginx version
     *
     * @return string
     */
    public static function get_server_version()
    {
        $sig = explode('/', $_SERVER['SERVER_SOFTWARE']);
        $temp = isset($sig[1]) ? explode(' ', $sig[1]) : array('0');
        return $temp[0];
    }

    public static function get_server_load()
    {
        if (function_exists('sys_getloadavg')) {
            return sys_getloadavg()[0];
        }

        if (PHP_OS !== 'WINNT' and PHP_OS !== 'WIN32') {
            if (@file_exists('/proc/loadavg')) {

                if ($fh = @fopen('/proc/loadavg', 'r')) {
                    $data = @fread($fh, 6);
                    @fclose($fh);
                    $load_avg = explode(" ", $data);
                    $server_load = trim($load_avg[0]);
                }
            }
            else {

                $data = @system('uptime');
                preg_match('/(.*):{1}(.*)/', $data, $matches);
                $load_arr = explode(',', $matches[2]);
                $server_load = trim($load_arr[0]);
            }
        }
        else {
            $cmd = "wmic cpu get loadpercentage /all";
            @exec($cmd, $output);

            if ($output) {
                foreach ($output as $line) {
                    if ($line && preg_match("/^\d+\$/", $line)) {
                        $server_load = $line;
                        break;
                    }
                }
            }
        }

        if (empty($server_load)) {
            $server_load = __('N/A', 'wpopt');
        }

        return $server_load;
    }

    /**
     * Checks if current request is REST REQUEST
     * @param $url
     * @return bool
     */
    public static function is_rest_request($url)
    {
        if (defined('REST_REQUEST') and REST_REQUEST)
            return true;

        // in case when called before constant is set
        // wp filters are not available in that case
        return str_contains($url, '/wp-json/');
    }

    public static function is_function_disabled($function_name)
    {
        return in_array($function_name, array_map('trim', explode(',', ini_get('disable_functions'))), true);
    }

    public static function is_shell_exec_available()
    {
        if (self::is_safe_mode_active())
            return false;

        // Is shell_exec or escapeshellcmd or escapeshellarg disabled?
        if (array_intersect(array('shell_exec', 'escapeshellarg', 'escapeshellcmd'), array_map('trim', explode(',', @ini_get('disable_functions')))))
            return false;

        // Can we issue a simple echo command?
        if (!@shell_exec('echo WP Backup'))
            return false;

        return true;
    }

    public static function is_safe_mode_active($ini_get_callback = 'ini_get')
    {
        if (($safe_mode = @call_user_func($ini_get_callback, 'safe_mode')) and strtolower($safe_mode) != 'off')
            return true;

        return false;
    }

    public static function size2bytes($val)
    {
        $val = trim($val);

        if (empty($val))
            return 0;

        $val = preg_replace('/[^\dkmgtb]/', '', strtolower($val));

        if (!preg_match("/\b(\d+(?:\.\d+)?)\s*([kmgt]?b)\b/", trim($val), $matches))
            return absint($val);

        $val = absint($matches[1]);

        switch ($matches[2]) {
            case 'gb':
                $val *= 1024;
            case 'mb':
                $val *= 1024;
            case 'kb':
                $val *= 1024;
        }

        return $val;
    }

    public static function download_file($file_path, $delete = false)
    {
        $file_path = trim($file_path);

        if (!file_exists($file_path) or headers_sent())
            return false;

        ob_start();

        header('Expires: 0');
        header("Cache-Control: public");
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Content-Description: File Transfer');
        header('Content-Type: application/force-download');
        header('Content-Type: application/octet-stream');
        header('Content-Type: application/download');
        header('Content-Disposition: attachment; filename=' . basename($file_path) . ';');
        header('Content-Transfer-Encoding: binary');
        header('Content-Length: ' . filesize($file_path));

        ob_clean();
        flush();

        $chunkSize = 1024 * 1024;
        $handle = fopen($file_path, 'rb');

        while (!feof($handle)) {
            $buffer = fread($handle, $chunkSize);
            echo $buffer;
            ob_flush();
            flush();
        }
        fclose($handle);

        if ($delete) {
            unlink($file_path);
        }

        exit();
    }

    /**
     * @param  $log
     * @param string $filename
     * @param bool $force
     */
    public static function write_log($log, string $filename = '', bool $force = false)
    {
        if (WP_DEBUG or SHZN_DEBUG or $force) {

            if (is_array($log) || is_object($log)) {
                $log = print_r($log, true);
            }

            if ($filename) {
                file_put_contents(self::normalize_path(WP_CONTENT_DIR) . DIRECTORY_SEPARATOR . $filename, $log, FILE_APPEND);
            }
            else {
                error_log($log);
            }
        }
    }

    /**
     *
     * @param int $current Current number.
     * @param int $total Total number.
     * @return string Number in percentage
     *
     * @access public
     */
    public static function format_percentage($current, $total)
    {
        if ($total == 0)
            return 0;

        return ($total > 0 ? round(($current / $total) * 100, 2) : 0) . '%';
    }

    /**
     * Internal helper function to sanitize a string from user input or from the db
     *
     * @param string $str String to sanitize.
     * @param bool $keep_newlines Optional. Whether to keep newlines. Default: false.
     * @return string Sanitized string.
     * @since 1.0.0
     *
     */
    public static function sanitize_text_field($str, $keep_newlines = false)
    {
        if (is_object($str) or is_array($str)) {
            return '';
        }

        $str = (string)$str;

        $filtered = wp_check_invalid_utf8($str);

        if (str_contains($filtered, '<')) {
            $filtered = wp_pre_kses_less_than($filtered);
            // This will strip extra whitespace for us.
            $filtered = wp_strip_all_tags($filtered, false);

            // Use HTML entities in a special case to make sure no later
            // newline stripping stage could lead to a functional tag.
            $filtered = str_replace("<\n", "&lt;\n", $filtered);
        }

        if (!$keep_newlines) {
            $filtered = preg_replace('/[\r\n\t ]+/', ' ', $filtered);
        }

        return trim($filtered);
    }

    /**
     * check if time left is more than a margin otherwise try to rise it
     *
     * @param int $margin
     * @param int $extend
     * @return int|bool
     */
    public static function safe_time_limit(int $margin = 0, int $extend = 0)
    {
        static $time_reset = WP_START_TIMESTAMP;

        if (($max_exec_time = absint(ini_get('max_execution_time'))) === 0) {
            return true;
        }

        $left_time = $max_exec_time - (microtime(true) - $time_reset);

        if ($margin > $left_time) {

            if ($extend and self::rise_time_limit($extend)) {
                $time_reset = microtime(true);
                return $extend;
            }

            return false;
        }

        return $left_time;
    }

    public static function verify_nonce($name, $nonce = false)
    {
        if (!$nonce) {
            $nonce = trim($_REQUEST['_wpnonce']);
        }

        return wp_verify_nonce($nonce, $name);
    }
}
