<?php
/**
 * @author    sh1zen
 * @copyright Copyright (C)  2022
 * @license   http://www.gnu.org/licenses/gpl.html GNU/GPL
 */

namespace FlexySEO\Engine\Generators\Templates;

use FlexySEO\Engine\Generator;
use FlexySEO\Engine\Generators\OpenGraph;
use FlexySEO\Engine\Helpers\CurrentPage;

class PostType_Generator extends Generator
{
    /**
     * @param CurrentPage $current_page
     */
    public function __construct($current_page)
    {
        parent::__construct($current_page);

        $type = get_post_type();

        if (!$type) {
            $type = 'none';
        }

        $this->settings_path = "seo.post_type.{$type}.";
    }

    public function redirect()
    {
        if ($this->current_page->is_attachment()) {
            if (!shzn('wpfs')->settings->get('seo.media.rewrite_url', false)) {
                return parent::redirect();
            }

            $url = $this->get_attachment_url();

            if (empty($url)) {
                return parent::redirect();
            }

            return array($url, 301);
        }
        else {
            return parent::redirect();
        }
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
         *
         * @since 1.0.0
         */
        return apply_filters(
            'wpfs_template_redirect_url',
            wp_get_attachment_url($this->current_page->get_queried_object_id()),
            $this->current_page->get_queried_object()
        );
    }

    /**
     * Generates the robots value.
     *
     * @param array $robots
     * @return array The robots value.
     */
    public function get_robots($robots = [])
    {
        return [
            'index'             => shzn('wpfs')->settings->get($this->settings_path . 'show', true) ? 'index' : 'noindex',
            'follow'            => shzn('wpfs')->settings->get($this->settings_path . 'follow', true) ? 'follow' : 'nofollow',
            'max-snippet'       => 'max-snippet:-1',
            'max-image-preview' => 'max-image-preview:large',
        ];
    }

    /**
     * Generates the meta keywords.
     *
     * @param string $keywords
     * @return string The meta keywords.
     */
    public function get_keywords(string $keywords = '')
    {
        return parent::get_keywords(shzn('wpfs')->settings->get($this->settings_path . 'keywords', ''));
    }

    /**
     * Generates the title structure.
     *
     * @param string $title
     * @return string The title.
     */
    public function generate_title($title = '')
    {
        return parent::generate_title(shzn('wpfs')->settings->get($this->settings_path . 'title', '%%title%%'));
    }

    /**
     * @return OpenGraph
     */
    public function openGraph()
    {
        $object = $this->current_page->get_queried_object();
        $og = parent::openGraph();

        $og->type('article');

        $og->article([
            'published_time' => wp_date("Y-m-d", strtotime($object->post_date)),
            'modified_time'  => wp_date("Y-m-d", strtotime($object->post_modified)),
            'author'         => get_author_posts_url($object->post_author),
        ]);

        return $og;
    }

    /**
     * Generates the title structure.
     *
     * @param string $description
     * @return string The meta description.
     */
    public function get_description(string $description = '')
    {
        return parent::get_description(shzn('wpfs')->settings->get($this->settings_path . 'meta_desc', ''));
    }


}