<?php
/**
 * @author    sh1zen
 * @copyright Copyright (C) 2025.
 * @license   http://www.gnu.org/licenses/gpl.html GNU/GPL
 */

use WPS\core\StringHelper;

class WPFS_Breadcrumb
{
    public static string $before = '';
    public static string $after = '';

    private static WPFS_Breadcrumb $_Instance;

    private string $show_on_front;
    private $page_for_posts;

    /** @var WP_Term|WP_Post_Type|WP_Post|WP_User|null */
    private $queried_object;

    private array $options;
    private string $element = 'li';
    private string $wrapper = 'ol';
    private array $crumbs = [];
    private array $links = [];
    private string $output = '';
    private string $home_url;

    /** @var WP_Query Cached reference */
    private WP_Query $wp_query;

    /** @var array Memoized term parents: term_id => array of parents */
    private array $term_parents_cache = [];

    /** @var array Memoized options */
    private array $option_cache = [];

    private function __construct()
    {
    }

    public static function breadcrumb(string $before = '', string $after = '', bool $display = true, array $args = []): string
    {
        if (!wps('wpfs')->settings->get('breadcrumbs.active', false)) {
            return '';
        }

        self::$_Instance ??= new self();
        self::$_Instance->generate($args);

        self::$before = $before;
        self::$after = $after;

        $output = $before . self::$_Instance->output . $after;

        if ($display) {
            echo $output;
        }

        return $output;
    }

    public static function export(): array
    {
        self::$_Instance ??= new self();
        self::$_Instance->generate();

        return array_filter(self::$_Instance->crumbs);
    }

    private function generate(array $args = []): void
    {
        if ($this->output !== '') {
            return;
        }

        $this->wp_query = $GLOBALS['wp_query'];

        if (!$this->wp_query) {
            _doing_it_wrong(__FUNCTION__, __('Conditional query tags do not work before the query is run. Before then, they always return false.', 'wpfs'), '1.0.0');
            return;
        }

        $this->queried_object = $this->wp_query->get_queried_object();
        $this->options = array_merge($this->get_option_raw('.'), $args);
        $this->option_cache = [];
        $this->term_parents_cache = [];
        $this->home_url = wps_core()->home_url;
        $this->show_on_front = get_option('show_on_front');
        $this->page_for_posts = get_option('page_for_posts');

        $this->crumbs = [];
        $this->links = [];
        $this->output = '';

        $this->set_crumbs_types();
        $this->transform_crumbs();
        $this->prepare_links();
        $this->wrap_breadcrumb();
    }

    /**
     * Raw option fetch without caching - used for initial load.
     */
    private function get_option_raw(string $option, $default = false)
    {
        $res = $this->options[$option] ?? wps('wpfs')->settings->get("breadcrumbs." . $option, '');
        return empty($res) ? $default : $res;
    }

    /**
     * Cached option fetch for repeated lookups within a single generate() call.
     */
    private function get_option(string $option, $default = false)
    {
        if (isset($this->option_cache[$option])) {
            return $this->option_cache[$option];
        }

        $res = $this->get_option_raw($option, $default);
        $this->option_cache[$option] = $res;
        return $res;
    }

    private function set_crumbs_types(): void
    {
        $q = $this->queried_object;

        if (!is_object($q)) {
            $this->add_home_crumb();
            return;
        }

        $wq = $this->wp_query;

        if ($wq->is_singular() && !$this->get_option("post_type.{$q->post_type}.active")) {
            return;
        }

        if ($this->get_option('flexed')) {
            $format = $this->get_breadcrumb_format();
            if ($format !== '') {
                foreach (explode('>>', $format) as $crumb_structure) {
                    $this->format_to_crumbs($crumb_structure);
                }
            }
            return;
        }

        $this->add_home_crumb();

        $is_front = ($this->show_on_front === 'page' && $wq->is_front_page());
        $is_posts_home = ($this->show_on_front === 'posts' && $wq->is_home());

        if ($is_front || $is_posts_home) {
            return;
        }

        if ($this->show_on_front === 'page' && $wq->is_home()) {
            $this->add_blog_crumb();
            return;
        }

        if ($wq->is_singular()) {
            $this->handle_singular_crumbs($q);
            return;
        }

        $this->handle_archive_crumbs($wq, $q);
    }

