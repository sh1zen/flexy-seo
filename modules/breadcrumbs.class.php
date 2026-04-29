<?php
/**
 * @author    sh1zen
 * @copyright Copyright (C) 2025.
 * @license   http://www.gnu.org/licenses/gpl.html GNU/GPL
 */

namespace FlexySEO\modules;

use WPS\core\Ajax;
use WPS\modules\Module;

class Mod_breadcrumbs extends Module
{
    public static ?string $name = 'Breadcrumbs';

    public array $scopes = array('settings', 'admin-page', 'autoload', 'ajax');

    protected string $context = 'wpfs';

    public function restricted_access($context = ''): bool
    {
        switch ($context) {
            case 'ajax':
            case 'settings':
            case 'render-admin':
                return !current_user_can('customize');

            default:
                return false;
        }
    }

    public function print_style(): void
    {
        $style = apply_filters("wpfs_breadcrumb_style", false);

        if (is_string($style)) {
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

            .wpfs-breadcrumb-dropdown {
                position: relative;
                overflow: visible;
                color: #3f4a56;
            }

            .wpfs-breadcrumb-dropdown-panel {
                display: inline-block;
                position: relative;
            }

            .wpfs-breadcrumb-dropdown-toggle {
                display: inline-flex;
                align-items: center;
                gap: 4px;
                cursor: pointer;
                color: #3f4a56;
                font-weight: 500;
                list-style: none;
            }

            .wpfs-breadcrumb-dropdown-toggle::-webkit-details-marker {
                display: none;
            }

            .wpfs-breadcrumb-dropdown-toggle span[aria-hidden="true"]::before {
                content: "";
                display: inline-block;
                width: 6px;
                height: 6px;
                border-right: 1px solid currentColor;
                border-bottom: 1px solid currentColor;
                transform: translateY(-2px) rotate(45deg);
            }

            .wpfs-breadcrumb-list {
                display: none;
                position: fixed;
                top: 0;
                left: 0;
                z-index: 100000;
                width: min(360px, calc(100vw - 16px));
                min-width: 280px;
                max-width: calc(100vw - 16px);
                max-height: 280px;
                overflow: auto;
                padding: 0;
                margin: 0;
                background: #fff;
                border: 1px solid #ddd;
                box-shadow: 0 8px 24px rgba(0, 0, 0, .14);
                white-space: normal;
                list-style: none;
            }

            .wpfs-breadcrumb-dropdown-panel[open] .wpfs-breadcrumb-list {
                display: block;
            }

            .wpfs-breadcrumb-list.is-open {
                display: block;
            }

            .wpfs-breadcrumb-search-item {
                position: sticky;
                top: 0;
                z-index: 1;
                display: block;
                margin: 0;
                padding: 10px;
                background: #fff;
                border-bottom: 1px solid #e5e7eb;
            }

            .wpfs-breadcrumb-search {
                width: 100%;
                box-sizing: border-box;
                padding: 7px 10px;
                border: 1px solid #d1d5db;
                border-radius: 4px;
                background: #fff;
                color: #1f2937;
                font: inherit;
                font-size: 13px;
                line-height: 18px;
                outline: none;
            }

            .wpfs-breadcrumb-search:focus {
                border-color: #7c98b6;
                box-shadow: 0 0 0 2px rgba(124, 152, 182, .18);
            }

            .wpfs-breadcrumb-list-item {
                display: block;
                margin: 0;
                padding: 0;
                color: #374151;
                white-space: nowrap;
            }

            .wpfs-breadcrumb-list-item.is-hidden {
                display: none;
            }

            .wpfs-breadcrumb-list-item a {
                display: block;
                padding: 9px 18px;
                color: inherit;
                text-decoration: none;
            }

            .wpfs-breadcrumb-list-item a:hover,
            .wpfs-breadcrumb-list-item a:focus {
                background: #f5f5f5;
            }

        </style>
        <script>
            (function () {
                function positionDropdown(panel) {
                    var summary = panel.querySelector('.wpfs-breadcrumb-dropdown-toggle');
                    var list = panel.wpfsDropdownList || panel.querySelector('.wpfs-breadcrumb-list');

                    if (!summary || !list || !panel.open) {
                        return;
                    }

                    list.classList.add('is-open');
                    list.style.visibility = 'hidden';

                    var summaryRect = summary.getBoundingClientRect();
                    var width = list.offsetWidth || 280;
                    var left = Math.max(8, Math.min(summaryRect.left - Math.max(0, width - summaryRect.width), window.innerWidth - width - 8));
                    var top = Math.max(8, summaryRect.bottom + 3);

                    list.style.left = left + 'px';
                    list.style.top = top + 'px';
                    list.style.visibility = '';
                }

                function filterDropdown(panel) {
                    var list = panel.wpfsDropdownList || panel.querySelector('.wpfs-breadcrumb-list');
                    var search = list ? list.querySelector('.wpfs-breadcrumb-search') : null;
                    var query = search ? search.value.trim().toLowerCase() : '';

                    if (!list) {
                        return;
                    }

                    list.querySelectorAll('.wpfs-breadcrumb-list-item').forEach(function (item) {
                        var text = item.textContent.trim().toLowerCase();
                        item.classList.toggle('is-hidden', query !== '' && text.indexOf(query) === -1);
                    });

                    positionDropdown(panel);
                }

                function closeOtherDropdowns(current) {
                    document.querySelectorAll('.wpfs-breadcrumb-dropdown-panel[open]').forEach(function (panel) {
                        if (panel !== current) {
                            var list = panel.wpfsDropdownList;

                            if (list) {
                                list.classList.remove('is-open');
                            }

                            panel.removeAttribute('open');
                        }
                    });
                }

                function initDropdowns() {
                    document.querySelectorAll('.wpfs-breadcrumb-dropdown-panel').forEach(function (panel) {
                        if (panel.dataset.wpfsDropdownReady) {
                            return;
                        }

                        panel.dataset.wpfsDropdownReady = '1';
                        panel.wpfsDropdownList = panel.querySelector('.wpfs-breadcrumb-list');

                        if (panel.wpfsDropdownList && panel.wpfsDropdownList.parentNode !== document.body) {
                            document.body.appendChild(panel.wpfsDropdownList);
                        }

                        panel.addEventListener('toggle', function () {
                            var list = panel.wpfsDropdownList;
                            var search = list ? list.querySelector('.wpfs-breadcrumb-search') : null;

                            if (panel.open) {
                                closeOtherDropdowns(panel);
                                if (search) {
                                    search.value = '';
                                }
                                if (list) {
                                    list.scrollTop = 0;
                                }
                                filterDropdown(panel);
                                positionDropdown(panel);

                                if (search) {
                                    window.setTimeout(function () {
                                        try {
                                            search.focus({preventScroll: true});
                                        }
                                        catch (error) {
                                            search.focus();
                                        }
                                    }, 0);
                                }
                            }
                            else if (list) {
                                list.classList.remove('is-open');
                            }
                        });

                        var search = panel.wpfsDropdownList ? panel.wpfsDropdownList.querySelector('.wpfs-breadcrumb-search') : null;

                        if (search) {
                            search.addEventListener('input', function () {
                                filterDropdown(panel);
                            });
                        }
                    });
                }

                document.addEventListener('click', function (event) {
                    if (!event.target.closest('.wpfs-breadcrumb-dropdown-panel') && !event.target.closest('.wpfs-breadcrumb-list')) {
                        closeOtherDropdowns(null);
                    }
                });

                document.addEventListener('keydown', function (event) {
                    if (event.key === 'Escape') {
                        closeOtherDropdowns(null);
                    }
                });

                window.addEventListener('resize', function () {
                    document.querySelectorAll('.wpfs-breadcrumb-dropdown-panel[open]').forEach(positionDropdown);
                });

                window.addEventListener('scroll', function () {
                    document.querySelectorAll('.wpfs-breadcrumb-dropdown-panel[open]').forEach(positionDropdown);
                }, true);

                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', initDropdowns);
                }
                else {
                    initDropdowns();
                }
            })();
        </script>
        <?php
    }

