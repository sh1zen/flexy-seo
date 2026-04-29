<?php
/**
 * @author    sh1zen
 * @copyright Copyright (C) 2025.
 * @license   http://www.gnu.org/licenses/gpl.html GNU/GPL
 */

namespace FlexySEO\core;

use WPS\core\Ajax;
use WPS\core\UtilEnv;

/**
 * Main class, used to set up the plugin
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

        $this->load_textdomain();

        wps_maybe_upgrade('wpfs', WPFS_VERSION, WPFS_ADMIN . "upgrades/");
    }

    private function register_actions(): void
    {
        // Plugin Activation/Deactivation.
        register_activation_hook(WPFS_FILE, array($this, 'plugin_activation'));
        register_deactivation_hook(WPFS_FILE, array($this, 'plugin_deactivation'));

        add_filter('plugin_row_meta', array($this, 'donate_link'), 10, 4);
        add_filter("plugin_action_links_{$this->plugin_basename}", array($this, 'extra_plugin_link'), 10, 2);
    }

    /**
     * Loads text domain for the plugin.
     */
    private function load_textdomain(): void
    {
        $locale = apply_filters('wpfs_plugin_locale', get_locale(), 'wpfs');

        $mo_file = 'wpfs' . '-' . $locale . '.mo';

        if (load_textdomain('wpfs', WP_LANG_DIR . '/plugins/flexy-seo/' . $mo_file)) {
            return;
        }

        load_textdomain('wpfs', WPFS_ABSPATH . 'languages/' . $mo_file);
    }

    public static function getInstance(): PluginInit
    {
        if (!isset(self::$_instance)) {
            return self::Initialize();
        }

        return self::$_instance;
    }

    public static function Initialize(): PluginInit
    {
        $object = self::$_instance = new self();

        /**
         * Keep Ajax requests fast:
         * if doing ajax : load only ajax handler and return
         */
        if (wp_doing_ajax()) {
            add_action('wp_ajax_wps', array($object, 'intercept_legacy_core_settings_autosave'), 0);
            add_action('wp_ajax_wpfs_autosave_core_settings', array($object, 'autosave_core_settings_ajax'), 0);

            /**
             * Instancing all modules that need to interact in the Ajax process
             */
            wps('wpfs')->moduleHandler->setup_modules('ajax');
        }
        elseif (wp_doing_cron()) {

            /**
             * Instancing all modules that need to interact in the cron process
             */
            wps('wpfs')->moduleHandler->setup_modules('cron');
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
            wps('wpfs')->moduleHandler->setup_modules('admin');
        }
        else {

            /**
             * Instancing all modules that need to interact only on the web-view
             */
            wps('wpfs')->moduleHandler->setup_modules('web-view');
        }

        /**
         * Instancing all modules that need to be always loaded
         */
        wps('wpfs')->moduleHandler->setup_modules('autoload');

        return self::$_instance;
    }

    public function intercept_legacy_core_settings_autosave(): void
    {
        if (($_REQUEST['mod'] ?? '') !== 'settings' || ($_REQUEST['mod_action'] ?? '') !== 'autosave_settings') {
            return;
        }

        $serialized_form = (string)wp_unslash($_REQUEST['mod_form'] ?? '');

        if (!$this->autosave_payload_belongs_to_wpfs($serialized_form)) {
            return;
        }

        $this->save_core_settings_autosave_payload($serialized_form);
    }

    public function autosave_core_settings_ajax(): void
    {
        $nonce = sanitize_text_field(wp_unslash($_POST['nonce'] ?? ''));

        if (!wp_verify_nonce($nonce, 'wpfs-ajax-nonce')) {
            Ajax::response([
                'text'  => __('It seems that you are not allowed to do this request.', 'wpfs'),
                'title' => __('Autosave error', 'wpfs')
            ], 'error');
        }

        $this->save_core_settings_autosave_payload((string)wp_unslash($_POST['form'] ?? ''));
    }

    private function autosave_payload_belongs_to_wpfs(string $serialized_form): bool
    {
        if ($serialized_form === '') {
            return false;
        }

        parse_str($serialized_form, $form_data);

        return isset($form_data[wps('wpfs')->settings->get_context()]) || (isset($form_data['option_page']) && $form_data['option_page'] === 'wpfs-settings');
    }

    private function save_core_settings_autosave_payload(string $serialized_form): void
    {
        if (!current_user_can('manage_options')) {
            Ajax::response([
                'text'  => __('It seems that you are not allowed to do this request.', 'wpfs'),
                'title' => __('Autosave error', 'wpfs')
            ], 'error');
        }

        parse_str($serialized_form, $form_data);

        $context = wps('wpfs')->settings->get_context();
        $input = $form_data[$context] ?? [];
        $module_slug = is_array($input) ? sanitize_key($input['change'] ?? '') : '';
        $requested_module = sanitize_key(wp_unslash($_POST['module'] ?? $_REQUEST['module'] ?? ''));

        if (!$module_slug && $requested_module) {
            $module_slug = $requested_module;
        }

        if (!$module_slug && !empty($form_data['option_panel'])) {
            $module_slug = sanitize_key(preg_replace('#^settings-#', '', (string)$form_data['option_panel']));
        }

        if (!$module_slug && is_array($input) && $this->looks_like_modules_handler_payload($input)) {
            $module_slug = 'modules_handler';
        }

        if (!$module_slug || !is_array($input)) {
            Ajax::response([
                'text'  => __('Cannot detect which module must be saved.', 'wpfs'),
                'title' => __('Autosave error', 'wpfs')
            ], 'error');
        }

        $object = wps('wpfs')->moduleHandler->get_module_instance($module_slug);

        if (is_null($object) || $object->restricted_access('settings')) {
            Ajax::response([
                'text'  => __('It seems that you are not allowed to do this request.', 'wpfs'),
                'title' => __('Autosave error', 'wpfs')
            ], 'error');
        }

        $valid = $object->validate_settings($input);
        $saved = wps('wpfs')->settings->get($object->slug, []) == $valid || wps('wpfs')->settings->update($object->slug, $valid, true);

        if (!$saved) {
            Ajax::response([
                'text'  => __('Unable to save settings.', 'wpfs'),
                'title' => __('Autosave error', 'wpfs')
            ], 'error');
        }

        Ajax::response([
            'text'  => __('Changes saved', 'wpfs'),
            'title' => __('Autosave', 'wpfs')
        ]);
    }

    private function looks_like_modules_handler_payload(array $input): bool
    {
        foreach (wps('wpfs')->moduleHandler->get_modules('all', false) as $module) {
            if (array_key_exists($module['slug'], $input)) {
                return true;
            }
        }

        return false;
    }

    /**
     * What to do when the plugin on plugin activation
     *
     * @param boolean $network_wide Is network wide.
     */
    public function plugin_activation($network_wide): void
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

    private function activate(): void
    {
        wps('wpfs')->settings->activate();

        /**
         * Hook for the plugin activation
         */
        do_action('wpfs-activate');
    }

    /**
     * What to do when the plugin on plugin deactivation
     *
     * @param boolean $network_wide Is network wide.
     */
    public function plugin_deactivation($network_wide): void
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

    private function deactivate(): void
    {
        /**
         * Hook for the plugin deactivation
         */
        do_action('wpfs-deactivate');
    }

    /**
     * Add donate link to plugin description in /wp-admin/plugins.php
     */
    public function donate_link($plugin_meta, $plugin_file, $plugin_data, $status): array
    {
        if ($plugin_file == $this->plugin_basename) {
            $plugin_meta[] = '&hearts; <a target="_blank" rel="noopener noreferrer" href="https://www.paypal.com/donate/?business=dev.sh1zen%40outlook.it&item_name=Thank+you+in+advance+for+the+kind+donations.+You+will+sustain+me+developing+FlexySEO.&currency_code=EUR">' . __('Donate with PayPal', 'wpfs') . '</a>';
        }

        return $plugin_meta;
    }


    /**
     * Add link to settings in Plugins list page
     */
    public function extra_plugin_link($links, $file)
    {
        $links[] = sprintf('<a href="%s">%s</a>', admin_url('admin.php?page=wpfs-seo'), __('Settings', 'wpfs'));

        return $links;
    }
}
