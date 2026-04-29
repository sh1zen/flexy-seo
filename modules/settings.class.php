<?php
/**
 * @author    sh1zen
 * @copyright Copyright (C) 2025.
 * @license   http://www.gnu.org/licenses/gpl.html GNU/GPL
 */

namespace FlexySEO\modules;

use WPS\core\Ajax;
use WPS\core\RequestActions;
use WPS\core\addon\Exporter;
use WPS\core\Graphic;
use WPS\modules\Module;

class Mod_Settings extends Module
{
    public array $scopes = array('core-settings', 'admin', 'ajax');

    protected string $context = 'wpfs';

    public function restricted_access($context = ''): bool
    {
        switch ($context) {

            case 'ajax':
            case 'settings':
                return !current_user_can('manage_options');

            default:
                return false;
        }
    }

    public function actions(): void
    {
        RequestActions::request($this->action_hook, function ($action) {

            $response = false;

            switch ($action) {
                case 'reset_options':
                    $response = wps('wpfs')->settings->reset();
                    $response &= wps('wpfs')->moduleHandler->upgrade();
                    break;

                case 'restore_options':
                    $response = wps('wpfs')->moduleHandler->upgrade();
                    break;

                case 'export_options':

                    require_once WPS_ADDON_PATH . 'Exporter.class.php';

                    $exporter = new Exporter();

                    $exporter->set_raw(wps('wpfs')->settings->export());
                    $exporter->format('text');
                    $exporter->download('wpfs-export.conf');

                    unset($exporter);
                    break;

                case 'import_options':
                    $response = wps('wpfs')->settings->import($_REQUEST['conf_data']);
                    $response &= wps('wpfs')->moduleHandler->upgrade();
                    break;
            }

            if ($response) {
                $this->add_notices('success', __('Action was correctly executed', $this->context));
            }
            else {
                $this->add_notices('warning', __('Action execution failed', $this->context));
            }
        });
    }

    protected function init(): void
    {
        if (wp_doing_ajax()) {
            add_action('wp_ajax_wps', [$this, 'intercept_legacy_autosave_ajax'], 0);
            add_action('wp_ajax_wpfs_autosave_core_settings', [$this, 'autosave_core_settings_ajax']);
        }
    }

    public function intercept_legacy_autosave_ajax(): void
    {
        if (($_REQUEST['mod'] ?? '') !== 'settings' || ($_REQUEST['mod_action'] ?? '') !== 'autosave_settings') {
            return;
        }

        if ($this->restricted_access('ajax')) {
            Ajax::response([
                'text'  => __('It seems that you are not allowed to do this request.', 'wpfs'),
                'title' => __('Autosave error', 'wpfs')
            ], 'error');
        }

        $serialized_form = (string)wp_unslash($_REQUEST['mod_form'] ?? '');

        if (!$this->payload_belongs_to_wpfs($serialized_form)) {
            return;
        }

        $this->save_autosave_payload($serialized_form);
    }

    public function ajax_handler($args = array()): void
    {
        if (($args['action'] ?? '') !== 'autosave_settings') {
            parent::ajax_handler($args);
            return;
        }

        $this->save_autosave_payload((string)($args['form_data'] ?? ''));
    }

    public function autosave_core_settings_ajax(): void
    {
        $nonce = sanitize_text_field(wp_unslash($_POST['nonce'] ?? ''));

        if (!wp_verify_nonce($nonce, 'wpfs-ajax-nonce') || $this->restricted_access('ajax')) {
            Ajax::response([
                'text'  => __('It seems that you are not allowed to do this request.', 'wpfs'),
                'title' => __('Autosave error', 'wpfs')
            ], 'error');
        }

        $this->save_autosave_payload((string)wp_unslash($_POST['form'] ?? ''));
    }

    private function save_autosave_payload(string $serialized_form): void
    {
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
                'text'  => __('Invalid settings payload.', 'wpfs'),
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

    private function payload_belongs_to_wpfs(string $serialized_form): bool
    {
        if ($serialized_form === '') {
            return false;
        }

        parse_str($serialized_form, $form_data);

        return isset($form_data[wps('wpfs')->settings->get_context()]) || (isset($form_data['option_page']) && $form_data['option_page'] === 'wpfs-settings');
    }

    protected function print_footer(): string
    {
        ob_start();
        ?>
        <form method="POST" autocapitalize="off" autocomplete="off">

            <?php RequestActions::nonce_field($this->action_hook); ?>

            <block class="wps-gridRow">
                <row class="wps-custom-action wps-row">
                    <?php

                    echo RequestActions::get_action_button($this->action_hook, 'reset_options', __('Reset Plugin options', 'wpfs'));

                    echo RequestActions::get_action_button($this->action_hook, 'restore_options', __('Restore Plugin options', 'wpfs'));

                    echo RequestActions::get_action_button($this->action_hook, 'export_options', __('Export Plugin options', 'wpfs'), 'button-primary');

                    ?>
                </row>
                <row class="wps-custom-action wps-row">
                    <?php

                    Graphic::generate_field(array(
                        'id'      => 'conf_data',
                        'type'    => 'textarea',
                        'context' => 'block'
                    ));

                    echo RequestActions::get_action_button($this->action_hook, 'import_options', __('Import Plugin options', 'wpfs'), 'button-primary');

                    ?>
                </row>
            </block>
        </form>
        <?php
        return ob_get_clean();
    }
}

return __NAMESPACE__;
