<?php
/**
 * @author    sh1zen
 * @copyright Copyright (C)  2022
 * @license   http://www.gnu.org/licenses/gpl.html GNU/GPL
 */

namespace FlexySEO\Engine\Helpers;

/**
 * A helper object for WordPress posts.
 */
class CurrentPage
{

    /**
     * @var \WP_Query
     */
    private $main_query;

    private $cache;

    /**
     * Current_Page_Helper constructor.
     *
     * @param \WP_Query $main_query
     */
    public function __construct($main_query)
    {
        $this->cache = shzn('wpfs')->cache;
        $this->main_query = $main_query;
    }

    public static function get_page_types()
    {
        return [
            'search',
            'post_archive',
            'home',
            'post',
            'term',
            'user',
            'date',
            '404'
        ];
    }

    /**
     * Returns the page type for the current request query.
     *
     * @return string Query type.
     */
    public function get_query_type()
    {
        if ($this->is_search()):
            $page_type = 'search';
        elseif ($this->is_post_type_archive() or $this->is_posts_page()):
            $page_type = 'post_archive';
        elseif ($this->is_homepage()):
            $page_type = 'home';
        elseif ($this->is_simple_page()):
            $page_type = 'post';
        elseif ($this->is_term_archive()):
            $page_type = 'term';
        elseif ($this->is_author_archive()):
            $page_type = 'user';
        elseif ($this->is_date_archive()):
            $page_type = 'date';
        elseif ($this->is_404()):
            $page_type = '404';
        else:
            $page_type = 'none';
        endif;

        return $page_type;
    }

    /**
     * Determine whether this is a search result.
     *
     * @return bool Whether nor not the current page is a search result.
     */
    public function is_search()
    {
        return $this->get_main_query()->is_search();
    }

    /**
     * @return \WP_Query
     */
    public function get_main_query()
    {
        if ($this->main_query instanceof \WP_Query) {
            return $this->main_query;
        }

        return $GLOBALS['wp_the_query'];
    }

    /**
     * Determine whether this is a post type archive.
     *
     * @return bool Whether nor not the current page is a post type archive.
     */
    public function is_post_type_archive()
    {
        $wp_query = $this->get_main_query();

        return $wp_query->is_post_type_archive();
    }

    /**
     * Determine whether this is the statically set posts page, when it's not the frontpage.
     *
     * @return bool Whether or not it's a non-frontpage, statically set posts page.
     */
    public function is_posts_page()
    {
        $wp_query = $this->get_main_query();

        if (!$wp_query->is_home()) {
            return false;
        }

        return get_option('show_on_front') === 'page';
    }

    public function is_home()
    {
        return $this->get_main_query()->is_home();
    }

    /**
     * Checks if the current page is the front page.
     *
     * @return bool Whether or not the current page is the front page.
     */
    public function is_homepage()
    {
        return $this->get_main_query()->is_front_page();
    }

    /**
     * Checks if the current page is the front page.
     *
     * @return bool Whether or not the current page is the front page.
     */
    public function is_front_page()
    {
        return $this->is_homepage();
    }

    /**
     * Checks if the currently opened page is a simple page.
     *
     * @return bool Whether the currently opened page is a simple page.
     */
    public function is_simple_page()
    {
        return $this->get_simple_page_id() > 0;
    }

    /**
     * Returns the id of the currently opened page.
     *
     * @return int The id of the currently opened page.
     */
    public function get_simple_page_id()
    {
        if (is_singular()) {
            return get_the_ID();
        }

        if ($this->is_posts_page()) {
            return get_option('page_for_posts');
        }

        return 0;
    }

    /**
     * Determine whether this is a term archive.
     *
     * @return bool Whether nor not the current page is a term archive.
     */
    public function is_term_archive()
    {
        $wp_query = $this->get_main_query();

        return $wp_query->is_tax || $wp_query->is_tag || $wp_query->is_category;
    }

