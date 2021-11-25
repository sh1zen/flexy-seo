<?php
/**
 * @author    sh1zen
 * @copyright Copyright (C)  2021
 * @license   http://www.gnu.org/licenses/gpl.html GNU/GPL
 */

namespace FlexySEO\modules;

use SHZN\core\Graphic;
use SHZN\modules\Module;
use FlexySEO\Engine\WPFS_SEO;


class Mod_seo extends Module
{
    public $scopes = array('admin-page', 'settings', 'autoload');

    private $performer_response = array();

    public function __construct()
    {
        parent::__construct('wpfs');

        if (!(wp_doing_cron() or wp_doing_ajax())) {

            require_once WPFS_MODULES . 'seo/WPFS_SEO.php';

            WPFS_SEO::Init();
        }
    }

    public function enqueue_scripts()
    {
        parent::enqueue_scripts();
        wp_enqueue_media();
    }

    public function render_admin_page()
    {
        if (WPFS_DEBUG)
            set_time_limit(0);
        else
            set_time_limit(60);
        ?>
        <section class="shzn-wrap">
            <section class='shzn-header'><h1>SEO / <?php echo __('Settings', 'wpfs'); ?></h1></section>
            <div id="shzn-ajax-message" class="shzn-notice"></div>
            <?php
            if (!empty($this->performer_response)) {

                echo '<div id="message" class="shzn-notice">';

                foreach ($this->performer_response as $response) {
                    list($text, $status) = $response;

                    echo "<p class='{$status}'> {$text} </p>";
                }

                echo '</div>';
            }
            ?>
            <block class="shzn">
                <form id="shzn-uoptions" action="options.php" method="post">
                    <input type="hidden" name="<?php echo shzn('wpfs')->settings->option_name . "[change]" ?>"
                           value="<?php echo $this->slug; ?>">
                    <?php

                    settings_fields('wpfs-settings');

                    $fields = array();

                    echo Graphic::generateHTML_tabs_panels(array_merge(array(

                        array(
                            'id'        => 'seo-general',
                            'tab-title' => __('General', 'wpfs'),
                            'callback'  => array($this, 'render_settings_block'),
                            'args'      => array('general')
                        ),
                        array(
                            'id'        => 'seo-special',
                            'tab-title' => __('Special pages', 'wpfs'),
                            'callback'  => array($this, 'render_settings_block'),
                            'args'      => array('special')
                        ),
                        array(
                            'id'        => 'seo-post-type',
                            'tab-title' => __('Content types', 'wpfs'),
                            'callback'  => array($this, 'render_settings_block'),
                            'args'      => array(get_post_types(array('public' => true)))
                        ),
                        array(
                            'id'        => 'seo-archives',
                            'tab-title' => __('Archives', 'wpfs'),
                            'callback'  => array($this, 'render_settings_block'),
                            'args'      => array('archives')
                        ),
                        array(
                            'id'        => 'seo-tax',
                            'tab-title' => __('Taxonomy', 'wpfs'),
                            'callback'  => array($this, 'render_settings_block'),
                            'args'      => array(get_taxonomies(array('public' => true)))
                        ),
                        array(
                            'id'        => 'seo-social',
                            'tab-title' => __('Social', 'wpfs'),
                            'callback'  => array($this, 'render_settings_block'),
                            'args'      => array('social')
                        ),
                        array(
                            'id'        => 'seo-webmaster',
                            'tab-title' => __('Webmaster', 'wpfs'),
                            'callback'  => array($this, 'render_settings_block'),
                            'args'      => array('webmaster')
                        ),
                        array(
                            'id'        => 'seo-schema-org',
                            'tab-title' => __('Schema.org', 'wpfs'),
                            'callback'  => array($this, 'render_settings_block'),
                            'args'      => array('schema.org')
                        ),
                        array(
                            'id'        => 'seo-vars',
                            'tab-title' => __('Replacers', 'wpfs'),
                            'callback'  => array($this, 'render_replacers')
                        ),
                    ), $fields));
                    ?>
                </form>
            </block>
        </section>
        <?php
    }

