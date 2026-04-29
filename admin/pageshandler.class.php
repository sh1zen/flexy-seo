<?php
/**
 * @author    sh1zen
 * @copyright Copyright (C) 2025.
 * @license   http://www.gnu.org/licenses/gpl.html GNU/GPL
 */

namespace FlexySeo\core;

use WPS\core\UtilEnv;

/**
 * Creates the menu page for the plugin.
 *
 * Provides the functionality necessary for rendering the page corresponding
 * to the menu with which this page is associated.
 */
class PagesHandler
{
    public function __construct()
    {
        add_action('admin_menu', array($this, 'add_plugin_pages'));
        add_action('admin_enqueue_scripts', array($this, 'register_assets'));

        add_action("admin_print_styles-post.php", array($this, 'enqueue_scripts_edit_page'));
        add_action('admin_print_styles-post-new.php', array($this, 'enqueue_scripts_edit_page'));

        //add_action('admin_notices', [$this, 'notice'], 10, 0);
    }

    public function notice()
    {
        global $pagenow;

        if (isset($_GET['wpfs-dismiss-notice'])) {

            wps('wpfs')->options->add(wps_core()->get_cuID(), 'dismissed', true, 'admin-notice', MONTH_IN_SECONDS);
        }
        elseif ($pagenow == 'index.php' and !wps('wpfs')->options->get(wps_core()->get_cuID(), 'dismissed', 'admin-notice', false)) {
            ?>
            <div class="notice notice-info is-dismissible">
                <h3>Help me to build <a href="<?php echo admin_url('admin.php?page=wp-flexyseo'); ?>">Flexy SEO</a>.
                </h3>
                <p><?php echo sprintf(__("Donate with PayPal <a href='%s'>here</a> or leave a 5-star review <a href='%s'>here</a>.", 'wpfs'), "https://www.paypal.com/donate?business=dev.sh1zen%40outlook.it&item_name=Thank+you+in+advance+for+the+kind+donations.+You+will+sustain+me+developing+Flexy+SEO.&currency_code=EUR", "https://wordpress.org/support/plugin/flexy-seo/reviews/?filter=5"); ?></p>
                <a href="?wpfs-dismiss-notice"><?php echo __('Dismiss', 'wpfs') ?></a>
            </div>
            <?php
        }
    }

    public function add_plugin_pages(): void
    {
        add_menu_page('Flexy SEO', 'Flexy SEO', 'customize', 'wp-flexyseo', array($this, 'render_main'), 'dashicons-admin-site');

        /**
         * Modules - sub pages
         */
        foreach (wps('wpfs')->moduleHandler->get_modules(array('scopes' => 'admin-page')) as $module) {
            wps('wpfs')->moduleHandler->get_module_instance($module)->register_panel('wp-flexyseo');
        }

        /**
         * Plugin core settings
         */
        add_submenu_page('wp-flexyseo', __('WPFS Settings', 'wpfs'), __('Settings', 'wpfs'), 'manage_options', 'wpfs-settings', array($this, 'render_core_settings'));

        /**
         * Plugin core settings
         */
        add_submenu_page('wp-flexyseo', __('WPFS FAQ', 'wpfs'), __('FAQ', 'wpfs'), 'edit_posts', 'wpfs-faqs', array($this, 'render_faqs'));

        add_action('wpfs_enqueue_panel_scripts', [$this, 'enqueue_scripts']);
    }

    public function enqueue_scripts(): void
    {
        wp_enqueue_style('wpfs_css');
        wp_enqueue_script('vendor-wps-js');
        wp_enqueue_script('wpfs_admin_js');
    }

    public function enqueue_scripts_edit_page(): void
    {
        wp_enqueue_style('wpfs_css');
        wp_enqueue_script('vendor-wps-js');
    }

    public function render_core_settings(): void
    {
        $this->enqueue_scripts();

        wps('wpfs')->settings->render_core_settings();
    }

    public function register_assets(): void
    {
        $assets_url = PluginInit::getInstance()->plugin_base_url;

        $min = wps_core()->online ? '.min' : '';
        $style_version = filemtime(WPFS_ABSPATH . "assets/style{$min}.css") ?: WPFS_VERSION;
        $script_version = filemtime(WPFS_ABSPATH . 'assets/admin.js') ?: WPFS_VERSION;
        $preview_sample = $this->get_preview_sample();

        wp_register_style('wpfs_css', "{$assets_url}assets/style{$min}.css", ['vendor-wps-css'], $style_version);
        wp_register_script('wpfs_admin_js', "{$assets_url}assets/admin.js", ['jquery'], $script_version, true);

        wp_localize_script('wpfs_admin_js', 'wpfsAdmin', [
            'ajaxUrl'         => admin_url('admin-ajax.php'),
            'ajaxNonce'       => wp_create_nonce('wpfs-ajax-nonce'),
            'savingText'      => __('Saving changes...', 'wpfs'),
            'savedText'       => __('Changes saved', 'wpfs'),
            'errorText'       => __('Autosave failed', 'wpfs'),
            'offlineText'     => __('Connection error. Changes are not saved yet.', 'wpfs'),
            'pendingText'     => __('Unsaved changes', 'wpfs'),
            'inactiveText'    => __('Autosave unavailable. Reload the page and try again.', 'wpfs'),
            'tokenSearchText' => __('Search replacements...', 'wpfs'),
            'tokenEmptyText'  => __('No replacements found', 'wpfs'),
            'preview'         => [
                'siteName'          => get_bloginfo('name', 'display'),
                'siteDescription'   => get_bloginfo('description', 'display'),
                'homeUrl'           => home_url('/'),
                'sampleUrl'         => $preview_sample['url'],
                'language'          => get_bloginfo('language'),
                'date'              => date_i18n('Y-m-d'),
                'time'              => date_i18n('H:i:s'),
                'titleLimit'        => 60,
                'descriptionLimit'  => 160,
                'defaultTitle'      => $preview_sample['title'],
                'defaultDescription' => $preview_sample['description'],
                'defaultImage'      => $preview_sample['image'] ?: wps('wpfs')->settings->get('seo.social.facebook.logo_url', '') ?: wps('wpfs')->settings->get('seo.org.logo_url.wide', '') ?: wps('wpfs')->settings->get('seo.org.logo_url.small', ''),
                'labels'            => [
                    'title'            => __('Search snippet', 'wpfs'),
                    'social'           => __('Social preview', 'wpfs'),
                    'titleCounter'     => __('Title length', 'wpfs'),
                    'descriptionCounter' => __('Description length', 'wpfs'),
                    'noImage'          => __('No image selected', 'wpfs'),
                    'imageAlt'         => __('Social preview image', 'wpfs'),
                    'exampleUrl'       => __('example-page', 'wpfs'),
                ],
                'tokens'            => [
                    'title'       => $preview_sample['title'],
                    'description' => $preview_sample['description'],
                    'sep'         => wps('wpfs')->settings->get('seo.title.separator', '-'),
                    'sitename'    => get_bloginfo('name', 'display'),
                    'sitedesc'    => get_bloginfo('description', 'display'),
                    'excerpt'     => $preview_sample['excerpt'],
                    'resume'      => $preview_sample['description'],
                    'search'      => __('example search', 'wpfs'),
                    'found_post'  => '24',
                    'pagetotal'   => '3',
                    'pagenumber'  => '1',
                    'language'    => get_bloginfo('language'),
                    'date'        => date_i18n('Y-m-d'),
                    'time'        => date_i18n('H:i:s'),
                ],
            ],
            'tokens'          => [
                ['token' => 'title', 'label' => 'title', 'description' => __('Default WordPress title of the current object.', 'wpfs')],
                ['token' => 'description', 'label' => 'description', 'description' => __('Default description for the current object.', 'wpfs')],
                ['token' => 'sep', 'label' => 'sep', 'description' => __('Configured title separator.', 'wpfs')],
                ['token' => 'sitename', 'label' => 'sitename', 'description' => __('Site name.', 'wpfs')],
                ['token' => 'sitedesc', 'label' => 'sitedesc', 'description' => __('Site description.', 'wpfs')],
                ['token' => 'search', 'label' => 'search', 'description' => __('Current search query.', 'wpfs')],
                ['token' => 'resume', 'label' => 'resume', 'description' => __('Generated resume of the current post.', 'wpfs')],
                ['token' => 'excerpt', 'label' => 'excerpt', 'description' => __('Excerpt of the current post.', 'wpfs')],
                ['token' => 'found_post', 'label' => 'found_post', 'description' => __('Number of found posts.', 'wpfs')],
                ['token' => 'pagetotal', 'label' => 'pagetotal', 'description' => __('Total pages for the current query.', 'wpfs')],
                ['token' => 'pagenumber', 'label' => 'pagenumber', 'description' => __('Current page number.', 'wpfs')],
                ['token' => 'language', 'label' => 'language', 'description' => __('Site language.', 'wpfs')],
                ['token' => 'date', 'label' => 'date', 'description' => __('Current date.', 'wpfs')],
                ['token' => 'time', 'label' => 'time', 'description' => __('Current time.', 'wpfs')],
                ['token' => '{query_var}', 'label' => '{query_var}', 'description' => __('Any WordPress query variable.', 'wpfs')],
                ['token' => 'meta_{meta_key}', 'label' => 'meta_{meta_key}', 'description' => __('Meta value of the current queried object.', 'wpfs')],
                ['token' => '{queried_object_property}', 'label' => '{queried_object_property}', 'description' => __('Property of the current queried object.', 'wpfs')],
            ],
        ]);

        wps_localize([
            'text_na'                => __('N/A', 'wpfs'),
            'saved'                  => __('Settings Saved', 'wpfs'),
            'error'                  => __('Request fail', 'wpfs'),
            'success'                => __('Request succeed', 'wpfs'),
            'running'                => __('Running', 'wpfs'),
            'wpfs_ajax_nonce'        => wp_create_nonce('wpfs-ajax-nonce'),
            'wpfs_autosave_saving'   => __('Saving changes...', 'wpfs'),
            'wpfs_autosave_saved'    => __('Changes saved', 'wpfs'),
            'wpfs_autosave_error'    => __('Autosave failed', 'wpfs'),
            'wpfs_autosave_offline'  => __('Connection error. Changes are not saved yet.', 'wpfs'),
            'wpfs_autosave_pending'  => __('Unsaved changes', 'wpfs'),
        ]);
    }

