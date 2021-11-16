<?php
/**
 * @author    sh1zen
 * @copyright Copyright (C)  2021
 * @license   http://www.gnu.org/licenses/gpl.html GNU/GPL
 */


class WPFS_Breadcrumb
{
    /**
     * @var string    Last used 'before' string
     */
    public static $before = '';

    /**
     * @var string    Last used 'after' string
     */
    public static $after = '';

    /**
     * @var    object    Instance of this class
     */
    private static $_instance;

    /**
     * @var    string    Blog's show on front setting, 'page' or 'posts'
     */
    private $show_on_front;

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
    private $options;

    /**
     * @var string    HTML wrapper element for a single breadcrumb element
     */
    private $element;

    /**
     * @var string    HTML wrapper element for the WP SEO breadcrumbs output
     */
    private $wrapper;

    /**
     * @var    array    Array of crumbs
     *
     * Each element of the crumbs array can either have one of these keys:
     *    "id"         for post types;
     *    "ptarchive"  for a post type archive;
     *    "term"       for a taxonomy term.
     * OR it consists of a predefined set of 'text', 'url' and 'allow_html'
     */
    private $crumbs = array();

    /**
     * @var array    Array of individual (linked) html strings created from crumbs
     */
    private $links = array();

    /**
     * @var    string    Breadcrumb html string
     */
    private $output;

    /**
     * @var    string    Home
     */
    private $home_url;

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
    public static function breadcrumb($before = '', $after = '', $display = true, $args = array())
    {
        if (!shzn('wpfs')->settings->get('breadcrumbs.active', false))
            return '';

        if (!isset(self::$_instance)) {
            self::$_instance = new self();
        }

        self::$_instance->generate($args);

        // Remember the last used before/after for use in case the object goes __toString()
        self::$before = $before;
        self::$after = $after;

        $output = $before . self::$_instance->output . $after;

        if ($display === true) {
            echo $output;
        }

        return $output;
    }

    /**
     * Create the breadcrumb
     * @param array $args
     */
    private function generate($args = array())
    {
        if (!$this->get_wp_query()) {
            _doing_it_wrong(__FUNCTION__, __('Conditional query tags do not work before the query is run. Before then, they always return false.', 'wpfs'), '1.0.0');
        }

        if (empty($this->output)) {

            $this->options = array_merge(array(
                'prefix.archive'          => __('Archive for:', 'wpfs'),
                'prefix.search'           => __('Searched:', 'wpfs'),
                'prefix.author'           => '',
                'post_types-post-maintax' => 'category',
            ), $args);

            $this->home_url = shzn()->utility->home_url;

            $this->queried_object = $this->get_wp_query()->get_queried_object();

            $this->show_on_front = get_option('show_on_front');
            $this->page_for_posts = get_option('page_for_posts');

            $this->wrapper = 'ol';
            $this->element = 'li';

            $format = $this->get_option('flexed') ? $this->get_breadcrumb_format() : '';

            $this->set_crumbs($format);
            $this->prepare_links();
            $this->links_to_string();
            $this->wrap_breadcrumb();
        }
    }

    /**
     * @return WP_Query
     */
    private function get_wp_query()
    {
        return $GLOBALS['wp_query'];
    }

    private function get_option($option, $default = false)
    {
        if (isset($this->options[$option]))
            return $this->options[$option];

        $settings = shzn('wpfs')->settings->get("breadcrumbs." . $option, '');

        if (empty($settings))
            $settings = $default;

        return $settings;
    }