    /**
     * Handle the gui for tables list
     * @param $block
     * @return string
     */
    public function render_settings_block($block)
    {
        return $this->render_settings($block);
    }

    public function render_settings($filter = '', $_setting_fields = array())
    {
        $_header = $this->setting_form_templates('header');
        $_footer = $this->setting_form_templates('footer');

        $_divider = false;

        if (empty($_setting_fields)) {
            $_setting_fields = $this->setting_fields($filter);
        }

        ob_start();

        if (!empty($_setting_fields)) {

            $_divider = true;

            if ($_header) {
                echo "<h3 class='shzn-setting-header'>{$_header}</h3>";
            }

            ?>
            <table class="shzn shzn-settings">
                <tbody>
                <?php

                Graphic::generate_fields($_setting_fields, array('name_prefix' => shzn('wpfs')->settings->option_name));

                ?>
                </tbody>
            </table>
            <p class="shzn-submit">
                <input type="submit" class="button-primary" value="<?php _e('Save Changes', 'wpfs') ?>"/>
            </p>
            <?php
        }

        if (!empty($_footer)) {

            if ($_divider)
                echo "<hr class='shzn-hr'>";

            echo "<section class='shzn-setting-footer'>" . $_footer . "</section>";
        }

        return ob_get_clean();
    }

