<?php
/**
 * @author    sh1zen
 * @copyright Copyright (C) 2023.
 * @license   http://www.gnu.org/licenses/gpl.html GNU/GPL
 */

namespace FlexySEO\Engine;

use FlexySEO\Engine\Helpers\Helpers;
use FlexySEO\Engine\Helpers\XRE_MetaBox;

if (!defined('WPFS_SEO_ENGINE')) {
    define('WPFS_SEO_ENGINE', __DIR__ . '/seo_engine/');
}

class WPFS_SEO
{
    private static ?WPFS_SEO $_instance;

    public ?Helpers $helpers = null;

    public ?XRE_MetaBox $metaBox = null;

    public ?Generator $generator = null;

    /**
     * Create the breadcrumb
     */
    private function __construct()
    {
        if (is_admin()) {
            $this->metaBox = new XRE_MetaBox();
        }

        $this->register_actions();
    }

    private function register_actions()
    {
        if (!is_admin()) {

            add_action('wp', array($this, 'set_up'), 1);

            remove_action('wp_head', 'rel_canonical');
            remove_action('wp_head', 'index_rel_link');
            remove_action('wp_head', 'start_post_rel_link');
            remove_action('wp_head', 'adjacent_posts_rel_link_wp_head');
            remove_action('wp_head', 'noindex', 1);
            remove_action('wp_head', '_wp_render_title_tag', 1);
            remove_action('wp_head', 'gutenberg_render_title_tag', 1);
            remove_action('wp_head', 'wp_robots', 1);

            remove_filter('wp_robots', 'wp_robots_noindex');
            remove_filter('wp_robots', 'wp_robots_noindex_embeds');
            remove_filter('wp_robots', 'wp_robots_noindex_search');
            remove_filter('wp_robots', 'wp_robots_max_image_preview_large');
        }
    }

    /**
     * @return \FlexySEO\Engine\WPFS_SEO|null
     */
    public static function getInstance()
    {
        return self::$_instance;
    }

    /**
     * @return \FlexySEO\Engine\WPFS_SEO
     */
    public static function Init()
    {
        if (!isset(self::$_instance)) {

            require_once WPFS_SEO_ENGINE . 'loader.php';

            self::$_instance = new self();
        }

        return self::$_instance;
    }

    public function set_up($wp_query)
    {
        $this->helpers = Helpers::Init($wp_query);

        $this->generator = new Generator($this->helpers->currentPage);

        $this->generator->load_template();

        $presenter = new Presenter($this->generator);

        $presenter->build();
    }
}

