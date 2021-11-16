<?php
/**
 * @author    sh1zen
 * @copyright Copyright (C)  2021
 * @license   http://www.gnu.org/licenses/gpl.html GNU/GPL
 */

namespace SHZN\core;

class Settings
{
    /**
     * Plugin options name
     */
    public $option_name;

    private $settings;

    private $context;

    public function __construct($option_name)
    {
        $this->option_name = $option_name;

        $this->context = $option_name;

        $this->settings = get_option($option_name);

        if (!$this->settings)
            $this->settings = array();

        if (is_admin()) {
            add_action('admin_init', array($this, 'register_hooks'));
        }
    }

    public static function get_option($settings, $setting_path, $default = false)
    {
        // remove last separator
        $setting_path = rtrim($setting_path, '.');

        while (strlen($setting_path) > 0) {

            $pos = strpos($setting_path, '.');

            if ($pos === false)
                $pos = strlen($setting_path);

            $slug = substr($setting_path, 0, $pos);

            if (!isset($settings[$slug])) {
                return $default;
            }

            $settings = $settings[$slug];

            // update search key
            $setting_path = substr_replace($setting_path, '', 0, $pos + 1);
        }

        if (is_array($settings) or is_object($settings)) {
            $settings = wp_parse_args($settings, $default);
        }

        return $settings;
    }

    public static function check($settings, $key, $default = false)
    {
        if (isset($settings[$key]))
            return $settings[$key];

        return $default;
    }



    /**
     * Access to settings by path -> delimiter: "."
     * @param string $setting_path
     * @param array $default
     * @param bool $update -> if no option were found, update theme, with defaults values
     * @return array|mixed|object|string
     */
    public function get($setting_path = '', $default = [], $update = false)
    {
        $settings = $this->settings;

        if (empty($setting_path))
            return $settings;

        // remove last separator
        $setting_path = rtrim($setting_path, '.');

        // keep in memory
        $context = $setting_path;

        while (strlen($setting_path) > 0) {

            $pos = strpos($setting_path, '.');

            if ($pos === false)
                $pos = strlen($setting_path);

            $slug = substr($setting_path, 0, $pos);

            if (!isset($settings[$slug])) {

                if ($update) {
                    $this->update($default, $context);
                }

                return $default;
            }

            $settings = $settings[$slug];

            // update search key
            $setting_path = substr_replace($setting_path, '', 0, $pos + 1);
        }

        if (is_array($settings) or is_object($settings)) {
            $settings = wp_parse_args($settings, $default);
        }

        return $settings;
    }

    public function update($option_data, $context)
    {
        if (empty($context))
            return false;

        if (!isset($this->settings[$context]))
            $this->settings[$context] = array();

        $this->settings[$context] = wp_parse_args($option_data, $this->settings[$context]);

        return $this->reset($this->settings);
    }

    public function reset($options = array())
    {
        $this->settings = $options;
        return update_option($this->option_name, $options);
    }

    public function render_core_settings()
    {
        /**
         * Consider only modules with settings handlers
         */
        $modules = shzn($this->context)->moduleHandler->get_modules(array('scopes' => array('core-settings')));

        settings_errors();
        ?>
        <section class="shzn-wrap shzn">
            <section class='shzn-header'><h1><?php _e('Core Settings', $this->context); ?></h1></section>
            <block class="shzn">
                <?php

                if (!empty($modules)) {
                    echo $this->generateHTML_tabpan($modules);
                }
                ?>
            </block>
        </section>
        <?php
    }

    private function generateHTML_tabpan($modules)
    {
        $fields = array();

        foreach ($modules as $module) {

            $object = shzn($this->context)->moduleHandler->get_module_instance($module);

            if (is_null($object))
                continue;

            $field = array(
                'id'          => "settings-{$module['slug']}",
                'tab-title'   => $module['name'],
                'panel-title' => $module['name'] . " setup",
                'callback'    => array($object, 'render_settings')
            );

            $fields[] = $field;
        }

        return Graphic::generateHTML_tabs_panels($fields);
    }

    public function register_hooks()
    {
        register_setting("{$this->option_name}-settings", $this->option_name, array(
            'type'              => 'array',
            'sanitize_callback' => array($this, 'validate')
        ));
    }

    public function render_modules_settings()
    {
        $mod_handler = shzn($this->context)->moduleHandler;

        /**
         * Consider only modules with settings handlers
         */
        $modules = $mod_handler->get_modules(array('scopes' => array('settings')));

        settings_errors();
        ?>
        <section class="shzn-wrap shzn">
            <section class='shzn-header'><h1><?php _e('Modules Settings', $this->context); ?></h1></section>
            <block class="shzn">
                <?php

                if (!empty($modules)) {
                    echo $this->generateHTML_tabpan($modules);
                }
                else {
                    echo "<h2>" . sprintf(__("No modules enabled. To enable them go <a href='%s'>here</a>.", $this->context), admin_url("admin.php?page={$this->option_name}-settings")) . "</h2>";
                }
                ?>
            </block>
        </section>
        <?php
    }

    public function activate()
    {
        $options = get_option($this->option_name, array());

        if (!$options or empty($options)) {

            /**
             * Load all modules to be allow them to set up their options
             */
            shzn($this->context)->moduleHandler->setup_modules('all');

            $this->reset($this->settings);
        }
    }

    public function validate($input)
    {
        if (!isset($input['change']))
            return $input;

        $object = shzn($this->context)->moduleHandler->get_module_instance($input['change']);

        if (is_null($object))
            die();

        $valid = $object->validate_settings($input, $object->settings);

        $this->settings = wp_parse_args(array($object->slug => $valid), $this->settings);

        return $this->settings;
    }

    public function export()
    {
        return base64_encode(serialize($this->settings));
    }

    public function import($import_settings)
    {
        $settings = unserialize(base64_decode($import_settings));

        if (!$settings or !is_array($settings))
            return false;

        return $this->reset($settings);
    }
}