    protected function setting_fields($filter = '')
    {
        $fields = $res = array();


        $fields['general'] = $this->group_setting_fields(
            $this->setting_field(__('Titles:', 'wpfs'), false, 'separator'),
            $this->setting_field(__('Rewrite titles', 'wpfs'), 'title.rewrite', 'checkbox', ['default_value' => true]),
            $this->setting_field(__('Title separator', 'wpfs'), 'title.separator', 'text', ['default_value' => ' << ']),

            $this->setting_field(__('Media:', 'wpfs'), false, 'separator'),
            $this->setting_field(__('Rewrite media url to attachment', 'wpfs'), 'media.rewrite_url', 'checkbox', ['default_value' => true]),

            $this->setting_field(__('Knowledge Graph configuration:', 'wpfs'), false, 'separator'),
            $this->setting_field(__('Default snippet image url (wide)', 'wpfs'), 'org.logo_url.wide', 'upload-input', ['placeholder' => __('Paste your image URL or select a new image', 'wpfs')]),
            $this->setting_field(__('Default snippet image url (small)', 'wpfs'), 'org.logo_url.small', 'upload-input', ['placeholder' => __('Paste your image URL or select a new image', 'wpfs')]),
            $this->setting_field(__('Add Twitter Card metadata', 'wpfs'), 'social.twitter.card', 'checkbox', ['default_value' => true]),
            $this->setting_field(__('Use larger images for Twitter Card', 'wpfs'), 'social.twitter.large_images', 'checkbox', ['default_value' => true]),
            $this->setting_field(__('Enable Facebook Open-Graph metadata', 'wpfs'), 'social.facebook.opengraph', 'checkbox', ['default_value' => true]),
            $this->setting_field(__('Facebook default share image', 'wpfs'), 'social.facebook.logo_url', 'upload-input', ['placeholder' => __('Paste your image URL or select a new image', 'wpfs')])
        );

        $fields['special'] = $this->group_setting_fields(
            $this->setting_field(__('Home:', 'wpfs'), false, 'separator'),
            $this->setting_field(__('SEO title', 'wpfs'), 'home.title', 'text', ['default_value' => '%%sitename%% %%sep%% %%sitedesc%%']),
            $this->setting_field(__('Meta description', 'wpfs'), 'home.meta_desc', 'textarea'),
            $this->setting_field(__('Keywords (comma separate)', 'wpfs'), 'home.keywords'),

            $this->setting_field(__('Search page:', 'wpfs'), false, 'separator'),
            $this->setting_field(__('Title', 'wpfs'), 'search.title'),
            $this->setting_field(__('Meta description', 'wpfs'), 'search.meta_desc', 'textarea'),
            $this->setting_field(__('Keywords', 'wpfs'), 'search.keywords'),

            $this->setting_field(__('404 page:', 'wpfs'), false, 'separator'),
            $this->setting_field(__('Title', 'wpfs'), 'E404.title'),
            $this->setting_field(__('Meta description', 'wpfs'), 'E404.meta_desc', 'textarea'),
            $this->setting_field(__('Keywords', 'wpfs'), 'E404.keywords')
        );

        $fields['social'] = $this->group_setting_fields(
            $this->setting_field(__('General', 'wpfs'), false, 'separator'),
            $this->setting_field(__('Use same username', 'wpfs'), 'social.sameUsername.enable', 'checkbox', ['default_value' => false]),
            $this->setting_field(__('Username', 'wpfs'), 'social.sameUsername.username', 'text', ['depend' => 'social.sameUsername.enable']),

            $this->setting_field(__('Facebook:', 'wpfs'), false, 'separator'),
            $this->setting_field(__('Active', 'wpfs'), 'social.facebook.enable', 'checkbox', ['depend' => 'social.sameUsername.enable', 'default_value' => true]),
            $this->setting_field(__('Profile name or url', 'wpfs'), 'social.facebook.url', 'text', ['depend' => '!social.sameUsername.enable']),
            $this->setting_field(__('Confirmation code', 'wpfs'), 'social.facebook.code'),

            $this->setting_field(__('Twitter:', 'wpfs'), false, 'separator'),
            $this->setting_field(__('Active', 'wpfs'), 'social.twitter.enable', 'checkbox', ['depend' => 'social.sameUsername.enable', 'default_value' => true]),
            $this->setting_field(__('Profile name or url', 'wpfs'), 'social.twitter.url', 'text', ['depend' => '!social.sameUsername.enable']),
            $this->setting_field(__('Confirmation code', 'wpfs'), 'social.twitter.code'),

            $this->setting_field(__('Linkedin:', 'wpfs'), false, 'separator'),
            $this->setting_field(__('Active', 'wpfs'), 'social.linkedin.enable', 'checkbox', ['depend' => 'social.sameUsername.enable', 'default_value' => true]),
            $this->setting_field(__('Profile name or url', 'wpfs'), 'social.linkedin.url', 'text', ['depend' => '!social.sameUsername.enable']),

            $this->setting_field(__('Pinterest:', 'wpfs'), false, 'separator'),
            $this->setting_field(__('Active', 'wpfs'), 'social.pinterest.enable', 'checkbox', ['depend' => 'social.sameUsername.enable', 'default_value' => true]),
            $this->setting_field(__('Profile name or url', 'wpfs'), 'social.pinterest.url', 'text', ['depend' => '!social.sameUsername.enable']),
            $this->setting_field(__('Confirmation code', 'wpfs'), 'social.pinterest.code'),

            $this->setting_field(__('Instagram:', 'wpfs'), false, 'separator'),
            $this->setting_field(__('Active', 'wpfs'), 'social.instagram.enable', 'checkbox', ['depend' => 'social.sameUsername.enable', 'default_value' => true]),
            $this->setting_field(__('Profile name or url', 'wpfs'), 'social.instagram.url', 'text', ['depend' => '!social.sameUsername.enable']),

            $this->setting_field(__('YouTube:', 'wpfs'), false, 'separator'),
            $this->setting_field(__('Active', 'wpfs'), 'social.youtube.enable', 'checkbox', ['depend' => 'social.sameUsername.enable', 'default_value' => false]),
            $this->setting_field(__('Profile name or url', 'wpfs'), 'social.youtube.url', 'text', ['depend' => '!social.sameUsername.enable']),

            $this->setting_field(__('Tumblr:', 'wpfs'), false, 'separator'),
            $this->setting_field(__('Active', 'wpfs'), 'social.tumblr.enable', 'checkbox', ['depend' => 'social.sameUsername.enable', 'default_value' => false]),
            $this->setting_field(__('Profile name or url', 'wpfs'), 'social.tumblr.url', 'text', ['depend' => '!social.sameUsername.enable']),

            $this->setting_field(__('Yelp:', 'wpfs'), false, 'separator'),
            $this->setting_field(__('Active', 'wpfs'), 'social.yelp.enable', 'checkbox', ['depend' => 'social.sameUsername.enable', 'default_value' => false]),
            $this->setting_field(__('Profile name or url', 'wpfs'), 'social.yelp.url', 'text', ['depend' => '!social.sameUsername.enable']),

            $this->setting_field(__('SoundCloud:', 'wpfs'), false, 'separator'),
            $this->setting_field(__('Active', 'wpfs'), 'social.soundcloud.enable', 'checkbox', ['depend' => 'social.sameUsername.enable', 'default_value' => false]),
            $this->setting_field(__('Profile name or url', 'wpfs'), 'social.soundcloud.url', 'text', ['depend' => '!social.sameUsername.enable']),

            $this->setting_field(__('WikiPedia:', 'wpfs'), false, 'separator'),
            $this->setting_field(__('Active', 'wpfs'), 'social.wikipedia.enable', 'checkbox', ['depend' => 'social.sameUsername.enable', 'default_value' => false]),
            $this->setting_field(__('Profile name or url', 'wpfs'), 'social.wikipedia.url', 'text', ['depend' => '!social.sameUsername.enable'])
        );

        $fields['webmaster'] = $this->group_setting_fields(
            $this->setting_field(__('Google:', 'wpfs'), false, 'separator'),
            $this->setting_field(__('Verification code', 'wpfs'), 'webmaster.google.vercode'),

            $this->setting_field(__('Bing:', 'wpfs'), false, 'separator'),
            $this->setting_field(__('Verification code', 'wpfs'), 'webmaster.bing.vercode'),

            $this->setting_field(__('Yandex:', 'wpfs'), false, 'separator'),
            $this->setting_field(__('Verification code', 'wpfs'), 'webmaster.yandex.vercode'),

            $this->setting_field(__('Baidu:', 'wpfs'), false, 'separator'),
            $this->setting_field(__('Verification code', 'wpfs'), 'webmaster.baidu.vercode')
        );

        $fields['archives'] = $this->group_setting_fields(
            $this->setting_field(__('Date archive:', 'wpfs'), false, 'separator'),
            $this->setting_field(__('Show date archive in search results', 'wpfs'), 'archives.date.active', 'checkbox', ['default_value' => false]),
            $this->setting_field(__('Title', 'wpfs'), 'archives.date.title', 'text', ['parent' => 'archives.date.active']),
            $this->setting_field(__('Meta description', 'wpfs'), 'archives.date.meta_desc', 'textarea', ['parent' => 'archives.date.active']),
            $this->setting_field(__('Keywords', 'wpfs'), 'archives.date.keywords', 'text', ['parent' => 'archives.date.active']),

            $this->setting_field(__('Author archive:', 'wpfs'), false, 'separator'),
            $this->setting_field(__('Status', 'wpfs'), 'archives.author.active', 'checkbox', ['default_value' => true]),
            $this->setting_field(__('Show author archive in search results', 'wpfs'), 'archives.author.show', 'checkbox', ['default_value' => true, 'parent' => 'archives.author.active']),
            $this->setting_field(__('Title', 'wpfs'), 'archives.date.title', 'text', ['parent' => 'archives.author.active']),
            $this->setting_field(__('Meta description', 'wpfs'), 'archives.date.meta_desc', 'textarea', ['parent' => 'archives.author.active']),
            $this->setting_field(__('Keywords', 'wpfs'), 'archives.date.keywords', 'text', ['parent' => 'archives.author.active'])
        );

        $fields['schema.org'] = $this->group_setting_fields(
            $this->setting_field(__('Schema settings:', 'wpfs'), false, 'separator'),
            $this->setting_field(__('Active', 'wpfs'), 'schema.enabled', 'checkbox', ['default_value' => true]),
            $this->setting_field(__('Organization', 'wpfs'), 'schema.organization.is', 'checkbox', ['parent' => 'schema.enabled', 'default_value' => false]),
            $this->setting_field(__('Organization name', 'wpfs'), 'schema.organization.name', 'text', ['parent' => 'schema.organization.is']),
            $this->setting_field(__('Organization logo', 'wpfs'), 'schema.organization.logo', 'upload-input', ['parent' => 'schema.organization.is', 'placeholder' => __('Paste your image URL or select a new image', 'wpfs')]),
            $this->setting_field(__('Organization phone', 'wpfs'), 'schema.organization.phone', 'text', ['parent' => 'schema.organization.is']),
            $this->setting_field(__('Contact Type', 'wpfs'), 'schema.organization.contact_type', 'dropdown', ['parent' => 'schema.organization.is', 'list' => [
                "Customer Service",
                "Technical Support",
                "Billing Support",
                "Bill Payment",
                "Sales",
                "Reservations",
                "Credit Card Support",
                "Emergency",
                "Baggage Tracking",
                "Roadside Assistance",
                "Package Tracking"
            ]]),

            $this->setting_field(__('Enable Sitelinks Search Box', 'wpfs'), 'schema.sitelink', 'checkbox', ['parent' => 'schema.enabled', 'default_value' => false])
        );

        //settings for each taxonomy
        foreach (get_taxonomies(array('public' => true), 'objects') as $tax_type_object) {

            $tax_type = $tax_type_object->name;

            $fields[$tax_type] = $this->group_setting_fields(
                $this->setting_field(ucwords($tax_type_object->label) . ($tax_type_object->_builtin ? "" : " ({$tax_type_object->name})"), false, 'separator'),
                $this->setting_field(sprintf(__("Show \"%s\" in search results", 'wpfs'), $tax_type_object->label), "tax.{$tax_type}.show", 'checkbox', ['default_value' => true]),
                $this->setting_field(__('Title', 'wpfs'), "tax.{$tax_type}.title", 'text', ['default_value' => '%%title%%']),
                $this->setting_field(__('Meta description', 'wpfs'), "tax.{$tax_type}.meta_desc", 'textarea'),
                $this->setting_field(__('Keywords', 'wpfs'), "tax.{$tax_type}.keywords"),
                $this->setting_field(__('Remove type prefix', 'wpfs'), "tax.{$tax_type}.prefix", 'checkbox', ['default_value' => false])

            );
        }

        //settings for each post type
        foreach (get_post_types(array('public' => true), 'objects') as $post_type_object) {

            $post_type = $post_type_object->name;

            if ($post_type_object->has_archive) {

                $archive_name = ucwords($post_type_object->label) . ($post_type_object->_builtin ? "" : " ({$post_type_object->name})");

                $fields['archives'] = array_merge(
                    $fields['archives'],
                    $this->group_setting_fields(

                        $this->setting_field(sprintf(__('%s archive:', 'wpfs'), $archive_name), false, 'separator'),
                        $this->setting_field(__('Status', 'wpfs'), "archives.{$post_type}.active", 'checkbox', ['default_value' => true]),
                        $this->setting_field(sprintf(__('Show %s archive in search results', 'wpfs'), $archive_name), "archives.{$post_type}.show", 'checkbox', ['default_value' => true, 'parent' => "archives.{$post_type}.active"]),
                        $this->setting_field(__('Title', 'wpfs'), "archives.{$post_type}.title", 'text', ['parent' => "archives.{$post_type}.active"]),
                        $this->setting_field(__('Meta description', 'wpfs'), "archives.{$post_type}.meta_desc", 'textarea', ['parent' => "archives.{$post_type}.active"]),
                        $this->setting_field(__('Keywords', 'wpfs'), "archives.{$post_type}.keywords", 'text', ['parent' => "archives.{$post_type}.active"])
                    ));
            }

            $fields[$post_type] = $this->group_setting_fields(
                $this->setting_field(ucwords($post_type_object->label) . ($post_type_object->_builtin ? "" : " ({$post_type_object->name})"), false, 'separator'),
                $this->setting_field(sprintf(__("Show \"%s\" in search results", 'wpfs'), str_replace(array('-', '_'), ' ', $post_type)), "post_type.{$post_type}.show", 'checkbox', ['default_value' => true]),
                $this->setting_field(__("Follow link directive", 'wpfs'), "post_type.{$post_type}.follow", 'checkbox', ['default_value' => true]),
                $this->setting_field(__('Title', 'wpfs'), "post_type.{$post_type}}.title", 'text', ['default_value' => '%%title%%']),
                $this->setting_field(__('Meta description', 'wpfs'), "post_type.{$post_type}.meta_desc", 'textarea'),
                $this->setting_field(__('Keywords', 'wpfs'), "post_type.{$post_type}.keywords")
            );
        }

        if (!empty($filter)) {
            foreach ((array)$filter as $_filter) {
                if (isset($fields[$_filter]))
                    $res = array_merge($res, $fields[$_filter]);
            }
        }
        else {
            $res = call_user_func_array('array_merge', array_values($fields));
        }

        return $res;
    }


