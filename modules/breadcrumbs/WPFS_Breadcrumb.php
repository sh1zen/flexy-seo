<?php
/**
 * @author    sh1zen
 * @copyright Copyright (C) 2024.
 * @license   http://www.gnu.org/licenses/gpl.html GNU/GPL
 */

use WPS\core\StringHelper;

class WPFS_Breadcrumb
{
    /**
     * @var string    Last used 'before' string
     */
    public static string $before = '';

    /**
     * @var string    Last used 'after' string
     */
    public static string $after = '';

    private static WPFS_Breadcrumb $_Instance;

    /**
     * @var    string    Blog's show on front setting, 'page' or 'posts'
     */
    private string $show_on_front;

    /**
     * @var    mixed    Blog's page for posts setting, page id or false
     */
    private $page_for_posts;

    /**
     * @var  WP_Term|WP_Post_Type|WP_Post|WP_User|null    Current queried object
     */
    private $queried_object;

    /**
     * @var    array    Flex options array from get_all()
     */
    private array $options;

    /**
     * @var string    HTML wrapper element for a single breadcrumb element
     */
    private string $element;

    /**
     * @var string    HTML wrapper element for the WP SEO breadcrumbs output
     */
    private string $wrapper;

    /**
     * @var    array    Array of crumbs
     *
     * Each element of the crumbs array can either have one of these keys:
     *    "id"         for post types;
     *    "ptarchive"  for a post type archive;
     *    "term"       for a taxonomy term.
     * OR it consists of a predefined set of 'text', 'url' and 'allow_html'
     */
    private array $crumbs = array();

    /**
     * @var array    Array of individual (linked) html strings created from crumbs
     */
    private array $links = array();

    /**
     * @var    string    Breadcrumb html string
     */
    private string $output;

    /**
     * @var    string    Home
     */
    private string $home_url;

    /**
     * Init the breadcrumb class
     */
    private function __construct()
    {
    }

    /**
     * Get breadcrumb string using the singleton instance of this class
     *
     * @param string $before
     * @param string $after
     * @param bool $display Echo or return ?
     * @param array $args
     * @return string
     */
    public static function breadcrumb(string $before = '', string $after = '', bool $display = true, array $args = array()): string
    {
        if (!wps('wpfs')->settings->get('breadcrumbs.active', false)) {
            return '';
        }

        if (!isset(self::$_Instance)) {
            self::$_Instance = new self();
        }

        self::$_Instance->generate($args);

        // Remember the last used before/after for use in case the object goes __toString()
        self::$before = $before;
        self::$after = $after;

        $output = $before . self::$_Instance->output . $after;

        if ($display === true) {
            echo $output;
        }

        return $output;
    }

    /**
     * Create the breadcrumb
     * @param array $args
     */
    private function generate(array $args = array())
    {
        if (!empty($this->output)) {
            return;
        }

        if (!$this->get_wp_query()) {
            _doing_it_wrong(__FUNCTION__, __('Conditional query tags do not work before the query is run. Before then, they always return false.', 'wpfs'), '1.0.0');
        }

        $this->queried_object = $this->get_wp_query()->get_queried_object();

        $this->options = array_merge($this->get_option('.'), $args);

        $this->home_url = wps_core()->home_url;

        $this->show_on_front = get_option('show_on_front');
        $this->page_for_posts = get_option('page_for_posts');

        $this->reset();
        $this->set_crumbs_types();
        $this->transform_crumbs();
        $this->prepare_links();
        $this->wrap_breadcrumb();
    }

    private function get_wp_query(): WP_Query
    {
        return $GLOBALS['wp_query'];
    }

    private function get_option($option, $default = false)
    {
        $res = $this->options[$option] ?? wps('wpfs')->settings->get("breadcrumbs." . $option, '');

        if (empty($res)) {
            $res = $default;
        }

        return $res;
    }

    private function reset()
    {
        $this->wrapper = 'ol';
        $this->element = 'li';
        $this->crumbs = [];
        $this->links = [];
        $this->output = '';
    }

