<?php
/**
 * @author    sh1zen
 * @copyright Copyright (C) 2025.
 * @license   http://www.gnu.org/licenses/gpl.html GNU/GPL
 */

namespace FlexySEO\modules;

use WPS\modules\Module;

class Mod_not_found_monitor extends Module
{
    private const DB_VERSION = '1.0.0';

    public static ?string $name = '404 Monitor';

    public array $scopes = array('admin-page', 'web-view', 'autoload');

    protected string $context = 'wpfs';

    public function restricted_access($context = ''): bool
    {
        switch ($context) {
            case 'render-admin':
            case 'settings':
                return !current_user_can('manage_options');

            default:
                return false;
        }
    }

    protected function init(): void
    {
        add_action('wpfs-activate', array($this, 'install_table'));
        add_action('init', array($this, 'maybe_install_table'), 1);

        if (is_admin()) {
            add_action('admin_init', array($this, 'handle_admin_actions'));
        }
        elseif (wps_core()->doing_webview()) {
            add_action('template_redirect', array($this, 'maybe_log_404'), 99);
        }
    }

    public function register_panel($parent, $capability = 'manage_options')
    {
        return parent::register_panel($parent, $capability);
    }

    public function install_table(): void
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $wpdb->get_charset_collate();
        $table = $this->table_name();
        $sql = "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            request_path varchar(191) NOT NULL,
            hits bigint(20) unsigned NOT NULL DEFAULT 1,
            first_seen datetime NOT NULL,
            last_seen datetime NOT NULL,
            referrer varchar(255) NULL,
            user_agent varchar(255) NULL,
            ip_hash varchar(64) NULL,
            created_redirect_id bigint(20) unsigned NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY request_path (request_path),
            KEY hits (hits),
            KEY last_seen (last_seen),
            KEY created_redirect_id (created_redirect_id)
        ) {$charset_collate};";

        dbDelta($sql);

        update_option('wpfs_404_monitor_db_version', self::DB_VERSION, false);
    }

    public function maybe_install_table(): void
    {
        if (get_option('wpfs_404_monitor_db_version') !== self::DB_VERSION) {
            $this->install_table();
        }
    }

    public function handle_admin_actions(): void
    {
        if (!$this->is_monitor_screen()) {
            return;
        }

        $this->install_table();

        if (empty($_POST['wpfs_404_action'])) {
            return;
        }

        $this->verify_admin_nonce('wpfs_404_action');

        $action = sanitize_key(wp_unslash($_POST['wpfs_404_action']));

        switch ($action) {
            case 'create_redirect':
                $this->create_redirect_from_request();
                break;

            case 'clear_log':
                $this->clear_log_from_request();
                break;
        }
    }

    protected function render_sub_modules(): void
    {
        $this->install_table();

        $rows = $this->get_logs();
        $stats = $this->get_stats();

        ?>
        <section class="wps-wrap wpfs-admin-shell wpfs-404-monitor-page">
            <header class="wpfs-page-hero">
                <div>
                    <span class="wpfs-kicker"><?php esc_html_e('URL lifecycle', 'wpfs'); ?></span>
                    <h1><?php esc_html_e('404 Monitor', 'wpfs'); ?></h1>
                    <p><?php esc_html_e('Review missing URLs, count visits and create redirects directly from the log.', 'wpfs'); ?></p>
                </div>
            </header>
            <block class="wps wpfs-card">
                <div class="wpfs-section-head">
                    <div>
                        <h2><?php esc_html_e('404 log', 'wpfs'); ?></h2>
                        <p><?php esc_html_e('Bot traffic and common spam probes are excluded before logging.', 'wpfs'); ?></p>
                    </div>
                    <?php if (!empty($rows)) : ?>
                        <form method="post" class="wpfs-inline-form">
                            <?php wp_nonce_field('wpfs_404_action'); ?>
                            <input type="hidden" name="wpfs_404_action" value="clear_log">
                            <button class="button" type="submit" onclick="return confirm('<?php echo esc_js(__('Clear the 404 log?', 'wpfs')); ?>');"><?php esc_html_e('Clear log', 'wpfs'); ?></button>
                        </form>
                    <?php endif; ?>
                </div>
                <div class="wpfs-audit-summary wpfs-404-summary" aria-label="<?php esc_attr_e('404 summary', 'wpfs'); ?>">
                    <div>
                        <strong><?php echo esc_html(number_format_i18n($stats['urls'])); ?></strong>
                        <span><?php esc_html_e('404 URLs', 'wpfs'); ?></span>
                    </div>
                    <div>
                        <strong><?php echo esc_html(number_format_i18n($stats['hits'])); ?></strong>
                        <span><?php esc_html_e('Visits', 'wpfs'); ?></span>
                    </div>
                    <div>
                        <strong><?php echo esc_html(number_format_i18n($stats['with_redirect'])); ?></strong>
                        <span><?php esc_html_e('Redirected', 'wpfs'); ?></span>
                    </div>
                </div>
                <div class="wpfs-404-table-wrap">
                    <table class="wp-list-table widefat fixed striped table-view-list wpfs-redirect-table wpfs-404-table">
                        <thead>
                        <tr>
                            <th scope="col"><?php esc_html_e('404 URL', 'wpfs'); ?></th>
                            <th scope="col"><?php esc_html_e('Visits', 'wpfs'); ?></th>
                            <th scope="col"><?php esc_html_e('First seen', 'wpfs'); ?></th>
                            <th scope="col"><?php esc_html_e('Last seen', 'wpfs'); ?></th>
                            <th scope="col"><?php esc_html_e('Referrer', 'wpfs'); ?></th>
                            <th scope="col"><?php esc_html_e('Quick redirect', 'wpfs'); ?></th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($rows)) : ?>
                            <tr>
                                <td colspan="6"><?php esc_html_e('No 404 URLs logged yet.', 'wpfs'); ?></td>
                            </tr>
                        <?php else : ?>
                            <?php foreach ($rows as $row) : ?>
                                <tr>
                                    <td class="wpfs-404-url"><code><?php echo esc_html($row['request_path']); ?></code></td>
                                    <td class="wpfs-404-count"><?php echo esc_html(number_format_i18n((int)$row['hits'])); ?></td>
                                    <td><?php echo esc_html(mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $row['first_seen'])); ?></td>
                                    <td><?php echo esc_html(mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $row['last_seen'])); ?></td>
                                    <td class="wpfs-404-referrer"><?php echo $row['referrer'] ? '<code>' . esc_html($row['referrer']) . '</code>' : esc_html__('Direct', 'wpfs'); ?></td>
                                    <td class="wpfs-404-action-cell">
                                        <?php if (!empty($row['created_redirect_id'])) : ?>
                                            <a class="wpfs-audit-action wpfs-audit-action--edit" href="<?php echo esc_url(add_query_arg(array('page' => 'wpfs-redirects', 'redirect_id' => (int)$row['created_redirect_id']), admin_url('admin.php'))); ?>">
                                                <span class="dashicons dashicons-edit"></span><?php esc_html_e('Edit redirect', 'wpfs'); ?>
                                            </a>
                                        <?php else : ?>
                                            <form method="post" class="wpfs-404-redirect-form">
                                                <?php wp_nonce_field('wpfs_404_action'); ?>
                                                <input type="hidden" name="wpfs_404_action" value="create_redirect">
                                                <input type="hidden" name="log_id" value="<?php echo esc_attr((string)$row['id']); ?>">
                                                <input type="hidden" name="source_path" value="<?php echo esc_attr($row['request_path']); ?>">
                                                <input type="text" name="target_url" placeholder="<?php esc_attr_e('/new-url/', 'wpfs'); ?>" aria-label="<?php esc_attr_e('Redirect target', 'wpfs'); ?>" required>
                                                <button class="button button-primary" type="submit"><?php esc_html_e('Create redirect', 'wpfs'); ?></button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </block>
        </section>
        <?php
    }

    public function maybe_log_404(): void
    {
        if (!$this->should_log_404()) {
            return;
        }

        global $wpdb;

        $path = $this->normalize_source((string)($_SERVER['REQUEST_URI'] ?? ''));

        if (!$path || $path === '/') {
            return;
        }

        $path = $this->limit_text($path, 191);
        $now = current_time('mysql');
        $referrer = $this->limit_text(esc_url_raw(wp_unslash($_SERVER['HTTP_REFERER'] ?? '')), 255);
        $user_agent = $this->limit_text(sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'] ?? '')), 255);
        $ip = sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'] ?? ''));
        $ip_hash = $ip ? hash_hmac('sha256', $ip, wp_salt('auth')) : null;

        $wpdb->query($wpdb->prepare(
            "INSERT INTO {$this->table_name()} (request_path, hits, first_seen, last_seen, referrer, user_agent, ip_hash)
            VALUES (%s, 1, %s, %s, %s, %s, %s)
            ON DUPLICATE KEY UPDATE hits = hits + 1, last_seen = VALUES(last_seen), referrer = VALUES(referrer), user_agent = VALUES(user_agent), ip_hash = VALUES(ip_hash)",
            $path,
            $now,
            $now,
            $referrer ?: null,
            $user_agent ?: null,
            $ip_hash
        ));
    }

    private function create_redirect_from_request(): void
    {
        $log_id = absint($_POST['log_id'] ?? 0);
        $source = $this->normalize_source((string)wp_unslash($_POST['source_path'] ?? ''));
        $target = $this->normalize_target((string)wp_unslash($_POST['target_url'] ?? ''));

        if (!$source || $source === '/') {
            $this->redirect_with_notice(__('404 URL is required.', 'wpfs'), 'error');
        }

        if (!$target) {
            $this->redirect_with_notice(__('Target URL is required to create a redirect.', 'wpfs'), 'error');
        }

        $saved = $this->upsert_redirect(array(
            'source_path'  => $source,
            'target_url'   => $target,
            'status'       => 301,
            'auto_created' => 0,
            'notes'        => __('Created from the 404 monitor.', 'wpfs'),
        ));

        if ($saved) {
            $redirect = $this->find_redirect($source);
            $this->mark_redirect((int)($redirect['id'] ?? 0), $log_id);
        }

        $this->redirect_with_notice(
            $saved ? __('Redirect created from 404 log.', 'wpfs') : __('Unable to create redirect from 404 log.', 'wpfs'),
            $saved ? 'success' : 'error'
        );
    }

    private function clear_log_from_request(): void
    {
        global $wpdb;

        $wpdb->query("TRUNCATE TABLE {$this->table_name()}");

        $this->redirect_with_notice(__('404 log cleared.', 'wpfs'), 'success');
    }

    private function upsert_redirect(array $data): bool
    {
        global $wpdb;

        $this->install_redirect_table();

        $now = current_time('mysql');
        $source = $this->normalize_source((string)($data['source_path'] ?? ''));
        $target = $this->normalize_target((string)($data['target_url'] ?? ''));

        $payload = array(
            'source_path'  => $source,
            'target_url'   => $target,
            'status'       => 301,
            'auto_created' => 0,
            'notes'        => sanitize_textarea_field((string)($data['notes'] ?? '')),
            'updated_at'   => $now,
        );
        $formats = array('%s', '%s', '%d', '%d', '%s', '%s');

        $existing_id = (int)$wpdb->get_var($wpdb->prepare("SELECT id FROM {$this->redirect_table_name()} WHERE source_path = %s", $source));

        if ($existing_id) {
            return false !== $wpdb->update($this->redirect_table_name(), $payload, array('id' => $existing_id), $formats, array('%d'));
        }

        $payload['created_at'] = $now;
        $formats[] = '%s';

        return false !== $wpdb->insert($this->redirect_table_name(), $payload, $formats);
    }

    private function install_redirect_table(): void
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $wpdb->get_charset_collate();
        $table = $this->redirect_table_name();
        $sql = "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            source_path varchar(191) NOT NULL,
            target_url text NOT NULL,
            status smallint(3) unsigned NOT NULL DEFAULT 301,
            hits bigint(20) unsigned NOT NULL DEFAULT 0,
            last_hit datetime NULL DEFAULT NULL,
            auto_created tinyint(1) unsigned NOT NULL DEFAULT 0,
            notes text NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY source_path (source_path),
            KEY status (status),
            KEY hits (hits)
        ) {$charset_collate};";

        dbDelta($sql);
    }

    private function find_redirect(string $source): ?array
    {
        global $wpdb;

        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->redirect_table_name()} WHERE source_path = %s", $source), ARRAY_A);

        return $row ?: null;
    }

    private function mark_redirect(int $redirect_id, int $log_id): void
    {
        if (!$redirect_id || !$log_id) {
            return;
        }

        global $wpdb;

        $wpdb->update(
            $this->table_name(),
            array('created_redirect_id' => $redirect_id),
            array('id' => $log_id),
            array('%d'),
            array('%d')
        );
    }

    private function get_logs(int $limit = 100): array
    {
        global $wpdb;

        $sql = "SELECT * FROM {$this->table_name()} ORDER BY hits DESC, last_seen DESC";

        if ($limit > 0) {
            $sql .= $wpdb->prepare(' LIMIT %d', $limit);
        }

        return $wpdb->get_results($sql, ARRAY_A) ?: array();
    }

    private function get_stats(): array
    {
        global $wpdb;

        $stats = $wpdb->get_row(
            "SELECT COUNT(*) AS urls, COALESCE(SUM(hits), 0) AS hits, SUM(CASE WHEN created_redirect_id IS NULL THEN 0 ELSE 1 END) AS with_redirect FROM {$this->table_name()}",
            ARRAY_A
        );

        if (!$stats) {
            return array('urls' => 0, 'hits' => 0, 'with_redirect' => 0);
        }

        return array(
            'urls'          => (int)$stats['urls'],
            'hits'          => (int)$stats['hits'],
            'with_redirect' => (int)$stats['with_redirect'],
        );
    }

    private function should_log_404(): bool
    {
        if (is_admin() || wp_doing_ajax() || wp_doing_cron() || !is_404()) {
            return false;
        }

        $path = $this->normalize_source((string)($_SERVER['REQUEST_URI'] ?? ''));

        if (!$path || $path === '/' || $this->is_probably_bot() || $this->is_spam_404_path($path)) {
            return false;
        }

        return true;
    }

    private function is_probably_bot(): bool
    {
        $user_agent = strtolower((string)($_SERVER['HTTP_USER_AGENT'] ?? ''));

        if ($user_agent === '') {
            return true;
        }

        $needles = array(
            'bot',
            'crawl',
            'spider',
            'slurp',
            'bingpreview',
            'facebookexternalhit',
            'python-requests',
            'curl',
            'wget',
            'go-http-client',
            'httpclient',
            'libwww-perl',
            'semrush',
            'ahrefs',
            'majestic',
            'dotbot',
            'petalbot',
            'bytespider',
            'amazonbot',
            'claudebot',
            'gptbot',
            'perplexity',
        );

        foreach ($needles as $needle) {
            if (false !== strpos($user_agent, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function is_spam_404_path(string $path): bool
    {
        $path = strtolower($path);
        $needles = array(
            '/.env',
            '/.git',
            '/wp-login.php',
            '/xmlrpc.php',
            '/phpmyadmin',
            '/pma/',
            '/vendor/phpunit',
            '/eval-stdin.php',
            '/cgi-bin/',
            '/setup.php',
            '/config.php',
            '/adminer',
            '/shell',
            '/webshell',
        );

        foreach ($needles as $needle) {
            if (false !== strpos($path, $needle)) {
                return true;
            }
        }

        return (bool)preg_match('#\.(?:php[0-9]?|asp|aspx|jsp|cgi)(?:$|\?)#', $path);
    }

    private function normalize_source(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        $home = wp_parse_url(home_url('/'));
        $parts = wp_parse_url($value);

        if (isset($parts['host']) && isset($home['host']) && strtolower($parts['host']) !== strtolower($home['host'])) {
            return '';
        }

        $path = $parts['path'] ?? $value;
        $path = '/' . ltrim((string)$path, '/');
        $path = preg_replace('#/+#', '/', $path);

        if (!empty($parts['query'])) {
            $path .= '?' . $parts['query'];
        }

        return esc_url_raw($path);
    }

    private function normalize_target(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        if (preg_match('#^https?://#i', $value)) {
            return esc_url_raw($value);
        }

        return esc_url_raw('/' . ltrim($value, '/'));
    }

    private function limit_text(string $value, int $length): string
    {
        $value = trim($value);

        if (function_exists('mb_substr')) {
            return mb_substr($value, 0, $length);
        }

        return substr($value, 0, $length);
    }

    private function table_name(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'flexy_seo_404_logs';
    }

    private function redirect_table_name(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'flexy_seo_redirects';
    }

    private function is_monitor_screen(): bool
    {
        return is_admin() && isset($_GET['page']) && $_GET['page'] === 'wpfs-not_found_monitor';
    }

    private function verify_admin_nonce(string $action): void
    {
        if (!current_user_can('manage_options') || !check_admin_referer($action)) {
            wp_die(esc_html__('You are not allowed to do this request.', 'wpfs'));
        }
    }

    private function redirect_with_notice(string $message, string $status): void
    {
        wp_safe_redirect(add_query_arg(array(
            'page'       => 'wpfs-not_found_monitor',
            'wps-notice' => rawurlencode($message),
            'wps-status' => $status,
        ), admin_url('admin.php')));
        exit;
    }
}

return __NAMESPACE__;
