<?php
/**
 * @author    sh1zen
 * @copyright Copyright (C)  2022
 * @license   http://www.gnu.org/licenses/gpl.html GNU/GPL
 */

namespace FlexySEO\core;

use SHZN\core\UtilEnv;

/**
 * Main class, used to setup the plugin
 */
class PluginInit
{
    private static PluginInit $_instance;

    /**
     * Holds the plugin base name
     */
    public string $plugin_basename;

    /**
     * Holds the plugin base url
     */
    public string $plugin_base_url;

    /**
     * Holds admin page presenter
     * @var ?\FlexySeo\core\PagesHandler
     */
    public ?PagesHandler $adminPageHandler = null;

    public function __construct()
    {
        $this->plugin_basename = UtilEnv::plugin_basename(WPFS_FILE);
        $this->plugin_base_url = UtilEnv::path_to_url(WPFS_ABSPATH);

        if (is_admin()) {

            $this->register_actions();
        }

        $this->load_textdomain('wpfs');

        $this->maybe_upgrade();
    }

    private function register_actions()
    {
        // Plugin Activation/Deactivation.
        register_activation_hook(WPFS_FILE, array($this, 'plugin_activation'));
        register_deactivation_hook(WPFS_FILE, array($this, 'plugin_deactivation'));

        add_filter('plugin_row_meta', array($this, 'donate_link'), 10, 4);
        add_filter("plugin_action_links_{$this->plugin_basename}", array($this, 'extra_plugin_link'), 10, 2);
    }

    /**
     * Loads text domain for the plugin.
     *
     * @param $domain
     * @return bool
     * @action plugins_loaded
     */
    private function load_textdomain($domain)
    {
        $locale = apply_filters('wpfs_plugin_locale', get_locale(), $domain);

        $mo_file = $domain . '-' . $locale . '.mo';

        if (load_textdomain($domain, WP_LANG_DIR . '/plugins/flexy-seo/' . $mo_file)) {
            return true;
        }

        return load_textdomain($domain, WPFS_ABSPATH . 'languages/' . $mo_file);
    }

    private function maybe_upgrade()
    {
        $version = shzn('wpfs')->settings->get('ver', false);

        // need upgrade
        if (!$version or version_compare($version, WPFS_VERSION, '<')) {
            require_once dirname(__FILE__) . '/upgrader.php';
        }
    }

    public static function getInstance()
    {
        if (!isset(self::$_instance)) {
            return self::Initialize();
        }

        return self::$_instance;
    }

    public static function Initialize()
    {
        $object = self::$_instance = new self();

        /**
         * Keep Ajax requests fast:
         * if doing ajax : load only ajax handler and return
         */
        if (wp_doing_ajax()) {

            /**
             * Instancing all modules that need to interact in the Ajax process
             */
            shzn('wpfs')->moduleHandler->setup_modules('ajax');
        }
        elseif (wp_doing_cron()) {

            /**
             * Instancing all modules that need to interact in the cron process
             */
            shzn('wpfs')->moduleHandler->setup_modules('cron');
        }
        elseif (is_admin()) {

            require_once WPFS_ADMIN . 'PagesHandler.class.php';

            /**
             * Load the admin pages handler
             */
            $object->adminPageHandler = new PagesHandler();

            /**
             * Instancing all modules that need to interact in admin area
             */
            shzn('wpfs')->moduleHandler->setup_modules('admin');
        }
        else {

            /**
             * Instancing all modules that need to interact only on the web-view
             */
            shzn('wpfs')->moduleHandler->setup_modules('web-view');
        }

        /**
         * Instancing all modules that need to be always loaded
         */
        shzn('wpfs')->moduleHandler->setup_modules('autoload');

        return self::$_instance;
    }

    /**
     * What to do when the plugin on plugin activation
     *
     * @param boolean $network_wide Is network wide.
     * @return void
     *
     * @access public
     */
    public function plugin_activation($network_wide)
    {
        if (is_multisite() and $network_wide) {
            $ms_sites = (array)get_sites();

            foreach ($ms_sites as $ms_site) {
                switch_to_blog($ms_site->blog_id);
                $this->activate();
                restore_current_blog();
            }
        }
        else {
            $this->activate();
        }
    }

    private function activate()
    {
        shzn('wpfs')->settings->activate();

        /**
         * Hook for the plugin activation
         * @since 1.0.0
         */
        do_action('wpfs-activate');
    }

    /**
     * What to do when the plugin on plugin deactivation
     *
     * @param boolean $network_wide Is network wide.
     * @return void
     *
     * @access public
     */
    public function plugin_deactivation($network_wide)
    {
        if (is_multisite() and $network_wide) {
            $ms_sites = (array)get_sites();

            foreach ($ms_sites as $ms_site) {
                switch_to_blog($ms_site->blog_id);
                $this->deactivate();
                restore_current_blog();
            }
        }
        else {
            $this->deactivate();
        }
    }

    private function deactivate()
    {
        /**
         * Hook for the plugin deactivation
         * @since 1.0.0
         */
        do_action('wpfs-deactivate');
    }

    /**
     * Add donate link to plugin description in /wp-admin/plugins.php
     *
     * @param array $plugin_meta
     * @param string $plugin_file
     * @param string $plugin_data
     * @param string $status
     * @return array
     */
    public function donate_link($plugin_meta, $plugin_file, $plugin_data, $status)
    {
        if ($plugin_file == $this->plugin_basename) {
            $plugin_meta[] = '&hearts; <a target="_blank" href="https://www.paypal.com/donate/?business=dev.sh1zen%40outlook.it&item_name=Thank+you+in+advanced+for+the+kind+donations.+You+will+sustain+me+developing+FlexySEO.&currency_code=EUR">' . __('Buy me a beer', 'wpfs') . ' :o)</a>';
        }

        return $plugin_meta;
    }


    /**
     * Add link to settings in Plugins list page
     *
     * @wp-hook plugin_action_links
     * @param $links
     * @param $file
     * @return mixed
     */
    public function extra_plugin_link($links, $file)
    {
        $links[] = sprintf('<a href="%s">%s</a>', admin_url('admin.php?page=seo'), __('Settings', 'wpfs'));

        return $links;
    }
}
