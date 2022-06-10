<?php
/**
 * @author    sh1zen
 * @copyright Copyright (C)  2022
 * @license   http://www.gnu.org/licenses/gpl.html GNU/GPL
 */

namespace FlexySEO\Engine\Helpers;

class Helpers
{
    private static $_Instance;

    /**
     * @var \WP_Query
     */
    public $main_query;

    /**
     * @var \FlexySEO\Engine\Helpers\StringHelper
     */
    public $string;

    /**
     * @var \FlexySEO\Engine\Helpers\Images
     */
    public $images;

    /**
     * @var \FlexySEO\Engine\Helpers\currentPage
     */
    public $currentPage;

    /**
     * @var \FlexySEO\Engine\Helpers\Post
     */
    public $post;

    /**
     * @var \FlexySEO\Engine\Helpers\Term
     */
    public $term;

    /**
     * @var \FlexySEO\Engine\Helpers\ECommerce
     */
    public $ecommerce;

    /**
     * @var \FlexySEO\Engine\Helpers\Language
     */
    public $language;

    /**
     * @var \SHZN\core\Cache
     */
    private $cache;

    /**
     * @param \WP_Query $main_query
     */
    private function __construct($main_query)
    {
        $this->main_query = $main_query;

        $this->cache = shzn('wpfs')->cache;

        $this->string = new StringHelper();

        $this->language = new Language();

        $this->currentPage = new CurrentPage($main_query);

        $this->post = new Post($this->currentPage->get_post());

        $this->term = new Term($this->currentPage->get_term());

        $this->ecommerce = new ECommerce();

        $this->images = new Images();
    }

    /**
     * @return Helpers|null
     */
    public static function get_Instance()
    {
        if (!isset(self::$_Instance)) {
            return null;
        }

        return self::$_Instance;
    }

    /**
     * @param \WP_Query $main_query
     * @return \FlexySEO\Engine\Helpers\Helpers
     */
    public static function Init($main_query = null)
    {
        return self::$_Instance = new self($main_query);
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
     *
     * @return bool Wether the request is an AJAX, CRON or REST request.
     */
    public function isAjaxCronRest()
    {
        return wp_doing_ajax() || wp_doing_cron() || $this->isRestApiRequest();
    }

    /**
     * Returns true if the request is a non-legacy REST API request.
     * This function was copied from WooCommerce and improved.
     *
     * @return bool True if this is a REST API request.
     *
     */
    public function isRestApiRequest()
    {
        if (empty($_SERVER['REQUEST_URI'])) {
            return false;
        }

        $restUrl = wp_parse_url(get_rest_url());
        $restUrl = $restUrl['path'] . (!empty($restUrl['query']) ? '?' . $restUrl['query'] : '');

        return (str_starts_with($_SERVER['REQUEST_URI'], $restUrl));
    }

    public function get_user_snippet_image($userID, $size)
    {
        $url = get_avatar_url($userID);

        if (empty($url)) {
            $url = "https://secure.gravatar.com/avatar/cb5febbf69fa9e85698bac992b2a4433?s=500&d=mm&r=g";
        }

        $url = apply_filters("wpfs_author_avatar", $url, $userID);

        $snippet_data = shzn('wpfs')->options->get($url, "snippet_data", "cache", false);

        if (!$snippet_data) {

            $snippet_data = wpfseo()->images->get_snippet_data($url, $size);

            shzn('wpfs')->options->add($url, "snippet_data", $snippet_data, "cache", WEEK_IN_SECONDS);
        }

        return $snippet_data;
    }
}