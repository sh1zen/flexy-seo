<?php
/**
 * @author    sh1zen
 * @copyright Copyright (C)  2021
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
            include_once WPFS_MODULES . 'breadcrumbs/WPFS_Breadcrumb.php';

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
            <section class='shzn-header'><h1>SEO / Breadcrumbs</h1></section>
            <block class="shzn">
                <?php
                echo $this->render_settings('');
                ?>
            </block>
        </section>
        <?php
    }

    protected function setting_fields($filter = '')
    {
        $fields = array(
            array('type' => 'separator', 'name' => __('Status:', 'wpfs')),
            array('type' => 'checkbox', 'name' => __('Active', 'wpfs'), 'id' => 'active', 'value' => $this->option('active', false)),
            array('type' => 'checkbox', 'name' => __('Flexed Breadcrumb', 'wpfs'), 'id' => 'flexed', 'value' => $this->option('flexed', false)),

            array('type' => 'separator', 'name' => __('Options:', 'wpfs')),
            array('type' => 'checkbox', 'name' => __('Highlight last page', 'wpfs'), 'id' => 'last_page', 'value' => $this->option('last_page', false)),
            //array('type' => 'checkbox', 'name' => __('Dropdown last', 'wpfs'), 'id' => 'dropdown_last', 'value' => $this->option('dropdown_last', false)),
            array('type' => 'text', 'name' => __('Separator', 'wpfs'), 'id' => 'separator', 'value' => $this->option('separator', '>')),
            array('type' => 'text', 'name' => __('Home text', 'wpfs'), 'id' => 'home_txt', 'value' => $this->option('home_txt', 'Home')),
            array('type' => 'text', 'name' => __('Author prefix', 'wpfs'), 'id' => 'prefix.author', 'value' => $this->option('prefix.author', '')),
            array('type' => 'text', 'name' => __('Search prefix', 'wpfs'), 'id' => 'prefix.search', 'value' => $this->option('prefix.search', 'You have searched for:')),
            array('type' => 'text', 'name' => __('Archive prefix', 'wpfs'), 'id' => 'prefix.archive', 'value' => $this->option('prefix.archive', 'Archive for:')),
            array('type' => 'text', 'name' => __('404 prefix', 'wpfs'), 'id' => 'prefix.404', 'value' => $this->option('prefix.404', '')),

            array('parent' => 'flexed', 'type' => 'separator', 'name' => __('Special pages format:', 'wpfs')),
            array('parent' => 'flexed', 'type' => 'text', 'name' => __('Author', 'wpfs'), 'id' => 'format.author', 'value' => $this->option('format.author', '')),
            array('parent' => 'flexed', 'type' => 'text', 'name' => __('Search', 'wpfs'), 'id' => 'format.search', 'value' => $this->option('format.search', '')),
            array('parent' => 'flexed', 'type' => 'text', 'name' => __('404', 'wpfs'), 'id' => 'format.404', 'value' => $this->option('format.404', '')),
        );

        $post_types = get_post_types(array('public' => true), 'objects');

        if (!empty($post_types)) {

            $fields[] = array('parent' => 'flexed', 'type' => 'separator', 'name' => __('Breadcrumb Post Type format:', 'wpfs'));

            foreach ($post_types as $post_type) {
                $fields[] = array('parent' => 'flexed', 'type' => 'text', 'name' => ucwords($post_type->name), 'id' => 'format.post_type.' . $post_type->name, 'value' => $this->option('format.post_type.' . $post_type->name, ''));

                if ($post_type->has_archive)
                    $fields_post_type_archive[] = array('parent' => 'flexed', 'type' => 'text', 'name' => ucwords($post_type->name), 'id' => 'format.post_type_archive.' . $post_type->name, 'value' => $this->option('format.post_type_archive.' . $post_type->name, ''));

            }
        }

        if (!empty($fields_post_type_archive)) {

            $fields[] = array('parent' => 'flexed', 'type' => 'separator', 'name' => __('Breadcrumb Post Type Archive format:', 'wpfs'));

            foreach ($fields_post_type_archive as $field_post_type_archive) {
                $fields[] = $field_post_type_archive;
            }
        }

        $fields[] = array('parent' => 'flexed', 'type' => 'separator', 'name' => __('Breadcrumb Taxonomies Archive format:', 'wpfs'));

        //settings for each taxonomy
        foreach (get_taxonomies(array('public' => true), 'objects') as $tax_type_object) {

            $tax_type = $tax_type_object->name;
            $fields[] = array('parent' => 'flexed', 'type' => 'text', 'name' => ucwords($tax_type_object->label), 'id' => 'format.tax.' . $tax_type, 'value' => $this->option('format.tax.' . $tax_type, ''));
        }

        return $fields;
    }
}

return __NAMESPACE__;