    /**
     * Handle the gui for exec-sql panel
     * @return string
     */
    public function render_replacers()
    {
        ob_start();
        ?>
        <br><br>
        <table class="wp-list-table widefat fixed striped table-view-list tables">
            <thead>
            <tr>
                <th scope="col"><?php echo __('Replacer', 'wpfs'); ?></th>
                <th scope="col"><?php echo __('Description', 'wpfs'); ?></th>
            </tr>
            </thead>
            <tbody>
            <tr>
                <td><strong>%%{query_var}%%</strong></td>
                <td><?php echo __('Will replace the specified query var.', 'wpfs'); ?></td>
            </tr>
            <tr>
                <td><strong>%%meta_{meta_key}%%</strong></td>
                <td><?php echo __('Will replace the specified meta of the queried object.', 'wpfs'); ?></td>
            </tr>
            <tr>
                <td><strong>%%{queried_object_property}%%</strong></td>
                <td><?php echo __('Will replace the WordPress queried object property if it\'s available (display_name, ID, post_modified...) .', 'wpfs'); ?></td>
            </tr>
            <tr>
                <td><strong>%%sep%%</strong></td>
                <td><?php echo __('Default separator.', 'wpfs'); ?></td>
            </tr>
            <tr>
                <td><strong>%%sitename%%</strong></td>
                <td><?php echo __('The site name.', 'wpfs'); ?></td>
            </tr>
            <tr>
                <td><strong>%%resume%%</strong></td>
                <td><?php echo __('The resume of the current queried post.', 'wpfs'); ?></td>
            </tr>
            <tr>
                <td><strong>%%title%%</strong></td>
                <td><?php echo __('The default WordPress title of the current queried object.', 'wpfs'); ?></td>
            </tr>
            <tr>
                <td><strong>%%language%%</strong></td>
                <td><?php echo __('The site language.', 'wpfs'); ?></td>
            </tr>
            <tr>
                <td><strong>%%date%%</strong></td>
                <td><?php echo __('Current date.', 'wpfs'); ?></td>
            </tr>
            <tr>
                <td><strong>%%sitedesc%%</strong></td>
                <td><?php echo __('The site description.', 'wpfs'); ?></td>
            </tr>
            </tbody>
        </table>
        <?php
        return ob_get_clean();
    }

    public function restricted_access($context = 'settings')
    {
        switch ($context) {

            case 'settings':
            case 'render-admin':
            case 'ajax':
                return !current_user_can('manage_options');

            default:
                return false;
        }
    }
}

return __NAMESPACE__;