    private function handle_singular_crumbs($q): void
    {
        $post_type = $q->post_type;

        if ($this->get_option("post_type_archive.{$post_type}.show", false)) {
            $pto = get_post_type_object($post_type);
            if ($pto && $pto->has_archive) {
                $this->add_crumb($post_type, 'ptarchive');
            }
        }

        if ($q->post_parent) {
            $this->add_post_ancestor_crumbs($q);
        }
        else {
            $this->maybe_add_taxonomy_crumbs_for_post();
        }

        if ($q->ID) {
            $this->add_crumb($q->ID, 'postId');
        }
    }

    private function handle_archive_crumbs(WP_Query $wq, $q): void
    {
        if ($wq->is_post_type_archive()) {
            $this->add_crumb($q->name, 'ptarchive');
            return;
        }

        if ($wq->is_tax() || $wq->is_tag() || $wq->is_category()) {
            $this->add_crumbs_for_taxonomy();
            return;
        }

        if ($wq->is_date()) {
            $this->handle_date_crumbs($wq);
            return;
        }

        if ($wq->is_author()) {
            $prefix = $this->get_option('author.prefix', '');
            $this->add_crumb(
                $this->make_crumb_value($prefix . ' ' . $q->display_name, null),
                'crumb', true
            );
            return;
        }

        if ($wq->is_search()) {
            $prefix = $this->get_option('search.prefix', '');
            $this->add_crumb(
                $this->make_crumb_value($prefix . ' "' . esc_html(get_search_query()) . '"', null),
                'crumb', true
            );
            return;
        }

        if ($wq->is_404()) {
            $this->handle_404_crumbs($wq);
        }
    }

    private function handle_date_crumbs(WP_Query $wq): void
    {
        if ($wq->is_day()) {
            $this->add_year_crumb();
            $this->add_month_crumb();
            $this->add_day_crumb(false);
        }
        elseif ($wq->is_month()) {
            $this->add_month_crumb();
        }
        elseif ($wq->is_year()) {
            $this->add_year_crumb();
        }
    }

    private function handle_404_crumbs(WP_Query $wq): void
    {
        $year = $wq->get('year');
        $monthnum = $wq->get('monthnum');
        $day = get_query_var('day');

        if ($year !== 0 || $monthnum !== 0 || $day !== 0) {
            if ($this->show_on_front === 'page' && !$wq->is_home()) {
                if ($this->page_for_posts && !$this->get_option('remove_blog')) {
                    $this->add_blog_crumb();
                }
            }

            if ($wq->get('day') !== 0) {
                $this->add_linked_month_year_crumb();
                $date = sprintf('%04d-%02d-%02d 00:00:00', $year, $monthnum, $wq->get('day'));
                $this->add_date_crumb($date);
            }
            elseif ($monthnum !== 0) {
                $this->add_month_crumb();
            }
            elseif ($year !== 0) {
                $this->add_year_crumb();
            }
        }
        else {
            $this->add_crumb(
                $this->make_crumb_value($this->get_option('404.prefix', ''), null),
                'crumb', true
            );
        }
    }

    private function add_home_crumb(): void
    {
        $this->add_crumb(
            $this->make_crumb_value($this->get_option('home.prefix', 'Home'), $this->home_url),
            'crumb', true
        );
    }

    private function add_crumb($value = null, string $type = 'crumb', bool $allow_html = false): void
    {
        $this->crumbs[] = [
            'type'       => $type,
            'value'      => $value,
            'allow_html' => $allow_html,
        ];
    }

    /**
     * Renamed from generate_crumb_value for brevity; same output.
     */
    private function make_crumb_value($text, $url): array
    {
        return ['text' => $text, 'url' => $url];
    }

    // Alias to keep compatibility with format_to_crumbs / perform_replace which call it
    private function generate_crumb_value($text, $url): array
    {
        return ['text' => $text, 'url' => $url];
    }

