<?php
/**
 * @author    sh1zen
 * @copyright Copyright (C) 2025.
 * @license   http://www.gnu.org/licenses/gpl.html GNU/GPL
 */

namespace FlexySEO\modules;

use FlexySEO\core\PluginInit;
use WPS\modules\Module;

class Mod_seo_audit extends Module
{
    public static ?string $name = 'SEO Audit';

    public array $scopes = array('admin-page');

    protected string $context = 'wpfs';

    public function restricted_access($context = ''): bool
    {
        switch ($context) {
            case 'render-admin':
                return !current_user_can('edit_posts');

            default:
                return false;
        }
    }

    public function register_panel($parent, $capability = 'edit_posts')
    {
        $handler = PluginInit::getInstance()->adminPageHandler ?? null;

        if (!$handler) {
            return false;
        }

        add_submenu_page($parent, __('SEO Audit', 'wpfs'), __('SEO Audit', 'wpfs'), $capability, 'wpfs-seo-audit', array($handler, 'render_seo_audit'));
        add_submenu_page(null, __('SEO Audit Detail', 'wpfs'), __('SEO Audit Detail', 'wpfs'), $capability, 'wpfs-seo-audit-detail', array($handler, 'render_seo_audit_detail'));

        return true;
    }
}

return __NAMESPACE__;
