<?php
/**
 * @author    sh1zen
 * @copyright Copyright (C)  2022
 * @license   http://www.gnu.org/licenses/gpl.html GNU/GPL
 */

if (is_admin() and shzn('wpfs')->settings->get("seo.addon.term_extra_fields", true)) {

    function wpfs_edit_term_meta_extra_fields($term, $taxonomy = 'category')
    {
        $term_meta_description = get_metadata_raw('term', $term->term_id, 'wpfs_metaterm_description', true);
        $term_meta_title = get_metadata_raw('term', $term->term_id, 'wpfs_metaterm_title', true);

        ?>
        <tr class="form-field term-description-wrap">
            <th scope="row"><label for="wpfs-termmeta-description"><?php _e('Meta Description', 'wpfs'); ?></label></th>
            <td>
                <textarea name="wpfs-termmeta-description" id="wpfs-termmeta-description" rows="5" cols="50"
                          class="large-text"><?php echo html_entity_decode(stripslashes($term_meta_description)); ?></textarea>
                <p class="description"><?php _e('Flexy SEO custom term meta description.', 'wpfs'); ?></p>
            </td>
        </tr>
        <tr class="form-field term-description-wrap">
            <th scope="row"><label for="wpfs-termmeta-title"><?php _e('Meta Title', 'wpfs'); ?></label></th>
            <td>
                <input name="wpfs-termmeta-title" id="wpfs-termmeta-title" class="large-text"
                       value="<?php echo html_entity_decode(stripslashes($term_meta_title)); ?>"/>
                <p class="description"><?php _e('Flexy SEO custom term meta title.', 'wpfs'); ?></p>
            </td>
        </tr>
        <?php
    }

    // add the field to the edit screen.
    foreach (get_taxonomies(array('public' => true), 'objects') as $tax_type_object) {
        add_action("{$tax_type_object->name}_edit_form_fields", 'wpfs_edit_term_meta_extra_fields', 10, 2);
    }

    if (isset($_POST['action']) and $_POST['action'] === 'editedtag') {

        $term_id = absint($_POST['tag_ID']);

        if ($term_id and isset($_POST['wpfs-termmeta-description'])) {
            $res = update_metadata('term', $term_id, 'wpfs_metaterm_description', sanitize_textarea_field($_POST['wpfs-termmeta-description']));
        }

        if ($term_id and isset($_POST['wpfs-termmeta-title'])) {
            $res = update_metadata('term', $term_id, 'wpfs_metaterm_title', sanitize_textarea_field($_POST['wpfs-termmeta-title']));
        }
    }
}