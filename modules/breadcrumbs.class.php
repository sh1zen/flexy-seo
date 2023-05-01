<?php
/**
 * @author    sh1zen
 * @copyright Copyright (C) 2023.
 * @license   http://www.gnu.org/licenses/gpl.html GNU/GPL
 */

namespace FlexySEO\modules;

use SHZN\modules\Module;

class Mod_breadcrumbs extends Module
{
    public $scopes = array('settings', 'admin-page', 'autoload');

    public function __construct()
    {
        parent::__construct('wpfs');

        if (!(wp_doing_cron() or wp_doing_ajax() or is_admin())) {
            require_once WPFS_MODULES . 'breadcrumbs/WPFS_Breadcrumb.php';

            add_action('wp_head', array($this, 'print_style'));
        }
    }

    public function print_style()
    {
        $style = apply_filters("wpfs_breadcrumb_style", false);

        if ($style and is_string($style)) {
            echo $style;
            return;
        }
        ?>
        <style>
            .wpfs-breadcrumb {
                white-space: nowrap;
                text-overflow: ellipsis;
                padding: 0;
                margin: 0 10px;
            }

            .wpfs-breadcrumb-item {
                display: inline-block;
                text-overflow: ellipsis;
                margin: 0;
                padding: 0;
                color: #f5822f;
            }

            .wpfs-breadcrumb-current {
                font-weight: bold;
                color: #5a5a5a;
                cursor: auto;
            }
        </style>
        <?php
    }

    public function render_admin_page()
    {
        ?>
        <section class="shzn-wrap">
            <block class="shzn">
                <section class='shzn-header'><h1>SEO / Breadcrumbs</h1></section>
                <?php
                echo $this->render_settings();
                ?>
            </block>
        </section>
        <?php
    }

    protected function setting_fields($filter = '')
    {
        $fields['general'] = $this->group_setting_fields(
            $this->setting_field(__('Status:', 'wpfs'), false, 'separator'),
            $this->setting_field(__('Active', 'wpfs'), "active", 'checkbox', ['default_value' => true]),
            $this->setting_field(__('Flexed Breadcrumbs', 'wpfs'), "flexed", 'checkbox', ['depend' => 'active', 'default_value' => false]),
            $this->setting_field(__('Highlight last page', 'wpfs'), "last_page", 'checkbox', ['depend' => 'active']),
            $this->setting_field(__('Separator', 'wpfs'), "separator", 'text', ['depend' => 'active', 'default_value' => '>']),
        //$this->setting_field(__('Dropdown last', 'wpfs'), "dropdown_last", 'checkbox'),
        );

        $fields['home'] = $this->group_setting_fields(
            $this->setting_field(__('Home:', 'wpfs'), false, 'separator', ['depend' => 'active']),
            $this->setting_field(__('Prefix', 'wpfs'), "home.prefix", 'text', ['default_value' => 'Home:', 'depend' => 'active']),
            $this->setting_field(__('Format', 'wpfs'), "home.format", 'text', ['depend' => ['flexed', 'active']])
        );

        $fields['author'] = $this->group_setting_fields(
            $this->setting_field(__('Author:', 'wpfs'), false, 'separator', ['depend' => ['active']]),
            $this->setting_field(__('Prefix', 'wpfs'), "author.prefix", 'text', ['default_value' => 'Author for:', 'depend' => 'active']),
            $this->setting_field(__('Format', 'wpfs'), "author.format", 'text', ['depend' => ['flexed', 'active']])
        );

        $fields['search'] = $this->group_setting_fields(
            $this->setting_field(__('Search:', 'wpfs'), false, 'separator', ['depend' => 'active']),
            $this->setting_field(__('Prefix', 'wpfs'), "search.prefix", 'text', ['default_value' => 'You have searched for:', 'depend' => 'active']),
            $this->setting_field(__('Format', 'wpfs'), "search.format", 'text', ['depend' => ['flexed', 'active']])
        );

        $fields['archive'] = $this->group_setting_fields(
            $this->setting_field(__('Archive:', 'wpfs'), false, 'separator', ['depend' => 'active']),
            $this->setting_field(__('Prefix', 'wpfs'), "archive.prefix", 'text', ['default_value' => 'Archive for:', 'depend' => 'active']),
        );

        $fields['404'] = $this->group_setting_fields(
            $this->setting_field(__('404:', 'wpfs'), false, 'separator', ['depend' => 'active']),
            $this->setting_field(__('Prefix', 'wpfs'), "404.prefix", 'text', ['default_value' => 'Page not found', 'depend' => 'active']),
            $this->setting_field(__('Format', 'wpfs'), "404.format", 'text', ['depend' => ['flexed', 'active']])
        );

        $post_types = get_post_types(array('public' => true), 'objects');

        if (!empty($post_types)) {

            $taxonomies = get_taxonomies(array('public' => true), 'names');

            foreach ($post_types as $post_type) {

                $fields[$post_type->name] = $this->group_setting_fields(
                    $this->setting_field(ucwords($post_type->name), false, 'separator', ['depend' => 'active']),
                    $this->setting_field("Show breadcrumbs", "post_type.{$post_type->name}.active", 'checkbox', ['default_value' => true, 'depend' => 'active']),
                    $this->setting_field("Main Taxonomy", "post_type.{$post_type->name}.maintax", 'dropdown', ['depend' => 'active', 'list' => $taxonomies, 'default_value' => 'category', 'allow_empty' => false]),
                    $this->setting_field("Format", "post_type.{$post_type->name}.format", 'text', ['depend' => ['active', 'flexed']]),
                    $post_type->has_archive ? $this->setting_field(sprintf(__("%s archive format", 'wpfs'), ucwords($post_type->name)), "post_type_archive.{$post_type->name}.format", 'text', ['depend' => ['active', 'flexed']]) : [],
                    $post_type->has_archive ? $this->setting_field(sprintf(__("Show post type for %s", 'wpfs'), ucwords($post_type->name)), "post_type_archive.{$post_type->name}.show", 'checkbox', ['depend' => ['active', '!flexed']]) : [],
                );
            }
        }

        $fields['taxonomy'] = $this->group_setting_fields(
            $this->setting_field(__('Breadcrumb Taxonomies Archive format:', 'wpfs'), false, 'separator', ['depend' => 'flexed'])
        );

        //settings for each taxonomy
        foreach (get_taxonomies(array('public' => true), 'objects') as $tax_type_object) {

            $tax_type = $tax_type_object->name;
            $fields['taxonomy'][] = $this->setting_field(ucwords($tax_type_object->label), "tax.{$tax_type}.format", 'text', ['depend' => ['active', 'flexed']]);
        }

        return $this->group_setting_sections($fields, $filter);
    }
}

return __NAMESPACE__;