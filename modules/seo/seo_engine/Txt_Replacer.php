<?php
/**
 * @author    sh1zen
 * @copyright Copyright (C)  2021
 * @license   http://www.gnu.org/licenses/gpl.html GNU/GPL
 */

namespace FlexySEO\Engine;

use FlexySEO\core\Cache;
use FlexySEO\core\Settings;
use FlexySEO\Engine\Helpers\CurrentPage;

class Txt_Replacer
{
    public static function replace_array($string = array(), $object = null, $type = 'post')
    {
        $return_array = [];
        foreach ($string as $key => $value) {
            $return_array[$key] = self::replace($value, $object, $type);
        }

        return $return_array;
    }

    /**
     * @param string $string
     * @param \WP_Term|\WP_Post_Type|\WP_Post|\WP_User|null $object
     * @param string $type "search|post_archive|home|post|term|user|date|404|none"
     */
    public static function replace($string, $object = null, $type = 'post')
    {
        if (empty($string))
            return '';

        $rules = array();

        if (is_array($string)) {
            return self::replace_array($string, $object, $type);
        }

        $string = apply_filters('wpfs_before_replace', $string, $object, $type);

        foreach (preg_split("/[\s,;:?!]+/", $string) as $rule) {
            if (stripos($rule, '%%') !== false) {
                $rules[] = str_replace("%%", '', $rule);
            }
        }

        $rules = apply_filters('wpfs_replacer_rules', $rules, $object, $type);

        foreach ($rules as $rule) {

            $replacement = '';

            if ($replace = self::replace_custom($rule, $object, $type)) {
                $replacement = $replace;
            }
            elseif ($replace = self::replace_static($rule, $object, $type)) {
                $replacement = $replace;
            }
            elseif ($replace = get_query_var($rule, false)) {
                $replacement = $replace;
            }
            elseif ($replace = self::replace_meta($rule, $object, $type)) {
                $replacement = $replace;
            }
            elseif ($replace = self::replace_property($rule, $object, $type)) {
                $replacement = $replace;
            }
            else {
                $replace = apply_filters("wpfs_custom_replace_{$type}", $rule, $object, $type);

                if (!empty($replace) and $replace !== $rule) {
                    $replacement = $replace;
                }
            }

            $replacement = apply_filters("wpfs_replacement", $replacement, $string, $rule, $object, $type);

            $string = str_replace("%%{$rule}%%", $replacement, $string);
        }

        $escaped_string = trim(esc_html(preg_replace('/[\r\n\t ]+/', ' ', $string)));

        // filter escaped string
        return apply_filters('wpfs_after_replace', $escaped_string, $string);
    }

    /**
     * @param $rule
     * @param \WP_Term|\WP_Post_Type|\WP_Post|\WP_User|null $object
     * @param string $type
     * @return false
     */
    public static function replace_custom($rule, $object, $type = 'post')
    {
        $res = false;

        $replacer = shzn('wpfs')->cache->get_cache("{$rule}-{$type}", "Replacer");

        if ($replacer) {
            if (is_callable($replacer)) {
                $res = call_user_func($replacer, $object);

                // update the callable with its result to get more efficiency
                shzn('wpfs')->cache->force_cache("{$rule}-{$type}", $res, "Replacer");
            }
            else {
                $res = $replacer;
            }
        }

        return $res;
    }

    /**
     * @param $rule
     * @param \WP_Term|\WP_Post_Type|\WP_Post|\WP_User|null $object
     * @param string $type
     * @return false
     */
    public static function replace_static($rule, $object = null, $type = 'post')
    {
        $res = false;

        $wp_query = $GLOBALS['wp_the_query'];

        switch ($rule) {
            case 'sep':
                $res = shzn('wpfs')->settings->get('seo.title.separator', '-');
                break;

            case 'sitename':
                $res = get_bloginfo('name');
                break;

            case 'resume':
                $res = $object->ID ? wp_trim_words(get_the_excerpt($object->ID), 32, '...') : '';
                break;

            case 'excerpt':
                $res = $object->ID ? get_the_excerpt($object->ID) : '';
                break;

            case 'title':
                $res = wp_get_document_title();
                break;

            case 'sitedesc':
                $res = get_bloginfo('description');
                break;

            case 'language':
                $res = get_bloginfo('language');
                break;

            case 'date':
                $res = wp_date('Y-m-d');
                break;

            case 'modified':
                if ($type === 'post')
                    $res = $object->post_modified;
                break;

            case 'created':
                if ($type === 'post')
                    $res = $object->post_date;
                break;

            case 'found_post':
                $res = $wp_query->found_posts;
                break;

            case 'pagenumber':
                if ($type === 'post')
                    $res = $wp_query->get('paged', 0);
                break;

            case 'pagetotal':
                $res = $wp_query->max_num_pages;
                break;
        }

        return $res;
    }

    /**
     * @param $rule
     * @param \WP_Term|\WP_Post_Type|\WP_Post|\WP_User|null $object
     * @param string $type
     * @return false
     */
    public static function replace_meta($rule, $object = null, $type = 'post')
    {
        if (!$object or substr($rule, 0, 4) !== "meta")
            return false;

        $meta = str_replace("meta_", "", $rule);

        return get_metadata_raw($type, $object->ID, $meta, true);
    }

    /**
     * @param $rule
     * @param \WP_Term|\WP_Post_Type|\WP_Post|\WP_User|null $object
     * @param string $type
     * @return false
     */
    public static function replace_property($rule, $object = null, $type = 'post')
    {
        if (!$object)
            return false;

        if (isset($object->$rule))
            return $object->$rule;

        return false;
    }

    /**
     * Add a custom replacement rule with query type support
     *
     * @param string $rule The rule ex. `%%custom_replace%%`
     * @param String|callable $replacement
     * @param string|string[] $type
     */
    public static function add_replacer($rule, $replacement, $type = [])
    {
        if (empty($type)) {
            $type = CurrentPage::get_page_types();
        }

        if (is_array($type)) {
            foreach ($type as $_type) {
                self::add_replacer($rule, $replacement, $_type);
            }
            return;
        }

        shzn('wpfs')->cache->set_cache("{$rule}-{$type}", $replacement, "Replacer");
    }

    /**
     * @param $string
     * @param int $length
     * @param string $append
     * @return string
     */
    private static function truncate($string, $length = 100, $append = "")
    {
        $string = trim($string);

        if (strlen($string) > $length) {
            $string = wordwrap($string, $length);
            $string = explode("\n", $string, 2);
            $string = $string[0] . $append;
        }

        return $string;
    }
}