    private function get_preview_sample(): array
    {
        $defaults = [
            'title'       => get_bloginfo('name', 'display') ?: __('Example page title', 'wpfs'),
            'description' => get_bloginfo('description', 'display') ?: __('This is an example search snippet generated from your SEO template.', 'wpfs'),
            'excerpt'     => get_bloginfo('description', 'display') ?: __('A concise excerpt for the current content.', 'wpfs'),
            'url'         => home_url('/'),
            'image'       => '',
        ];

        $post_types = get_post_types(['public' => true], 'names');
        unset($post_types['attachment']);

        if (empty($post_types)) {
            return $defaults;
        }

        $posts = get_posts([
            'post_type'              => array_values($post_types),
            'post_status'            => 'publish',
            'posts_per_page'         => 1,
            'orderby'                => 'rand',
            'ignore_sticky_posts'    => true,
            'no_found_rows'          => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
        ]);

        if (empty($posts)) {
            return $defaults;
        }

        $post = $posts[0];
        $raw_excerpt = has_excerpt($post) ? $post->post_excerpt : $post->post_content;
        $excerpt = $this->normalize_preview_text($raw_excerpt);
        $description = wp_trim_words($excerpt, 32, '');

        return [
            'title'       => get_the_title($post) ?: $defaults['title'],
            'description' => $description ?: $defaults['description'],
            'excerpt'     => $excerpt ?: $defaults['excerpt'],
            'url'         => get_permalink($post) ?: $defaults['url'],
            'image'       => get_the_post_thumbnail_url($post, 'large') ?: '',
        ];
    }

    private function normalize_preview_text(string $value): string
    {
        $value = strip_shortcodes($value);
        $value = wp_strip_all_tags($value, true);
        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, get_bloginfo('charset') ?: 'UTF-8');
        $value = preg_replace('/\s+/u', ' ', $value);