    /**
     * Determine the crumbs which should form the breadcrumb.
     */
    private function get_breadcrumb_format()
    {
        if (!$this->queried_object)
            return '';

        $wp_query = $this->get_wp_query();

        $format = '';

        if ($wp_query->is_home()) {
            $format = $this->get_option("format.post_type.{$this->queried_object->post_type}", "%%home%%");
        }
        elseif ($wp_query->is_singular()) {

            $format = $this->get_option("format.post_type.{$this->queried_object->post_type}", "%%home%% >> %%category%% >> %%title%%");
        }
        elseif ($wp_query->is_author()) {
            $format = $this->get_option("format.author", "%%home%% >> %%queried_object%%");
        }
        elseif ($wp_query->is_search()) {
            $format = $this->get_option("format.search", "%%home%% >> %%queried_object%%");
        }
        elseif ($wp_query->is_404()) {

            $format = $this->get_option("format.404", "%%home%% >> %%queried_object%%");
        }
        elseif ($wp_query->is_archive()) {
            if ($wp_query->is_post_type_archive())
                $format = $this->get_option("format.post_type_archive.{$this->queried_object->post_type}", "%%home%% >> %%queried_object%%");
            elseif (is_object($this->queried_object))
                $format = $this->get_option("format.tax.{$this->queried_object->taxonomy}", "%%home%% >> %%queried_object%%");
        }

        return $format;
    }

