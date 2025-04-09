<?php
/**
 * @author    sh1zen
 * @copyright Copyright (C) 2025.
 * @license   http://www.gnu.org/licenses/gpl.html GNU/GPL
 */

namespace FlexySEO\Engine\Helpers;

use WPS\core\StringHelper;

class ExtraTermFields
{
    private static ExtraTermFields $Instance;

    private function __construct()
    {
        if (is_admin() and wps('wpfs')->settings->get("seo.addon.term_extra_fields", true)) {
            add_action('admin_init', [$this, 'inject_term_fields'], 10, 0);

            $this->actions();
        }
    }

    private function actions()
    {
        if (isset($_POST['action']) and $_POST['action'] === 'editedtag') {

            $term_id = absint($_POST['tag_ID']);

            if ($term_id and isset($_POST['wpfs-termmeta-description'])) {
                update_metadata('term', $term_id, 'wpfs_metaterm_description', StringHelper::sanitize_text($_POST['wpfs-termmeta-description']));
            }

            if ($term_id and isset($_POST['wpfs-termmeta-title'])) {
                update_metadata('term', $term_id, 'wpfs_metaterm_title', StringHelper::sanitize_text($_POST['wpfs-termmeta-title']));
            }
        }
    }

    public static function Init()
    {
        if (isset(self::$Instance)) {
            return;
        }

        self::$Instance = new self();
    }

    public function inject_term_fields()
    {

        foreach (get_taxonomies(array('public' => true), 'objects') as $tax_type_object) {
            add_action("{$tax_type_object->name}_edit_form_fields", [$this, 'editors_html'], 10, 2);
        }
    }

    public function editors_html($term, $taxonomy = 'category')
    {
        ?>
        <tr class="form-field term-description-wrap">
            <th scope="row"><label for="wpfs-termmeta-description"><?php _e('Meta Description', 'wpfs'); ?></label></th>
            <td>
                <textarea name="wpfs-termmeta-description" id="wpfs-termmeta-description" rows="5" cols="50"
                          class="large-text"><?php echo wpfs_get_term_meta_description($term, true); ?></textarea>
                <p class="description"><?php _e('Flexy SEO custom term meta description.', 'wpfs'); ?></p>
            </td>
        </tr>
        <tr class="form-field term-description-wrap">
            <th scope="row"><label for="wpfs-termmeta-title"><?php _e('Meta Title', 'wpfs'); ?></label></th>
            <td>
                <input name="wpfs-termmeta-title" id="wpfs-termmeta-title" class="large-text"
                       value="<?php echo wpfs_get_term_meta_title($term, true); ?>"/>
                <p class="description"><?php _e('Flexy SEO custom term meta title.', 'wpfs'); ?></p>
            </td>
        </tr>
        <?php
    }
}