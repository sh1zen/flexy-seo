<?php
/**
 * @author    sh1zen
 * @copyright Copyright (C) 2025.
 * @license   http://www.gnu.org/licenses/gpl.html GNU/GPL
 */

namespace FlexySEO\modules;

use WPS\core\Graphic;
use WPS\modules\Module;

class Mod_sitemap extends Module
{
    public static ?string $name = 'Sitemap XML';

    public array $scopes = array('admin-page', 'settings', 'autoload');

    protected string $context = 'wpfs';

    private const CACHE_TRANSIENT = 'wpfs_sitemap_xml_cache';

    private const CACHE_GROUP_PREFIX = 'wpfs_sitemap_';

    private const CLEANUP_HOOK = 'wpfs_sitemap_cache_cleanup_event';

    private array $changefreqs = array('always', 'hourly', 'daily', 'weekly', 'monthly', 'yearly', 'never');

    private array $priorities = array('1.0', '0.9', '0.8', '0.7', '0.6', '0.5', '0.4', '0.3', '0.2', '0.1', '0.0');

    protected function init(): void
    {
        add_filter('wp_sitemaps_enabled', array($this, 'disable_wordpress_sitemap'), 99);
        add_filter('query_vars', array($this, 'register_query_var'));
        add_filter('do_parse_request', array($this, 'maybe_render_sitemap_pre_parse'), -100, 2);
        add_action('init', array($this, 'register_rewrite_rule'));
        add_action('parse_request', array($this, 'maybe_render_sitemap_early'), 0);
        add_action('template_redirect', array($this, 'maybe_render_sitemap'), 0);

        add_action('save_post', array($this, 'schedule_ping_after_save'), 10, 3);
        add_action('transition_post_status', array($this, 'schedule_ping_after_status_change'), 10, 3);
        add_action('deleted_post', array($this, 'schedule_ping_from_post_id'));
        add_action('trashed_post', array($this, 'schedule_ping_from_post_id'));
        add_action('add_attachment', array($this, 'clear_sitemap_cache'));
        add_action('edit_attachment', array($this, 'clear_sitemap_cache'));
        add_action('delete_attachment', array($this, 'clear_sitemap_cache'));
        add_action('created_term', array($this, 'clear_sitemap_cache'));
        add_action('edited_term', array($this, 'clear_sitemap_cache'));
        add_action('delete_term', array($this, 'clear_sitemap_cache'));
        add_action('wpfs_sitemap_ping_event', array($this, 'ping_search_engines'));
        add_action(self::CLEANUP_HOOK, array($this, 'cleanup_expired_cache'));

        add_action('wpfs-activate', array($this, 'activate_rewrite_rules'));
        add_action('wpfs-deactivate', array($this, 'deactivate_rewrite_rules'));

        $this->schedule_cache_cleanup();
    }

    public function restricted_access($context = ''): bool
    {
        switch ($context) {
            case 'settings':
            case 'render-admin':
                return !current_user_can('manage_options');

            default:
                return false;
        }
    }

    public function disable_wordpress_sitemap($enabled): bool
    {
        if (!$this->option('disable_wp_core', true)) {
            return (bool)$enabled;
        }

        return false;
    }

    public function register_query_var(array $vars): array
    {
        $vars[] = 'wpfs_sitemap';

        return $vars;
    }

    public function register_rewrite_rule(): void
    {
        add_rewrite_rule('^sitemap\.xml$', 'index.php?wpfs_sitemap=1', 'top');
    }

    public function activate_rewrite_rules(): void
    {
        $this->register_rewrite_rule();
        flush_rewrite_rules(false);
    }

    public function deactivate_rewrite_rules(): void
    {
        flush_rewrite_rules(false);
        wp_clear_scheduled_hook(self::CLEANUP_HOOK);
    }

    public function maybe_render_sitemap(): void
    {
        if (!$this->is_sitemap_request()) {
            return;
        }

        if (!$this->option('active', true)) {
            status_header(404);
            nocache_headers();
            exit;
        }

        $this->render_xml();
        exit;
    }

