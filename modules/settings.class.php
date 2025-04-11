<?php
/**
 * @author    sh1zen
 * @copyright Copyright (C) 2025.
 * @license   http://www.gnu.org/licenses/gpl.html GNU/GPL
 */

namespace FlexySEO\modules;

use WPS\core\RequestActions;
use WPS\core\addon\Exporter;
use WPS\core\Graphic;
use WPS\modules\Module;

class Mod_Settings extends Module
{
    public array $scopes = array('core-settings', 'admin');

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
                    $response &= wps('wpopt')->moduleHandler->upgrade();
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