    /**
     * Determine the crumbs which should form the breadcrumb.
     */
    private function set_crumbs($format = '')
    {
        $wp_query = $this->get_wp_query();

        $queried_object = $this->queried_object;

        if (!is_object($queried_object)) {
            $this->add_home_crumb();
            return;
        }

        if (!empty($format)) {

            //$format = preg_replace('/\s+/', ' ', $format);

            $crumbs_structure = array_map('trim', explode('>>', $format));

            foreach ($crumbs_structure as $crumb_structure) {
                $this->format_to_crumbs($crumb_structure);
            }

            if (!empty($this->crumbs))
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

            if (shzn('wpfs')->settings->get('breadcrumbs.post_type', true)) {

                // get_post_type_archive_link($post->post_type)
                if (get_post_type_object($queried_object->post_type)->has_archive) {
                    $this->add_crumb($queried_object->post_type, 'ptarchive');
                }
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
                    $this->generate_crumb($this->get_option('prefix.author', '') . ' ' . $queried_object->display_name, null),
                    'crumb',
                    true
                );
            }
            elseif ($wp_query->is_search()) {
                $this->add_crumb(
                    $this->generate_crumb($this->get_option('prefix.search', '') . ' "' . esc_html(get_search_query()) . '"', null),
                    'crumb',
                    true
                );
            }
            elseif ($wp_query->is_404()) {

                if (0 !== $wp_query->get('year') || (0 !== $wp_query->get('monthnum') || 0 !== get_query_var('day'))) {
                    if ('page' == $this->show_on_front and !$wp_query->is_home()) {
                        if ($this->page_for_posts and !$this->get_option('breadcrumbs-blog-remove')) {
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
                        $this->generate_crumb($this->get_option('prefix.404', ''), null),
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
            $this->generate_crumb(shzn('wpfs')->settings->get('breadcrumbs.home_txt', 'Home'), $this->home_url),
            'crumb',
            true
        );
    }

    /**
     * Add a predefined crumb to the crumbs property
     *
     * @param $type
     * @param null $value
     * @param bool $allow_html
     */
    private function add_crumb($value = null, $type = 'crumb', $allow_html = false)
    {
        $this->crumbs[] = array(
            'type'       => $type,
            'value'      => $value,
            'allow_html' => $allow_html,
        );
    }

    private function generate_crumb($text, $url)
    {
        return array(
            'text' => $text,
            'url'  => $url,
        );
    }

    private function format_to_crumbs($format)
    {
        preg_match_all("/%%[^%]*%%/", $format, $rules);

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
            $url = preg_replace("/\[[^]]+\]/", '', str_replace(array('(', ')'), '', $obj['url']));

            if (!empty($url) and strpos($url, $this->home_url) === false) {
                $url = $this->home_url . $url;
            }

            // fix multiple slashes
            $url = preg_replace('/([^:])(\/{2,})/i', '$1/', $url);

            $this->add_crumb($this->generate_crumb(

            // remove only link format
                preg_replace("/\([^)]+\)/", '', str_replace(array('[', ']'), '', $obj['text'])),
                $url
            ));
        }

    }

    private function perform_replace($rule, $format_crumb)
    {
        $replaces = $this->generate_replacements($rule);

        $items = array();

        foreach ($replaces as $replace) {
            foreach ($format_crumb as $subject) {

                $items[] = array(
                    'text' => str_replace("%%{$rule}%%", $replace['text'], $subject['text']),
                    'url'  => str_replace("%%{$rule}%%", $replace['url'], $subject['url']),
                );
            }
        }

        return $items;
    }

    private function generate_replacements($rule)
    {
        $wp_query = $this->get_wp_query();

        $replaces = array();

        switch ($rule) {

            case 'home':
            case 'sitename':
                $replaces[] = array(
                    'text' => shzn('wpfs')->settings->get('breadcrumbs.home_txt', get_bloginfo('name')),
                    'url'  => $this->home_url
                );
                break;

            case 'title':
                $replaces[] = array(
                    'text' => wp_get_document_title(),
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
                        'text' => get_the_title($this->queried_object->post_parent),
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
                        'text' => get_the_title($this->queried_object->ID),
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

                $compact_url_request = strpos($rule, "compact") !== false;

                if (strpos($rule, "category") !== false) {
                    $terms = wp_get_object_terms($this->queried_object->ID, 'category');

                    if (!is_array($terms))
                        break;

                    $deepest_term = $this->find_deepest_term($terms);
                }
                else {
                    $deepest_term = $this->find_deepest_term(array($this->queried_object));
                }

                $parent_terms = $this->get_term_parents($deepest_term);

                $url = '';
                $compact_url = '';
                $depth = 1;

                foreach ($parent_terms as $parent_term) {

                    $url .= $parent_term->slug;

                    $replaces[] = array(
                        'text' => $parent_term->name,
                        'url'  => ($compact_url_request ? ($compact_url . "{$parent_term->slug}") : $url) . '/'
                    );

                    /**
                     * set as subcategory like normal url after the 3th element guarantee better seo performances
                     */
                    if (++$depth > 3)
                        $compact_url .= $parent_term->slug . "/";
                }

                $replaces[] = array(
                    'text' => $deepest_term->name,
                    'url'  => ($compact_url_request ? $compact_url : $url) . $deepest_term->slug . '/'
                );

                break;
        }

        if (empty($replaces) and substr($rule, 0, 4) === "meta") {
            $meta = str_replace("meta_", "", $rule);

            $_meta = get_metadata_raw(
                wpfseo()->currentPage->get_query_type(),
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
         * @since 1.0.0
         *
         */
        return apply_filters('wpfs_breadcrumb_replacements', $replaces, $rule);
    }

    /**
     * Find the deepest term in an array of term objects
     *
     * @param array $terms
     *
     * @return WP_Term
     */
    private function find_deepest_term($terms)
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
        $parents_count = 0;
        reset($terms_by_id);
        $deepest_term = current($terms_by_id);

        foreach ($terms_by_id as $term) {
            $parents = $this->get_term_parents($term);

            if (count($parents) >= $parents_count) {
                $parents_count = count($parents);
                $deepest_term = $term;
            }
        }

        return $deepest_term;
    }

    /**
     * Get a term's parents.
     *
     * @param object $term Term to get the parents for
     *
     * @return    WP_Term[]
     */
    private function get_term_parents($term)
    {
        $tax = $term->taxonomy;
        $parents = array();
        while ($term->parent) {
            $term = get_term($term->parent, $tax);
            $parents[] = $term;
        }

        return array_reverse($parents);
    }

    /**
     * Add Blog crumb to the crumbs property
     */
    private function add_blog_crumb()
    {
        $this->add_crumb($this->page_for_posts, 'postId');
    }

    /**
     * Add hierarchical ancestor crumbs to the crumbs property for a single post
     * @param WP_Post $post
     */
    private function add_post_ancestor_crumbs($post)
    {
        $ancestors = $this->get_post_ancestors($post);
        if (is_array($ancestors) and $ancestors !== array()) {
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
    private function get_post_ancestors($post)
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
    private function maybe_add_taxonomy_crumbs_for_post()
    {
        $main_tax = $this->get_option('post_types-' . $this->queried_object->post_type . '-maintax', 'category');

        if ($main_tax and isset($this->queried_object->ID)) {
            $terms = wp_get_object_terms($this->queried_object->ID, $main_tax);

            if (is_array($terms)) {

                $deepest_term = $this->find_deepest_term($terms);

                $is_hierarchical = is_taxonomy_hierarchical($main_tax);

                if ($is_hierarchical and $deepest_term->parent) {
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
     *
     * @param object $term
     */
    private function maybe_add_term_parent_crumbs($term)
    {
        if (is_taxonomy_hierarchical($term->taxonomy) and $term->parent != 0) {
            foreach ($this->get_term_parents($term) as $parent_term) {
                $this->add_crumb($parent_term, 'term');
            }
        }
    }

    /**
     * Add parent taxonomy crumbs to the crumb property for hierarchical taxonomy
     *
     * @param object $term
     */
    private function maybe_add_term_children_crumbs($term)
    {
        if ($this->get_option('dropdown_last') and is_taxonomy_hierarchical($this->queried_object->taxonomy)) {

            $children_terms = $this->get_term_children($term);

            if (!empty($children_terms))
                $this->add_crumb($children_terms, 'term_list');
        }
    }

    /**
     * Get a term's children.
     *
     * @param object $term Term to get the parents for
     *
     * @return    array
     */
    private function get_term_children($term)
    {
        $terms = _get_term_hierarchy($term->taxonomy);

        $children = array();

        foreach ((array)$terms[$term->term_id] as $child) {
            $children[] = get_term($child, $term->taxonomy);
        }

        return $children;
    }

    /**
     * Add year crumb to crumbs property
     * @param bool $link
     */
    private function add_year_crumb($link = true)
    {
        $wp_query = $this->get_wp_query();

        $this->add_crumb(
            $this->generate_crumb((string)$wp_query->get('year'), $link ? get_year_link($wp_query->get('year')) : null),
            'crumb',
            true
        );
    }

    /**
     * Add month crumb to crumbs property
     * @param bool $link
     */
    private function add_month_crumb($link = true)
    {
        global $wp_locale;

        $wp_query = $this->get_wp_query();

        $this->add_crumb(
            $this->generate_crumb($wp_locale->get_month($wp_query->get('monthnum')), $link ? get_month_link($wp_query->get('year'), $wp_query->get('monthnum')) : null),
            'crumb',
            true
        );
    }

    /**
     * Add month crumb to crumbs property
     * @param bool $link
     */
    private function add_day_crumb($link = true)
    {
        $wp_query = $this->get_wp_query();

        $this->add_crumb(
            $this->generate_crumb((string)$wp_query->get('day'), $link ? get_day_link($wp_query->get('year'), $wp_query->get('monthnum'), $wp_query->get('day')) : null),
            'crumb',
            true
        );
    }

    /**
     * Add month-year crumb to crumbs property
     */
    private function add_linked_month_year_crumb()
    {
        global $wp_locale;

        $wp_query = $this->get_wp_query();

        $this->add_crumb(
            $this->generate_crumb($wp_locale->get_month($wp_query->get('monthnum')) . ' ' . $wp_query->get('year'),
                get_month_link($wp_query->get('year'), $wp_query->get('monthnum')))
        );
    }

    /**
     * Add (non-link) date crumb to crumbs property
     *
     * @param string $date
     */
    private function add_date_crumb($date = null)
    {
        if (is_null($date)) {
            $date = get_the_date();
        }
        else {
            $date = mysql2date(get_option('date_format'), $date, true);
            $date = apply_filters('get_the_date', $date, '');
        }

        $this->add_crumb(
            $this->generate_crumb($this->get_option('prefix.archive', '') . ' ' . esc_html($date), null),
            'crumb',
            true
        );
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

        foreach ($this->crumbs as $i => $crumb) {

            switch ($crumb['type']) {

                case 'postId':
                    $link_info = array(
                        'url'  => get_permalink($crumb['value']),
                        'text' => strip_tags(get_the_title($crumb['value']))
                    );
                    break;

                case 'term':
                case 'term_list':
                    $link_info = $this->get_link_info_for_terms($crumb['value']);
                    break;

                case 'ptarchive':
                    $link_info = $this->get_link_info_for_ptarchive($crumb['value']);
                    break;

                default:
                    $link_info = $crumb['value'];
                    break;
            }

            $this->links[] = $this->crumb_to_link($link_info, $i + 1, $i === $crumb_count);
        }
    }

    private function get_link_info_for_terms($terms)
    {
        if (is_array($terms)) {
            $link_info = array(
                'list' => true
            );

            foreach ($terms as $term) {
                $link_info[] = array(
                    'url'  => get_term_link($term),
                    'text' => $term->name
                );
            }
        }
        else {
            // var_dump($terms);

            $link_info = array(
                'url'  => get_term_link($terms),
                'text' => $terms->name
            );
        }

        return $link_info;
    }

    /**
     * Retrieve link url and text based on post type
     *
     * @param string $pt Post type
     *
     * @return array Array of link text and url
     */
    private function get_link_info_for_ptarchive($pt)
    {
        $link = array();
        $archive_title = '';

        $post_type_obj = get_post_type_object($pt);
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

        $link['url'] = get_post_type_archive_link($pt);
        $link['text'] = $archive_title;

        return $link;
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
    private function crumb_to_link($link, $position, $current = false)
    {
        $link_output = '';

        if (isset($link['list']) and $link['list']) {

            unset($link['list']);

            $link_output = "<{$this->element} data-role='links'>";

            $link_output .= "<a role='button' data-action='toggle'><span>Cascade</span><span class='caret'></span></a>";

            $link_output .= "<ul class='wpfs-breadcrumb-list'>";

            foreach ($link as $_link) {
                // todo not output meta in this case
                $link_output .= $this->crumb_to_link($_link, $position, false);
            }

            $link_output .= "</ul>";
            $link_output .= "</{$this->element}>";

        }
        elseif (isset($link['text']) and (is_string($link['text']) and !empty($link['text']))) {

            $link['text'] = trim($link['text']);

            if (!isset($link['allow_html']) or !$link['allow_html']) {
                $link['text'] = esc_html($link['text']);
            }

            $link_output = "<{$this->element} class='wpfs-breadcrumb-item' itemprop='itemListElement' itemscope='' itemtype='http://schema.org/ListItem'>";

            if (!empty($link['url']) and (!$current or !$this->get_option('last_page'))):

                // remove multiple slashes
                $url = preg_replace('/([^:])(\/+)/', '$1/', $link['url']);

                $url = esc_url($url);

                $url = mb_strtolower($url);

                $alternative = "href='" . $url . "'";
            else :
                $alternative = "class='wpfs-breadcrumb-current'";
            endif;

            $link_output .= "<a itemprop='item' {$alternative} itemtype='https://schema.org/WebPage'><span itemprop='name'>{$link['text']}</span></a>";

            if ($position !== false)
                $link_output .= "<meta itemprop='position' content='{$position}'>";

            $link_output .= "</{$this->element}>";
        }

        return $link_output;
    }

    /**
     * Create a complete breadcrumb string from an array of breadcrumb element strings
     */
    private function links_to_string()
    {
        if (!empty($this->links)) {

            // Remove any effectively empty links
            $links = array_map('trim', $this->links);
            $links = array_filter($links);

            $this->output = implode(' ' . $this->get_option('separator', '>') . ' ', $links);
        }
    }

    /**
     * Wrap a complete breadcrumb string in a Breadcrumb RDFA wrapper
     */
    private function wrap_breadcrumb()
    {
        if (!empty($this->output)) {
            $this->output = "<{$this->wrapper} class='wpfs-breadcrumb' itemscope='' itemtype='http://schema.org/BreadcrumbList'>{$this->output}</{$this->wrapper}>";
        }
    }

    public static function export()
    {
        if (!isset(self::$_instance)) {
            self::$_instance = new self();
        }

        self::$_instance->generate();

        // Remove any effectively empty crumbs
        return array_filter(self::$_instance->crumbs);
    }
}