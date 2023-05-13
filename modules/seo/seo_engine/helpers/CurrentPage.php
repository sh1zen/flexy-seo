<?php
/**
 * @author    sh1zen
 * @copyright Copyright (C) 2023.
 * @license   http://www.gnu.org/licenses/gpl.html GNU/GPL
 */

namespace FlexySEO\Engine\Helpers;

/**
 * A helper object for WordPress posts.
 */
class CurrentPage
{
    private \WP_Query $query;

    private $cache;

    /**
     * Current_Page_Helper constructor.
     */
    public function __construct(\WP_Query $main_query)
    {
        $this->cache = wps('wpfs')->cache;
        $this->query = $main_query;
    }

    public static function get_page_types(): array
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
     */
    public function get_query_type(): string
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
     */
    public function is_search(): bool
    {
        return $this->query->is_search();
    }

    public function get_query(): \WP_Query
    {
        return $this->query;
    }

    /**
     * Determine whether this is a post type archive.
     */
    public function is_post_type_archive(): bool
    {
        return $this->query->is_post_type_archive();
    }

    /**
     * Determine whether this is the statically set posts page, when it's not the frontpage.
     */
    public function is_posts_page(): bool
    {
        $wp_query = $this->query;

        if (!$wp_query->is_home()) {
            return false;
        }

        return get_option('show_on_front') === 'page';
    }

    public function is_home(): bool
    {
        return $this->query->is_home();
    }

    /**
     * Checks if the current page is the front page.
     */
    public function is_homepage(): bool
    {
        return $this->query->is_front_page();
    }

    /**
     * Checks if the currently opened page is a simple page.
     */
    public function is_simple_page(): bool
    {
        return $this->get_simple_page_id() > 0;
    }

    /**
     * Returns the id of the currently opened page.
     */
    public function get_simple_page_id(): int
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
     */
    public function is_term_archive(): bool
    {
        return ($this->query->is_tax || $this->query->is_tag || $this->query->is_category);
    }

    /**
     * Determine whether this is an author archive.
     */
    public function is_author_archive(): bool
    {
        return $this->query->is_author();
    }

    /**
     * Determine whether this is a date archive.
     */
    public function is_date_archive(): bool
    {
        return $this->query->is_date();
    }

    /**
     * Determine whether this is a 404 page.
     */
    public function is_404(): bool
    {
        return $this->query->is_404();
    }

    public function get_queried_object_id(): int
    {
        return $this->query->get_queried_object_id();
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
        return $this->query->get_queried_object();
    }

    /**
     * Retrieves an array of posts based on query variables.
     *
     * There are a few filters and actions that can be used to modify the post
     * database query.
     */
    public function get_queried_posts(): array
    {
        return $this->query->posts ?? $this->query->get_posts();
    }

    public function get_post(): ?\WP_Post
    {
        return $this->is_simple_page() ? $this->get_queried_object() : null;
    }

    /**
     * Returns the page type for the current request.
     */
    public function get_page_type(): string
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
     */
    public function is_home_static_page(): bool
    {
        if (!$this->query->is_front_page()) {
            return false;
        }

        if (get_option('show_on_front') !== 'page') {
            return false;
        }

        return $this->query->is_page(get_option('page_on_front'));
    }

    /**
     * Determine whether this is the homepage and shows posts.
     */
    public function is_home_posts_page(): bool
    {
        if (!$this->query->is_home()) {
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
     */
    public function get_author_id(): int
    {
        return $this->query->get('author');
    }

    public function get($query_var, $default = '')
    {
        return $this->query->get($query_var, $default);
    }

    /**
     * Returns the id of the front page.
     */
    public function get_front_page_id(): int
    {
        if (get_option('show_on_front') !== 'page') {
            return 0;
        }

        return (int)get_option('page_on_front');
    }

    /**
     * Returns the post type of the main query.
     */
    public function get_queried_post_type(): string
    {
        $post_type = $this->query->get('post_type');

        if (is_array($post_type)) {
            $post_type = reset($post_type);
        }

        return $post_type;
    }

    /**
     * Returns the permalink of the currently opened date archive.
     * If the permalink was cached, it returns this permalink.
     * If not, we call another function to get the permalink through wp_query.
     */
    public function get_date_archive_permalink(): string
    {
        if ($permalink = $this->cache->get('date_archive_permalink')) {
            return $permalink;
        }

        $wp_query = $this->query;

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
     */
    public function is_attachment(): bool
    {
        return $this->query->is_attachment;
    }

    /**
     * Checks if the current page is the post format archive.
     */
    public function is_post_format_archive(): bool
    {
        return $this->query->is_tax('post_format');
    }

    /**
     * Determine whether this page is a taxonomy archive page for multiple terms (url: /term-1,term2/).
     */
    public function is_multiple_terms_page(): bool
    {
        if (!$this->is_term_archive()) {
            return false;
        }

        return $this->count_queried_terms() > 1;
    }

    /**
     * Counts the total amount of queried terms.
     */
    protected function count_queried_terms(): int
    {
        $wp_query = $this->query;
        $term = $wp_query->get_queried_object();
        $queried_terms = $wp_query->tax_query->queried_terms;

        if (empty($queried_terms[$term->taxonomy]['terms'])) {
            return 0;
        }

        return count($queried_terms[$term->taxonomy]['terms']);
    }

    /**
     * Checks whether the current page is paged.
     */
    public function is_paged(): bool
    {
        return $this->query->is_paged();
    }

    /**
     * Retrieves the current admin page.
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

    public function is_feed(): bool
    {
        return $this->query->is_feed();
    }
}