    /**
     * Determine the crumbs which should form the breadcrumb.
     */
    private function set_crumbs_types()
    {
        $queried_object = $this->queried_object;

        if (!is_object($queried_object)) {
            $this->add_home_crumb();
            return;
        }

        $wp_query = $this->get_wp_query();

        if ($wp_query->is_singular() and !$this->get_option("post_type.{$queried_object->post_type}.active")) {
            return;
        }

        if ($this->get_option('flexed')) {

            $crumbs_structure = explode('>>', $this->get_breadcrumb_format());

            foreach ($crumbs_structure as $crumb_structure) {
                $this->format_to_crumbs($crumb_structure);
            }

            return;
        }

        $this->add_home_crumb();

        if (($this->show_on_front === 'page' and $wp_query->is_front_page()) or ($this->show_on_front === 'posts' and $wp_query->is_home())) {
            return;
        }

        if ($this->show_on_front == 'page' and $wp_query->is_home()) {
            $this->add_blog_crumb();
        }
        elseif ($wp_query->is_singular()) {

            // get_post_type_archive_link($post->post_type)
            if ($this->get_option("post_type_archive.{$queried_object->post_type}.show", false) and get_post_type_object($queried_object->post_type)->has_archive) {
                $this->add_crumb($queried_object->post_type, 'ptarchive');
            }

            if ($queried_object->post_parent) {
                $this->add_post_ancestor_crumbs($queried_object);
            }
            else {
                $this->maybe_add_taxonomy_crumbs_for_post();
            }

            if ($queried_object->ID) {
                $this->add_crumb($queried_object->ID, 'postId');
            }
        }
        else {

            if ($wp_query->is_post_type_archive()) {
                $this->add_crumb($queried_object->name, 'ptarchive');
            }
            elseif ($wp_query->is_tax() || $wp_query->is_tag() || $wp_query->is_category()) {
                $this->add_crumbs_for_taxonomy();
            }
            elseif ($wp_query->is_date()) {
                if ($wp_query->is_day()) {
                    $this->add_year_crumb();
                    $this->add_month_crumb();
                    $this->add_day_crumb(false);
                }
                elseif ($wp_query->is_month()) {
                    $this->add_month_crumb();
                }
                elseif ($wp_query->is_year()) {
                    $this->add_year_crumb();
                }
            }
            elseif ($wp_query->is_author()) {
                $this->add_crumb(
                    $this->generate_crumb_value($this->get_option('author.prefix', '') . ' ' . $queried_object->display_name, null),
                    'crumb',
                    true
                );
            }
            elseif ($wp_query->is_search()) {
                $this->add_crumb(
                    $this->generate_crumb_value($this->get_option('search.prefix', '') . ' "' . esc_html(get_search_query()) . '"', null),
                    'crumb',
                    true
                );
            }
            elseif ($wp_query->is_404()) {

                if (0 !== $wp_query->get('year') || (0 !== $wp_query->get('monthnum') || 0 !== get_query_var('day'))) {
                    if ('page' == $this->show_on_front and !$wp_query->is_home()) {
                        if ($this->page_for_posts and !$this->get_option('remove_blog')) {
                            $this->add_blog_crumb();
                        }
                    }

                    if (0 !== $wp_query->get('day')) {
                        $this->add_linked_month_year_crumb();

                        $date = sprintf('%04d-%02d-%02d 00:00:00', $wp_query->get('year'), $wp_query->get('monthnum'), $wp_query->get('day'));
                        $this->add_date_crumb($date);
                    }
                    elseif (0 !== $wp_query->get('monthnum')) {
                        $this->add_month_crumb();
                    }
                    elseif (0 !== $wp_query->get('year')) {
                        $this->add_year_crumb();
                    }
                }
                else {
                    $this->add_crumb(
                        $this->generate_crumb_value($this->get_option('404.prefix', ''), null),
                        'crumb',
                        true
                    );
                }
            }
        }
    }

    /**
     * Add Homepage crumb to the crumbs property
     */
    private function add_home_crumb()
    {
        $this->add_crumb(
            $this->generate_crumb_value($this->get_option('home.prefix', 'Home'), $this->home_url),
            'crumb',
            true
        );
    }

    /**
     * Add a predefined crumb to the crumbs property
     *
     * @param string $type
     * @param null $value
     * @param bool $allow_html
     */
    private function add_crumb($value = null, string $type = 'crumb', bool $allow_html = false)
    {
        $this->crumbs[] = array(
            'type'       => $type,
            'value'      => $value,
            'allow_html' => $allow_html,
        );
    }

