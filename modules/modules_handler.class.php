<?php
/**
 * @author    sh1zen
 * @copyright Copyright (C) 2025.
 * @license   http://www.gnu.org/licenses/gpl.html GNU/GPL
 */

namespace FlexySEO\modules;

use WPS\modules\Module;

class Mod_modules_handler extends Module
{
    public static ?string $name = 'Modules';

    public array $scopes = array('core-settings');

    protected string $context = 'wpfs';

    public function restricted_access($context = ''): bool
    {
        return !current_user_can('manage_options');
    }

    protected function print_header(): string
    {
        ob_start();
        ?>
        <p class="description"><?php esc_html_e('Disable the Flexy SEO modules you do not use. Core settings and this module manager stay active so you can re-enable modules later.', 'wpfs'); ?></p>
        <?php
        return ob_get_clean();
    }

    protected function setting_fields($filter = ''): array
    {
        $fields = array();

        foreach (wps($this->context)->moduleHandler->get_modules('all', false) as $module) {
            $slug = $module['slug'];

            if (in_array($slug, $this->locked_modules(), true)) {
                continue;
            }

            $fields[] = $this->setting_field($module['name'], $slug, 'checkbox', array(
                'default_value' => true,
            ));
        }

        return $fields;
    }

    public function validate_settings($input, $filtering = false): array
    {
        $valid = parent::validate_settings($input, $filtering);

        foreach ($this->locked_modules() as $slug) {
            $valid[$slug] = true;
        }

        return $valid;
    }

    private function locked_modules(): array
    {
        return array('settings', 'modules_handler');
    }
}

return __NAMESPACE__;