    /**
     * Determine whether this is an author archive.
     *
     * @return bool Whether nor not the current page is an author archive.
     */
    public function is_author_archive()
    {
        return $this->get_main_query()->is_author();
    }

    /**
     * Determine whether this is an date archive.
     *
     * @return bool Whether nor not the current page is an date archive.
     */
    public function is_date_archive()
    {
        $wp_query = $this->get_main_query();

        return $wp_query->is_date();
    }

    /**
     * Determine whether this is a 404 page.
     *
     * @return bool Whether nor not the current page is a 404 page.
     */
    public function is_404()
    {
        $wp_query = $this->get_main_query();

        return $wp_query->is_404();
    }

    public function get_queried_object_id()
    {
        return $this->get_main_query()->get_queried_object_id();
    }

    public function get_term()
    {
        return $this->is_term_archive() ? $this->get_queried_object() : null;
    }

    /**
     * Returns the post type of the main query.
     *
     * @return \WP_Term|\WP_Post_Type|\WP_Post|\WP_User|null The queried object.
     */
    public function get_queried_object()
    {
        return $this->get_main_query()->get_queried_object();
    }

    /**
     * Retrieves an array of posts based on query variables.
     *
     * There are a few filters and actions that can be used to modify the post
     * database query.
     *
     * @return int[]|\WP_Post[] The queried object.
     */
    public function get_queried_posts()
    {
        return $this->get_main_query()->get_posts();
    }


    /**
     * @return \WP_Post
     */
    public function get_post()
    {
        return $this->is_simple_page() ? $this->get_queried_object() : null;
    }

    /**
     * Returns the page type for the current request.
     *
     * @return string Page type.
     */
    public function get_page_type()
    {
        if ($this->is_search()):
            $page_type = 'search_page';
        elseif ($this->is_posts_page()):
            $page_type = 'static_posts_page';
        elseif ($this->is_home_static_page()):
            $page_type = 'static_homepage';
        elseif ($this->is_home_posts_page()):
            $page_type = 'homepage';
        elseif ($this->is_simple_page()):
            $page_type = 'post_page';
        elseif ($this->is_post_type_archive()):
            $page_type = 'post_type_archive';
        elseif ($this->is_term_archive()):
            $page_type = 'term_archive';
        elseif ($this->is_author_archive()):
            $page_type = 'author_archive';
        elseif ($this->is_date_archive()):
            $page_type = 'date_archive';
        elseif ($this->is_404()):
            $page_type = 'E404_page';
        else:
            $page_type = 'none';
        endif;

        return $page_type;
    }

    /**
     * Determine whether this is the static frontpage.
     *
     * @return bool Whether or not the current page is a static frontpage.
     */
    public function is_home_static_page()
    {
        $wp_query = $this->get_main_query();

        if (!$wp_query->is_front_page()) {
            return false;
        }

        if (get_option('show_on_front') !== 'page') {
            return false;
        }

        return $wp_query->is_page(get_option('page_on_front'));
    }

    /**
     * Determine whether this is the homepage and shows posts.
     *
     * @return bool Whether or not the current page is the homepage that displays posts.
     */
    public function is_home_posts_page()
    {
        $wp_query = $this->get_main_query();

        if (!$wp_query->is_home()) {
            return false;
        }

        /*
         * Whether the static page's `Homepage` option is actually not set to a page.
         * Otherwise WordPress proceeds to handle the homepage as a `Your latest posts` page.
         */
        if ((int)get_option('page_on_front') === 0) {
            return true;
        }

        return get_option('show_on_front') === 'posts';
    }

    /**
     * Returns the id of the currently opened author archive.
     *
     * @return int The id of the currently opened author archive.
     */
    public function get_author_id()
    {
        return $this->get_main_query()->get('author');
    }

    public function get($query_var, $default = '')
    {
        return $this->get_main_query()->get($query_var, $default);
    }