    private function generate_crumb_value($text, $url): array
    {
        return array(
            'text' => $text,
            'url'  => $url,
        );
    }

    /**
     * Determine the crumbs which should form the breadcrumb.
     */
    private function get_breadcrumb_format()
    {
        if (!$this->queried_object) {
            return '';
        }

        $wp_query = $this->get_wp_query();

        $format = '';

        if ($wp_query->is_home()) {
            $format = $this->get_option("post_type.{$this->queried_object->post_type}.format", "%%home%%");
        }
        elseif ($wp_query->is_singular()) {

            $format = $this->get_option("post_type.{$this->queried_object->post_type}.format", "%%home%% >> %%category%% >> %%title%%");
        }
        elseif ($wp_query->is_author()) {
            $format = $this->get_option("author.format", "%%home%% >> %%queried_object%%");
        }
        elseif ($wp_query->is_search()) {
            $format = $this->get_option("search.format", "%%home%% >> %%queried_object%%");
        }
        elseif ($wp_query->is_404()) {

            $format = $this->get_option("404.format", "%%home%% >> %%queried_object%%");
        }
        elseif ($wp_query->is_archive()) {
            if ($wp_query->is_post_type_archive()) {
                $format = $this->get_option("post_type_archive.{$this->queried_object->post_type}.format", "%%home%% >> %%queried_object%%");
            }
            elseif (is_object($this->queried_object)) {
                $format = $this->get_option("tax.{$this->queried_object->taxonomy}.format", "%%home%% >> %%queried_object%%");
            }
        }

        return $format;
    }

    private function format_to_crumbs($format)
    {
        $format = trim($format);

        if (empty($format)) {
            return;
        }

        preg_match_all("/%%[^%]*%%/U", $format, $rules);

        $format_crumb = array(
            array(
                'text' => $format,
                'url'  => $format
            )
        );

        foreach ($rules[0] as $rule) {
            $_rule = str_replace("%%", '', $rule);
            $format_crumb = $this->perform_replace($_rule, $format_crumb);
        }

        foreach ($format_crumb as $obj) {

            // remove only text format
            $url = preg_replace("#\[[^]]+]#U", '', str_replace(array('(', ')'), '', $obj['url']));

            if (!empty($url) and !str_contains($url, $this->home_url)) {
                $url = $this->home_url . $url;
            }

            // fix multiple slashes
            $url = preg_replace('#([^:])(/{2,})#U', '$1/', $url);

            $this->add_crumb($this->generate_crumb_value(

            // remove only link format
                preg_replace("#\([^)]+\)#U", '', str_replace(array('[', ']'), '', $obj['text'])),
                $url
            ));
        }
    }

    private function perform_replace($rule, $format_crumb): array
    {
        $replaces = $this->generate_replacements($rule);

        $items = array();

        foreach ($replaces as $replace) {
            foreach ($format_crumb as $subject) {

                $items[] = array(
                    'text' => str_replace("%%$rule%%", $replace['text'] ?: '', $subject['text']),
                    'url'  => str_replace("%%$rule%%", $replace['url'] ?: '', $subject['url']),
                );
            }
        }

        return $items;
    }

