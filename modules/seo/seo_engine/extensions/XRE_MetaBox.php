<?php
/**
 * @author    sh1zen
 * @copyright Copyright (C)  2021
 * @license   http://www.gnu.org/licenses/gpl.html GNU/GPL
 */

namespace FlexySEO\Engine\Helpers;

use FlexySEO\core\Options;
use FlexySEO\core\Settings;

class XRE_MetaBox
{
    public function __construct()
    {
        add_action('add_meta_boxes', [$this, 'add']);
        add_action('save_post', [$this, 'save'], 10, 3);
    }

    public static function get_value($id, $item, $context, $default = false)
    {
        return Options::get($id, $item, $context, $default);
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
                Options::remove($post_id, $meta_name, "customMeta");
                continue;
            }

            $value = $metas[$meta_name];

            if (isset($field_meta['sanitize_callback']) and is_callable($field_meta['sanitize_callback'])) {
                $value = call_user_func($field_meta['sanitize_callback'], $value);
            }

            if (empty($value)) {
                Options::remove($post_id, $meta_name, "customMeta");
            }
            else {
                Options::update($post_id, $meta_name, $value, "customMeta");
            }
        }
    }

    private function fields($post)
    {
        $keyword = $description = "";
        $indexable = shzn('wpfs')->settings->get('seo.post_type.post.show');

        if (isset($post->ID) and $post->ID > 0) {
            $post_id = $post->ID;
            $keyword = Options::get($post_id, "keywords", "customMeta", "");
            $description = Options::get($post_id, "description", "customMeta", "");
            $indexable = Options::get($post_id, "indexable", "customMeta", $indexable);
        }

        return [
            [
                'name'              => 'keywords',
                'label'             => __('Keyword phrase', 'wpfs'),
                'type'              => 'textarea',
                'sanitize_callback' => 'sanitize_text_field',
                'values'            => [['value' => $keyword]]
            ],
            [
                'name'              => 'description',
                'label'             => __('Description', 'wpfs'),
                'type'              => 'textarea',
                'sanitize_callback' => 'sanitize_text_field',
                'values'            => [['value' => $description]]
            ],
            [
                'name'              => 'schema.org',
                'label'             => __('Page type (Schema.org)', 'wpfs'),
                'type'              => 'select',
                'sanitize_callback' => 'sanitize_text_field',
                'values'            => [
                    ['text' => __('Auto Detect', 'wpfs'), 'value' => ""],
                ]
            ],
            /*[
                'name'   => 'indexable',
                'label'  => __('Show in search result', 'wpfs'),
                'type'   => 'radio',
                'sanitize_callback' => ,
                'values' => [
                    ['text' => __('Yes', 'wpfs'), 'checked' => $indexable, 'value' => "index"],
                    ['text' => __('No', 'wpfs'), 'checked' => !$indexable, 'value' => "noindex"]
                ]
            ],*/
        ];
    }

    /**
     * Display the meta box HTML to the user.
     *
     * @param \WP_Post $post Post object.
     */
    public function print_html($post)
    {
        ?>
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
                            echo "<{$value['type']} value='{$value['value']}' {$value['checked']}>{$value['text']}</{$value['type']}>";
                        }
                        echo "</select>";
                        break;

                    case 'textarea':
                        foreach ($values as $index => $value) {
                            if ($value['text']) {
                                echo "<label class='wpfs_label' for='{$field['name']}_{$index}}'>{$value['text']}</label>";
                            }

                            echo "<textarea autocomplete='off' class='wpfs_input' name='wpfs_metabox[{$field['name']}]' value='{$value['value']}' id='{$field['name']}_{$index}'></textarea>";
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


