<?php
/**
 * @author    sh1zen
 * @copyright Copyright (C) 2023.
 * @license   http://www.gnu.org/licenses/gpl.html GNU/GPL
 */

namespace FlexySEO\Engine\Helpers;

use WPS\core\Cache;

class Helpers
{
    public \WP_Query $main_query;

    public Images $images;

    public CurrentPage $currentPage;

    public Post $post;

    public Term $term;

    public ECommerce $ecommerce;

    public Language $language;

    private Cache $cache;

    public function __construct(\WP_Query $main_query = null)
    {
        $this->main_query = $main_query;

        $this->cache = wps('wpfs')->cache;

        $this->language = new Language();

        $this->currentPage = new CurrentPage($main_query);

        $this->post = new Post($this->currentPage->get_post());

        $this->term = new Term($this->currentPage->get_term());

        $this->ecommerce = new ECommerce();

        $this->images = new Images();
    }

    public function __get($key)
    {
        return $this->cache->get($key, 'wpfs_helpers');
    }

    public function __set($key, $data)
    {
        return $this->cache->set($key, $data, 'wpfs_helpers', true);
    }

    /**
     * Checks whether the current request is an AJAX, CRON or REST request.
     */
    public function isAjaxCronRest(): bool
    {
        return wp_doing_ajax() || wp_doing_cron() || $this->isRestApiRequest();
    }

    /**
     * Returns true if the request is a non-legacy REST API request.
     * This function was copied from WooCommerce and improved.
     */
    public function isRestApiRequest(): bool
    {
        if (empty($_SERVER['REQUEST_URI'])) {
            return false;
        }

        $restUrl = wp_parse_url(get_rest_url());
        $restUrl = $restUrl['path'] . (!empty($restUrl['query']) ? '?' . $restUrl['query'] : '');

        return (str_starts_with($_SERVER['REQUEST_URI'], $restUrl));
    }
}