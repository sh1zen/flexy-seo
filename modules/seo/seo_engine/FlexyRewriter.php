<?php
/**
 * @author    sh1zen
 * @copyright Copyright (C) 2023.
 * @license   http://www.gnu.org/licenses/gpl.html GNU/GPL
 */

namespace FlexySEO\Engine;

class FlexyRewriter
{
    private static FlexyRewriter $_instance;

    private $rules;

    private Helpers\CurrentPage $current_page;

    /**
     * Sets the helpers.
     * @param array $rules
     */
    private function __construct($rules = array())
    {
        $this->rules = $rules;

        $this->current_page = wpfseo()->currentPage;

        $this->register_hooks();
    }

    /**
     * Initializes the integration.
     *
     * This is the place to register hooks and filters.
     *
     * @return void
     */
    public function register_hooks()
    {
        add_action('wp', [$this, 'static_redirects']);
        //add_action('template_redirect', [$this, 'template_redirect'], 1);
    }

    public static function getInstance(): FlexyRewriter
    {
        if (!isset(self::$_instance)) {
            self::$_instance = new self();
        }

        return self::$_instance;
    }

    public function get_pagenum_link($pagenum = 1, $escape = true)
    {
        global $wp_rewrite;

        $pagenum = (int)$pagenum;

        $request = remove_query_arg('paged');

        $home_root = parse_url(shzn_utils()->home_url);
        $home_root = (isset($home_root['path'])) ? $home_root['path'] : '';
        $home_root = preg_quote($home_root, '|');

        $request = preg_replace('|^' . $home_root . '|i', '', $request);
        //$request = preg_replace('|^/+|', '', $request);
        $request = ltrim($request, "/");

        if (!$wp_rewrite->using_permalinks() || is_admin()) {
            $base = trailingslashit(get_bloginfo('url'));

            if ($pagenum > 1) {
                $result = add_query_arg('paged', $pagenum, $base . $request);
            }
            else {
                $result = $base . $request;
            }
        }
        else {
            $qs_regex = '|\?.*?$|';
            preg_match($qs_regex, $request, $qs_match);

            if (!empty($qs_match[0])) {
                $query_string = $qs_match[0];
                $request = preg_replace($qs_regex, '', $request);
            }
            else {
                $query_string = '';
            }

            $request = preg_replace("|$wp_rewrite->pagination_base/\d+/?$|", '', $request);
            $request = preg_replace('|^' . preg_quote($wp_rewrite->index, '|') . '|i', '', $request);
            $request = ltrim($request, '/');

            $base = trailingslashit(get_bloginfo('url'));

            if ($wp_rewrite->using_index_permalinks() && ($pagenum > 1 || '' !== $request)) {
                $base .= $wp_rewrite->index . '/';
            }

            if ($pagenum > 1) {
                $request = ((!empty($request)) ? trailingslashit($request) : $request) . user_trailingslashit($wp_rewrite->pagination_base . '/' . $pagenum, 'paged');
            }

            $result = $base . $request . $query_string;
        }

        if ($escape) {
            return esc_url($result);
        }
        else {
            return esc_url_raw($result);
        }
    }

    /**
     * When certain archives are disabled, this redirects those to the homepage.
     */
    public function static_redirects()
    {
        if (!shzn('wpfs')->settings->get('seo.archives.date.active', true) and $this->current_page->is_date_archive()) {
            shzn_utils()->rewriter->redirect('/');
        }

        if (!shzn('wpfs')->settings->get('seo.archives.author.active', true) and $this->current_page->is_author_archive()) {
            shzn_utils()->rewriter->redirect('/');
        }

        if (!shzn('wpfs')->settings->get('seo.tax.post_format.active', true) and $this->current_page->is_post_format_archive()) {
            shzn_utils()->rewriter->redirect('/');
        }

        if (!shzn('wpfs')->settings->get('seo.tax.post_format.active', true) and $this->current_page->is_post_format_archive()) {
            shzn_utils()->rewriter->redirect('/');
        }

        /**
         * Based on the redirect meta value, this function determines whether it should redirect the current post / page.
         */
        if ($this->current_page->is_simple_page()) {

            $post = get_post();
            if (!is_object($post)) {
                return;
            }

            if (!$redirect = $this->redirect_rule($post->ID)) {
                return;
            }

            shzn_utils()->rewriter->redirect($redirect->url ?: '/', $redirect->status);
        }
    }

    private function redirect_rule($object_id, $type = 'post')
    {
        if (!isset($this->rules[$type][$object_id]))
            return false;

        return (object)$this->rules[$type][$object_id];
    }

    public function add_rule($object_id, $url, $status = 301, $type = 'post')
    {

        $this->rules[$type][$object_id] = (object)array('url' => $url, 'status' => $status);
    }

    public function remove_rule($object_id, $type = 'post')
    {
        unset($this->rules[$type][$object_id]);
    }


    /**
     * If the option to disable attachment URLs is checked, this performs the redirect to the attachment.
     */
    public function template_redirect()
    {
        if (!$this->current_page->is_attachment()) {
            return;
        }

        if (!shzn('wpfs')->settings->get('seo.media.rewrite_url', false)) {
            return;
        }

        $url = $this->get_attachment_url();

        if (empty($url)) {
            return;
        }

        shzn_utils()->rewriter->redirect($url);
    }

    /**
     * Retrieves the attachment url for the current page.
     *
     * @return string The attachment url.
     */
    private function get_attachment_url()
    {
        /**
         * Allows the developer to change the target redirection URL for attachments.
         *
         * @api string $attachment_url The attachment URL for the queried object.
         * @api object $queried_object The queried object.
         */
        return \apply_filters('wpfs_template_redirect_url', wp_get_attachment_url($this->current_page->get_queried_object_id()), $this->current_page->get_queried_object());
    }
}
