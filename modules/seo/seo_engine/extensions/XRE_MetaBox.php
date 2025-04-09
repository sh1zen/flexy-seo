<?php
/**
 * @author    sh1zen
 * @copyright Copyright (C) 2025.
 * @license   http://www.gnu.org/licenses/gpl.html GNU/GPL
 */

namespace FlexySEO\Engine\Helpers;

use FlexySEO\Engine\Generators\Schema;
use WPS\core\StringHelper;

class XRE_MetaBox
{
    static string $context = 'customMeta';

    private static XRE_MetaBox $Instance;

    private function __construct()
    {
        if (wps('wpfs')->settings->get("seo.addon.xre_metaboxe", true)) {
            add_action('add_meta_boxes', [$this, 'add']);
            add_action('save_post', [$this, 'save'], 10, 3);
        }
    }

    public static function Init(): void
    {
        if (isset(self::$Instance)) {
            return;
        }

        self::$Instance = new self();
    }

    public function enqueue_scripts(): void
    {
        wp_enqueue_style('vendor-wps-css');
    }

    /**
     * Set up and add the meta box.
     */
    public function add(): void
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
     */
    public function save($post_id, $post, $update): void
    {
        // not updating revision
        if (!isset($_POST['wpfs_metabox']) or $post->post_type == 'revision') {
            return;
        }

        $metas = array_filter($_POST['wpfs_metabox']);

        foreach ($this->fields($post) as $field_meta) {

            $meta_name = $field_meta['name'];

            $value = $metas[$meta_name];

            if ($value == self::get_default_GraphType($post->post_type)) {
                $value = '';
            }

            if (!empty($value) and isset($field_meta['sanitize_callback']) and is_callable($field_meta['sanitize_callback'])) {
                $value = call_user_func($field_meta['sanitize_callback'], $value);
            }

            if (empty($value)) {
                wps('wpfs')->options->remove($post_id, $meta_name, self::$context);
            }
            else {
                wps('wpfs')->options->update($post_id, $meta_name, $value, self::$context);
            }
        }
    }

    private function fields($post): array
    {
        $keyword = $description = "";

        if (!empty($post) and $post->ID) {
            $keyword = wpfs_get_post_meta_keywords($post, true);
            $description = wpfs_get_post_meta_description($post, true);
        }

        $supportedGraphs = array_map(function ($graph) {
            return ['text' => $graph, 'value' => $graph];
        }, array_merge(Schema::$webArticleGraphs, Schema::$webPageGraphs));

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
                'name'   => 'graphType',
                'label'  => __('Schema.org graph type', 'wpfs'),
                'type'   => 'select',
                'values' => $supportedGraphs,
                'value'  => self::get_value($post->ID, 'graphType', self::get_default_GraphType($post->post_type))
            ]
        ];
    }

    public static function get_value($id, $item, $default = false)
    {
        return wps('wpfs')->options->get($id, $item, self::$context, $default);
    }

    public static function get_default_GraphType($post_type): string
    {
        if (!empty($post_type)) {

            $for = $post_type === 'page' ? 'Page' : 'Article';

            return wps('wpfs')->settings->get("seo.post_type.$post_type.schema.{$for}Type", $post_type === 'page' ? "WebPage" : "Article");
        }

        return '';
    }

    public function sanitize_text($str): string
    {
        return StringHelper::sanitize_text($str);
    }

    /**
     * Display the meta box HTML to the user.
     *
     * @param \WP_Post $post Post object.
     */
    public function print_html(\WP_Post $post): void
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
                            echo "<input autocomplete='off' type='{$field['type']}' class='wpfs_input' name='wpfs_metabox[{$field['name']}]' value='{$value['value']}' id='{$field['name']}_{$index}' $checked>";
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