        return trim($value);
    }

    private function get_dashboard_configuration(): array
    {
        $settings = wps('wpfs')->settings;
        $modules = wps('wpfs')->moduleHandler;
        $seo_url = admin_url('admin.php?page=wpfs-seo');
        $modules_url = admin_url('admin.php?page=wpfs-settings#settings-modules_handler');
        $core_checks = array(
            array(
                'label' => __('SEO title rewriting is active', 'wpfs'),
                'done'  => (bool)$settings->get('seo.title.rewrite', true),
                'url'   => $seo_url,
            ),
            array(
                'label' => __('Home SEO title template is configured', 'wpfs'),
                'done'  => trim((string)$settings->get('seo.home.title', '%%sitename%% %%sep%% %%sitedesc%%')) !== '',
                'url'   => $seo_url,
            ),
            array(
                'label' => __('Home meta description is configured', 'wpfs'),
                'done'  => trim((string)$settings->get('seo.home.meta_desc', '')) !== '',
                'url'   => $seo_url,
            ),
            array(
                'label' => __('Social metadata output is enabled', 'wpfs'),
                'done'  => (bool)$settings->get('seo.social.facebook.opengraph', true) || (bool)$settings->get('seo.social.twitter.card', true),
                'url'   => $seo_url,
            ),
            array(
                'label' => __('Default social image is set', 'wpfs'),
                'done'  => $this->dashboard_has_social_image(),
                'url'   => $seo_url,
            ),
            array(
                'label' => __('Schema.org output is enabled', 'wpfs'),
                'done'  => (bool)$settings->get('seo.schema.enabled', true),
                'url'   => $seo_url,
            ),
        );

        $tool_checks = array(
            array(
                'label' => __('SEO module is active', 'wpfs'),
                'done'  => $modules->module_is_active('seo'),
                'url'   => $modules->module_is_active('seo') ? $seo_url : $modules_url,
            ),
            array(
                'label' => __('Breadcrumbs module is active', 'wpfs'),
                'done'  => $modules->module_is_active('breadcrumbs') && (bool)$settings->get('breadcrumbs.active', true),
                'url'   => $modules->module_is_active('breadcrumbs') ? admin_url('admin.php?page=wpfs-breadcrumbs') : $modules_url,
            ),
            array(
                'label' => __('XML sitemap is active', 'wpfs'),
                'done'  => $modules->module_is_active('sitemap') && (bool)$settings->get('sitemap.active', true),
                'url'   => $modules->module_is_active('sitemap') ? admin_url('admin.php?page=wpfs-sitemap') : $modules_url,
            ),
            array(
                'label' => __('Redirect manager module is active', 'wpfs'),
                'done'  => $modules->module_is_active('redirects'),
                'url'   => $modules->module_is_active('redirects') ? admin_url('admin.php?page=wpfs-redirects') : $modules_url,
            ),
            array(
                'label' => __('404 monitor module is active', 'wpfs'),
                'done'  => $modules->module_is_active('not_found_monitor'),
                'url'   => $modules->module_is_active('not_found_monitor') ? admin_url('admin.php?page=wpfs-not_found_monitor') : $modules_url,
            ),
            array(
                'label' => __('SEO Audit module is active', 'wpfs'),
                'done'  => $modules->module_is_active('seo_audit'),
                'url'   => $modules->module_is_active('seo_audit') ? admin_url('admin.php?page=wpfs-seo-audit') : $modules_url,
            ),
        );

        $core_completed = count(array_filter($core_checks, static function ($check) {
            return !empty($check['done']);
        }));
        $tool_completed = count(array_filter($tool_checks, static function ($check) {
            return !empty($check['done']);
        }));
        $core_total = max(1, count($core_checks));
        $tool_total = max(1, count($tool_checks));
        $core_level = (int)round(($core_completed / $core_total) * 100);
        $tool_level = (int)round(($tool_completed / $tool_total) * 100);

        return array(
            'level'          => (int)round(($core_level * 0.65) + ($tool_level * 0.35)),
            'core_level'     => $core_level,
            'tool_level'     => $tool_level,
            'completed'      => $core_completed + $tool_completed,
            'total'          => $core_total + $tool_total,
            'core_completed' => $core_completed,
            'core_total'     => $core_total,
            'tool_completed' => $tool_completed,
            'tool_total'     => $tool_total,
            'checks'         => array_merge($core_checks, $tool_checks),
            'core_checks'    => $core_checks,
            'tool_checks'    => $tool_checks,
        );
    }

    private function dashboard_has_social_image(): bool
    {
        $settings = wps('wpfs')->settings;

        return trim((string)$settings->get('seo.social.facebook.logo_url', '')) !== ''
            || trim((string)$settings->get('seo.org.logo_url.wide', '')) !== ''
            || trim((string)$settings->get('seo.org.logo_url.small', '')) !== '';
    }

    private function render_dashboard_checklist(array $configuration): void
    {
        $pending = array_values(array_filter($configuration['checks'], static function ($check) {
            return empty($check['done']);
        }));
        $items = empty($pending) ? $configuration['checks'] : array_slice($pending, 0, 4);
        ?>
        <div class="wpfs-config-checklist">
            <div class="wpfs-config-breakdown" aria-label="<?php esc_attr_e('SEO readiness breakdown', 'wpfs'); ?>">
                <span><?php echo esc_html(sprintf(__('Core SEO: %1$s/%2$s', 'wpfs'), $configuration['core_completed'], $configuration['core_total'])); ?></span>
                <span><?php echo esc_html(sprintf(__('Technical tools: %1$s/%2$s', 'wpfs'), $configuration['tool_completed'], $configuration['tool_total'])); ?></span>
            </div>
            <span><?php echo esc_html(empty($pending) ? __('SEO readiness checks completed', 'wpfs') : __('Next recommended steps', 'wpfs')); ?></span>
            <?php foreach ($items as $item) : ?>
                <a class="<?php echo empty($item['done']) ? 'is-pending' : 'is-complete'; ?>" href="<?php echo esc_url($item['url']); ?>">
                    <span class="dashicons <?php echo empty($item['done']) ? 'dashicons-marker' : 'dashicons-yes-alt'; ?>"></span>
                    <?php echo esc_html($item['label']); ?>
                </a>
            <?php endforeach; ?>
        </div>
        <?php
    }

    public function render_seo_audit(): void
    {
        $this->enqueue_scripts();

        $filters = [
            'type'  => $this->sanitize_seo_audit_type(sanitize_key(wp_unslash($_GET['wpfs_audit_type'] ?? 'all'))),
            'issue' => $this->sanitize_seo_audit_issue(sanitize_key(wp_unslash($_GET['wpfs_audit_issue'] ?? 'all'))),
            's'     => sanitize_text_field(wp_unslash($_GET['wpfs_audit_s'] ?? '')),
        ];
        $paged = max(1, absint($_GET['wpfs_audit_paged'] ?? 1));
        $per_page = 40;

        $audit = $this->get_seo_audit_page($filters, $paged, $per_page);
        $visible_rows = $audit['rows'];
        $stats = $this->get_seo_audit_stats($visible_rows);
        $pagination_base = [
            'page'             => 'wpfs-seo-audit',
            'wpfs_audit_type'  => $filters['type'],
            'wpfs_audit_issue' => $filters['issue'],
            'wpfs_audit_s'     => $filters['s'],
        ];
        $current_list_url = add_query_arg(array_merge($pagination_base, ['wpfs_audit_paged' => $paged]), admin_url('admin.php'));
        ?>
        <section class="wps-wrap wpfs-admin-shell wpfs-audit-page">
            <header class="wpfs-page-hero">
                <div>
                    <span class="wpfs-kicker"><?php esc_html_e('Rapid metadata check', 'wpfs'); ?></span>
                    <h1><?php esc_html_e('SEO Audit', 'wpfs'); ?></h1>
                    <p><?php esc_html_e('Review titles, meta descriptions, image alt coverage, heavy images, dimensions, noindex status and canonical availability.', 'wpfs'); ?></p>
                </div>
            </header>
            <block class="wps wpfs-card">
                <div class="wpfs-audit-summary" aria-label="<?php esc_attr_e('SEO Audit summary', 'wpfs'); ?>">
                    <div>
                        <strong><?php echo esc_html(number_format_i18n($stats['shown'])); ?></strong>
                        <span><?php esc_html_e('Items shown', 'wpfs'); ?></span>
                    </div>
                    <div>
                        <strong><?php echo esc_html(number_format_i18n($stats['attention'])); ?></strong>
                        <span><?php esc_html_e('Need attention here', 'wpfs'); ?></span>
                    </div>
                    <div>
                        <strong><?php echo esc_html(number_format_i18n($stats['description_missing'])); ?></strong>
                        <span><?php esc_html_e('Missing descriptions', 'wpfs'); ?></span>
                    </div>
                    <div>
                        <strong><?php echo esc_html(number_format_i18n($stats['image_issues'])); ?></strong>
                        <span><?php esc_html_e('Image issues', 'wpfs'); ?></span>
                    </div>
                </div>
                <form class="wpfs-audit-filters" method="get">
                    <input type="hidden" name="page" value="wpfs-seo-audit">
                    <label>
                        <span><?php esc_html_e('Content', 'wpfs'); ?></span>
                        <select name="wpfs_audit_type">
                            <?php foreach ($this->get_seo_audit_type_options() as $value => $label) : ?>
                                <option value="<?php echo esc_attr($value); ?>" <?php selected($filters['type'], $value); ?>><?php echo esc_html($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>
                        <span><?php esc_html_e('Issue', 'wpfs'); ?></span>
                        <select name="wpfs_audit_issue">
                            <?php foreach ($this->get_seo_audit_issue_options() as $value => $label) : ?>
                                <option value="<?php echo esc_attr($value); ?>" <?php selected($filters['issue'], $value); ?>><?php echo esc_html($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="wpfs-audit-search">
                        <span><?php esc_html_e('Search', 'wpfs'); ?></span>
                        <input type="search" name="wpfs_audit_s" value="<?php echo esc_attr($filters['s']); ?>" placeholder="<?php esc_attr_e('Title or type...', 'wpfs'); ?>">
                    </label>
                    <button class="button button-primary" type="submit"><?php esc_html_e('Filter', 'wpfs'); ?></button>
                    <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=wpfs-seo-audit')); ?>"><?php esc_html_e('Reset', 'wpfs'); ?></a>
                </form>
                <form class="wpfs-audit-detail-lookup" method="get">
                    <input type="hidden" name="page" value="wpfs-seo-audit-detail">
                    <input type="hidden" name="wpfs_audit_type" value="<?php echo esc_attr($filters['type']); ?>">
                    <input type="hidden" name="wpfs_audit_back" value="<?php echo esc_url($current_list_url); ?>">
                    <label class="wpfs-audit-detail-field">
                        <span><?php esc_html_e('Open detail', 'wpfs'); ?></span>
                        <input type="search" name="wpfs_audit_detail" placeholder="<?php esc_attr_e('ID, title, slug, URL or row key...', 'wpfs'); ?>">
                    </label>
                    <button class="button" type="submit"><?php esc_html_e('Open detail', 'wpfs'); ?></button>
                </form>
                <table class="wp-list-table widefat fixed striped table-view-list wpfs-audit-table">
                    <thead>
                    <tr>
                        <th scope="col"><?php esc_html_e('Item', 'wpfs'); ?></th>
                        <th scope="col"><?php esc_html_e('Type', 'wpfs'); ?></th>
                        <th scope="col"><?php esc_html_e('Title', 'wpfs'); ?></th>
                        <th scope="col"><?php esc_html_e('Description', 'wpfs'); ?></th>
                        <th scope="col"><?php esc_html_e('Images', 'wpfs'); ?></th>
                        <th scope="col"><?php esc_html_e('Indexing', 'wpfs'); ?></th>
                        <th scope="col"><?php esc_html_e('Canonical', 'wpfs'); ?></th>
                        <th scope="col"><?php esc_html_e('Actions', 'wpfs'); ?></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($visible_rows)) : ?>
                        <tr>
                            <td colspan="8"><?php esc_html_e('No items match the selected filters.', 'wpfs'); ?></td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ($visible_rows as $row) : ?>
                            <tr>
                                <td>
                                    <strong><?php echo esc_html($row['label']); ?></strong>
                                    <div class="row-actions">
                                        <span><?php echo esc_html($row['status_label']); ?></span>
                                    </div>
                                </td>
                                <td><?php echo esc_html($row['type_label']); ?></td>
                                <td>
                                    <?php echo $this->render_seo_audit_badge($row['title_status'], $row['title_status_label']); ?>
                                    <span class="wpfs-audit-count"><?php echo esc_html(sprintf(_n('%s char', '%s chars', $row['title_length'], 'wpfs'), number_format_i18n($row['title_length']))); ?></span>
                                    <p><?php echo esc_html($row['display_title'] ?: __('Empty', 'wpfs')); ?></p>
                                </td>
                                <td>
                                    <?php echo $this->render_seo_audit_badge($row['description_status'], $row['description_status_label']); ?>
                                    <span class="wpfs-audit-count"><?php echo esc_html(sprintf(_n('%s char', '%s chars', $row['description_length'], 'wpfs'), number_format_i18n($row['description_length']))); ?></span>
                                    <p><?php echo esc_html($row['display_description'] ?: __('Empty', 'wpfs')); ?></p>
                                </td>
                                <td>
                                    <?php echo $this->render_seo_audit_badge($row['image_status'], $row['image_status_label']); ?>
                                    <span class="wpfs-audit-count"><?php echo esc_html(sprintf(_n('%s image', '%s images', $row['image_count'], 'wpfs'), number_format_i18n($row['image_count']))); ?></span>
                                    <p><?php echo esc_html($row['image_report']); ?></p>
                                </td>
                                <td><?php echo $this->render_seo_audit_badge($row['noindex'] ? 'bad' : 'ok', $row['noindex'] ? __('Noindex', 'wpfs') : __('Index', 'wpfs')); ?></td>
                                <td>
                                    <?php echo $this->render_seo_audit_badge($row['canonical'] ? 'ok' : 'bad', $row['canonical'] ? __('Present', 'wpfs') : __('Missing', 'wpfs')); ?>
                                    <?php if ($row['canonical']) : ?>
                                        <p><?php echo esc_html($row['canonical']); ?></p>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="wpfs-audit-actions">
                                        <?php if ($row['edit_url']) : ?>
                                            <a class="wpfs-audit-action wpfs-audit-action--edit" href="<?php echo esc_url($row['edit_url']); ?>"><span class="dashicons dashicons-edit"></span><?php esc_html_e('Edit', 'wpfs'); ?></a>
                                        <?php else : ?>
                                            <span class="wpfs-audit-muted"><?php esc_html_e('Unavailable', 'wpfs'); ?></span>
                                        <?php endif; ?>
                                        <a class="wpfs-audit-action wpfs-audit-action--details" href="<?php echo esc_url(add_query_arg(['page' => 'wpfs-seo-audit-detail', 'wpfs_audit_detail' => $row['detail_key'], 'wpfs_audit_type' => $filters['type'], 'wpfs_audit_back' => $current_list_url], admin_url('admin.php'))); ?>"><span class="dashicons dashicons-visibility"></span><?php esc_html_e('Details', 'wpfs'); ?></a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
                <div class="wpfs-audit-pagination">
                    <div class="wpfs-audit-page-current">
                        <strong><?php echo esc_html(sprintf(__('Page %s', 'wpfs'), number_format_i18n($paged))); ?></strong>
                        <small><?php echo esc_html(sprintf(__('%s items per page', 'wpfs'), number_format_i18n($per_page))); ?></small>
                    </div>
                    <div class="wpfs-audit-pagination__controls">
                        <?php if ($paged > 1) : ?>
                            <a class="wpfs-audit-page-link" href="<?php echo esc_url(add_query_arg(array_merge($pagination_base, ['wpfs_audit_paged' => $paged - 1]), admin_url('admin.php'))); ?>"><span class="dashicons dashicons-arrow-left-alt2"></span><?php esc_html_e('Previous', 'wpfs'); ?></a>
                        <?php else : ?>
                            <span class="wpfs-audit-page-link is-disabled"><span class="dashicons dashicons-arrow-left-alt2"></span><?php esc_html_e('Previous', 'wpfs'); ?></span>
                        <?php endif; ?>
                        <?php if ($audit['has_more']) : ?>
                            <a class="wpfs-audit-page-link" href="<?php echo esc_url(add_query_arg(array_merge($pagination_base, ['wpfs_audit_paged' => $paged + 1]), admin_url('admin.php'))); ?>"><?php esc_html_e('Next', 'wpfs'); ?><span class="dashicons dashicons-arrow-right-alt2"></span></a>
                        <?php else : ?>
                            <span class="wpfs-audit-page-link is-disabled"><?php esc_html_e('Next', 'wpfs'); ?><span class="dashicons dashicons-arrow-right-alt2"></span></span>
                        <?php endif; ?>
                    </div>
                </div>
            </block>
        </section>
        <?php
    }

    public function render_seo_audit_detail(): void
    {
        $this->enqueue_scripts();

        $detail = sanitize_text_field(wp_unslash($_GET['wpfs_audit_detail'] ?? ''));
        $type = $this->sanitize_seo_audit_type(sanitize_key(wp_unslash($_GET['wpfs_audit_type'] ?? 'all')));
        $back_url = esc_url_raw(wp_unslash($_GET['wpfs_audit_back'] ?? admin_url('admin.php?page=wpfs-seo-audit')));
        $back_url = wp_validate_redirect($back_url, admin_url('admin.php?page=wpfs-seo-audit'));
        $detail_row = $this->get_seo_audit_detail_row($detail, $type);
        ?>
        <section class="wps-wrap wpfs-admin-shell wpfs-audit-page wpfs-audit-detail-page">
            <header class="wpfs-page-hero">
                <div>
                    <span class="wpfs-kicker"><?php esc_html_e('Single item metadata check', 'wpfs'); ?></span>
                    <h1><?php esc_html_e('SEO Audit Detail', 'wpfs'); ?></h1>
                    <p><?php esc_html_e('Inspect one post, page or category with complete metadata values and direct edit links.', 'wpfs'); ?></p>
                </div>
                <nav class="wpfs-quick-actions" aria-label="<?php esc_attr_e('SEO Audit detail quick links', 'wpfs'); ?>">
                    <a href="<?php echo esc_url($back_url); ?>"><span class="dashicons dashicons-arrow-left-alt2"></span><?php esc_html_e('Back to audit', 'wpfs'); ?></a>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=wpfs-seo')); ?>"><span class="dashicons dashicons-search"></span><?php esc_html_e('SEO Settings', 'wpfs'); ?></a>
                </nav>
            </header>
            <block class="wps wpfs-card">
                <form class="wpfs-audit-detail-lookup is-detail-page" method="get">
                    <input type="hidden" name="page" value="wpfs-seo-audit-detail">
                    <input type="hidden" name="wpfs_audit_back" value="<?php echo esc_url($back_url); ?>">
                    <label>
                        <span><?php esc_html_e('Content', 'wpfs'); ?></span>
                        <select name="wpfs_audit_type">
                            <?php foreach ($this->get_seo_audit_type_options() as $value => $label) : ?>
                                <option value="<?php echo esc_attr($value); ?>" <?php selected($type, $value); ?>><?php echo esc_html($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="wpfs-audit-detail-field">
                        <span><?php esc_html_e('Detail item', 'wpfs'); ?></span>
                        <input type="search" name="wpfs_audit_detail" value="<?php echo esc_attr($detail); ?>" placeholder="<?php esc_attr_e('ID, title, slug, URL or row key...', 'wpfs'); ?>">
                    </label>
                    <button class="button button-primary" type="submit"><?php esc_html_e('Open detail', 'wpfs'); ?></button>
                    <a class="button" href="<?php echo esc_url($back_url); ?>"><?php esc_html_e('Back', 'wpfs'); ?></a>
                </form>
                <?php if ($detail === '') : ?>
                    <div class="wpfs-audit-detail is-empty">
                        <span class="dashicons dashicons-info"></span>
                        <p><?php esc_html_e('Enter an ID, title, slug, edit URL or row key to inspect a single item.', 'wpfs'); ?></p>
                    </div>
                <?php else : ?>
                    <?php $this->render_seo_audit_detail_panel($detail_row, $detail); ?>
                <?php endif; ?>
            </block>
        </section>
        <?php
    }

    private function get_seo_audit_type_options(): array
    {
        return [
            'all'      => __('All', 'wpfs'),
            'post'     => __('Posts', 'wpfs'),
            'page'     => __('Pages', 'wpfs'),
            'category' => __('Categories', 'wpfs'),
        ];
    }

    private function get_seo_audit_issue_options(): array
    {
        return [
            'all'                 => __('All', 'wpfs'),
            'title-too-long'      => __('Title too long', 'wpfs'),
            'title-too-short'     => __('Title too short', 'wpfs'),
            'description-missing' => __('Description missing', 'wpfs'),
            'image-alt-missing'   => __('Image alt missing', 'wpfs'),
            'image-heavy'         => __('Heavy image', 'wpfs'),
            'image-no-dimensions' => __('Image dimensions missing', 'wpfs'),
            'noindex'             => __('Noindex active', 'wpfs'),
            'canonical-missing'   => __('Canonical missing', 'wpfs'),
        ];
    }

    private function sanitize_seo_audit_type(string $type): string
    {
        return array_key_exists($type, $this->get_seo_audit_type_options()) ? $type : 'all';
    }

    private function sanitize_seo_audit_issue(string $issue): string
    {
        return array_key_exists($issue, $this->get_seo_audit_issue_options()) ? $issue : 'all';
    }

    private function get_seo_audit_detail_row(string $needle, string $type_filter): ?array
    {
        $needle = trim($needle);

        if ($needle === '') {
            return null;
        }

        if (preg_match('/^(post|page|category):(\d+)$/', $needle, $matches)) {
            return $this->get_seo_audit_detail_row_by_key($matches[1], (int)$matches[2]);
        }

        $query_value = wp_parse_url($needle, PHP_URL_QUERY);

        if ($query_value) {
            parse_str($query_value, $query_args);

            if (!empty($query_args['post'])) {
                $row = $this->get_seo_audit_detail_row_by_key('post', absint($query_args['post']));

                if ($row) {
                    return $row;
                }
            }

            if (!empty($query_args['tag_ID'])) {
                $row = $this->get_seo_audit_detail_row_by_key('category', absint($query_args['tag_ID']));

                if ($row) {
                    return $row;
                }
            }
        }

        if (ctype_digit($needle)) {
            if ($type_filter === 'category') {
                return $this->get_seo_audit_detail_row_by_key('category', (int)$needle);
            }

            $row = $this->get_seo_audit_detail_row_by_key('post', (int)$needle);

            if ($row) {
                return $row;
            }

            $row = $this->get_seo_audit_detail_row_by_key('page', (int)$needle);

            if ($row) {
                return $row;
            }

            return $this->get_seo_audit_detail_row_by_key('category', (int)$needle);
        }

        $post_types = $type_filter === 'all' ? ['post', 'page'] : array_values(array_intersect([$type_filter], ['post', 'page']));

        foreach ($post_types as $post_type) {
            $post = get_page_by_path(sanitize_title($needle), OBJECT, $post_type);

            if ($post instanceof \WP_Post && $post->post_status === 'publish') {
                return $this->build_post_seo_audit_row($post);
            }
        }

        if (!empty($post_types)) {
            $posts = get_posts([
                'post_type'              => $post_types,
                'post_status'            => 'publish',
                'posts_per_page'         => 1,
                's'                      => $needle,
                'no_found_rows'          => true,
                'update_post_meta_cache' => false,
                'update_post_term_cache' => false,
            ]);

            if (!empty($posts)) {
                return $this->build_post_seo_audit_row($posts[0]);
            }
        }

        if ($type_filter === 'all' || $type_filter === 'category') {
            $terms = get_terms([
                'taxonomy'   => 'category',
                'hide_empty' => false,
                'number'     => 1,
                'search'     => $needle,
            ]);

            if (!is_wp_error($terms) && !empty($terms)) {
                return $this->build_term_seo_audit_row($terms[0]);
            }
        }

        return null;
    }

    private function get_seo_audit_detail_row_by_key(string $type, int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        if ($type === 'category') {
            $term = get_term($id, 'category');

            return $term instanceof \WP_Term ? $this->build_term_seo_audit_row($term) : null;
        }

        $post = get_post($id);

        if (!$post instanceof \WP_Post || !in_array($post->post_type, ['post', 'page'], true) || $post->post_status !== 'publish') {
            return null;
        }

        if ($type !== $post->post_type) {
            return null;
        }

        return $this->build_post_seo_audit_row($post);
    }

    private function render_seo_audit_detail_panel(?array $row, string $needle): void
    {
        if (trim($needle) === '') {
            return;
        }

        if (!$row) {
            ?>
            <div class="wpfs-audit-detail is-empty">
                <span class="dashicons dashicons-warning"></span>
                <p><?php esc_html_e('No matching item found for the detail field.', 'wpfs'); ?></p>
            </div>
            <?php
            return;
        }

        $issues = $this->get_seo_audit_issue_labels($row['issues']);
        ?>
        <section class="wpfs-audit-detail" aria-label="<?php esc_attr_e('SEO Audit detail', 'wpfs'); ?>">
            <div class="wpfs-audit-detail__head">
                <div>
                    <span class="wpfs-kicker"><?php echo esc_html($row['detail_key']); ?></span>
                    <h2><?php echo esc_html($row['label']); ?></h2>
                    <p><?php echo esc_html($row['type_label'] . ' - ' . $row['status_label']); ?></p>
                </div>
                <div class="wpfs-audit-detail__actions">
                    <?php if ($row['canonical']) : ?>
                        <a class="button" href="<?php echo esc_url($row['canonical']); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('View', 'wpfs'); ?></a>
                    <?php endif; ?>
                    <?php if ($row['edit_url']) : ?>
                        <a class="button button-primary" href="<?php echo esc_url($row['edit_url']); ?>"><?php esc_html_e('Edit', 'wpfs'); ?></a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="wpfs-audit-detail__grid">
                <article>
                    <h3><?php esc_html_e('Title', 'wpfs'); ?></h3>
                    <div>
                        <?php echo $this->render_seo_audit_badge($row['title_status'], $row['title_status_label']); ?>
                        <span><?php echo esc_html(sprintf(_n('%s char', '%s chars', $row['title_length'], 'wpfs'), number_format_i18n($row['title_length']))); ?></span>
                    </div>
                    <p><?php echo esc_html($row['title'] ?: __('Empty', 'wpfs')); ?></p>
                </article>
                <article>
                    <h3><?php esc_html_e('Meta description', 'wpfs'); ?></h3>
                    <div>
                        <?php echo $this->render_seo_audit_badge($row['description_status'], $row['description_status_label']); ?>
                        <span><?php echo esc_html(sprintf(_n('%s char', '%s chars', $row['description_length'], 'wpfs'), number_format_i18n($row['description_length']))); ?></span>
                    </div>
                    <p><?php echo esc_html($row['description'] ?: __('Empty', 'wpfs')); ?></p>
                </article>
                <article>
                    <h3><?php esc_html_e('Indexing', 'wpfs'); ?></h3>
                    <p><?php echo $this->render_seo_audit_badge($row['noindex'] ? 'bad' : 'ok', $row['noindex'] ? __('Noindex active', 'wpfs') : __('Indexable', 'wpfs')); ?></p>
                </article>
                <article>
                    <h3><?php esc_html_e('Canonical', 'wpfs'); ?></h3>
                    <p><?php echo esc_html($row['canonical'] ?: __('Missing', 'wpfs')); ?></p>
                </article>
                <article class="wpfs-audit-detail__wide">
                    <h3><?php esc_html_e('Images', 'wpfs'); ?></h3>
                    <div>
                        <?php echo $this->render_seo_audit_badge($row['image_status'], $row['image_status_label']); ?>
                        <span><?php echo esc_html(sprintf(_n('%s image', '%s images', $row['image_count'], 'wpfs'), number_format_i18n($row['image_count']))); ?></span>
                    </div>
                    <p><?php echo esc_html($row['image_report']); ?></p>
                    <?php if (!empty($row['image_audit']['items'])) : ?>
                        <ul class="wpfs-audit-image-list">
                            <?php foreach (array_slice($row['image_audit']['items'], 0, 8) as $image_item) : ?>
                                <li>
                                    <strong><?php echo esc_html($image_item['label']); ?></strong>
                                    <span><?php echo esc_html($image_item['summary']); ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </article>
                <article class="wpfs-audit-detail__wide">
                    <h3><?php esc_html_e('Issues', 'wpfs'); ?></h3>
                    <?php if (empty($issues)) : ?>
                        <p><?php esc_html_e('No issues detected for this item.', 'wpfs'); ?></p>
                    <?php else : ?>
                        <div class="wpfs-audit-detail__issues">
                            <?php foreach ($issues as $issue_label) : ?>
                                <span><?php echo esc_html($issue_label); ?></span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </article>
            </div>
        </section>
        <?php
    }

    private function get_seo_audit_issue_labels(array $issues): array
    {
        $labels = $this->get_seo_audit_issue_options();
        unset($labels['all']);

        return array_values(array_intersect_key($labels, array_flip($issues)));
    }

    private function get_seo_audit_page(array $filters, int $paged, int $per_page): array
    {
        $rows = [];
        $matched = 0;
        $offset = ($paged - 1) * $per_page;
        $has_more = false;
        $chunk_size = 120;

        foreach (['post', 'page'] as $post_type) {
            if ($filters['type'] !== 'all' && $filters['type'] !== $post_type) {
                continue;
            }

            $query_offset = 0;

            do {
                $post_ids = get_posts([
                    'post_type'              => $post_type,
                    'post_status'            => 'publish',
                    'posts_per_page'         => $chunk_size,
                    'offset'                 => $query_offset,
                    'orderby'                => 'title',
                    'order'                  => 'ASC',
                    'fields'                 => 'ids',
                    'no_found_rows'          => true,
                    'update_post_meta_cache' => false,
                    'update_post_term_cache' => false,
                ]);

                foreach ($post_ids as $post_id) {
                    $post = get_post($post_id);

                    if (!$post instanceof \WP_Post) {
                        continue;
                    }

                    $row = $this->build_post_seo_audit_row($post);

                    if (!$this->seo_audit_row_matches_filters($row, $filters)) {
                        continue;
                    }

                    if ($matched++ < $offset) {
                        continue;
                    }

                    if (count($rows) >= $per_page) {
                        $has_more = true;
                        break 3;
                    }

                    $rows[] = $row;
                }

                $query_offset += $chunk_size;
            } while (count($post_ids) === $chunk_size);
        }

        if (!$has_more && ($filters['type'] === 'all' || $filters['type'] === 'category')) {
            $term_offset = 0;

            do {
                $term_ids = get_terms([
                    'taxonomy'   => 'category',
                    'hide_empty' => false,
                    'orderby'    => 'name',
                    'order'      => 'ASC',
                    'fields'     => 'ids',
                    'number'     => $chunk_size,
                    'offset'     => $term_offset,
                ]);

                if (is_wp_error($term_ids)) {
                    break;
                }

                foreach ($term_ids as $term_id) {
                    $term = get_term($term_id, 'category');

                    if (!$term instanceof \WP_Term) {
                        continue;
                    }

                    $row = $this->build_term_seo_audit_row($term);

                    if (!$this->seo_audit_row_matches_filters($row, $filters)) {
                        continue;
                    }

                    if ($matched++ < $offset) {
                        continue;
                    }

                    if (count($rows) >= $per_page) {
                        $has_more = true;
                        break 2;
                    }

                    $rows[] = $row;
                }

                $term_offset += $chunk_size;
            } while (count($term_ids) === $chunk_size);
        }

        return [
            'rows'     => $rows,
            'has_more' => $has_more,
        ];
    }

    private function seo_audit_row_matches_filters(array $row, array $filters): bool
    {
        if ($filters['issue'] !== 'all' && !in_array($filters['issue'], $row['issues'], true)) {
            return false;
        }

        if ($filters['s'] !== '') {
            $search = strtolower($filters['s']);
            $haystack = strtolower($row['label'] . ' ' . $row['type_label'] . ' ' . $row['title']);

            if (!str_contains($haystack, $search)) {
                return false;
            }
        }

        return true;
    }

    private function build_post_seo_audit_row(\WP_Post $post): array
    {
        $post_type_object = get_post_type_object($post->post_type);
        $description_template = function_exists('wpfs_get_post_meta_description') ? wpfs_get_post_meta_description($post, false, '') : '';

        if ($description_template === '') {
            $description_template = wps('wpfs')->settings->get("seo.post_type.$post->post_type.meta_desc", '%%description%%');
        }

        $title = $this->generate_audit_title(
            wps('wpfs')->settings->get("seo.post_type.$post->post_type.title", '%%title%%'),
            $post,
            'post'
        );
        $description = $this->normalize_seo_audit_text($this->replace_seo_audit_tokens($description_template, $post, 'post'));

        if ($description === '') {
            $description = $this->normalize_seo_audit_text(wp_trim_words(has_excerpt($post) ? $post->post_excerpt : $post->post_content, 32, ''));
        }

        return $this->build_seo_audit_row([
            'id'           => (int)$post->ID,
            'object_type'  => $post->post_type,
            'type_label'   => $post_type_object->labels->singular_name ?? $post->post_type,
            'label'        => get_the_title($post) ?: sprintf(__('Post #%s', 'wpfs'), $post->ID),
            'status_label' => ($status_object = get_post_status_object($post->post_status)) ? $status_object->label : $post->post_status,
            'title'        => $title,
            'description'  => $description,
            'image_audit'   => $this->get_post_image_audit($post),
            'noindex'      => !wps('wpfs')->settings->get("seo.post_type.$post->post_type.show", true),
            'canonical'    => get_permalink($post) ?: '',
            'edit_url'     => get_edit_post_link($post->ID, ''),
        ]);
    }

    private function build_term_seo_audit_row(\WP_Term $term): array
    {
        $term_link = get_term_link($term, $term->taxonomy);
        $description_template = function_exists('wpfs_get_term_meta_description') ? wpfs_get_term_meta_description($term, false, '') : '';

        if ($description_template === '') {
            $description_template = $term->description ?: wps('wpfs')->settings->get("seo.tax.$term->taxonomy.meta_desc", '%%description%%');
        }

        $title_template = function_exists('wpfs_get_term_meta_title') ? wpfs_get_term_meta_title($term, false, '') : '';

        if ($title_template === '') {
            $title_template = wps('wpfs')->settings->get("seo.tax.$term->taxonomy.title", '%%title%%');
        }

        return $this->build_seo_audit_row([
            'id'           => (int)$term->term_id,
            'object_type'  => 'category',
            'type_label'   => __('Category', 'wpfs'),
            'label'        => $term->name,
            'status_label' => sprintf(_n('%s item', '%s items', $term->count, 'wpfs'), number_format_i18n($term->count)),
            'title'        => $this->generate_audit_title($title_template, $term, 'term'),
            'description'  => $this->normalize_seo_audit_text($this->replace_seo_audit_tokens($description_template, $term, 'term')),
            'image_audit'   => $this->get_term_image_audit($term),
            'noindex'      => !wps('wpfs')->settings->get("seo.tax.$term->taxonomy.show", true),
            'canonical'    => is_wp_error($term_link) ? '' : $term_link,
            'edit_url'     => get_edit_term_link($term->term_id, $term->taxonomy, ''),
        ]);
    }

    private function build_seo_audit_row(array $row): array
    {
        $row['detail_key'] = ($row['object_type'] ?? 'item') . ':' . ($row['id'] ?? 0);
        $row['title_length'] = $this->seo_audit_strlen($row['title']);
        $row['description_length'] = $this->seo_audit_strlen($row['description']);
        $row['display_title'] = $this->truncate_seo_audit_text($row['title'], 90);
        $row['display_description'] = $this->truncate_seo_audit_text($row['description'], 180);
        $row['title_status'] = 'ok';
        $row['title_status_label'] = __('OK', 'wpfs');
        $row['description_status'] = 'ok';
        $row['description_status_label'] = __('OK', 'wpfs');
        $row['image_audit'] = $row['image_audit'] ?? $this->empty_image_audit();
        $row['image_count'] = $row['image_audit']['count'];
        $row['image_status'] = 'ok';
        $row['image_status_label'] = __('OK', 'wpfs');
        $row['image_report'] = $this->format_image_audit_report($row['image_audit']);
        $row['issues'] = [];

        if ($row['title_length'] < 30) {
            $row['title_status'] = 'warn';
            $row['title_status_label'] = __('Too short', 'wpfs');
            $row['issues'][] = 'title-too-short';
        }
        elseif ($row['title_length'] > 60) {
            $row['title_status'] = 'warn';
            $row['title_status_label'] = __('Too long', 'wpfs');
            $row['issues'][] = 'title-too-long';
        }

        if ($row['description_length'] === 0) {
            $row['description_status'] = 'bad';
            $row['description_status_label'] = __('Missing', 'wpfs');
            $row['issues'][] = 'description-missing';
        }

        if ($row['image_audit']['missing_alt'] > 0) {
            $row['image_status'] = 'bad';
            $row['image_status_label'] = __('Missing alt', 'wpfs');
            $row['issues'][] = 'image-alt-missing';
        }

        if ($row['image_audit']['heavy'] > 0) {
            if ($row['image_status'] === 'ok') {
                $row['image_status'] = 'warn';
                $row['image_status_label'] = __('Heavy', 'wpfs');
            }
            $row['issues'][] = 'image-heavy';
        }

        if ($row['image_audit']['missing_dimensions'] > 0) {
            if ($row['image_status'] === 'ok') {
                $row['image_status'] = 'warn';
                $row['image_status_label'] = __('No dimensions', 'wpfs');
            }
            $row['issues'][] = 'image-no-dimensions';
        }

        if ($row['noindex']) {
            $row['issues'][] = 'noindex';
        }

        if (!$row['canonical']) {
            $row['issues'][] = 'canonical-missing';
        }

        return $row;
    }

    private function get_seo_audit_stats(array $rows): array
    {
        $stats = [
            'shown'               => count($rows),
            'attention'           => 0,
            'description_missing' => 0,
            'image_issues'        => 0,
        ];

        foreach ($rows as $row) {
            if (!empty($row['issues'])) {
                $stats['attention']++;
            }

            if (in_array('description-missing', $row['issues'], true)) {
                $stats['description_missing']++;
            }

            if (array_intersect(['image-alt-missing', 'image-heavy', 'image-no-dimensions'], $row['issues'])) {
                $stats['image_issues']++;
            }
        }

        return $stats;
    }

    private function generate_audit_title(string $template, $object, string $type): string
    {
        $title = $this->normalize_seo_audit_text($this->replace_seo_audit_tokens($template, $object, $type));

        if ($title === '') {
            $title = $type === 'term' ? ($object->name ?? '') : get_the_title($object);
        }

        if (wps('wpfs')->settings->get('seo.title.blogname', false)) {
            $site_name = get_bloginfo('name', 'display');

            if ($site_name && !str_ends_with($title, $site_name)) {
                $separator = wps('wpfs')->settings->get('seo.title.separator', '-');
                $title = trim(preg_replace('#\W*' . preg_quote($site_name, '#') . '\W*#si', ' ', $title));
                $title = trim("$title $separator $site_name");
            }
        }

        return $this->normalize_seo_audit_text($title);
    }

    private function replace_seo_audit_tokens(string $template, $object, string $type): string
    {
        if ($template === '' || !str_contains($template, '%%')) {
            return $template;
        }

        return preg_replace_callback('#%%([^%]+)%%#Us', function ($matches) use ($object, $type) {
            $token = trim($matches[1]);

            if ($token === 'title') {
                return $type === 'term' ? ($object->name ?? '') : get_the_title($object);
            }

            if ($token === 'description' || $token === 'resume') {
                if ($object instanceof \WP_Term) {
                    return $object->description ?? '';
                }

                if ($object instanceof \WP_Post) {
                    return wp_trim_words(has_excerpt($object) ? $object->post_excerpt : $object->post_content, 32, '');
                }
            }

            if ($token === 'excerpt' && $object instanceof \WP_Post) {
                return $object->post_excerpt;
            }

            if ($token === 'sep') {
                return wps('wpfs')->settings->get('seo.title.separator', '-');
            }

            if ($token === 'sitename') {
                return get_bloginfo('name', 'display');
            }

            if ($token === 'sitedesc') {
                return get_bloginfo('description', 'display');
            }

            if ($token === 'language') {
                return get_bloginfo('language');
            }

            if ($token === 'date') {
                return date_i18n('Y-m-d');
            }

            if ($token === 'time') {
                return date_i18n('H:i:s');
            }

            if ($object instanceof \WP_Post && $token === 'modified') {
                return $object->post_modified;
            }

            if ($object instanceof \WP_Post && $token === 'created') {
                return $object->post_date;
            }

            if (str_starts_with($token, 'meta_') && $object instanceof \WP_Post) {
                return get_metadata_raw('post', $object->ID, substr($token, 5), true) ?: '';
            }

            if (is_object($object) && isset($object->$token)) {
                return (string)$object->$token;
            }

            return '';
        }, $template);
    }

    private function normalize_seo_audit_text(string $text): string
    {
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, get_bloginfo('charset') ?: 'UTF-8');
        $text = strip_shortcodes($text);
        $text = wp_strip_all_tags($text, true);
        $text = preg_replace('/%%[^%]+%%/', '', $text);
        $text = preg_replace('/\s+/u', ' ', $text);

        return trim($text);
    }

    private function seo_audit_strlen(string $text): int
    {
        return function_exists('mb_strlen') ? mb_strlen($text) : strlen($text);
    }

    private function truncate_seo_audit_text(string $text, int $limit): string
    {
        if ($this->seo_audit_strlen($text) <= $limit) {
            return $text;
        }

        if (function_exists('mb_substr')) {
            return rtrim(mb_substr($text, 0, $limit - 1)) . '...';
        }

        return rtrim(substr($text, 0, $limit - 1)) . '...';
    }

    private function render_seo_audit_badge(string $status, string $label): string
    {
        return sprintf(
            '<span class="wpfs-audit-badge is-%s">%s</span>',
            esc_attr($status),
            esc_html($label)
        );
    }

    private function get_post_image_audit(\WP_Post $post): array
    {
        $items = [];
        $seen = [];

        $thumbnail_id = get_post_thumbnail_id($post);
        if ($thumbnail_id) {
            $image = wp_get_attachment_image_src($thumbnail_id, 'full');
            if (!empty($image[0])) {
                $items[] = $this->build_image_audit_item(
                    __('Featured image', 'wpfs'),
                    $image[0],
                    (int)$thumbnail_id,
                    (string)get_post_meta($thumbnail_id, '_wp_attachment_image_alt', true),
                    !empty($image[1]),
                    !empty($image[2]),
                    false
                );
                $seen[$this->normalize_image_audit_url($image[0])] = true;
            }
        }

        foreach ($this->extract_image_tags($post->post_content) as $tag) {
            $src = $this->get_image_tag_attribute($tag, 'src');

            if ($src === '') {
                continue;
            }

            $key = $this->normalize_image_audit_url($src);
            if (isset($seen[$key])) {
                continue;
            }

            $attachment_id = function_exists('attachment_url_to_postid') ? (int)attachment_url_to_postid($src) : 0;
            $alt = $this->get_image_tag_attribute($tag, 'alt');
            $width = (int)$this->get_image_tag_attribute($tag, 'width');
            $height = (int)$this->get_image_tag_attribute($tag, 'height');

            $items[] = $this->build_image_audit_item(
                wp_basename((string)wp_parse_url($src, PHP_URL_PATH)) ?: __('Content image', 'wpfs'),
                $src,
                $attachment_id,
                $alt,
                $width > 0,
                $height > 0,
                true
            );
            $seen[$key] = true;
        }

        return $this->summarize_image_audit($items);
    }

    private function get_term_image_audit(\WP_Term $term): array
    {
        $items = [];

        foreach ($this->extract_image_tags($term->description) as $tag) {
            $src = $this->get_image_tag_attribute($tag, 'src');

            if ($src === '') {
                continue;
            }

            $items[] = $this->build_image_audit_item(
                wp_basename((string)wp_parse_url($src, PHP_URL_PATH)) ?: __('Term image', 'wpfs'),
                $src,
                function_exists('attachment_url_to_postid') ? (int)attachment_url_to_postid($src) : 0,
                $this->get_image_tag_attribute($tag, 'alt'),
                (int)$this->get_image_tag_attribute($tag, 'width') > 0,
                (int)$this->get_image_tag_attribute($tag, 'height') > 0,
                true
            );
        }

        return $this->summarize_image_audit($items);
    }

    private function extract_image_tags(string $content): array
    {
        if (!str_contains($content, '<img')) {
            return [];
        }

        preg_match_all('#<img\b[^>]*>#i', $content, $matches);

        return $matches[0] ?? [];
    }

    private function get_image_tag_attribute(string $tag, string $attribute): string
    {
        if (preg_match('#\s' . preg_quote($attribute, '#') . '\s*=\s*(["\'])(.*?)\1#i', $tag, $matches)) {
            return html_entity_decode($matches[2], ENT_QUOTES | ENT_HTML5, get_bloginfo('charset') ?: 'UTF-8');
        }

        if (preg_match('#\s' . preg_quote($attribute, '#') . '\s*=\s*([^\s>]+)#i', $tag, $matches)) {
            return html_entity_decode(trim($matches[1], '"\''), ENT_QUOTES | ENT_HTML5, get_bloginfo('charset') ?: 'UTF-8');
        }

        return '';
    }

    private function build_image_audit_item(string $label, string $url, int $attachment_id, string $alt, bool $has_width, bool $has_height, bool $from_rendered_html): array
    {
        if (!$from_rendered_html && $attachment_id) {
            $metadata = wp_get_attachment_metadata($attachment_id);
            $has_width = !empty($metadata['width']);
            $has_height = !empty($metadata['height']);
        }

        $filesize = $this->get_image_audit_filesize($attachment_id, $url);
        $is_heavy = $filesize > 0 && $filesize > $this->get_heavy_image_threshold_bytes();
        $missing_alt = trim(wp_strip_all_tags($alt)) === '';
        $missing_dimensions = !$has_width || !$has_height;
        $summary = [];

        if ($missing_alt) {
            $summary[] = __('alt missing', 'wpfs');
        }

        if ($is_heavy) {
            $summary[] = sprintf(__('heavy: %s KB', 'wpfs'), number_format_i18n((int)ceil($filesize / 1024)));
        }

        if ($missing_dimensions) {
            $summary[] = __('dimensions missing', 'wpfs');
        }

        return [
            'label'              => $label,
            'url'                => $url,
            'missing_alt'        => $missing_alt,
            'heavy'              => $is_heavy,
            'missing_dimensions' => $missing_dimensions,
            'filesize'           => $filesize,
            'summary'            => empty($summary) ? __('OK', 'wpfs') : implode(', ', $summary),
        ];
    }

    private function summarize_image_audit(array $items): array
    {
        $audit = $this->empty_image_audit();
        $audit['items'] = $items;
        $audit['count'] = count($items);

        foreach ($items as $item) {
            if ($item['missing_alt']) {
                $audit['missing_alt']++;
            }

            if ($item['heavy']) {
                $audit['heavy']++;
            }

            if ($item['missing_dimensions']) {
                $audit['missing_dimensions']++;
            }
        }

        return $audit;
    }

    private function empty_image_audit(): array
    {
        return [
            'count'              => 0,
            'missing_alt'        => 0,
            'heavy'              => 0,
            'missing_dimensions' => 0,
            'items'              => [],
        ];
    }

    private function format_image_audit_report(array $audit): string
    {
        if ($audit['count'] === 0) {
            return __('No images found.', 'wpfs');
        }

        $parts = [];

        if ($audit['missing_alt'] > 0) {
            $parts[] = sprintf(_n('%s missing alt', '%s missing alt', $audit['missing_alt'], 'wpfs'), number_format_i18n($audit['missing_alt']));
        }

        if ($audit['heavy'] > 0) {
            $parts[] = sprintf(_n('%s heavy image', '%s heavy images', $audit['heavy'], 'wpfs'), number_format_i18n($audit['heavy']));
        }

        if ($audit['missing_dimensions'] > 0) {
            $parts[] = sprintf(_n('%s without dimensions', '%s without dimensions', $audit['missing_dimensions'], 'wpfs'), number_format_i18n($audit['missing_dimensions']));
        }

        return empty($parts) ? __('Images OK.', 'wpfs') : implode(' - ', $parts);
    }

    private function get_heavy_image_threshold_bytes(): int
    {
        $threshold_kb = absint(wps('wpfs')->settings->get('seo.image.heavy_threshold_kb', 300));

        return max(1, $threshold_kb) * 1024;
    }

    private function get_image_audit_filesize(int $attachment_id, string $url): int
    {
        $path = '';

        if ($attachment_id) {
            $path = function_exists('wp_get_original_image_path') ? (string)wp_get_original_image_path($attachment_id) : (string)get_attached_file($attachment_id, true);
        }

        if ($path === '') {
            $path = $this->image_url_to_local_path($url);
        }

        return ($path && file_exists($path)) ? (int)filesize($path) : 0;
    }

    private function image_url_to_local_path(string $url): string
    {
        $uploads = wp_upload_dir();
        $base_url = $uploads['baseurl'] ?? '';
        $base_dir = $uploads['basedir'] ?? '';

        if ($base_url && $base_dir && str_starts_with($url, $base_url)) {
            return wp_normalize_path($base_dir . substr($url, strlen($base_url)));
        }

        $home_url = home_url('/');
        if (defined('ABSPATH') && str_starts_with($url, $home_url)) {
            return wp_normalize_path(ABSPATH . ltrim(substr($url, strlen($home_url)), '/'));
        }

        return '';
    }

    private function normalize_image_audit_url(string $url): string
    {
        $url = preg_replace('#-\d+x\d+(\.[a-z0-9]{2,5})$#i', '$1', $url);

        return strtolower((string)$url);
    }

    public function render_faqs(): void
    {
        $this->enqueue_scripts();
        ?>
        <section class="wps-wrap wpfs-admin-shell wpfs-faq-page">
            <header class="wpfs-page-hero">
                <div>
                    <span class="wpfs-kicker"><?php esc_html_e('Flexy SEO knowledge base', 'wpfs'); ?></span>
                    <h1><?php esc_html_e('FAQ', 'wpfs'); ?></h1>
                    <p><?php esc_html_e('Quick reference for breadcrumbs, replacements and dynamic SEO output.', 'wpfs'); ?></p>
                </div>
                <nav class="wpfs-quick-actions" aria-label="<?php esc_attr_e('FAQ quick links', 'wpfs'); ?>">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=wpfs-seo')); ?>"><span class="dashicons dashicons-search"></span><?php esc_html_e('SEO', 'wpfs'); ?></a>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=wpfs-breadcrumbs')); ?>"><span class="dashicons dashicons-randomize"></span><?php esc_html_e('Breadcrumbs', 'wpfs'); ?></a>
                </nav>
            </header>
            <block class="wps">
                <section class='wps-header'><h1>FAQ</h1></section>
                <br>
                <div class="wps-faq-list">
                    <div class="wps-faq-item">
                        <div class="wps-faq-question-wrapper ">
                            <div
                                    class="wps-faq-question wps-collapse-handler"><?php echo __('How do Flexy Breadcrumbs work?', 'wpfs') ?>
                                <icon class="wps-collapse-icon">+</icon>
                            </div>
                            <div class="wps-faq-answer wps-collapse">
                                <p><?php echo __('Flexy Breadcrumbs generates a contextual path for posts, pages, taxonomies and archives. Add the function in your theme where the breadcrumb trail should appear.', 'wpfs'); ?></p>
                                <code>&lt;?php if(function_exists('wpfs_breadcrumb')) wpfs_breadcrumb($pre='', $after=''); ?&gt;</code><br>
                                <p><?php echo __('The $pre and $after arguments let you wrap the output with your own markup. If you leave them empty, Flexy SEO prints only the breadcrumb markup.', 'wpfs'); ?></p>
                                <strong><?php echo __('Options:', 'wpfs'); ?></strong>
                                <ul class="wps-list">
                                    <li><?php echo __('Use the Breadcrumbs page to define a structure for every entity: posts, pages, taxonomies, archives and custom post types.', 'wpfs'); ?></li>
                                    <li><?php echo __('With flexible breadcrumbs you can mix dynamic replacements, custom text and custom links in the same path:', 'wpfs'); ?>
                                        <ul class="wps-list">
                                            <li>
                                                <strong><?php echo __('>>', 'wpfs'); ?></strong> : <?php echo __('separates each breadcrumb item.', 'wpfs'); ?>
                                            </li>
                                            <li>
                                                <strong>[<?php echo __('Text', 'wpfs'); ?>]</strong> : <?php echo __('uses the value only as visible breadcrumb text.', 'wpfs'); ?>
                                            </li>
                                            <li>
                                                <strong>(<?php echo __('Text', 'wpfs'); ?>)</strong> : <?php echo __('uses the value only as breadcrumb URL.', 'wpfs'); ?>
                                            </li>
                                            <li>
                                                <strong><?php echo __('/custom/link/', 'wpfs'); ?></strong> : <?php echo __('adds a custom link structure.', 'wpfs'); ?>
                                            </li>
                                            <li>
                                                <strong>%%{query_var}%%</strong> : <?php echo __('replaces the matching WordPress query variable. If it is not available, it is discarded.', 'wpfs'); ?>
                                            </li>
                                            <li>
                                                <strong>%%meta_{var}%%</strong> : <?php echo __('replaces a meta value from the current queried object. If it is empty, it is discarded.', 'wpfs'); ?>
                                            </li>
                                            <li>
                                                <strong>%%home%%</strong> : <?php echo __('prints the configured home breadcrumb item.', 'wpfs'); ?>
                                            </li>
                                            <li>
                                                <strong>%%taxonomy%%</strong>,
                                                <strong>%%category%%</strong>: <?php echo __('prints the taxonomy or category path for the current object. Hierarchical terms generate multiple breadcrumb items.', 'wpfs'); ?>
                                            </li>
                                            <li>
                                                <strong>%%taxonomy-full%%</strong>,
                                                <strong>%%category-full%%</strong>: <?php echo __('works like the taxonomy/category replacements, but keeps the taxonomy prefix in each term link.', 'wpfs'); ?>
                                            </li>
                                            <li>
                                                <strong>%%post_parent%%</strong> : <?php echo __('Replace the parent post of the current queried object.', 'wpfs'); ?>
                                            </li>
                                            <li>
                                                <strong>%%post_type%%</strong> : <?php echo __('Replace the post type of the current queried object.', 'wpfs'); ?>
                                            </li>
                                            <li>
                                                <strong>%%queried_object%%</strong> : <?php echo __('Replace the title and link of the current queried object.', 'wpfs'); ?>
                                            </li>
                                        </ul>
                                        <br>
                                        <em><strong><?php echo __('Example: %%home%% >> (/%%meta_var%%-%%custom_var%%/)%%category%% >> %%queried_object%%', 'wpfs'); ?></strong></em>
                                        <br>
                                        <br>
                                    </li>
                                    <li><?php echo __('Use the <em>wpfs_breadcrumb_style</em> filter to change the default breadcrumb CSS.', 'wpfs'); ?></li>
                                    <li><?php echo __('Use the <em>wpfs_breadcrumb_replacements</em> filter to add or override breadcrumb replacements. The return format must be <strong>array(array(\'text\' =>, \'url\' => ))</strong>.', 'wpfs'); ?></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <div class="wps-faq-item">
                        <div class="wps-faq-question-wrapper ">
                            <div
                                    class="wps-faq-question wps-collapse-handler"><?php echo __('How does Flexy SEO generate metadata?', 'wpfs') ?>
                                <icon class="wps-collapse-icon">+</icon>
                            </div>
                            <div class="wps-faq-answer wps-collapse">
                                <p><?php echo __('Flexy SEO starts from the template configured for the current context, applies WordPress filters, replaces dynamic tokens and then normalizes the final title, description, keywords, social metadata and schema output.', 'wpfs'); ?></p>
                                <p><?php echo __('This means one template can adapt to posts, taxonomies, archives, search pages and custom post types without duplicating manual SEO rules.', 'wpfs'); ?></p>
                                <br>
                                <?php echo sprintf(__('See the complete replacer list <a href="%s">here</a>.', 'wpfs'), admin_url('admin.php?page=wpfs-seo#seo-vars')); ?>
                            </div>
                        </div>
                    </div>
                    <div class="wps-faq-item">
                        <div class="wps-faq-question-wrapper ">
                            <div
                                    class="wps-faq-question wps-collapse-handler"><?php echo __('What are replacers and when should I use them?', 'wpfs') ?>
                                <icon class="wps-collapse-icon">+</icon>
                            </div>
                            <div class="wps-faq-answer wps-collapse">
                                <p><?php echo __('Replacers are dynamic tokens such as %%title%%, %%description%%, %%sitename%%, %%sep%% and %%meta_price%%. They are replaced with the value available in the current page context.', 'wpfs'); ?></p>
                                <p><?php echo __('Use them when a rule should work across many pages. For example, a product post type can use one title template while each product still receives its own title, category, excerpt and custom fields.', 'wpfs'); ?></p>
                                <code>%%title%% %%sep%% %%category%% %%sep%% %%sitename%%</code>
                            </div>
                        </div>
                    </div>
                    <div class="wps-faq-item">
                        <div class="wps-faq-question-wrapper ">
                            <div
                                    class="wps-faq-question wps-collapse-handler"><?php echo __('How does autosave work in the SEO panel?', 'wpfs') ?>
                                <icon class="wps-collapse-icon">+</icon>
                            </div>
                            <div class="wps-faq-answer wps-collapse">
                                <p><?php echo __('The SEO settings page saves changes automatically after you edit a field. A status badge and toast notification show whether changes are pending, saving, saved or blocked by a connection error.', 'wpfs'); ?></p>
                                <p><?php echo __('If you leave the page while a change is still pending, the browser warns you before closing so you do not lose unsaved settings.', 'wpfs'); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="wps-faq-item">
                        <div class="wps-faq-question-wrapper ">
                            <div
                                    class="wps-faq-question wps-collapse-handler"><?php echo __('What should I configure first?', 'wpfs') ?>
                                <icon class="wps-collapse-icon">+</icon>
                            </div>
                            <div class="wps-faq-answer wps-collapse">
                                <p><?php echo __('Start with General settings, then configure the Home page, Post types and Taxonomies. After that, review Social and Schema.org settings so shared links and structured data are consistent with your brand.', 'wpfs'); ?></p>
                                <ul class="wps-list">
                                    <li><?php echo __('Set the title separator and whether the site name should be appended automatically.', 'wpfs'); ?></li>
                                    <li><?php echo __('Create one strong title and description template for each important post type.', 'wpfs'); ?></li>
                                    <li><?php echo __('Add a default wide and small image for pages that do not have a usable featured image.', 'wpfs'); ?></li>
                                    <li><?php echo __('Enable Schema.org and fill organization data if the site represents a business, agency or brand.', 'wpfs'); ?></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <div class="wps-faq-item">
                        <div class="wps-faq-question-wrapper ">
                            <div
                                    class="wps-faq-question wps-collapse-handler"><?php echo __('How are title and meta description fallbacks handled?', 'wpfs') ?>
                                <icon class="wps-collapse-icon">+</icon>
                            </div>
                            <div class="wps-faq-answer wps-collapse">
                                <p><?php echo __('If a configured template produces an empty value, Flexy SEO builds a fallback from the current context: post excerpt or content, term description, author description, search query or site description.', 'wpfs'); ?></p>
                                <p><?php echo __('The final text is cleaned before output: unresolved tokens, shortcodes, HTML tags and excessive spaces are removed so search engines receive a readable value.', 'wpfs'); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="wps-faq-item">
                        <div class="wps-faq-question-wrapper ">
                            <div
                                    class="wps-faq-question wps-collapse-handler"><?php echo __('How do Open Graph and Twitter Card settings affect sharing?', 'wpfs') ?>
                                <icon class="wps-collapse-icon">+</icon>
                            </div>
                            <div class="wps-faq-answer wps-collapse">
                                <p><?php echo __('Open Graph controls how links appear on Facebook, LinkedIn and other platforms that read og:* tags. Twitter Card controls twitter:* tags and lets you choose standard or large image cards.', 'wpfs'); ?></p>
                                <p><?php echo __('Flexy SEO reuses the generated SEO title, description, canonical URL and best available image, so social previews stay aligned with the page metadata.', 'wpfs'); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="wps-faq-item">
                        <div class="wps-faq-question-wrapper ">
                            <div
                                    class="wps-faq-question wps-collapse-handler"><?php echo __('Which Schema.org type should I choose?', 'wpfs') ?>
                                <icon class="wps-collapse-icon">+</icon>
                            </div>
                            <div class="wps-faq-answer wps-collapse">
                                <p><?php echo __('Use WebPage for static pages, Article or BlogPosting for editorial posts, CollectionPage for archives and listing pages, SearchResultsPage for search pages and RealEstateListing for property listing contexts.', 'wpfs'); ?></p>
                                <p><?php echo __('The selected graph type is still dynamic: Flexy SEO fills URL, title, description, language, breadcrumb, dates, author and image data from the current WordPress object when available.', 'wpfs'); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="wps-faq-item">
                        <div class="wps-faq-question-wrapper ">
                            <div
                                    class="wps-faq-question wps-collapse-handler"><?php echo __('When should I clear the Flexy SEO cache?', 'wpfs') ?>
                                <icon class="wps-collapse-icon">+</icon>
                            </div>
                            <div class="wps-faq-answer wps-collapse">
                                <p><?php echo __('Clear the cache after major template changes, taxonomy restructures, permalink changes or when you suspect old generated data is still being reused.', 'wpfs'); ?></p>
                                <p><?php echo __('For normal edits in the SEO panel, autosave updates the stored settings immediately and the frontend generator will use the new rules on subsequent requests.', 'wpfs'); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </block>
        </section>
        <?php
    }

    /**
     * This function renders the contents of the page associated with the menu
     * that invokes the render method. In the context of this plugin, this is the
     * menu class.
     */
    public function render_main()
    {
        global $wpdb;

        if (isset($_POST['wpfs-clear-cache']) && UtilEnv::verify_nonce('wpfs-dashboard-action')) {
            $wpdb->delete("{$wpdb->prefix}flexy_seo", ['context' => 'cache']);
            delete_transient('wpfs_sitemap_xml_cache');
            add_settings_error('wpfs-dashboard', 'wpfs-cache-cleared', __('Flexy SEO cache cleared.', 'wpfs'), 'updated');
        }

        if (isset($_POST['wpfs-refresh-rules']) && UtilEnv::verify_nonce('wpfs-dashboard-action')) {
            delete_transient('wpfs_sitemap_xml_cache');
            flush_rewrite_rules(false);
            add_settings_error('wpfs-dashboard', 'wpfs-rules-refreshed', __('Permalink and sitemap rewrite rules refreshed.', 'wpfs'), 'updated');
        }

        $this->enqueue_scripts();

        settings_errors();

        $configuration = $this->get_dashboard_configuration();
        $conf_level = $configuration['level'];
        $conf_color = $conf_level >= 80 ? '#1da64f' : ($conf_level >= 55 ? '#d7e600' : '#d43f3a');
        $modules = wps('wpfs')->moduleHandler;

        ?>
        <section class="wps-wrap-flex wps-wrap wps-home wpfs-admin-shell wpfs-dashboard">
            <section class="wps">
                <header class="wpfs-page-hero">
                    <div>
                        <span class="wpfs-kicker"><?php esc_html_e('WordPress SEO toolkit', 'wpfs'); ?></span>
                        <h1>Flexy SEO</h1>
                        <p><?php esc_html_e('Manage dynamic metadata, breadcrumbs, social sharing and schema from one focused panel.', 'wpfs'); ?></p>
                    </div>
                    <nav class="wpfs-quick-actions" aria-label="<?php esc_attr_e('Flexy SEO quick links', 'wpfs'); ?>">
                        <a href="<?php echo esc_url(admin_url('admin.php?page=wpfs-seo')); ?>"><span class="dashicons dashicons-search"></span><?php esc_html_e('SEO Settings', 'wpfs'); ?></a>
                        <?php if ($modules->module_is_active('seo_audit')) : ?>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=wpfs-seo-audit')); ?>"><span class="dashicons dashicons-visibility"></span><?php esc_html_e('SEO Audit', 'wpfs'); ?></a>
                        <?php endif; ?>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=wpfs-breadcrumbs')); ?>"><span class="dashicons dashicons-randomize"></span><?php esc_html_e('Breadcrumbs', 'wpfs'); ?></a>
                        <?php if ($modules->module_is_active('redirects')) : ?>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=wpfs-redirects')); ?>"><span class="dashicons dashicons-redo"></span><?php esc_html_e('Redirects', 'wpfs'); ?></a>
                        <?php endif; ?>
                        <?php if ($modules->module_is_active('not_found_monitor')) : ?>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=wpfs-not_found_monitor')); ?>"><span class="dashicons dashicons-warning"></span><?php esc_html_e('404 Monitor', 'wpfs'); ?></a>
                        <?php endif; ?>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=wpfs-settings#settings-modules_handler')); ?>"><span class="dashicons dashicons-admin-plugins"></span><?php esc_html_e('Modules', 'wpfs'); ?></a>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=wpfs-faqs')); ?>"><span class="dashicons dashicons-editor-help"></span><?php esc_html_e('FAQ', 'wpfs'); ?></a>
                    </nav>
                </header>
                <block class="wps wpfs-card wpfs-config-card">
                    <h2><?php _e('Configuration:', 'wpfs'); ?></h2>
                    <div class="wpfs-config-layout">
                        <?php
                        echo "<div class='wps-progressbarCircle' data-percent='{$conf_level}' data-stroke='2' data-size='155' data-color='{$conf_color}'></div>";
                        ?>
                        <div class="wpfs-config-summary">
                            <strong><?php echo esc_html(sprintf(__('SEO readiness: %s%%', 'wpfs'), $conf_level)); ?></strong>
                            <p><?php echo esc_html(sprintf(__('Core SEO is %1$s%% complete and technical tools are %2$s%% active. Enable the missing tools only if they match your SEO workflow.', 'wpfs'), $configuration['core_level'], $configuration['tool_level'])); ?></p>
                            <?php $this->render_dashboard_checklist($configuration); ?>
                            <div class="wpfs-action-row">
                                <a class="button button-primary" href="<?php echo esc_url(admin_url('admin.php?page=wpfs-seo')); ?>"><?php esc_html_e('Configure SEO', 'wpfs'); ?></a>
                                <?php if ($modules->module_is_active('seo_audit')) : ?>
                                    <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=wpfs-seo-audit')); ?>"><?php esc_html_e('Run SEO Audit', 'wpfs'); ?></a>
                                <?php endif; ?>
                                <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=wpfs-breadcrumbs')); ?>"><?php esc_html_e('Configure Breadcrumbs', 'wpfs'); ?></a>
                            </div>
                        </div>
                    </div>
                </block>
                <block class="wps wpfs-card wpfs-options-card">
                    <h2><?php _e('Options:', 'wpfs'); ?></h2>
                    <div class="wpfs-dashboard-options">
                        <form method="POST" class="wpfs-option-tile">
                            <?php wp_nonce_field('wpfs-dashboard-action'); ?>
                            <strong><?php esc_html_e('Cache', 'wpfs'); ?></strong>
                            <p><?php esc_html_e('Clear generated SEO cache after template, schema or metadata changes.', 'wpfs'); ?></p>
                            <button name="wpfs-clear-cache" type="submit" class="button button-primary"><?php esc_html_e('Reset Flexy SEO cache', 'wpfs'); ?></button>
                        </form>
                        <form method="POST" class="wpfs-option-tile">
                            <?php wp_nonce_field('wpfs-dashboard-action'); ?>
                            <strong><?php esc_html_e('Permalinks', 'wpfs'); ?></strong>
                            <p><?php esc_html_e('Refresh rewrite rules when sitemap or redirect URLs do not resolve as expected.', 'wpfs'); ?></p>
                            <button name="wpfs-refresh-rules" type="submit" class="button"><?php esc_html_e('Refresh rewrite rules', 'wpfs'); ?></button>
                        </form>
                        <?php if ($modules->module_is_active('sitemap')) : ?>
                            <a class="wpfs-option-tile" href="<?php echo esc_url(home_url('/sitemap.xml')); ?>" target="_blank" rel="noopener noreferrer">
                                <strong><?php esc_html_e('XML Sitemap', 'wpfs'); ?></strong>
                                <p><?php esc_html_e('Open the generated sitemap in a new tab and confirm search engines can crawl it.', 'wpfs'); ?></p>
                                <span><?php esc_html_e('View sitemap', 'wpfs'); ?></span>
                            </a>
                        <?php endif; ?>
                        <a class="wpfs-option-tile" href="<?php echo esc_url(admin_url('admin.php?page=wpfs-settings#settings-modules_handler')); ?>">
                            <strong><?php esc_html_e('Modules', 'wpfs'); ?></strong>
                            <p><?php esc_html_e('Enable only the SEO tools you use to keep the admin and frontend output focused.', 'wpfs'); ?></p>
                            <span><?php esc_html_e('Manage modules', 'wpfs'); ?></span>
                        </a>
                    </div>
                </block>
                <?php
                if (!is_plugin_active('wp-optimizer/wp-optimizer.php')) {
                    ?>
                    <block class="wps wpfs-card">
                        <h2><?php _e('Tips:', 'wpfs'); ?></h2>
                        <h3>
                            <?php
                            echo '<strong>' . sprintf(__('For a better SEO optimization, it\'s recommended to install also <a href="%s">this</a> plugin.', 'wpfs'), "https://wordpress.org/plugins/wp-optimizer/") . '</strong>';
                            ?>
                        </h3>
                    </block>
                    <?php
                }
                ?>
            </section>
            <aside class="wps">
                <section class="wps-box wpfs-card wpfs-donation-card">
                    <div class="wpfs-donation-panel">
                        <h2><?php esc_html_e('Flexy SEO saving you time?', 'wpfs'); ?></h2>
                        <p><?php esc_html_e('Support maintenance, fixes, and new features with a donation, or help more users discover the plugin with a 5-star review.', 'wpfs'); ?></p>
                        <a class="wpfs-donation-button wpfs-donation-button--paypal"
                           href="https://www.paypal.com/donate/?business=dev.sh1zen%40outlook.it&item_name=Thank+you+in+advance+for+the+kind+donations.+You+will+sustain+me+developing+FlexySEO.&currency_code=EUR"
                           target="_blank" rel="noopener noreferrer">
                            <?php esc_html_e('Donate with PayPal', 'wpfs'); ?>
                        </a>
                        <a class="wpfs-donation-button wpfs-donation-button--review"
                           href="https://wordpress.org/support/plugin/flexy-seo/reviews/?filter=5"
                           target="_blank" rel="noopener noreferrer">
                            <?php esc_html_e('Leave a 5-star review', 'wpfs'); ?>
                        </a>
                    </div>
                </section>
                <section class="wps-box wpfs-card wpfs-support-card">
                    <h3><?php esc_html_e('Want to support in other ways?', 'wpfs'); ?></h3>
                    <nav class="wpfs-support-links" aria-label="<?php esc_attr_e('Support links', 'wpfs'); ?>">
                        <a href="https://translate.wordpress.org/projects/wp-plugins/flexy-seo/"
                           target="_blank" rel="noopener noreferrer">
                            <span><?php esc_html_e('Help me translating', 'wpfs'); ?></span>
                            <span aria-hidden="true">&gt;</span>
                        </a>
                    </nav>
                    <h3>WP-Optimizer</h3>
                    <nav class="wpfs-support-links" aria-label="<?php esc_attr_e('Flexy SEO links', 'wpfs'); ?>">
                        <a href="https://github.com/sh1zen/flexy-seo/"
                           target="_blank" rel="noopener noreferrer">
                            <span><?php esc_html_e('Source code', 'wpfs'); ?></span>
                            <span aria-hidden="true">&gt;</span>
                        </a>
                        <a href="https://sh1zen.github.io/"
                           target="_blank" rel="noopener noreferrer">
                            <span><?php esc_html_e('About me', 'wpfs'); ?></span>
                            <span aria-hidden="true">&gt;</span>
                        </a>
                    </nav>
                </section>
            </aside>
        </section>
        <?php
    }
}
