<?php
/**
 * @author    sh1zen
 * @copyright Copyright (C) 2025.
 * @license   http://www.gnu.org/licenses/gpl.html GNU/GPL
 */

namespace FlexySEO\Engine\Helpers;

class ImageSEO
{
    public static function register_frontend_hooks(): void
    {
        if (!wps('wpfs')->settings->get('seo.image.alt_generation', true)) {
            return;
        }

        add_filter('the_content', [self::class, 'fill_missing_content_alts'], 20);
        add_filter('post_thumbnail_html', [self::class, 'fill_missing_content_alts'], 20);
        add_filter('wp_get_attachment_image_attributes', [self::class, 'fill_missing_attachment_alt'], 20, 3);
    }

    public static function fill_missing_content_alts(string $html): string
    {
        if (!str_contains($html, '<img')) {
            return $html;
        }

        $alt = self::generate_alt_text();

        if ($alt === '') {
            return $html;
        }

        return preg_replace_callback('#<img\b[^>]*>#i', static function ($matches) use ($alt) {
            $tag = $matches[0];

            if (preg_match('#\salt\s*=\s*(["\'])(.*?)\1#i', $tag, $alt_match)) {
                if (trim(wp_strip_all_tags(html_entity_decode($alt_match[2], ENT_QUOTES | ENT_HTML5, get_bloginfo('charset') ?: 'UTF-8'))) !== '') {
                    return $tag;
                }

                return preg_replace('#\salt\s*=\s*(["\'])(.*?)\1#i', ' alt="' . esc_attr($alt) . '"', $tag, 1);
            }

            if (preg_match('#\salt\s*=\s*([^\s>]+)#i', $tag, $alt_match)) {
                if (trim(wp_strip_all_tags(html_entity_decode($alt_match[1], ENT_QUOTES | ENT_HTML5, get_bloginfo('charset') ?: 'UTF-8'))) !== '') {
                    return $tag;
                }

                return preg_replace('#\salt\s*=\s*([^\s>]+)#i', ' alt="' . esc_attr($alt) . '"', $tag, 1);
            }

            return preg_replace('#<img\b#i', '<img alt="' . esc_attr($alt) . '"', $tag, 1);
        }, $html);
    }

    public static function fill_missing_attachment_alt(array $attr, \WP_Post $attachment, $size): array
    {
        if (!empty($attr['alt'])) {
            return $attr;
        }

        $context = $attachment->post_parent ? get_post($attachment->post_parent) : null;
        $attr['alt'] = self::generate_alt_text($context ?: $attachment);

        return $attr;
    }

    public static function generate_alt_text($context = null): string
    {
        if (!$context) {
            $loop_post = get_post();
            $context = (function_exists('in_the_loop') && in_the_loop() && $loop_post instanceof \WP_Post) ? $loop_post : get_queried_object();
        }

        $title = '';
        $category = '';

        if ($context instanceof \WP_Post) {
            $title = get_the_title($context);

            $categories = get_the_category($context->ID);
            if (!empty($categories) && !is_wp_error($categories)) {
                $category = $categories[0]->name;
            }
        }
        elseif ($context instanceof \WP_Term) {
            $title = $context->name;
            $taxonomy = get_taxonomy($context->taxonomy);
            $category = $taxonomy->labels->singular_name ?? '';
        }

        if ($title === '') {
            $title = get_bloginfo('name', 'display');
        }

        $template = wps('wpfs')->settings->get('seo.image.alt_template', '%%title%% %%category%%');
        $alt = str_replace(
            ['%%title%%', '%%category%%', '%%sitename%%'],
            [$title, $category, get_bloginfo('name', 'display')],
            $template
        );

        $alt = html_entity_decode($alt, ENT_QUOTES | ENT_HTML5, get_bloginfo('charset') ?: 'UTF-8');
        $alt = wp_strip_all_tags($alt, true);
        $alt = preg_replace('/%%[^%]+%%/', '', $alt);
        $alt = preg_replace('/\s+/u', ' ', $alt);
        $alt = trim($alt);

        return apply_filters('wpfs_image_alt_text', $alt, $context);
    }

    public static function get_smart_open_graph_image(CurrentPage $current_page, $size = 'large'): string
    {
        $url = '';
        $sizes = is_array($size) ? $size : [$size];

        if ($current_page->is_term_archive()) {
            $url = self::get_term_image_url($current_page->get_term(), $sizes);
        }

        if ($url === '') {
            foreach ($current_page->get_queried_posts() as $post) {
                if (!$post instanceof \WP_Post) {
                    continue;
                }

                $main_image = wps_get_mainImage($post, $size, true);
                if (!empty($main_image[1])) {
                    $url = $main_image[1];
                    break;
                }
            }
        }

        if ($url === '' && function_exists('has_site_icon') && has_site_icon()) {
            $url = get_site_icon_url(512);
        }

        if ($url === '' && function_exists('get_theme_mod')) {
            $logo_id = (int)get_theme_mod('custom_logo');
            if ($logo_id) {
                foreach ($sizes as $preferred_size) {
                    $image = wp_get_attachment_image_src($logo_id, $preferred_size);
                    if (!empty($image[0])) {
                        $url = $image[0];
                        break;
                    }
                }
            }
        }

        return apply_filters('wpfs_smart_og_image_fallback', $url, $current_page, $size);
    }

    private static function get_term_image_url($term, array $sizes): string
    {
        if (!$term instanceof \WP_Term) {
            return '';
        }

        $meta_keys = [
            'thumbnail_id',
            'image_id',
            'category_image_id',
            'product_cat_thumbnail_id',
        ];

        foreach ($meta_keys as $meta_key) {
            $attachment_id = (int)get_term_meta($term->term_id, $meta_key, true);
            if (!$attachment_id) {
                continue;
            }

            foreach ($sizes as $size) {
                $image = wp_get_attachment_image_src($attachment_id, $size);
                if (!empty($image[0])) {
                    return $image[0];
                }
            }
        }

        foreach (['image', 'category_image', 'wpfs_term_image'] as $meta_key) {
            $url = (string)get_term_meta($term->term_id, $meta_key, true);
            if ($url && filter_var($url, FILTER_VALIDATE_URL)) {
                return $url;
            }
        }

        return '';
    }
}