    private function get_breadcrumb_format(): string
    {
        if (!$this->queried_object) {
            return '';
        }

        $wq = $this->wp_query;
        $q = $this->queried_object;

        if ($wq->is_home()) {
            return $this->get_option("post_type.{$q->post_type}.format", "%%home%%");
        }
        if ($wq->is_singular()) {
            return $this->get_option("post_type.{$q->post_type}.format", "%%home%% >> %%category%% >> %%title%%");
        }
        if ($wq->is_author()) {
            return $this->get_option("author.format", "%%home%% >> %%queried_object%%");
        }
        if ($wq->is_search()) {
            return $this->get_option("search.format", "%%home%% >> %%queried_object%%");
        }
        if ($wq->is_404()) {
            return $this->get_option("404.format", "%%home%% >> %%queried_object%%");
        }
        if ($wq->is_archive()) {
            if ($wq->is_post_type_archive()) {
                return $this->get_option("post_type_archive.{$q->post_type}.format", "%%home%% >> %%queried_object%%");
            }
            if (is_object($q)) {
                return $this->get_option("tax.{$q->taxonomy}.format", "%%home%% >> %%queried_object%%");
            }
        }

        return '';
    }

    private function format_to_crumbs(string $format): void
    {
        $format = trim($format);
        if ($format === '') {
            return;
        }

        preg_match_all("/%%[^%]*%%/U", $format, $rules);

        $format_crumb = [['text' => $format, 'url' => $format]];

        foreach ($rules[0] as $rule) {
            $format_crumb = $this->perform_replace(str_replace("%%", '', $rule), $format_crumb);
        }

        $home = $this->home_url;

        foreach ($format_crumb as $obj) {
            $url = preg_replace("#\[[^]]+]#U", '', str_replace(['(', ')'], '', $obj['url']));

            if ($url !== '' && strpos($url, $home) === false) {
                $url = $home . $url;
            }

            $url = preg_replace('#([^:])(/{2,})#U', '$1/', $url);

            $text = preg_replace("#\([^)]+\)#U", '', str_replace(['[', ']'], '', $obj['text']));

            $this->add_crumb($this->make_crumb_value($text, $url));
        }
    }

    private function perform_replace(string $rule, array $format_crumb): array
    {
        $replaces = $this->generate_replacements($rule);
        $items = [];
        $placeholder = "%%{$rule}%%";

        foreach ($replaces as $replace) {
            $r_text = $replace['text'] ?? '';
            $r_url = $replace['url'] ?? '';
            foreach ($format_crumb as $subject) {
                $items[] = [
                    'text' => str_replace($placeholder, $r_text, $subject['text']),
                    'url'  => str_replace($placeholder, $r_url, $subject['url']),
                ];
            }
        }

        return $items;
    }

    private function generate_replacements(string $rule): array
    {
        global $wp_rewrite;

        $wq = $this->wp_query;
        $q = $this->queried_object;
        $replaces = [];

        switch ($rule) {
            case 'home':
                $replaces[] = ['text' => $this->get_option('home.prefix', 'Home'), 'url' => $this->home_url];
                break;

            case 'sitename':
                $replaces[] = ['text' => get_bloginfo('name'), 'url' => $this->home_url];
                break;

            case 'title':
                $replaces[] = ['text' => wpfs_the_title(), 'url' => get_permalink($q->ID)];
                break;

            case 'language':
                $locale = get_locale();
                $replaces[] = ['text' => $locale, 'url' => $locale];
                break;

            case 'post_parent':
                if (isset($q->post_parent) && $q->post_parent) {
                    $replaces[] = [
                        'text' => wps_get_post($q->post_parent)->post_title,
                        'url'  => get_permalink($q->post_parent),
                    ];
                }
                break;

            case 'post_type':
                if (isset($q->post_type)) {
                    $pto = get_post_type_object($q->post_type);
                    $replaces[] = [
                        'text' => $pto->name,
                        'url'  => get_post_type_archive_link($q->post_type),
                    ];
                }
                break;

            case 'queried_object':
                $replaces = $this->generate_queried_object_replacement($wq, $q);
                break;

            case 'category':
            case 'taxonomy':
            case 'category-compact':
            case 'taxonomy-compact':
            case 'category-full':
            case 'taxonomy-full':
                $replaces = $this->generate_taxonomy_replacements($rule, $wp_rewrite);
                break;

            default:
                $replaces = $this->generate_fallback_replacements($rule);
                break;
        }

        return apply_filters('wpfs_breadcrumb_replacements', $replaces, $rule);
    }