    private function generate_replacements($rule)
    {
        global $wp_rewrite;

        $wp_query = $this->get_wp_query();

        $replaces = array();

        switch ($rule) {

            case 'home':
                $replaces[] = array(
                    'text' => $this->get_option('home.prefix', 'Home'),
                    'url'  => $this->home_url
                );
                break;

            case 'sitename':
                $replaces[] = array(
                    'text' => get_bloginfo('name'),
                    'url'  => $this->home_url
                );
                break;

            case 'title':
                $replaces[] = array(
                    'text' => wpfs_the_title(), // todo verify wpfs_document_title
                    'url'  => get_permalink($this->queried_object->ID)
                );
                break;

            case 'language':
                $replaces[] = array(
                    'text' => get_locale(),
                    'url'  => get_locale()
                );
                break;

            case 'post_parent':
                if (isset($this->queried_object->post_parent) and $this->queried_object->post_parent) {
                    $replaces[] = array(
                        'text' => wps_get_post($this->queried_object->post_parent)->post_title,
                        'url'  => get_permalink($this->queried_object->post_parent)
                    );
                }
                break;

            case 'post_type':
                if (isset($this->queried_object->post_type)) {
                    $replaces[] = array(
                        'text' => get_post_type_object($this->queried_object->post_type)->name,
                        'url'  => get_post_type_archive_link($this->queried_object->post_type)
                    );
                }
                break;

            case 'queried_object':
                if ($wp_query->is_singular()) {
                    $replaces[] = array(
                        'text' => wps_get_post($this->queried_object)->post_title,
                        'url'  => get_permalink($this->queried_object->ID)
                    );
                }
                elseif ($wp_query->is_404()) {
                    $replaces[] = array(
                        'text' => $this->queried_object->display_name,
                        'url'  => get_author_posts_url($this->queried_object->ID)
                    );
                }
                elseif ($wp_query->is_author()) {
                    $replaces[] = array(
                        'text' => $this->queried_object->display_name,
                        'url'  => get_author_posts_url($this->queried_object->ID)
                    );
                }
                elseif ($wp_query->is_search()) {
                    $replaces[] = array(
                        'text' => esc_html(get_search_query()),
                        'url'  => ''
                    );
                }
                elseif ($wp_query->is_archive()) {

                    if ($wp_query->is_post_type_archive()) {
                        $replaces[] = array(
                            'text' => $this->queried_object->post_type,
                            'url'  => get_post_type_archive_link($this->queried_object->post_type)
                        );
                    }
                    else {
                        $replaces[] = array(
                            'text' => $this->queried_object->name,
                            'url'  => get_term_link($this->queried_object->term_id, $this->queried_object->taxonomy)
                        );
                    }
                }
                break;

            case 'category':
            case 'taxonomy':
            case 'category-compact':
            case 'taxonomy-compact':
            case 'category-full':
            case 'taxonomy-full':

                if ($this->queried_object instanceof \WP_Term) {

                    $deepest_term = $this->find_deepest_term(array($this->queried_object));
                }
                else {

                    $main_tax = $this->get_option("post_type.{$this->queried_object->post_type}.maintax", 'category');

                    $terms = wp_get_object_terms($this->queried_object->ID, $main_tax);

                    if (!is_array($terms) or empty($terms)) {
                        break;
                    }

                    $deepest_term = $this->find_deepest_term($terms);
                }

                $parent_terms = $this->get_term_parents($deepest_term);

                $compact_url = $url = '';
                $depth = 1;
                $compact_url_request = str_contains($rule, "compact");

                if (str_contains($rule, "full")) {
                    $url = str_replace("%$deepest_term->taxonomy%", '', $wp_rewrite->get_extra_permastruct($deepest_term->taxonomy));
                }

                foreach ($parent_terms as $parent_term) {

                    $url .= "{$parent_term->slug}/";

                    $replaces[] = array(
                        'text' => $parent_term->name,
                        'url'  => $compact_url_request ? "{$compact_url}{$parent_term->slug}/" : $url
                    );

                    /**
                     * set as subcategory like normal url after the 3th element guarantee better seo performances
                     */
                    if (++$depth > 3) {
                        $compact_url .= $parent_term->slug . "/";
                    }
                }

                $replaces[] = array(
                    'text' => $deepest_term->name,
                    'url'  => ($compact_url_request ? $compact_url : $url) . "{$deepest_term->slug}/"
                );

                break;
        }

        if (empty($replaces) and str_starts_with($rule, "meta")) {

            $meta = str_replace("meta_", "", $rule);

            $_meta = get_metadata_raw(
                wpfseo('helpers')->currentPage->get_query_type(),
                $this->queried_object->ID, $meta,
                true
            );

            $replaces[] = array(
                'text' => $_meta,
                'url'  => $_meta
            );
        }

        if (empty($replaces) and $replace = get_query_var($rule, false)) {
            $replaces[] = array(
                'text' => $replace,
                'url'  => $replace
            );
        }

        /**
         * Allow developers to filter generated replacements and to build their own.
         *
         * @param string $rule The rule.
         * @param array $replaces The currently generated replacements.
         */
        return apply_filters('wpfs_breadcrumb_replacements', $replaces, $rule);
    }

