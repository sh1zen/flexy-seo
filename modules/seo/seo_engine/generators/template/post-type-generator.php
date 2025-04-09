<?php
/**
 * @author    sh1zen
 * @copyright Copyright (C) 2025.
 * @license   http://www.gnu.org/licenses/gpl.html GNU/GPL
 */

namespace FlexySEO\Engine\Generators\Templates;

use FlexySEO\Engine\Default_Generator;
use FlexySEO\Engine\Generators\OpenGraph;
use FlexySEO\Engine\Helpers\CurrentPage;

class PostType_Generator extends Default_Generator
{
    public function __construct(CurrentPage $current_page)
    {
        parent::__construct($current_page);

        $type = get_post_type();

        if (!$type) {
            $type = 'none';
        }

        $this->settings_path = "seo.post_type.$type.";
    }

    public function redirect(): array
    {
        if ($this->current_page->is_attachment()) {

            if (!wps('wpfs')->settings->get('seo.media.rewrite_url', false)) {
                return parent::redirect();
            }

            $url = $this->get_attachment_url();

            if (empty($url)) {
                return parent::redirect();
            }

            return array($url, 301);
        }

        return parent::redirect();
    }

    /**
     * Retrieves the attachment url for the current page.
     */
    private function get_attachment_url(): string
    {
        /**
         * Allows the developer to change the target redirection URL for attachments.
         *
         * @api string $attachment_url The attachment URL for the queried object.
         * @api object $queried_object The queried object.
         */
        return apply_filters(
            'wpfs_template_redirect_url',
            wp_get_attachment_url($this->current_page->get_queried_object_id()),
            $this->current_page->get_queried_object()
        );
    }

    public function get_robots(): array
    {
        return [
            'index'  => wps('wpfs')->settings->get($this->settings_path . 'show', true) ? 'index' : 'noindex',
            'follow' => wps('wpfs')->settings->get($this->settings_path . 'follow', true) ? 'follow' : 'nofollow'
        ];
    }

    public function get_keywords(): string
    {
        $custom_keywords = wpfs_get_post_meta_keywords($this->current_page->get_queried_object(), false, '');

        if (!empty($custom_keywords)) {
            return $custom_keywords;
        }

        return wps('wpfs')->settings->get($this->settings_path . 'keywords', '');
    }

    public function openGraph(OpenGraph $og): OpenGraph
    {
        $object = $this->current_page->get_queried_object();

        $og->type('article');

        $og->article([
            'published_time' => wp_date("Y-m-d", strtotime($object->post_date)),
            'modified_time'  => wp_date("Y-m-d", strtotime($object->post_modified)),
            'author'         => get_author_posts_url($object->post_author),
        ]);

        return $og;
    }

    public function get_description(): string
    {
        $custom_description = wpfs_get_post_meta_description($this->current_page->get_queried_object(), false, '');

        if (!empty($custom_description)) {
            return $custom_description;
        }

        return wps('wpfs')->settings->get($this->settings_path . 'meta_desc', '%%description%%');
    }
}