    /**
     * Returns the id of the front page.
     *
     * @return int The id of the front page. 0 if the front page is not a static page.
     */
    public function get_front_page_id()
    {
        if (get_option('show_on_front') !== 'page') {
            return 0;
        }

        return (int)get_option('page_on_front');
    }

    /**
     * Returns the id of the currently opened term archive.
     *
     * @return int The id of the currently opened term archive.
     */
    public function get_term_id()
    {
        $wp_query = $this->get_main_query();

        if ($wp_query->is_category()) {
            return $wp_query->get('cat');
        }

        if ($wp_query->is_tag()) {
            return $wp_query->get('tag_id');
        }

        if ($wp_query->is_tax()) {
            $queried_object = $wp_query->get_queried_object();
            if ($queried_object and !is_wp_error($queried_object)) {
                return $queried_object->term_id;
            }
        }

        return 0;
    }

    /**
     * Returns the post type of the main query.
     *
     * @return string The post type of the main query.
     */
    public function get_queried_post_type()
    {
        $post_type = $this->get_main_query()->get('post_type');

        if (is_array($post_type)) {
            $post_type = reset($post_type);
        }

        return $post_type;
    }

    /**
     * Returns the permalink of the currently opened date archive.
     * If the permalink was cached, it returns this permalink.
     * If not, we call another function to get the permalink through wp_query.
     *
     * @return string The permalink of the currently opened date archive.
     */
    public function get_date_archive_permalink()
    {
        if ($permalink = $this->cache->get('date_archive_permalink')) {
            return $permalink;
        }

        $wp_query = $this->get_main_query();

        if ($wp_query->is_day()) {
            $permalink = get_day_link($wp_query->get('year'), $wp_query->get('monthnum'), $wp_query->get('day'));
        }
        if ($wp_query->is_month()) {
            $permalink = get_month_link($wp_query->get('year'), $wp_query->get('monthnum'));
        }
        if ($wp_query->is_year()) {
            $permalink = get_year_link($wp_query->get('year'));
        }

        $this->cache->set('date_archive_permalink', $permalink, 'cph');

        return $permalink;
    }

    /**
     * Determine whether this is an attachment page.
     *
     * @return bool Whether nor not the current page is an attachment page.
     */
    public function is_attachment()
    {
        return $this->get_main_query()->is_attachment;
    }

    /**
     * Checks if the current page is the post format archive.
     *
     * @return bool Whether or not the current page is the post format archive.
     */
    public function is_post_format_archive()
    {
        return $this->get_main_query()->is_tax('post_format');
    }

    /**
     * Determine whether this page is an taxonomy archive page for multiple terms (url: /term-1,term2/).
     *
     * @return bool Whether or not the current page is an archive page for multiple terms.
     */
    public function is_multiple_terms_page()
    {
        if (!$this->is_term_archive()) {
            return false;
        }

        return $this->count_queried_terms() > 1;
    }

    /**
     * Counts the total amount of queried terms.
     *
     * @return int The amoumt of queried terms.
     */
    protected function count_queried_terms()
    {
        $wp_query = $this->get_main_query();
        $term = $wp_query->get_queried_object();
        $queried_terms = $wp_query->tax_query->queried_terms;
        if (empty($queried_terms[$term->taxonomy]['terms'])) {
            return 0;
        }

        return count($queried_terms[$term->taxonomy]['terms']);
    }

    /**
     * Checks whether the current page is paged.
     *
     * @return bool Whether the current page is paged.
     */
    public function is_paged()
    {
        return $this->get_main_query()->is_paged();
    }

    /**
     * Retrieves the current admin page.
     *
     * @return string The current page.
     */
    public function get_current_admin_page()
    {
        global $pagenow;
        return $pagenow;
    }

    public function get_page_number()
    {
        $page = get_query_var('page');
        $page_n = get_query_var('paged');
        return !empty($page) ? $page : (!empty($page_n) ? $page_n : 1);
    }

    public function is_feed()
    {
        return $this->get_main_query()->is_feed();
    }
}