    /**
     * Find the deepest term in an array of term objects
     */
    private function find_deepest_term(array $terms): WP_Term
    {
        /* Let's find the deepest term in this array, by looping through and then
           unsetting every term that is used as a parent by another one in the array. */
        $terms_by_id = array();
        foreach ($terms as $term) {
            $terms_by_id[$term->term_id] = $term;
        }

        foreach ($terms as $term) {
            unset($terms_by_id[$term->parent]);
        }

        /* As we could still have two subcategories, from different parent categories,
           let's pick the one with the lowest ordered ancestor. */
        $max_parents_count = 0;
        $deepest_term = reset($terms_by_id);

        foreach ($terms_by_id as $term) {
            $parents_count = count($this->get_term_parents($term));

            if ($parents_count >= $max_parents_count) {
                $max_parents_count = $parents_count;
                $deepest_term = $term;
            }
        }

        return $deepest_term;
    }

    /**
     * Get a term's parents.
     */
    private function get_term_parents(object $term): array
    {
        $tax = $term->taxonomy;
        $parents = array();
        while ($term->parent) {
            $term = wps_get_term($term->parent, $tax);
            $parents[] = $term;
        }

        return array_reverse($parents);
    }

    /**
     * Add Blog crumb to the crumbs property
     */
    private function add_blog_crumb(): void
    {
        $this->add_crumb($this->page_for_posts, 'postId');
    }

    /**
     * Add hierarchical ancestor crumbs to the crumbs property for a single post
     * @param WP_Post $post
     */
    private function add_post_ancestor_crumbs(WP_Post $post): void
    {
        $ancestors = $this->get_post_ancestors($post);
        if (!empty($ancestors)) {
            foreach ($ancestors as $ancestor) {
                $this->add_crumb($ancestor, 'postId');
            }
        }
    }

    /**
     * Retrieve the hierarchical ancestors for the current 'post'
     *
     * @param WP_Post $post
     * @return array
     */
    private function get_post_ancestors(WP_Post $post): array
    {
        $ancestors = array();

        if (isset($post->ancestors)) {
            if (is_array($post->ancestors)) {
                $ancestors = array_values($post->ancestors);
            }
            else {
                $ancestors = array($post->ancestors);
            }
        }
        elseif (isset($post->post_parent)) {
            $ancestors = array($post->post_parent);
        }

        // Reverse the order so it's oldest to newest
        return array_reverse($ancestors);
    }

    /**
     * Add taxonomy crumbs to the crumbs property for a single post
     */
    private function maybe_add_taxonomy_crumbs_for_post(): void
    {
        $main_tax = $this->get_option("post_type.{$this->queried_object->post_type}.maintax", 'category');

        if ($this->queried_object->ID) {

            $terms = wp_get_object_terms($this->queried_object->ID, $main_tax);

            if (is_array($terms) and !empty($terms)) {

                $deepest_term = $this->find_deepest_term($terms);

                if (is_taxonomy_hierarchical($main_tax) and $deepest_term->parent) {
                    $parent_terms = $this->get_term_parents($deepest_term);
                    foreach ($parent_terms as $parent_term) {
                        $this->add_crumb($parent_term, 'term');
                    }
                }

                $this->add_crumb($deepest_term, 'term');
            }
        }
    }

    /**
     * Add taxonomy parent crumbs to the crumbs property for a taxonomy
     */
    private function add_crumbs_for_taxonomy()
    {
        $this->maybe_add_term_parent_crumbs($this->queried_object);

        $this->add_crumb($this->queried_object, 'term');

        $this->maybe_add_term_children_crumbs($this->queried_object);
    }

    /**
     * Add parent taxonomy crumbs to the crumb property for hierarchical taxonomy
     */
    private function maybe_add_term_parent_crumbs(WP_Term $term)
    {
        if (is_taxonomy_hierarchical($term->taxonomy) and $term->parent != 0) {
            foreach ($this->get_term_parents($term) as $parent_term) {
                $this->add_crumb($parent_term, 'term');
            }
        }
    }