    public function maybe_render_sitemap_pre_parse($do_parse, $wp): bool
    {
        if (!$this->is_sitemap_uri()) {
            return (bool)$do_parse;
        }

        if (!$this->option('active', true)) {
            status_header(404);
            nocache_headers();
            exit;
        }

        $this->render_xml();
        exit;
    }

    public function maybe_render_sitemap_early(): void
    {
        if (!$this->is_sitemap_uri()) {
            return;
        }

        if (!$this->option('active', true)) {
            status_header(404);
            nocache_headers();
            exit;
        }

        $this->render_xml();
        exit;
    }

    public function schedule_ping_after_save($post_id, $post, $update): void
    {
        if (!($post instanceof \WP_Post)) {
            return;
        }

        if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id) || $post->post_status !== 'publish') {
            return;
        }

        $this->schedule_ping_for_post_type($post->post_type);
    }

    public function schedule_ping_after_status_change(string $new_status, string $old_status, $post): void
    {
        if (!($post instanceof \WP_Post) || $new_status === $old_status) {
            return;
        }

        if ($new_status !== 'publish' && $old_status !== 'publish') {
            return;
        }

        $this->schedule_ping_for_post_type($post->post_type);
    }

    public function schedule_ping_from_post_id($post_id): void
    {
        $post_type = get_post_type($post_id);

        if ($post_type) {
            $this->schedule_ping_for_post_type($post_type);
        }
    }

    private function schedule_ping_for_post_type(string $post_type): void
    {
        if ($this->option("post_type.$post_type.include", $post_type !== 'attachment')) {
            $this->clear_sitemap_cache();
        }

        if (!$this->option('active', true) || !$this->option('ping.active', true)) {
            return;
        }

        if (!$this->option("post_type.$post_type.include", $post_type !== 'attachment')) {
            return;
        }

        if (get_transient('wpfs_sitemap_ping_lock')) {
            return;
        }

        set_transient('wpfs_sitemap_ping_lock', 1, 10 * MINUTE_IN_SECONDS);

        if (!wp_next_scheduled('wpfs_sitemap_ping_event')) {
            wp_schedule_single_event(time() + MINUTE_IN_SECONDS, 'wpfs_sitemap_ping_event');
        }
    }

    public function ping_search_engines(): void
    {
        if (!$this->option('active', true) || !$this->option('ping.active', true)) {
            return;
        }

        $sitemap = rawurlencode($this->sitemap_url());
        $endpoints = array();

        if ($this->option('ping.google', true)) {
            $endpoints[] = "https://www.google.com/ping?sitemap=$sitemap";
        }

        if ($this->option('ping.bing', true)) {
            $endpoints[] = "https://www.bing.com/ping?sitemap=$sitemap";
        }

        foreach ($this->custom_ping_endpoints($sitemap) as $endpoint) {
            $endpoints[] = $endpoint;
        }

        foreach (array_unique(array_filter($endpoints)) as $endpoint) {
            wp_remote_get($endpoint, array(
                'blocking'   => false,
                'timeout'    => 3,
                'user-agent' => 'Flexy SEO/' . WPFS_VERSION . '; ' . home_url('/'),
            ));
        }
    }

    protected function render_sub_modules(): void
    {
        ?>
        <section class="wps-wrap wpfs-admin-shell wpfs-settings-page">
            <header class="wpfs-page-hero">
                <div>
                    <span class="wpfs-kicker"><?php esc_html_e('Flexy SEO control panel', 'wpfs'); ?></span>
                    <h1><?php esc_html_e('Sitemap XML', 'wpfs'); ?></h1>
                    <p><?php esc_html_e('Configure XML sitemap entries, image tags and automatic search engine pings.', 'wpfs'); ?></p>
                </div>
            </header>
            <block class="wps">
                <p class="description">
                    <?php esc_html_e('Sitemap URL:', 'wpfs'); ?>
                    <a href="<?php echo esc_url($this->sitemap_url()); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html($this->sitemap_url()); ?></a>
                </p>
                <form id="wps-options" action="options.php" method="post">
                    <input type="hidden" name="<?php echo esc_attr(wps('wpfs')->settings->get_context() . '[change]'); ?>" value="<?php echo esc_attr($this->slug); ?>">
                    <?php
                    settings_fields('wpfs-settings');

                    echo Graphic::generateHTML_tabs_panels(array(
                        array(
                            'id'        => 'sitemap-general',
                            'tab-title' => __('General', 'wpfs'),
                            'callback'  => array($this, 'render_settings'),
                            'args'      => array('general'),
                        ),
                        array(
                            'id'        => 'sitemap-post-types',
                            'tab-title' => __('Post types', 'wpfs'),
                            'callback'  => array($this, 'render_settings'),
                            'args'      => array('post_types'),
                        ),
                        array(
                            'id'        => 'sitemap-taxonomies',
                            'tab-title' => __('Taxonomies', 'wpfs'),
                            'callback'  => array($this, 'render_settings'),
                            'args'      => array('taxonomies'),
                        ),
                        array(
                            'id'        => 'sitemap-archives',
                            'tab-title' => __('Archives', 'wpfs'),
                            'callback'  => array($this, 'render_settings'),
                            'args'      => array('archives'),
                        ),
                        array(
                            'id'        => 'sitemap-ping',
                            'tab-title' => __('Ping', 'wpfs'),
                            'callback'  => array($this, 'render_settings'),
                            'args'      => array('ping'),
                        ),
                    ));
                    ?>
                    <section class="wps-submit">
                        <input type="submit" class="button-primary" value="<?php esc_attr_e('Save Changes', 'wpfs'); ?>">
                    </section>
                </form>
            </block>
        </section>
        <?php
    }

    public function render_settings($filter = ''): string
    {
        $setting_fields = $this->setting_fields($filter);

        ob_start();

        if (!empty($setting_fields)) {
            ?>
            <block class="wps-options">
                <?php Graphic::generate_fields($setting_fields, $this->infos(), array('name_prefix' => wps('wpfs')->settings->get_context())); ?>
            </block>
            <?php
        }

        return ob_get_clean();
    }

    protected function setting_fields($filter = ''): array
    {
        $fields = array();

        $fields['general'] = $this->group_setting_fields(
            $this->setting_field(__('Status:', 'wpfs'), false, 'separator'),
            $this->setting_field(__('Enable Flexy SEO sitemap', 'wpfs'), 'active', 'checkbox', array('default_value' => true)),
            $this->setting_field(__('Disable default WordPress sitemap', 'wpfs'), 'disable_wp_core', 'checkbox', array('default_value' => true)),
            $this->setting_field(__('Cache sitemap XML', 'wpfs'), 'cache.active', 'checkbox', array('default_value' => true)),
            $this->setting_field(__('Cache duration (minutes)', 'wpfs'), 'cache.ttl_minutes', 'number', array('default_value' => 720, 'parent' => 'cache.active')),
            $this->setting_field(__('Include images in post URLs', 'wpfs'), 'images.active', 'checkbox', array('default_value' => true)),
            $this->setting_field(__('Include attached media images', 'wpfs'), 'images.attachments', 'checkbox', array('default_value' => false)),
            $this->setting_field(__('Maximum images per URL', 'wpfs'), 'images.limit', 'number', array('default_value' => 10)),
            $this->setting_field(__('Maximum URLs', 'wpfs'), 'limit', 'number', array('default_value' => 5000))
        );

        $post_type_fields = array();

        foreach ($this->public_post_types() as $post_type) {
            $name = $post_type->name;
            $default_include = $name !== 'attachment';

            $post_type_fields[] = $this->group_setting_fields(
                $this->setting_field($post_type->label . ($post_type->_builtin ? '' : " ($name)"), false, 'separator'),
                $this->setting_field(__('Include in sitemap', 'wpfs'), "post_type.$name.include", 'checkbox', array('default_value' => $default_include)),
                $this->setting_field(__('Priority', 'wpfs'), "post_type.$name.priority", 'dropdown', array('default_value' => $name === 'page' ? '0.8' : '0.7', 'list' => $this->priorities, 'allow_empty' => false)),
                $this->setting_field(__('Change frequency', 'wpfs'), "post_type.$name.changefreq", 'dropdown', array('default_value' => $name === 'page' ? 'monthly' : 'weekly', 'list' => $this->changefreqs, 'allow_empty' => false)),
                $this->setting_field(__('Add image tags', 'wpfs'), "post_type.$name.images", 'checkbox', array('default_value' => $default_include))
            );
        }

        $fields['post_types'] = $this->group_setting_fields(...$post_type_fields);

        $taxonomy_fields = array();

        foreach ($this->public_taxonomies() as $taxonomy) {
            $name = $taxonomy->name;

            $taxonomy_fields[] = $this->group_setting_fields(
                $this->setting_field($taxonomy->label . ($taxonomy->_builtin ? '' : " ($name)"), false, 'separator'),
                $this->setting_field(__('Include in sitemap', 'wpfs'), "taxonomy.$name.include", 'checkbox', array('default_value' => true)),
                $this->setting_field(__('Priority', 'wpfs'), "taxonomy.$name.priority", 'dropdown', array('default_value' => '0.5', 'list' => $this->priorities, 'allow_empty' => false)),
                $this->setting_field(__('Change frequency', 'wpfs'), "taxonomy.$name.changefreq", 'dropdown', array('default_value' => 'weekly', 'list' => $this->changefreqs, 'allow_empty' => false))
            );
        }

        $fields['taxonomies'] = $this->group_setting_fields(...$taxonomy_fields);

        $archive_fields = array(
            $this->group_setting_fields(
                $this->setting_field(__('Home:', 'wpfs'), false, 'separator'),
                $this->setting_field(__('Include home page', 'wpfs'), 'archive.home.include', 'checkbox', array('default_value' => true)),
                $this->setting_field(__('Priority', 'wpfs'), 'archive.home.priority', 'dropdown', array('default_value' => '1.0', 'list' => $this->priorities, 'allow_empty' => false)),
                $this->setting_field(__('Change frequency', 'wpfs'), 'archive.home.changefreq', 'dropdown', array('default_value' => 'daily', 'list' => $this->changefreqs, 'allow_empty' => false))
            ),
            $this->group_setting_fields(
                $this->setting_field(__('Author archives:', 'wpfs'), false, 'separator'),
                $this->setting_field(__('Include author archives', 'wpfs'), 'archive.author.include', 'checkbox', array('default_value' => false)),
                $this->setting_field(__('Priority', 'wpfs'), 'archive.author.priority', 'dropdown', array('default_value' => '0.3', 'list' => $this->priorities, 'allow_empty' => false)),
                $this->setting_field(__('Change frequency', 'wpfs'), 'archive.author.changefreq', 'dropdown', array('default_value' => 'monthly', 'list' => $this->changefreqs, 'allow_empty' => false))
            ),
            $this->group_setting_fields(
                $this->setting_field(__('Date archives:', 'wpfs'), false, 'separator'),
                $this->setting_field(__('Include monthly date archives', 'wpfs'), 'archive.date.include', 'checkbox', array('default_value' => false)),
                $this->setting_field(__('Priority', 'wpfs'), 'archive.date.priority', 'dropdown', array('default_value' => '0.3', 'list' => $this->priorities, 'allow_empty' => false)),
                $this->setting_field(__('Change frequency', 'wpfs'), 'archive.date.changefreq', 'dropdown', array('default_value' => 'monthly', 'list' => $this->changefreqs, 'allow_empty' => false))
            ),
        );

        foreach ($this->public_post_types() as $post_type) {
            if (!$post_type->has_archive) {
                continue;
            }

            $name = $post_type->name;

            $archive_fields[] = $this->group_setting_fields(
                $this->setting_field(sprintf(__('%s archive:', 'wpfs'), $post_type->label), false, 'separator'),
                $this->setting_field(__('Include post type archive', 'wpfs'), "archive.post_type.$name.include", 'checkbox', array('default_value' => true)),
                $this->setting_field(__('Priority', 'wpfs'), "archive.post_type.$name.priority", 'dropdown', array('default_value' => '0.6', 'list' => $this->priorities, 'allow_empty' => false)),
                $this->setting_field(__('Change frequency', 'wpfs'), "archive.post_type.$name.changefreq", 'dropdown', array('default_value' => 'weekly', 'list' => $this->changefreqs, 'allow_empty' => false))
            );
        }

        $fields['archives'] = $this->group_setting_fields(...$archive_fields);

        $fields['ping'] = $this->group_setting_fields(
            $this->setting_field(__('Automatic ping:', 'wpfs'), false, 'separator'),
            $this->setting_field(__('Ping search engines when published content changes', 'wpfs'), 'ping.active', 'checkbox', array('default_value' => true)),
            $this->setting_field(__('Google', 'wpfs'), 'ping.google', 'checkbox', array('default_value' => true)),
            $this->setting_field(__('Bing', 'wpfs'), 'ping.bing', 'checkbox', array('default_value' => true)),
            $this->setting_field(__('Custom ping endpoints', 'wpfs'), 'ping.custom', 'textarea', array('placeholder' => __('One URL per line. Use {sitemap} as placeholder for the encoded sitemap URL.', 'wpfs')))
        );

        return $this->group_setting_sections($fields, $filter);
    }

    public function validate_settings($input, $filtering = false): array
    {
        $valid = parent::validate_settings($input, $filtering);

        $valid['limit'] = max(1, min(50000, absint($valid['limit'] ?? 5000)));
        $valid['images']['limit'] = max(0, min(100, absint($valid['images']['limit'] ?? 10)));
        $valid['cache']['ttl_minutes'] = max(5, min(10080, absint($valid['cache']['ttl_minutes'] ?? 720)));

        add_action('shutdown', function () {
            $this->clear_sitemap_cache();
            flush_rewrite_rules(false);
        });

        return $valid;
    }

    private function is_sitemap_request(): bool
    {
        if ((string)get_query_var('wpfs_sitemap') === '1') {
            return true;
        }

        return $this->is_sitemap_uri();
    }

    private function is_sitemap_uri(): bool
    {
        $request_uri = isset($_SERVER['REQUEST_URI']) ? (string)wp_unslash($_SERVER['REQUEST_URI']) : '';
        $request_path = trim((string)wp_parse_url($request_uri, PHP_URL_PATH), '/');
        $home_path = trim((string)wp_parse_url(home_url('/'), PHP_URL_PATH), '/');

        if ($home_path !== '' && strpos($request_path, $home_path . '/') === 0) {
            $request_path = substr($request_path, strlen($home_path) + 1);
        }

        return $request_path === 'sitemap.xml';
    }

    private function render_xml(): void
    {
        status_header(200);
        nocache_headers();
        header('Content-Type: application/xml; charset=' . get_bloginfo('charset'));

        echo $this->sitemap_xml();
    }

    private function sitemap_xml(): string
    {
        if ($this->option('cache.active', true)) {
            $cached = get_transient(self::CACHE_TRANSIENT);

            if (is_string($cached) && $cached !== '') {
                header('X-Flexy-SEO-Sitemap-Cache: HIT');
                return $cached;
            }
        }

        header('X-Flexy-SEO-Sitemap-Cache: MISS');

        $xml = $this->build_sitemap_xml();

        if ($this->option('cache.active', true)) {
            set_transient(self::CACHE_TRANSIENT, $xml, $this->cache_ttl());
        }

        return $xml;
    }

    private function build_sitemap_xml(): string
    {
        $xml = '<?xml version="1.0" encoding="' . esc_attr(get_bloginfo('charset')) . "\"?>\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">' . "\n";

        foreach ($this->sitemap_entries() as $entry) {
            $xml .= "\t<url>\n";
            $xml .= "\t\t<loc>" . $this->xml($entry['loc']) . "</loc>\n";

            if (!empty($entry['lastmod'])) {
                $xml .= "\t\t<lastmod>" . $this->xml($entry['lastmod']) . "</lastmod>\n";
            }

            $xml .= "\t\t<changefreq>" . $this->xml($entry['changefreq']) . "</changefreq>\n";
            $xml .= "\t\t<priority>" . $this->xml($entry['priority']) . "</priority>\n";

            foreach ($entry['images'] ?? array() as $image) {
                $xml .= "\t\t<image:image>\n";
                $xml .= "\t\t\t<image:loc>" . $this->xml($image) . "</image:loc>\n";
                $xml .= "\t\t</image:image>\n";
            }

            $xml .= "\t</url>\n";
        }

        $xml .= '</urlset>';

        return $xml;
    }

    public function clear_sitemap_cache(...$args): void
    {
        delete_transient(self::CACHE_TRANSIENT);
    }

    public function cleanup_expired_cache(): void
    {
        global $wpdb;

        $timeout_prefix = '_transient_timeout_' . self::CACHE_GROUP_PREFIX;
        $like = $wpdb->esc_like($timeout_prefix) . '%';
        $timeout_names = $wpdb->get_col($wpdb->prepare(
            "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s AND option_value < %d LIMIT 100",
            $like,
            time()
        ));

        foreach ($timeout_names as $timeout_name) {
            $transient_name = str_replace('_transient_timeout_', '_transient_', $timeout_name);
            delete_option($timeout_name);
            delete_option($transient_name);
        }
    }

    private function cache_ttl(): int
    {
        return max(300, min(WEEK_IN_SECONDS, absint($this->option('cache.ttl_minutes', 720)) * MINUTE_IN_SECONDS));
    }

    private function schedule_cache_cleanup(): void
    {
        if (!wp_next_scheduled(self::CLEANUP_HOOK)) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', self::CLEANUP_HOOK);
        }
    }

    private function sitemap_entries(): array
    {
        $entries = array();
        $limit = max(1, absint($this->option('limit', 5000)));

        foreach ($this->archive_entries() as $entry) {
            $entries[] = $entry;

            if (count($entries) >= $limit) {
                return $entries;
            }
        }

        foreach ($this->post_entries($limit - count($entries)) as $entry) {
            $entries[] = $entry;

            if (count($entries) >= $limit) {
                return $entries;
            }
        }

        foreach ($this->taxonomy_entries($limit - count($entries)) as $entry) {
            $entries[] = $entry;

            if (count($entries) >= $limit) {
                return $entries;
            }
        }

        return $entries;
    }

    private function archive_entries(): array
    {
        global $wpdb;

        $entries = array();

        if ($this->option('archive.home.include', true)) {
            $entries[] = array(
                'loc'        => home_url('/'),
                'lastmod'    => $this->latest_post_modified_time(),
                'changefreq' => $this->changefreq('archive.home.changefreq', 'daily'),
                'priority'   => $this->priority('archive.home.priority', '1.0'),
                'images'     => array(),
            );
        }

        foreach ($this->public_post_types() as $post_type) {
            if (!$post_type->has_archive || !$this->option("archive.post_type.$post_type->name.include", true)) {
                continue;
            }

            $link = get_post_type_archive_link($post_type->name);

            if (!$link) {
                continue;
            }

            $entries[] = array(
                'loc'        => $link,
                'lastmod'    => $this->latest_post_modified_time(array($post_type->name)),
                'changefreq' => $this->changefreq("archive.post_type.$post_type->name.changefreq", 'weekly'),
                'priority'   => $this->priority("archive.post_type.$post_type->name.priority", '0.6'),
                'images'     => array(),
            );
        }

        $included_post_types = $this->included_post_type_names();

        if ($this->option('archive.author.include', false)) {
            foreach (get_users(array('has_published_posts' => $included_post_types, 'fields' => array('ID'))) as $user) {
                $entries[] = array(
                    'loc'        => get_author_posts_url($user->ID),
                    'lastmod'    => '',
                    'changefreq' => $this->changefreq('archive.author.changefreq', 'monthly'),
                    'priority'   => $this->priority('archive.author.priority', '0.3'),
                    'images'     => array(),
                );
            }
        }

        if ($this->option('archive.date.include', false) && !empty($included_post_types)) {
            $placeholders = implode(',', array_fill(0, count($included_post_types), '%s'));
            $sql = "
                SELECT YEAR(post_date) AS year_value, MONTH(post_date) AS month_value, MAX(post_modified_gmt) AS lastmod
                FROM {$wpdb->posts}
                WHERE post_status = 'publish'
                    AND post_type IN ($placeholders)
                GROUP BY YEAR(post_date), MONTH(post_date)
                ORDER BY post_date DESC
            ";

            $rows = $wpdb->get_results($wpdb->prepare($sql, $included_post_types));

            foreach ($rows as $row) {
                $entries[] = array(
                    'loc'        => get_month_link((int)$row->year_value, (int)$row->month_value),
                    'lastmod'    => $row->lastmod ? mysql2date('c', $row->lastmod, false) : '',
                    'changefreq' => $this->changefreq('archive.date.changefreq', 'monthly'),
                    'priority'   => $this->priority('archive.date.priority', '0.3'),
                    'images'     => array(),
                );
            }
        }

        return $entries;
    }

    private function post_entries(int $limit): array
    {
        if ($limit <= 0) {
            return array();
        }

        $post_types = $this->included_post_type_names();

        if (empty($post_types)) {
            return array();
        }

        $query = new \WP_Query(array(
            'post_type'              => $post_types,
            'post_status'            => 'publish',
            'posts_per_page'         => $limit,
            'orderby'                => 'modified',
            'order'                  => 'DESC',
            'no_found_rows'          => true,
            'ignore_sticky_posts'    => true,
            'update_post_meta_cache' => true,
            'update_post_term_cache' => false,
        ));

        $entries = array();

        foreach ($query->posts as $post) {
            if (!($post instanceof \WP_Post)) {
                continue;
            }

            $post_type = $post->post_type;

            $entries[] = array(
                'loc'        => get_permalink($post),
                'lastmod'    => get_post_modified_time('c', true, $post),
                'changefreq' => $this->changefreq("post_type.$post_type.changefreq", 'weekly'),
                'priority'   => $this->priority("post_type.$post_type.priority", $post_type === 'page' ? '0.8' : '0.7'),
                'images'     => $this->images_for_post($post, $post_type),
            );
        }

        return $entries;
    }

    private function taxonomy_entries(int $limit): array
    {
        if ($limit <= 0) {
            return array();
        }

        $entries = array();

        foreach ($this->public_taxonomies() as $taxonomy) {
            if (!$this->option("taxonomy.$taxonomy->name.include", true)) {
                continue;
            }

            $terms = get_terms(array(
                'taxonomy'   => $taxonomy->name,
                'hide_empty' => true,
                'number'     => $limit - count($entries),
            ));

            if (is_wp_error($terms)) {
                continue;
            }

            foreach ($terms as $term) {
                $link = get_term_link($term);

                if (is_wp_error($link)) {
                    continue;
                }

                $entries[] = array(
                    'loc'        => $link,
                    'lastmod'    => '',
                    'changefreq' => $this->changefreq("taxonomy.$taxonomy->name.changefreq", 'weekly'),
                    'priority'   => $this->priority("taxonomy.$taxonomy->name.priority", '0.5'),
                    'images'     => array(),
                );

                if (count($entries) >= $limit) {
                    return $entries;
                }
            }
        }

        return $entries;
    }

    private function images_for_post(\WP_Post $post, string $post_type): array
    {
        if (!$this->option('images.active', true) || !$this->option("post_type.$post_type.images", true)) {
            return array();
        }

        $limit = max(0, absint($this->option('images.limit', 10)));

        if ($limit === 0) {
            return array();
        }

        $images = array();
        $thumbnail_id = get_post_thumbnail_id($post);

        if ($thumbnail_id) {
            $thumbnail_url = wp_get_attachment_image_url($thumbnail_id, 'full');

            if ($thumbnail_url) {
                $images[] = $thumbnail_url;
            }
        }

        if ($this->option('images.attachments', false)) {
            foreach (get_attached_media('image', $post->ID) as $attachment) {
                $url = wp_get_attachment_url($attachment->ID);

                if ($url) {
                    $images[] = $url;
                }
            }
        }

        $content = (string)$post->post_content;

        if ($content && preg_match_all('/<img[^>]+src=["\']([^"\']+)["\']/i', $content, $matches)) {
            foreach ($matches[1] as $src) {
                $images[] = $this->normalize_url($src);
            }
        }

        $images = array_values(array_unique(array_filter(array_map('esc_url_raw', $images))));

        return array_slice($images, 0, $limit);
    }

    private function public_post_types(): array
    {
        return get_post_types(array('public' => true), 'objects');
    }

    private function public_taxonomies(): array
    {
        return get_taxonomies(array('public' => true), 'objects');
    }

    private function included_post_type_names(): array
    {
        $post_types = array();

        foreach ($this->public_post_types() as $post_type) {
            if ($this->option("post_type.$post_type->name.include", $post_type->name !== 'attachment')) {
                $post_types[] = $post_type->name;
            }
        }

        return $post_types;
    }

    private function latest_post_modified_time(array $post_types = array()): string
    {
        global $wpdb;

        $post_types = $post_types ?: $this->included_post_type_names();

        if (empty($post_types)) {
            return '';
        }

        $placeholders = implode(',', array_fill(0, count($post_types), '%s'));
        $sql = "SELECT MAX(post_modified_gmt) FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type IN ($placeholders)";
        $lastmod = $wpdb->get_var($wpdb->prepare($sql, $post_types));

        return $lastmod ? mysql2date('c', $lastmod, false) : '';
    }

    private function changefreq(string $path, string $default): string
    {
        $value = (string)$this->option($path, $default);

        return in_array($value, $this->changefreqs, true) ? $value : $default;
    }

    private function priority(string $path, string $default): string
    {
        $value = (string)$this->option($path, $default);

        return in_array($value, $this->priorities, true) ? $value : $default;
    }

    private function sitemap_url(): string
    {
        return home_url('/sitemap.xml');
    }

    private function custom_ping_endpoints(string $encoded_sitemap): array
    {
        $custom = (string)$this->option('ping.custom', '');

        if ($custom === '') {
            return array();
        }

        $endpoints = array();

        foreach (preg_split("#[\r\n]+#", $custom) as $endpoint) {
            $endpoint = trim($endpoint);

            if ($endpoint === '') {
                continue;
            }

            $endpoints[] = str_replace('{sitemap}', $encoded_sitemap, $endpoint);
        }

        return $endpoints;
    }

    private function normalize_url(string $url): string
    {
        $url = trim(html_entity_decode($url, ENT_QUOTES, get_bloginfo('charset')));

        if ($url === '') {
            return '';
        }

        if (strpos($url, '//') === 0) {
            $scheme = is_ssl() ? 'https:' : 'http:';

            return $scheme . $url;
        }

        if (strpos($url, '/') === 0) {
            return home_url($url);
        }

        return $url;
    }

    private function xml(string $value): string
    {
        if (function_exists('esc_xml')) {
            return esc_xml($value);
        }

        return _wp_specialchars($value, ENT_XML1, get_bloginfo('charset'), false);
    }
}

return __NAMESPACE__;