    protected function render_sub_modules(): void
    {
        ?>
        <section class="wps-wrap wpfs-breadcrumbs-page">
            <block class="wps">
                <section class='wps-header'><h1>SEO / Breadcrumbs</h1></section>
                <?php
                echo $this->render_settings();
                ?>
            </block>
        </section>
        <?php
    }

    protected function init(): void
    {
        if (wp_doing_ajax()) {
            add_action('wp_ajax_wpfs_autosave_breadcrumbs_settings', [$this, 'autosave_settings_ajax']);
        }

        if (wps_core()->doing_webview()) {

            require_once WPFS_MODULES . 'breadcrumbs/WPFS_Breadcrumb.php';

            add_action('wp_head', array($this, 'print_style'));
        }
    }

    protected function setting_fields($filter = ''): array
    {
        $fields['active'] =
            $this->group_setting_fields(
                $this->setting_field(__('Status:', 'wpfs'), false, 'separator'),
                $this->setting_field(__('Active', 'wpfs'), "active", 'checkbox', ['default_value' => true]),
            );

        $fields['general'] = $this->group_setting_fields(

            $this->setting_field(__('Flexed Breadcrumbs', 'wpfs'), "flexed", 'checkbox', ['depend' => 'active', 'default_value' => false]),
            $this->setting_field(__('Highlight last page', 'wpfs'), "last_page", 'checkbox', ['depend' => 'active']),
            $this->setting_field(__('Separator', 'wpfs'), "separator", 'text', ['depend' => 'active', 'default_value' => '>']),
            $this->setting_field(__('Show child categories dropdown', 'wpfs'), "dropdown_last", 'checkbox', ['depend' => 'active', 'default_value' => false]),
            $this->setting_field(__('Child dropdown label', 'wpfs'), "dropdown_label", 'text', ['depend' => ['active', 'dropdown_last'], 'default_value' => __("Scegli l'area geografica", 'wpfs')]),
            $this->setting_field(__('Hide empty child categories', 'wpfs'), "dropdown_hide_empty", 'checkbox', ['depend' => ['active', 'dropdown_last'], 'default_value' => false]),
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

    public function autosave_settings_ajax(): void
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

        if (!is_array($input) || ($input['change'] ?? '') !== $this->slug) {
            Ajax::response([
                'text'  => __('Invalid settings payload.', 'wpfs'),
                'title' => __('Autosave error', 'wpfs')
            ], 'error');
        }

        $valid = $this->validate_settings($input);
        $saved = wps('wpfs')->settings->update($this->slug, $valid, true);

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
}

return __NAMESPACE__;