    private function generate_queried_object_replacement(WP_Query $wq, $q): array
    {
        if ($wq->is_singular()) {
            return [['text' => wps_get_post($q)->post_title, 'url' => get_permalink($q->ID)]];
        }
        if ($wq->is_author() || $wq->is_404()) {
            return [['text' => $q->display_name, 'url' => get_author_posts_url($q->ID)]];
        }
        if ($wq->is_search()) {
            return [['text' => esc_html(get_search_query()), 'url' => '']];
        }
        if ($wq->is_archive()) {
            if ($wq->is_post_type_archive()) {
                return [['text' => $q->post_type, 'url' => get_post_type_archive_link($q->post_type)]];
            }
            return [['text' => $q->name, 'url' => get_term_link($q->term_id, $q->taxonomy)]];
        }
        return [];
    }

    private function generate_taxonomy_replacements(string $rule, $wp_rewrite): array
    {
        $q = $this->queried_object;
        $replaces = [];

        if ($q instanceof \WP_Term) {
            $deepest_term = $this->find_deepest_term([$q]);
        }
        else {
            $main_tax = $this->get_option("post_type.{$q->post_type}.maintax", 'category');
            $terms = wp_get_object_terms($q->ID, $main_tax);

            if (!is_array($terms) || empty($terms)) {
                return [];
            }
            $deepest_term = $this->find_deepest_term($terms);
        }

        $parent_terms = $this->get_term_parents($deepest_term);
        $is_compact = str_contains($rule, "compact");
        $is_full = str_contains($rule, "full");

        $compact_url = '';
        $url = '';
        $depth = 1;

        if ($is_full) {
            $url = str_replace("%{$deepest_term->taxonomy}%", '', $wp_rewrite->get_extra_permastruct($deepest_term->taxonomy));
        }

        foreach ($parent_terms as $parent_term) {
            $slug = $parent_term->slug;
            $url .= "{$slug}/";

            $replaces[] = [
                'text' => $parent_term->name,
                'url'  => $is_compact ? "{$compact_url}{$slug}/" : $url,
            ];

            if (++$depth > 3) {
                $compact_url .= "{$slug}/";
            }
        }

        $replaces[] = [
            'text' => $deepest_term->name,
            'url'  => ($is_compact ? $compact_url : $url) . "{$deepest_term->slug}/",
        ];

        return $replaces;
    }

    private function generate_fallback_replacements(string $rule): array
    {
        if (str_starts_with($rule, "meta")) {
            $meta = str_replace("meta_", "", $rule);
            $_meta = get_metadata_raw(
                wpfseo('helpers')->currentPage->get_query_type(),
                $this->queried_object->ID,
                $meta,
                true
            );
            return [['text' => $_meta, 'url' => $_meta]];
        }

        $var = get_query_var($rule, false);
        if ($var !== false) {
            return [['text' => $var, 'url' => $var]];
        }

        return [];
    }

    private function find_deepest_term(array $terms): WP_Term
    {
        $terms_by_id = [];
        foreach ($terms as $term) {
            $terms_by_id[$term->term_id] = $term;
        }

        foreach ($terms as $term) {
            unset($terms_by_id[$term->parent]);
        }

        $max = 0;
        $deepest = reset($terms_by_id);

        foreach ($terms_by_id as $term) {
            $count = count($this->get_term_parents($term));
            if ($count >= $max) {
                $max = $count;
                $deepest = $term;
            }
        }

        return $deepest;
    }

    /**
     * Get a term's parents with memoization.
     */
    private function get_term_parents(object $term): array
    {
        $tid = $term->term_id;

        if (isset($this->term_parents_cache[$tid])) {
            return $this->term_parents_cache[$tid];
        }

        $tax = $term->taxonomy;
        $parents = [];
        $current = $term;

        while ($current->parent) {
            $current = wps_get_term($current->parent, $tax);
            $parents[] = $current;
        }

        $parents = array_reverse($parents);
        $this->term_parents_cache[$tid] = $parents;

        return $parents;
    }

    private function add_blog_crumb(): void
    {
        $this->add_crumb($this->page_for_posts, 'postId');
    }

