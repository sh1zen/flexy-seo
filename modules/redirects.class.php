<?php
/**
 * @author    sh1zen
 * @copyright Copyright (C) 2025.
 * @license   http://www.gnu.org/licenses/gpl.html GNU/GPL
 */

namespace FlexySEO\modules;

use WPS\modules\Module;

class Mod_redirects extends Module
{
    private const DB_VERSION = '1.1.0';

    public static ?string $name = 'Redirects';

    public array $scopes = array('admin-page', 'web-view', 'autoload');

    protected string $context = 'wpfs';

    private array $old_post_urls = array();

    private array $old_term_urls = array();

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
        add_action('pre_post_update', array($this, 'capture_old_post_url'), 10, 2);
        add_action('post_updated', array($this, 'maybe_create_post_redirect'), 10, 3);
        add_action('pre_edit_term', array($this, 'capture_old_term_url'), 10, 2);
        add_action('edited_term', array($this, 'maybe_create_term_redirect'), 10, 3);
        add_action('init', array($this, 'maybe_install_table'), 1);

        if (is_admin()) {
            add_action('admin_init', array($this, 'handle_admin_actions'));
        }
        elseif (wps_core()->doing_webview()) {
            add_action('template_redirect', array($this, 'maybe_redirect'), 0);
        }
    }

    public function install_table(): void
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $wpdb->get_charset_collate();
        $table = $this->table_name();

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

        update_option('wpfs_redirects_db_version', self::DB_VERSION, false);
    }

    public function maybe_install_table(): void
    {
        if (get_option('wpfs_redirects_db_version') !== self::DB_VERSION) {
            $this->install_table();
        }
    }

    public function register_panel($parent, $capability = 'manage_options')
    {
        return parent::register_panel($parent, $capability);
    }

    public function handle_admin_actions(): void
    {
        if (!$this->is_redirects_screen()) {
            return;
        }

        $this->install_table();

        if (isset($_GET['wpfs_redirect_export'])) {
            $this->verify_admin_nonce('wpfs_redirect_export');
            $this->export_csv();
        }

        if (empty($_POST['wpfs_redirect_action'])) {
            return;
        }

        $this->verify_admin_nonce('wpfs_redirect_action');

        $action = sanitize_key(wp_unslash($_POST['wpfs_redirect_action']));

        switch ($action) {
            case 'save':
                $this->save_redirect_from_request();
                break;

            case 'delete':
                $this->delete_redirect_from_request();
                break;

            case 'import':
                $this->import_csv_from_request();
                break;
        }
    }

    protected function render_sub_modules(): void
    {
        $this->install_table();

        $edit_id = absint($_GET['redirect_id'] ?? 0);
        $editing = $edit_id ? $this->get_redirect($edit_id) : null;
        $rows = $this->get_redirects();
        $stats = $this->get_stats($rows);
        $export_url = wp_nonce_url(
            add_query_arg(array('page' => 'wpfs-redirects', 'wpfs_redirect_export' => 1), admin_url('admin.php')),
            'wpfs_redirect_export'
        );

        ?>
        <section class="wps-wrap wpfs-admin-shell wpfs-redirects-page">
            <header class="wpfs-page-hero">
                <div>
                    <span class="wpfs-kicker"><?php esc_html_e('URL lifecycle', 'wpfs'); ?></span>
                    <h1><?php esc_html_e('Redirect manager', 'wpfs'); ?></h1>
                    <p><?php esc_html_e('Manage 301, 302 and 410 responses, import CSV rules and track how often old URLs are requested.', 'wpfs'); ?></p>
                </div>
            </header>
            <block class="wps wpfs-card">
                <div class="wpfs-audit-summary" aria-label="<?php esc_attr_e('Redirect summary', 'wpfs'); ?>">
                    <div>
                        <strong><?php echo esc_html(number_format_i18n($stats['total'])); ?></strong>
                        <span><?php esc_html_e('Rules', 'wpfs'); ?></span>
                    </div>
                    <div>
                        <strong><?php echo esc_html(number_format_i18n($stats['hits'])); ?></strong>
                        <span><?php esc_html_e('Tracked hits', 'wpfs'); ?></span>
                    </div>
                    <div>
                        <strong><?php echo esc_html(number_format_i18n($stats['auto'])); ?></strong>
                        <span><?php esc_html_e('Automatic', 'wpfs'); ?></span>
                    </div>
                    <div>
                        <strong><?php echo esc_html(number_format_i18n($stats['gone'])); ?></strong>
                        <span><?php esc_html_e('410 gone', 'wpfs'); ?></span>
                    </div>
                </div>
            </block>
            <div class="wpfs-redirect-layout">
                <block class="wps wpfs-card">
                    <h2><?php echo $editing ? esc_html__('Edit redirect', 'wpfs') : esc_html__('Add redirect', 'wpfs'); ?></h2>
                    <form method="post" class="wpfs-redirect-form">
                        <?php wp_nonce_field('wpfs_redirect_action'); ?>
                        <input type="hidden" name="wpfs_redirect_action" value="save">
                        <input type="hidden" name="redirect_id" value="<?php echo esc_attr((string)($editing['id'] ?? 0)); ?>">
                        <label>
                            <span><?php esc_html_e('Old URL', 'wpfs'); ?></span>
                            <input type="text" name="source_path" value="<?php echo esc_attr($editing['source_path'] ?? ''); ?>" placeholder="/old-page/" required>
                        </label>
                        <label>
                            <span><?php esc_html_e('Target URL', 'wpfs'); ?></span>
                            <input type="text" name="target_url" value="<?php echo esc_attr($editing['target_url'] ?? ''); ?>" placeholder="/new-page/">
                        </label>
                        <label>
                            <span><?php esc_html_e('Status', 'wpfs'); ?></span>
                            <select name="status">
                                <?php foreach ($this->status_options() as $status => $label) : ?>
                                    <option value="<?php echo esc_attr((string)$status); ?>" <?php selected((int)($editing['status'] ?? 301), $status); ?>><?php echo esc_html($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label>
                            <span><?php esc_html_e('Notes', 'wpfs'); ?></span>
                            <textarea name="notes" rows="3"><?php echo esc_textarea($editing['notes'] ?? ''); ?></textarea>
                        </label>
                        <div class="wpfs-action-row">
                            <button class="button button-primary" type="submit"><?php esc_html_e('Save redirect', 'wpfs'); ?></button>
                            <?php if ($editing) : ?>
                                <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=wpfs-redirects')); ?>"><?php esc_html_e('Cancel edit', 'wpfs'); ?></a>
                            <?php endif; ?>
                        </div>
                    </form>
                </block>
                <block class="wps wpfs-card">
                    <h2><?php esc_html_e('CSV import/export', 'wpfs'); ?></h2>
                    <form method="post" enctype="multipart/form-data" class="wpfs-redirect-import">
                        <?php wp_nonce_field('wpfs_redirect_action'); ?>
                        <input type="hidden" name="wpfs_redirect_action" value="import">
                        <div class="wpfs-csv-uploader" data-wpfs-file-upload>
                            <label class="wpfs-upload-drop">
                                <input class="wpfs-file-input" type="file" name="redirect_csv" accept=".csv,text/csv" required data-wpfs-file-input>
                                <span class="dashicons dashicons-upload" aria-hidden="true"></span>
                                <span class="wpfs-upload-copy">
                                    <strong><?php esc_html_e('CSV file', 'wpfs'); ?></strong>
                                    <small><?php esc_html_e('Drop or choose a .csv redirect file.', 'wpfs'); ?></small>
                                </span>
                                <span class="wpfs-upload-button"><?php esc_html_e('Choose file', 'wpfs'); ?></span>
                            </label>
                            <span class="wpfs-file-name" data-wpfs-file-name><?php esc_html_e('No file selected', 'wpfs'); ?></span>
                        </div>
                        <div class="wpfs-action-row">
                            <button class="button button-primary" type="submit"><?php esc_html_e('Import CSV', 'wpfs'); ?></button>
                            <a class="button" href="<?php echo esc_url($export_url); ?>"><?php esc_html_e('Export CSV', 'wpfs'); ?></a>
                        </div>
                    </form>
                </block>
            </div>
            <block class="wps wpfs-card">
                <h2><?php esc_html_e('Redirect rules', 'wpfs'); ?></h2>
                <table class="wp-list-table widefat fixed striped table-view-list wpfs-redirect-table">
                    <thead>
                    <tr>
                        <th scope="col"><?php esc_html_e('Old URL', 'wpfs'); ?></th>
                        <th scope="col"><?php esc_html_e('Target', 'wpfs'); ?></th>
                        <th scope="col"><?php esc_html_e('Status', 'wpfs'); ?></th>
                        <th scope="col"><?php esc_html_e('Hits', 'wpfs'); ?></th>
                        <th scope="col"><?php esc_html_e('Last hit', 'wpfs'); ?></th>
                        <th scope="col"><?php esc_html_e('Source', 'wpfs'); ?></th>
                        <th scope="col"><?php esc_html_e('Actions', 'wpfs'); ?></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($rows)) : ?>
                        <tr>
                            <td colspan="7"><?php esc_html_e('No redirects configured yet.', 'wpfs'); ?></td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ($rows as $row) : ?>
                            <tr>
                                <td><code><?php echo esc_html($row['source_path']); ?></code></td>
                                <td><?php echo (int)$row['status'] === 410 ? esc_html__('None', 'wpfs') : esc_html($row['target_url']); ?></td>
                                <td><span class="wpfs-audit-badge <?php echo (int)$row['status'] === 410 ? 'is-bad' : 'is-ok'; ?>"><?php echo esc_html($row['status']); ?></span></td>
                                <td><?php echo esc_html(number_format_i18n((int)$row['hits'])); ?></td>
                                <td><?php echo esc_html($row['last_hit'] ? mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $row['last_hit']) : __('Never', 'wpfs')); ?></td>
                                <td><?php echo (int)$row['auto_created'] ? esc_html__('Auto', 'wpfs') : esc_html__('Manual', 'wpfs'); ?></td>
                                <td>
                                    <div class="wpfs-audit-actions">
                                        <a class="wpfs-audit-action wpfs-audit-action--edit" href="<?php echo esc_url(add_query_arg(array('page' => 'wpfs-redirects', 'redirect_id' => (int)$row['id']), admin_url('admin.php'))); ?>">
                                            <span class="dashicons dashicons-edit"></span><?php esc_html_e('Edit', 'wpfs'); ?>
                                        </a>
                                        <form method="post" class="wpfs-inline-form">
                                            <?php wp_nonce_field('wpfs_redirect_action'); ?>
                                            <input type="hidden" name="wpfs_redirect_action" value="delete">
                                            <input type="hidden" name="redirect_id" value="<?php echo esc_attr((string)$row['id']); ?>">
                                            <button class="wpfs-audit-action" type="submit" onclick="return confirm('<?php echo esc_js(__('Delete this redirect?', 'wpfs')); ?>');">
                                                <span class="dashicons dashicons-trash"></span><?php esc_html_e('Delete', 'wpfs'); ?>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </block>
        </section>
        <?php
    }

    public function maybe_redirect(): void
    {
        if (is_admin() || wp_doing_ajax() || wp_doing_cron()) {
            return;
        }

        $request_uri = (string)($_SERVER['REQUEST_URI'] ?? '');
        $path = $this->normalize_source($request_uri);

        if ($path === '/') {
            return;
        }

        $redirect = $this->find_redirect($path);

        if (!$redirect && false !== strpos($path, '?')) {
            $redirect = $this->find_redirect(strtok($path, '?'));
        }

        if (!$redirect) {
            return;
        }

        $this->record_hit((int)$redirect['id']);

        $status = (int)$redirect['status'];

        if ($status === 410) {
            status_header(410);
            nocache_headers();
            wp_die(esc_html__('This URL is gone.', 'wpfs'), esc_html__('Gone', 'wpfs'), array('response' => 410));
        }

        if (!in_array($status, array(301, 302), true) || empty($redirect['target_url'])) {
            return;
        }

        $target = $this->normalize_target($redirect['target_url']);

        if ($this->normalize_source($target) === $path) {
            return;
        }

        wp_redirect($target, $status, 'Flexy SEO');
        exit;
    }

    public function capture_old_post_url($post_id, $data): void
    {
        $post = get_post($post_id);

        if (!$post || $post->post_status === 'auto-draft' || wp_is_post_revision($post_id)) {
            return;
        }

        $url = get_permalink($post);

        if ($url) {
            $this->old_post_urls[(int)$post_id] = $url;
        }
    }

    public function maybe_create_post_redirect($post_id, $post_after, $post_before): void
    {
        if (wp_is_post_revision($post_id) || !$post_before || !$post_after) {
            return;
        }

        if ($post_before->post_name === $post_after->post_name || $post_after->post_status !== 'publish') {
            return;
        }

        $old_url = $this->old_post_urls[(int)$post_id] ?? '';
        $new_url = get_permalink($post_after);

        $this->create_auto_redirect($old_url, $new_url);
    }

    public function capture_old_term_url($term_id, $taxonomy): void
    {
        $term = get_term((int)$term_id, (string)$taxonomy);

        if (!$term || is_wp_error($term)) {
            return;
        }

        $url = get_term_link($term, (string)$taxonomy);

        if (!is_wp_error($url)) {
            $this->old_term_urls[(string)$taxonomy . ':' . (int)$term_id] = $url;
        }
    }

    public function maybe_create_term_redirect($term_id, $tt_id, $taxonomy): void
    {
        $key = (string)$taxonomy . ':' . (int)$term_id;
        $old_url = $this->old_term_urls[$key] ?? '';
        $term = get_term((int)$term_id, (string)$taxonomy);

        if (!$old_url || !$term || is_wp_error($term)) {
            return;
        }

        $new_url = get_term_link($term, (string)$taxonomy);

        if (is_wp_error($new_url)) {
            return;
        }

        $this->create_auto_redirect($old_url, $new_url);
    }

    private function create_auto_redirect(string $old_url, string $new_url): void
    {
        $source = $this->normalize_source($old_url);
        $target = $this->normalize_target($new_url);

        if (!$source || $source === '/' || $this->normalize_source($target) === $source) {
            return;
        }

        $this->upsert_redirect(array(
            'source_path'  => $source,
            'target_url'   => $target,
            'status'       => 301,
            'auto_created' => 1,
            'notes'        => __('Created automatically after slug change.', 'wpfs'),
        ));
    }

    private function save_redirect_from_request(): void
    {
        $id = absint($_POST['redirect_id'] ?? 0);
        $source = $this->normalize_source((string)wp_unslash($_POST['source_path'] ?? ''));
        $target = $this->normalize_target((string)wp_unslash($_POST['target_url'] ?? ''));
        $status = $this->sanitize_status((int)($_POST['status'] ?? 301));
        $notes = sanitize_textarea_field(wp_unslash($_POST['notes'] ?? ''));

        if (!$source || $source === '/') {
            $this->redirect_with_notice(__('Old URL is required.', 'wpfs'), 'error');
        }

        if ($status !== 410 && !$target) {
            $this->redirect_with_notice(__('Target URL is required for 301 and 302 redirects.', 'wpfs'), 'error');
        }

        $saved = $this->upsert_redirect(array(
            'id'           => $id,
            'source_path'  => $source,
            'target_url'   => $status === 410 ? '' : $target,
            'status'       => $status,
            'auto_created' => 0,
            'notes'        => $notes,
        ));

        $this->redirect_with_notice(
            $saved ? __('Redirect saved.', 'wpfs') : __('Unable to save redirect.', 'wpfs'),
            $saved ? 'success' : 'error'
        );
    }

    private function delete_redirect_from_request(): void
    {
        global $wpdb;

        $deleted = false;
        $id = absint($_POST['redirect_id'] ?? 0);

        if ($id) {
            $deleted = (bool)$wpdb->delete($this->table_name(), array('id' => $id), array('%d'));
        }

        $this->redirect_with_notice(
            $deleted ? __('Redirect deleted.', 'wpfs') : __('Unable to delete redirect.', 'wpfs'),
            $deleted ? 'success' : 'error'
        );
    }

    private function import_csv_from_request(): void
    {
        if (empty($_FILES['redirect_csv']['tmp_name'])) {
            $this->redirect_with_notice(__('CSV file is required.', 'wpfs'), 'error');
        }

        $handle = fopen((string)$_FILES['redirect_csv']['tmp_name'], 'rb');

        if (!$handle) {
            $this->redirect_with_notice(__('Unable to read CSV file.', 'wpfs'), 'error');
        }

        $imported = 0;
        $headers = array();

        while (($row = fgetcsv($handle)) !== false) {
            if (empty(array_filter($row, 'strlen'))) {
                continue;
            }

            if (empty($headers)) {
                $headers = array_map('sanitize_key', $row);

                if (in_array('source_path', $headers, true)) {
                    continue;
                }

                $headers = array('source_path', 'target_url', 'status', 'auto_created', 'hits', 'last_hit', 'notes');
            }

            $values = array_pad(array_slice($row, 0, count($headers)), count($headers), '');
            $data = array_combine($headers, $values);

            if (!$data) {
                continue;
            }

            $source = $this->normalize_source((string)($data['source_path'] ?? ''));
            $status = $this->sanitize_status((int)($data['status'] ?? 301));
            $target = $this->normalize_target((string)($data['target_url'] ?? ''));

            if (!$source || $source === '/' || ($status !== 410 && !$target)) {
                continue;
            }

            if ($this->upsert_redirect(array(
                'source_path'  => $source,
                'target_url'   => $status === 410 ? '' : $target,
                'status'       => $status,
                'auto_created' => absint($data['auto_created'] ?? 0) ? 1 : 0,
                'hits'         => absint($data['hits'] ?? 0),
                'last_hit'     => $this->sanitize_datetime((string)($data['last_hit'] ?? '')),
                'notes'        => sanitize_textarea_field((string)($data['notes'] ?? '')),
            ))) {
                $imported++;
            }
        }

        fclose($handle);

        $this->redirect_with_notice(sprintf(_n('%d redirect imported.', '%d redirects imported.', $imported, 'wpfs'), $imported), 'success');
    }

    private function export_csv(): void
    {
        $rows = $this->get_redirects(0);

        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=flexy-seo-redirects.csv');

        $output = fopen('php://output', 'wb');
        fputcsv($output, array('source_path', 'target_url', 'status', 'auto_created', 'hits', 'last_hit', 'notes'));

        foreach ($rows as $row) {
            fputcsv($output, array(
                $row['source_path'],
                $row['target_url'],
                $row['status'],
                $row['auto_created'],
                $row['hits'],
                $row['last_hit'],
                $row['notes'],
            ));
        }

        fclose($output);
        exit;
    }

    private function upsert_redirect(array $data): bool
    {
        global $wpdb;

        $this->install_table();

        $now = current_time('mysql');
        $id = absint($data['id'] ?? 0);
        $source = $this->normalize_source((string)($data['source_path'] ?? ''));
        $status = $this->sanitize_status((int)($data['status'] ?? 301));

        $payload = array(
            'source_path'  => $source,
            'target_url'   => $status === 410 ? '' : $this->normalize_target((string)($data['target_url'] ?? '')),
            'status'       => $status,
            'auto_created' => absint($data['auto_created'] ?? 0) ? 1 : 0,
            'notes'        => sanitize_textarea_field((string)($data['notes'] ?? '')),
            'updated_at'   => $now,
        );

        $formats = array('%s', '%s', '%d', '%d', '%s', '%s');

        if (array_key_exists('hits', $data)) {
            $payload['hits'] = absint($data['hits']);
            $formats[] = '%d';
        }

        if (array_key_exists('last_hit', $data)) {
            $payload['last_hit'] = $data['last_hit'] ?: null;
            $formats[] = '%s';
        }

        if ($id) {
            return false !== $wpdb->update($this->table_name(), $payload, array('id' => $id), $formats, array('%d'));
        }

        $existing_id = (int)$wpdb->get_var($wpdb->prepare("SELECT id FROM {$this->table_name()} WHERE source_path = %s", $source));

        if ($existing_id) {
            return false !== $wpdb->update($this->table_name(), $payload, array('id' => $existing_id), $formats, array('%d'));
        }

        $payload['created_at'] = $now;
        $formats[] = '%s';

        return false !== $wpdb->insert($this->table_name(), $payload, $formats);
    }

    private function get_redirects(int $limit = 200): array
    {
        global $wpdb;

        $sql = "SELECT * FROM {$this->table_name()} ORDER BY updated_at DESC, id DESC";

        if ($limit > 0) {
            $sql .= $wpdb->prepare(' LIMIT %d', $limit);
        }

        return $wpdb->get_results($sql, ARRAY_A) ?: array();
    }

    private function get_redirect(int $id): ?array
    {
        global $wpdb;

        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table_name()} WHERE id = %d", $id), ARRAY_A);

        return $row ?: null;
    }

    private function find_redirect(string $source): ?array
    {
        global $wpdb;

        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table_name()} WHERE source_path = %s", $source), ARRAY_A);

        return $row ?: null;
    }

    private function record_hit(int $id): void
    {
        global $wpdb;

        $wpdb->query($wpdb->prepare(
            "UPDATE {$this->table_name()} SET hits = hits + 1, last_hit = %s WHERE id = %d",
            current_time('mysql'),
            $id
        ));
    }

    private function get_stats(array $rows): array
    {
        $stats = array('total' => count($rows), 'hits' => 0, 'auto' => 0, 'gone' => 0);

        foreach ($rows as $row) {
            $stats['hits'] += (int)$row['hits'];
            $stats['auto'] += (int)$row['auto_created'] ? 1 : 0;
            $stats['gone'] += (int)$row['status'] === 410 ? 1 : 0;
        }

        return $stats;
    }

    private function status_options(): array
    {
        return array(
            301 => __('301 Permanent redirect', 'wpfs'),
            302 => __('302 Temporary redirect', 'wpfs'),
            410 => __('410 Gone', 'wpfs'),
        );
    }

    private function sanitize_status(int $status): int
    {
        return in_array($status, array(301, 302, 410), true) ? $status : 301;
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

    private function sanitize_datetime(string $value): ?string
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        $timestamp = strtotime($value);

        return $timestamp ? gmdate('Y-m-d H:i:s', $timestamp) : null;
    }

    private function table_name(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'flexy_seo_redirects';
    }

    private function is_redirects_screen(): bool
    {
        return is_admin() && isset($_GET['page']) && $_GET['page'] === 'wpfs-redirects';
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
            'page'       => 'wpfs-redirects',
            'wps-notice' => rawurlencode($message),
            'wps-status' => $status,
        ), admin_url('admin.php')));
        exit;
    }
}

return __NAMESPACE__;
