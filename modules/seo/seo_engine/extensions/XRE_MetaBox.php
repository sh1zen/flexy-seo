<?php
/**
 * @author    sh1zen
 * @copyright Copyright (C)  2022
 * @license   http://www.gnu.org/licenses/gpl.html GNU/GPL
 */

namespace FlexySEO\Engine\Helpers;

use FlexySEO\Engine\Generators\Schema;

class XRE_MetaBox
{
    public $loaded = false;

    public function __construct()
    {
        if (shzn('wpfs')->settings->get("seo.addon.xre_metaboxe", true)) {
            add_action('add_meta_boxes', [$this, 'add']);
            add_action('save_post', [$this, 'save'], 10, 3);
            $this->loaded = true;
        }
    }

    public static function get_value($id, $item, $context, $default = false)
    {
        return shzn('wpfs')->options->get($id, $item, $context, $default);
    }

    public function enqueue_scripts()
    {
        wp_enqueue_style('vendor-shzn-css');
    }

    /**
     * Set up and add the meta box.
     */
    public function add()
    {
        add_meta_box(
            'wpfs_metabox',
            'Flexy SEO',
            [$this, 'print_html'],
            get_post_types(['public' => true]),
            'normal',
            'core'
        );
    }

    /**
     * Save the meta box selections.
     *
     * @param int $post_id The post ID.
     */
    public function save($post_id, $post, $update)
    {
        if (!isset($_POST['wpfs_metabox']))
            return;

        $metas = array_filter($_POST['wpfs_metabox']);

        foreach ($this->fields($post) as $field_meta) {
            $meta_name = $field_meta['name'];

            if (empty($metas[$meta_name])) {
                shzn('wpfs')->options->remove($post_id, $meta_name, "customMeta");
                continue;
            }

            $value = $metas[$meta_name];

            if (isset($field_meta['sanitize_callback']) and is_callable($field_meta['sanitize_callback'])) {
                $value = call_user_func($field_meta['sanitize_callback'], $value);
            }

            if (empty($value)) {
                shzn('wpfs')->options->remove($post_id, $meta_name, "customMeta");
            }
            else {
                shzn('wpfs')->options->update($post_id, $meta_name, $value, "customMeta");
            }
        }
    }

    private function fields($post)
    {
        $keyword = $description = "";
        $post_id = 0;

        if (!empty($post) and $post->ID) {
            $post_id = $post->ID;
            $keyword = shzn('wpfs')->options->get($post_id, "keywords", "customMeta", "");
            $description = shzn('wpfs')->options->get($post_id, "description", "customMeta", "");
        }

        $supportedPageGraphs = array_map(function ($graph) {
            return ['text' => $graph, 'value' => $graph];
        }, Schema::$webPageGraphs);

        $supportedArticleGraphs = array_map(function ($graph) {
            return ['text' => $graph, 'value' => $graph];
        }, Schema::$webArticleGraphs);

        return [
            [
                'name'              => 'keywords',
                'label'             => __('Keyword phrase', 'wpfs'),
                'type'              => 'textarea',
                'sanitize_callback' => [$this, 'sanitize_text'],
                'values'            => [['value' => $keyword]]
            ],
            [
                'name'              => 'description',
                'label'             => __('Description', 'wpfs'),
                'type'              => 'textarea',
                'sanitize_callback' => [$this, 'sanitize_text'],
                'values'            => [['value' => $description]]
            ],
            [
                'name'              => 'graphPageType',
                'label'             => __('Page type for Schema.org', 'wpfs'),
                'type'              => 'select',
                'sanitize_callback' => [$this, 'sanitize_text'],
                'values'            => $supportedPageGraphs,
                'value'             => shzn('wpfs')->options->get($post_id, "graphPageType", "customMeta")
            ],
            [
                'name'              => 'graphArticleType',
                'label'             => __('Article type for Schema.org', 'wpfs'),
                'type'              => 'select',
                'sanitize_callback' => 'sanitize_text_field',
                'values'            => $supportedArticleGraphs,
                'value'             => shzn('wpfs')->options->get($post_id, "graphArticleType", "customMeta")
            ],
        ];
    }

    public function sanitize_text($str)
    {
        $str = (string)$str;

        $filtered = wp_check_invalid_utf8($str);

        $filtered = htmlspecialchars($filtered, ENT_QUOTES);

        return trim($filtered);
    }

    /**
     * Display the meta box HTML to the user.
     *
     * @param \WP_Post $post Post object.
     */
    public function print_html($post)
    {
        ?>
        <style>
            .wpfs {
                width: 100%;
            }

            .wpfs_label {
                width: 100%;
            }

            .wpfs_option {
                width: 100%;
            }

            .wpfs_input {
                width: 100%;
            }
        </style>
        <section>
            <?php
            foreach ($this->fields($post) as $field) {

                echo "<h3 class='wpfs_label'>{$field['label']}</h3>";
                echo "<section class='wpfs'>";

                $values = [];

                foreach ($field['values'] as $value) {

                    $values[] = array_merge([
                        'type'    => 'option',
                        'value'   => '',
                        'checked' => false,
                        'text'    => false
                    ], $value);
                }

                switch ($field['type']) {
                    case 'select':
                        echo "<select name='wpfs_metabox[{$field['name']}]' class='wpfs_option'>";
                        foreach ($values as $value) {
                            echo "<{$value['type']} value='{$value['value']}' " . ($value['value'] === $field['value'] ? 'selected' : '') . " {$value['checked']}>{$value['text']}</{$value['type']}>";
                        }
                        echo "</select>";
                        break;

                    case 'textarea':
                        foreach ($values as $index => $value) {

                            if ($value['text']) {
                                echo "<label class='wpfs_label' for='{$field['name']}_{$index}}'>{$value['text']}</label>";
                            }

                            echo "<textarea autocomplete='off' class='wpfs_input' name='wpfs_metabox[{$field['name']}]' value='{$value['value']}' id='{$field['name']}_{$index}'>{$value['value']}</textarea>";
                        }
                        break;

                    default:
                    {
                        foreach ($values as $index => $value) {
                            if ($value['text']) {
                                echo "<label class='wpfs_label' for='{$field['name']}_{$index}}'>{$value['text']}</label>";
                            }

                            $checked = $value['checked'] ? 'checked' : '';
                            echo "<input autocomplete='off' type='{$field['type']}' class='wpfs_input' name='wpfs_metabox[{$field['name']}]' value='{$value['value']}' id='{$field['name']}_{$index}' {$checked}>";
                        }
                    }
                }
                echo "</section>";
            }
            ?>
        </section>
        <?php
    }
}