    private function add_post_ancestor_crumbs(WP_Post $post): void
    {
        $ancestors = $this->get_post_ancestors($post);
        foreach ($ancestors as $ancestor) {
            $this->add_crumb($ancestor, 'postId');
        }
    }

    private function get_post_ancestors(WP_Post $post): array
    {
        if (isset($post->ancestors)) {
            $ancestors = is_array($post->ancestors) ? array_values($post->ancestors) : [$post->ancestors];
        }
        elseif (isset($post->post_parent)) {
            $ancestors = [$post->post_parent];
        }
        else {
            return [];
        }

        return array_reverse($ancestors);
    }

    private function maybe_add_taxonomy_crumbs_for_post(): void
    {
        $q = $this->queried_object;
        if (!$q->ID) {
            return;
        }

        $main_tax = $this->get_option("post_type.{$q->post_type}.maintax", 'category');
        $terms = wp_get_object_terms($q->ID, $main_tax);

        if (!is_array($terms) || empty($terms)) {
            return;
        }

        $deepest = $this->find_deepest_term($terms);

        if (is_taxonomy_hierarchical($main_tax) && $deepest->parent) {
            foreach ($this->get_term_parents($deepest) as $parent_term) {
                $this->add_crumb($parent_term, 'term');
            }
        }

        $this->add_crumb($deepest, 'term');
    }

    private function add_crumbs_for_taxonomy(): void
    {
        $q = $this->queried_object;

        $this->maybe_add_term_parent_crumbs($q);
        $this->add_crumb($q, 'term');
        $this->maybe_add_term_children_crumbs($q);
    }

    private function maybe_add_term_parent_crumbs(WP_Term $term): void
    {
        if (is_taxonomy_hierarchical($term->taxonomy) && $term->parent != 0) {
            foreach ($this->get_term_parents($term) as $parent_term) {
                $this->add_crumb($parent_term, 'term');
            }
        }
    }

    private function maybe_add_term_children_crumbs(WP_Term $term): void
    {
        if (!$this->get_option('dropdown_last') || !is_taxonomy_hierarchical($term->taxonomy)) {
            return;
        }

        $children = $this->get_term_children($term);
        if (!empty($children)) {
            $this->add_crumb($children, 'term_list');
        }
    }

    private function get_term_children(WP_Term $term): array
    {
        $hierarchy = _get_term_hierarchy($term->taxonomy);
        $tax = $term->taxonomy;
        $tid = $term->term_id;

        if (!isset($hierarchy[$tid])) {
            return [];
        }

        $children = [];
        foreach ((array)$hierarchy[$tid] as $child) {
            $children[] = wps_get_term($child, $tax);
        }

        return $children;
    }

    private function add_year_crumb(bool $link = true): void
    {
        $year = $this->wp_query->get('year');
        $this->add_crumb(
            $this->make_crumb_value((string)$year, $link ? get_year_link($year) : null),
            'crumb', true
        );
    }

    private function add_month_crumb(bool $link = true): void
    {
        global $wp_locale;

        $year = $this->wp_query->get('year');
        $month = $this->wp_query->get('monthnum');

        $this->add_crumb(
            $this->make_crumb_value($wp_locale->get_month($month), $link ? get_month_link($year, $month) : null),
            'crumb', true
        );
    }

    private function add_day_crumb(bool $link = true): void
    {
        $wq = $this->wp_query;
        $year = $wq->get('year');
        $month = $wq->get('monthnum');
        $day = $wq->get('day');

        $this->add_crumb(
            $this->make_crumb_value((string)$day, $link ? get_day_link($year, $month, $day) : null),
            'crumb', true
        );
    }

    private function add_linked_month_year_crumb(): void
    {
        global $wp_locale;

        $wq = $this->wp_query;
        $year = $wq->get('year');
        $month = $wq->get('monthnum');

        $this->add_crumb(
            $this->make_crumb_value(
                $wp_locale->get_month($month) . ' ' . $year,
                get_month_link($year, $month)
            )
        );
    }

    private function add_date_crumb(string $date = ''): void
    {
        if ($date === '') {
            $date = get_the_date();
        }
        else {
            $date = mysql2date(get_option('date_format'), $date, true);
            $date = apply_filters('get_the_date', $date, '');
        }

        $this->add_crumb(
            $this->make_crumb_value($this->get_option('archive.prefix', '') . ' ' . esc_html($date), null),
            'crumb', true
        );
    }