    /**
     * Add parent taxonomy crumbs to the crumb property for hierarchical taxonomy
     */
    private function maybe_add_term_children_crumbs(\WP_Term $term)
    {
        if ($this->get_option('dropdown_last') and is_taxonomy_hierarchical($this->queried_object->taxonomy)) {

            $children_terms = $this->get_term_children($term);

            if (!empty($children_terms))
                $this->add_crumb($children_terms, 'term_list');
        }
    }

    /**
     * Get a term's children.
     */
    private function get_term_children(\WP_Term $term): array
    {
        $terms = _get_term_hierarchy($term->taxonomy);

        $children = array();

        foreach ((array)$terms[$term->term_id] as $child) {
            $children[] = wps_get_term($child, $term->taxonomy);
        }

        return $children;
    }

    /**
     * Add year crumb to crumbs property
     * @param bool $link
     */
    private function add_year_crumb(bool $link = true)
    {
        $wp_query = $this->get_wp_query();

        $this->add_crumb(
            $this->generate_crumb_value((string)$wp_query->get('year'), $link ? get_year_link($wp_query->get('year')) : null),
            'crumb',
            true
        );
    }

    /**
     * Add month crumb to crumbs property
     * @param bool $link
     */
    private function add_month_crumb(bool $link = true)
    {
        global $wp_locale;

        $wp_query = $this->get_wp_query();

        $this->add_crumb(
            $this->generate_crumb_value($wp_locale->get_month($wp_query->get('monthnum')), $link ? get_month_link($wp_query->get('year'), $wp_query->get('monthnum')) : null),
            'crumb',
            true
        );
    }

    /**
     * Add month crumb to crumbs property
     * @param bool $link
     */
    private function add_day_crumb(bool $link = true): void
    {
        $wp_query = $this->get_wp_query();

        $this->add_crumb(
            $this->generate_crumb_value((string)$wp_query->get('day'), $link ? get_day_link($wp_query->get('year'), $wp_query->get('monthnum'), $wp_query->get('day')) : null),
            'crumb',
            true
        );
    }

    /**
     * Add month-year crumb to crumbs property
     */
    private function add_linked_month_year_crumb(): void
    {
        global $wp_locale;

        $wp_query = $this->get_wp_query();

        $this->add_crumb(
            $this->generate_crumb_value($wp_locale->get_month($wp_query->get('monthnum')) . ' ' . $wp_query->get('year'),
                get_month_link($wp_query->get('year'), $wp_query->get('monthnum')))
        );
    }

    /**
     * Add (non-link) date crumb to crumbs property
     *
     * @param string $date
     */
    private function add_date_crumb(string $date = '')
    {
        if (empty($date)) {
            $date = get_the_date();
        }
        else {
            $date = mysql2date(get_option('date_format'), $date, true);
            $date = apply_filters('get_the_date', $date, '');
        }

        $this->add_crumb(
            $this->generate_crumb_value($this->get_option('archive.prefix', '') . ' ' . esc_html($date), null),
            'crumb',
            true
        );
    }

    /**
     * Take the crumbs array and convert each crumb to ['type' => 'crumb', 'value' => ...]
     */
    private function transform_crumbs(): void
    {
        foreach ($this->crumbs as $i => $crumb) {

            switch ($crumb['type']) {

                case 'postId':
                    $crumb_spec = $this->generate_crumb_value(strip_tags(get_the_title($crumb['value'])), get_permalink($crumb['value']));
                    break;

                case 'term':
                case 'term_list':
                    $crumb_spec = $this->get_link_info_for_terms($crumb['value']);
                    break;

                case 'ptarchive':
                    $crumb_spec = $this->get_link_info_for_ptarchive($crumb['value']);

                    break;

                default:
                    continue 2;
            }

            $this->crumbs[$i] = [
                'value'      => $crumb_spec,
                'type'       => 'crumb',
                'allow_html' => $crumb['allow_html']

            ];
        }
    }

    private function get_link_info_for_terms($terms): array
    {
        if (empty($terms)) {
            return $this->generate_crumb_value('', '');
        }

        if (is_array($terms)) {
            $link_info = array(
                'list' => true
            );

            foreach ($terms as $term) {

                $link_info[] = $this->generate_crumb_value($term->name, get_term_link($term));
            }
        }
        else {
            $link_info = $this->generate_crumb_value($terms->name, get_term_link($terms));
        }

        return $link_info;
    }