    private function transform_crumbs(): void
    {
        foreach ($this->crumbs as $i => &$crumb) {
            switch ($crumb['type']) {
                case 'postId':
                    $crumb['value'] = $this->make_crumb_value(
                        strip_tags(get_the_title($crumb['value'])),
                        get_permalink($crumb['value'])
                    );
                    $crumb['type'] = 'crumb';
                    break;

                case 'term':
                case 'term_list':
                    $crumb['value'] = $this->get_link_info_for_terms($crumb['value']);
                    $crumb['type'] = 'crumb';
                    break;

                case 'ptarchive':
                    $crumb['value'] = $this->get_link_info_for_ptarchive($crumb['value']);
                    $crumb['type'] = 'crumb';
                    break;
            }
        }
        unset($crumb);
    }

    private function get_link_info_for_terms($terms): array
    {
        if (empty($terms)) {
            return $this->make_crumb_value('', '');
        }

        if (is_array($terms)) {
            $link_info = ['list' => true];
            foreach ($terms as $term) {
                $link_info[] = $this->make_crumb_value($term->name, get_term_link($term));
            }
            return $link_info;
        }

        return $this->make_crumb_value($terms->name, get_term_link($terms));
    }

    private function get_link_info_for_ptarchive(string $post_type): array
    {
        $pto = get_post_type_object($post_type);
        $title = '';

        if (is_object($pto)) {
            $title = ($pto->label !== '') ? $pto->label
                : (($pto->labels->menu_name ?? '') !== '' ? $pto->labels->menu_name : $pto->name);
        }

        return [
            'url'  => get_post_type_archive_link($post_type),
            'text' => $title,
        ];
    }

    private function prepare_links(): void
    {
        if (empty($this->crumbs)) {
            return;
        }

        $last = count($this->crumbs) - 1;
        $this->links = [];

        foreach ($this->crumbs as $i => $crumb) {
            $link = $this->crumb_to_link($crumb['value'], $i + 1, $i === $last);
            if ($link !== '') {
                $this->links[] = $link;
            }
        }
    }

    private function crumb_to_link(array $link, int $position, bool $current = false): string
    {
        if (isset($link['list']) && $link['list']) {
            unset($link['list']);

            $el = $this->element;
            $parts = ["<{$el} data-role='links'>"];
            $parts[] = "<a role='button' data-action='toggle'><span>Cascade</span><span></span></a>";
            $parts[] = "<ul class='wpfs-breadcrumb-list'>";

            foreach ($link as $_link) {
                $parts[] = $this->crumb_to_link($_link, $position, false);
            }

            $parts[] = "</ul>";
            $parts[] = "</{$el}>";

            return implode('', $parts);
        }

        if (empty($link['text']) || !is_string($link['text'])) {
            return '';
        }

        $text = trim($link['text']);
        if ($position > 1) {
            $text = ucfirst($text);
        }

        if (empty($link['allow_html'])) {
            $text = esc_html($text);
        }

        $el = $this->element;
        $has_url = !empty($link['url']) && (!$current || !$this->get_option('last_page'));

        if ($has_url) {
            $url = StringHelper::strtolower(esc_url(preg_replace('/([^:])(\/+)/', '$1/', $link['url'])));
            $attr = "href='{$url}'";
        }
        else {
            $attr = "class='wpfs-breadcrumb-current'";
        }

        return "<{$el} class='wpfs-breadcrumb-item' itemprop='itemListElement' itemtype='https://schema.org/ListItem'>"
            . "<a itemprop='item' {$attr} itemtype='https://schema.org/WebPage'><span itemprop='name'>{$text}</span></a>"
            . "<meta itemprop='position' content='{$position}'>"
            . "</{$el}>";
    }

    private function wrap_breadcrumb(): void
    {
        $links = array_filter($this->links);

        if (empty($links)) {
            return;
        }

        $separator = trim($this->get_option('separator', '>'));
        $output = implode($separator, $links);

        if ($output !== '') {
            $this->output = "<{$this->wrapper} class='wpfs-breadcrumb' itemtype='https://schema.org/BreadcrumbList'>{$output}</{$this->wrapper}>";
        }
    }
}