    /**
     * Retrieve link url and text based on post type
     */
    private function get_link_info_for_ptarchive(string $post_type): array
    {
        $link = array();
        $archive_title = '';

        $post_type_obj = get_post_type_object($post_type);

        if (is_object($post_type_obj)) {
            if (isset($post_type_obj->label) and $post_type_obj->label !== '') {
                $archive_title = $post_type_obj->label;
            }
            elseif (isset($post_type_obj->labels->menu_name) and $post_type_obj->labels->menu_name !== '') {
                $archive_title = $post_type_obj->labels->menu_name;
            }
            else {
                $archive_title = $post_type_obj->name;
            }
        }

        $link['url'] = get_post_type_archive_link($post_type);
        $link['text'] = $archive_title;

        return $link;
    }

    /**
     * Take the crumbs array and convert each crumb to a single breadcrumb string.
     */
    private function prepare_links()
    {
        if (empty($this->crumbs)) {
            return;
        }

        $crumb_count = count($this->crumbs) - 1;
        $this->links = [];

        foreach ($this->crumbs as $i => $crumb) {

            $this->links[] = trim($this->crumb_to_link($crumb['value'], $i + 1, $i === $crumb_count));
        }
    }

    /**
     * Create a breadcrumb element string
     *
     * @param array $link Link info array containing the keys:
     *                     'text'    => (string) link text
     *                     'url'    => (string) link url
     *                     (optional) 'allow_html'    => (bool) whether to (not) escape html in the link text
     *                     This prevents html stripping from the text strings set in the
     *                     Flex -> Internal Links options page
     * @param $position
     * @param bool $current
     * @return string
     * for paged article at this moment
     */
    private function crumb_to_link(array $link, $position, bool $current = false): string
    {
        $link_output = '';

        if (isset($link['list']) and $link['list']) {

            unset($link['list']);

            $link_output = "<$this->element data-role='links'>";

            $link_output .= "<a role='button' data-action='toggle'><span>Cascade</span><span></span></a>";

            $link_output .= "<ul class='wpfs-breadcrumb-list'>";

            foreach ($link as $_link) {
                $link_output .= $this->crumb_to_link($_link, $position, false);
            }

            $link_output .= "</ul>";
            $link_output .= "</$this->element>";

        }
        elseif (!empty($link['text']) and is_string($link['text'])) {

            $link['text'] = trim($link['text']);

            if ($position > 1) {
                $link['text'] = ucfirst($link['text']);
            }

            if (!isset($link['allow_html']) or !$link['allow_html']) {
                $link['text'] = esc_html($link['text']);
            }

            $link_output = "<$this->element class='wpfs-breadcrumb-item' itemprop='itemListElement' itemtype='https://schema.org/ListItem'>";

            if (!empty($link['url']) and (!$current or !$this->get_option('last_page'))):

                // remove multiple slashes
                $url = preg_replace('/([^:])(\/+)/', '$1/', $link['url']);

                $url = StringHelper::strtolower(esc_url($url));

                $alternative = "href='" . $url . "'";
            else :
                $alternative = "class='wpfs-breadcrumb-current'";
            endif;

            $link_output .= "<a itemprop='item' {$alternative} itemtype='https://schema.org/WebPage'><span itemprop='name'>{$link['text']}</span></a>";

            if ($position !== false) {
                $link_output .= "<meta itemprop='position' content='{$position}'>";
            }

            $link_output .= "</$this->element>";
        }

        return $link_output;
    }

    /**
     * Wrap a complete breadcrumb string in a Breadcrumb RDFA wrapper
     */
    private function wrap_breadcrumb()
    {
        // Remove any effectively empty links
        $links = array_filter($this->links);

        if (!empty($links)) {

            $separator = trim($this->get_option('separator', '>'));

            $output = implode($separator, $links);

            if (!empty($output)) {
                $this->output = "<{$this->wrapper} class='wpfs-breadcrumb' itemtype='https://schema.org/BreadcrumbList'>{$output}</{$this->wrapper}>";
            }
        }
    }

    public static function export(): array
    {
        if (!isset(self::$_Instance)) {
            self::$_Instance = new self();
        }

        self::$_Instance->generate();

        // Remove any effectively empty crumbs
        return array_filter(self::$_Instance->crumbs);
    }
}