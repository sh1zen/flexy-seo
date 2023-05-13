<?php
/**
 * @author    sh1zen
 * @copyright Copyright (C) 2023.
 * @license   http://www.gnu.org/licenses/gpl.html GNU/GPL
 */

namespace FlexySEO\modules;

use WPS\core\Graphic;
use WPS\modules\Module;

use FlexySEO\Engine\Generators\Schema;
use FlexySEO\Engine\WPFS_SEO;

class Mod_seo extends Module
{
    public array $scopes = array('admin-page', 'settings', 'autoload');

    protected string $context = 'wpfs';

    private array $performer_response = array();

    public function enqueue_scripts(): void
    {
        parent::enqueue_scripts();
        wp_enqueue_media();
    }

    public function render_sub_modules(): void
    {
        ?>
        <section class="wps-wrap">
            <div id="wps-ajax-message" class="wps-notice"></div>
            <?php
            if (!empty($this->performer_response)) {

                echo '<div id="message" class="wps-notice">';

                foreach ($this->performer_response as $response) {
                    list($text, $status) = $response;

                    echo "<p class='{$status}'> {$text} </p>";
                }

                echo '</div>';
            }
            ?>
            <block class="wps">
                <section class='wps-header'><h1>SEO / <?php echo __('Settings', 'wpfs'); ?></h1></section>
                <form id="wps-uoptions" action="options.php" method="post">
                    <input type="hidden" name="<?php echo wps('wpfs')->settings->get_context() . "[change]" ?>"
                           value="<?php echo $this->slug; ?>">
                    <?php

                    settings_fields('wpfs-settings');

                    echo Graphic::generateHTML_tabs_panels(array(

                        array(
                            'id'        => 'seo-general',
                            'tab-title' => __('General', 'wpfs'),
                            'callback'  => array($this, 'render_settings'),
                            'args'      => array('general')
                        ),
                        array(
                            'id'        => 'seo-special',
                            'tab-title' => __('Special pages', 'wpfs'),
                            'callback'  => array($this, 'render_settings'),
                            'args'      => array('special')
                        ),
                        array(
                            'id'        => 'seo-post-type',
                            'tab-title' => __('Post types', 'wpfs'),
                            'callback'  => array($this, 'render_settings'),
                            'args'      => array(get_post_types(array('public' => true)))
                        ),
                        array(
                            'id'        => 'seo-archives',
                            'tab-title' => __('Archives', 'wpfs'),
                            'callback'  => array($this, 'render_settings'),
                            'args'      => array('archives')
                        ),
                        array(
                            'id'        => 'seo-tax',
                            'tab-title' => __('Taxonomy', 'wpfs'),
                            'callback'  => array($this, 'render_settings'),
                            'args'      => array(get_taxonomies(array('public' => true)))
                        ),
                        array(
                            'id'        => 'seo-social',
                            'tab-title' => __('Social', 'wpfs'),
                            'callback'  => array($this, 'render_settings'),
                            'args'      => array('social')
                        ),
                        array(
                            'id'        => 'seo-webmaster',
                            'tab-title' => __('Webmaster', 'wpfs'),
                            'callback'  => array($this, 'render_settings'),
                            'args'      => array('webmaster')
                        ),
                        array(
                            'id'        => 'seo-schema-org',
                            'tab-title' => __('Schema.org', 'wpfs'),
                            'callback'  => array($this, 'render_settings'),
                            'args'      => array('schema.org')
                        ),
                        array(
                            'id'        => 'seo-vars',
                            'tab-title' => __('Replacers', 'wpfs'),
                            'callback'  => array($this, 'render_replacers')
                        ),
                    ));
                    ?>
                    <p class="wps-submit">
                        <input type="submit" class="button-primary" value="<?php _e('Save Changes', 'wpfs') ?>"/>
                    </p>
                </form>
            </block>
        </section>
        <script type="application/javascript">
            jQuery('textarea.wps').TextBoxHighlighter({
                highlight: [/(%%\w+%%)+/gi]
            });

            jQuery('input[type="text"].wps').TextBoxHighlighter({
                highlight: [/(%%\w+%%)+/gi]
            });
        </script>
        <?php
    }

    /**
     * overwrite the base render setting gui for settings
     */
    public function render_settings($filter = ''): string
    {
        $_setting_fields = $this->setting_fields($filter);

        ob_start();

        if (!empty($_setting_fields)) {

            ?>
            <block class="wps-options">
                <?php Graphic::generate_fields($_setting_fields, $this->infos(), array('name_prefix' => wps('wpfs')->settings->get_context())); ?>
            </block>
            <?php
        }

        return ob_get_clean();
    }

    protected function setting_fields($filter = ''): array
    {
        static $fields;

        if (!isset($fields)) {

            $fields = [];

            $fields['general'] = $this->group_setting_fields(
                $this->group_setting_fields(
                    $this->setting_field(__('Titles:', 'wpfs'), false, 'separator'),
                    $this->setting_field(__('Rewrite titles', 'wpfs'), 'title.rewrite', 'checkbox', ['default_value' => true]),
                    $this->setting_field(__('Force blog name as trailing part of the title', 'wpfs'), 'title.blogname', 'checkbox', ['default_value' => true]),
                    $this->setting_field(__('Title separator', 'wpfs'), 'title.separator', 'dropdown', ['default_value' => '-', 'list' => ["-", ">", "<", ">>", "<<", "~", "•"]]),
                ),
                $this->group_setting_fields(
                    $this->setting_field(__('Addon:', 'wpfs'), false, 'separator'),
                    $this->setting_field(__('Custom Term Description', 'wpfs'), 'addon.term_extra_fields', 'checkbox', ['default_value' => true]),
                    $this->setting_field(__('Edit page meta-boxes', 'wpfs'), 'addon.xre_metaboxe', 'checkbox', ['default_value' => true]),
                ),
                $this->group_setting_fields(
                    $this->setting_field(__('Media:', 'wpfs'), false, 'separator'),
                    $this->setting_field(__('Rewrite media url to attachment', 'wpfs'), 'media.rewrite_url', 'checkbox', ['default_value' => true]),
                ),
                $this->group_setting_fields(
                    $this->setting_field(__('Knowledge Graph configuration:', 'wpfs'), false, 'separator'),
                    $this->setting_field(__('Default snippet image url (wide)', 'wpfs'), 'org.logo_url.wide', 'upload-input', ['placeholder' => __('Paste your image URL or select a new image', 'wpfs')]),
                    $this->setting_field(__('Default snippet image url (small)', 'wpfs'), 'org.logo_url.small', 'upload-input', ['placeholder' => __('Paste your image URL or select a new image', 'wpfs')]),
                    $this->setting_field(__('Add Twitter Card metadata', 'wpfs'), 'social.twitter.card', 'checkbox', ['default_value' => true]),
                    $this->setting_field(__('Use larger images for Twitter Card', 'wpfs'), 'social.twitter.large_images', 'checkbox', ['default_value' => true]),
                    $this->setting_field(__('Enable Facebook Open-Graph metadata', 'wpfs'), 'social.facebook.opengraph', 'checkbox', ['default_value' => true]),
                    $this->setting_field(__('Facebook default share image', 'wpfs'), 'social.facebook.logo_url', 'upload-input', ['placeholder' => __('Paste your image URL or select a new image', 'wpfs')])
                )
            );

            $fields['special'] = $this->group_setting_fields(
                $this->group_setting_fields(
                    $this->setting_field(__('Home:', 'wpfs'), false, 'separator'),
                    $this->setting_field(__('SEO title', 'wpfs'), 'home.title', 'text', ['default_value' => '%%sitename%% %%sep%% %%sitedesc%%']),
                    $this->setting_field(__('Meta description', 'wpfs'), 'home.meta_desc', 'textarea'),
                    $this->setting_field(__('Keywords (comma separate)', 'wpfs'), 'home.keywords'),
                ),
                $this->group_setting_fields(
                    $this->setting_field(__('Search page:', 'wpfs'), false, 'separator'),
                    $this->setting_field(__('Show search page', 'wpfs'), 'search.active', 'checkbox', ['default_value' => true]),
                    $this->setting_field(__('Title', 'wpfs'), 'search.title', 'text', ['parent' => 'search.active']),
                    $this->setting_field(__('Meta description', 'wpfs'), 'search.meta_desc', 'textarea', ['parent' => 'search.active']),
                    $this->setting_field(__('Keywords', 'wpfs'), 'search.keywords', 'text', ['parent' => 'search.active']),
                ),

                $this->group_setting_fields(
                    $this->setting_field(__('404 page:', 'wpfs'), false, 'separator'),
                    $this->setting_field(__('Title', 'wpfs'), 'E404.title')
                ),
            );

            $fields['social'] = $this->group_setting_fields(
                $this->group_setting_fields(
                    $this->setting_field(__('General', 'wpfs'), false, 'separator'),
                    $this->setting_field(__('Use same username', 'wpfs'), 'social.sameUsername.enable', 'checkbox', ['default_value' => false]),
                    $this->setting_field(__('Username', 'wpfs'), 'social.sameUsername.username', 'text', ['depend' => 'social.sameUsername.enable']),
                ),
                $this->group_setting_fields(
                    $this->setting_field(__('Facebook:', 'wpfs'), false, 'separator'),
                    $this->setting_field(__('Active', 'wpfs'), 'social.facebook.enable', 'checkbox', ['depend' => 'social.sameUsername.enable', 'default_value' => true]),
                    $this->setting_field(__('Profile name or url', 'wpfs'), 'social.facebook.url', 'text', ['depend' => '!social.sameUsername.enable']),
                    $this->setting_field(__('Confirmation code', 'wpfs'), 'social.facebook.code'),
                ),
                $this->group_setting_fields(
                    $this->setting_field(__('Twitter:', 'wpfs'), false, 'separator'),
                    $this->setting_field(__('Active', 'wpfs'), 'social.twitter.enable', 'checkbox', ['depend' => 'social.sameUsername.enable', 'default_value' => true]),
                    $this->setting_field(__('Profile name or url', 'wpfs'), 'social.twitter.url', 'text', ['depend' => '!social.sameUsername.enable']),
                    $this->setting_field(__('Confirmation code', 'wpfs'), 'social.twitter.code'),
                ),
                $this->group_setting_fields(
                    $this->setting_field(__('Linkedin:', 'wpfs'), false, 'separator'),
                    $this->setting_field(__('Active', 'wpfs'), 'social.linkedin.enable', 'checkbox', ['depend' => 'social.sameUsername.enable', 'default_value' => true]),
                    $this->setting_field(__('Profile name or url', 'wpfs'), 'social.linkedin.url', 'text', ['depend' => '!social.sameUsername.enable']),
                ),
                $this->group_setting_fields(
                    $this->setting_field(__('Pinterest:', 'wpfs'), false, 'separator'),
                    $this->setting_field(__('Active', 'wpfs'), 'social.pinterest.enable', 'checkbox', ['depend' => 'social.sameUsername.enable', 'default_value' => true]),
                    $this->setting_field(__('Profile name or url', 'wpfs'), 'social.pinterest.url', 'text', ['depend' => '!social.sameUsername.enable']),
                    $this->setting_field(__('Confirmation code', 'wpfs'), 'social.pinterest.code'),
                ),
                $this->group_setting_fields(
                    $this->setting_field(__('Instagram:', 'wpfs'), false, 'separator'),
                    $this->setting_field(__('Active', 'wpfs'), 'social.instagram.enable', 'checkbox', ['depend' => 'social.sameUsername.enable', 'default_value' => true]),
                    $this->setting_field(__('Profile name or url', 'wpfs'), 'social.instagram.url', 'text', ['depend' => '!social.sameUsername.enable']),
                ),
                $this->group_setting_fields(
                    $this->setting_field(__('YouTube:', 'wpfs'), false, 'separator'),
                    $this->setting_field(__('Active', 'wpfs'), 'social.youtube.enable', 'checkbox', ['depend' => 'social.sameUsername.enable', 'default_value' => false]),
                    $this->setting_field(__('Profile name or url', 'wpfs'), 'social.youtube.url', 'text', ['depend' => '!social.sameUsername.enable']),
                ),
                $this->group_setting_fields(
                    $this->setting_field(__('Tumblr:', 'wpfs'), false, 'separator'),
                    $this->setting_field(__('Active', 'wpfs'), 'social.tumblr.enable', 'checkbox', ['depend' => 'social.sameUsername.enable', 'default_value' => false]),
                    $this->setting_field(__('Profile name or url', 'wpfs'), 'social.tumblr.url', 'text', ['depend' => '!social.sameUsername.enable']),
                ),
                $this->group_setting_fields(
                    $this->setting_field(__('Yelp:', 'wpfs'), false, 'separator'),
                    $this->setting_field(__('Active', 'wpfs'), 'social.yelp.enable', 'checkbox', ['depend' => 'social.sameUsername.enable', 'default_value' => false]),
                    $this->setting_field(__('Profile name or url', 'wpfs'), 'social.yelp.url', 'text', ['depend' => '!social.sameUsername.enable']),
                ),
                $this->group_setting_fields(
                    $this->setting_field(__('SoundCloud:', 'wpfs'), false, 'separator'),
                    $this->setting_field(__('Active', 'wpfs'), 'social.soundcloud.enable', 'checkbox', ['depend' => 'social.sameUsername.enable', 'default_value' => false]),
                    $this->setting_field(__('Profile name or url', 'wpfs'), 'social.soundcloud.url', 'text', ['depend' => '!social.sameUsername.enable']),
                ),
                $this->group_setting_fields(
                    $this->setting_field(__('WikiPedia:', 'wpfs'), false, 'separator'),
                    $this->setting_field(__('Active', 'wpfs'), 'social.wikipedia.enable', 'checkbox', ['depend' => 'social.sameUsername.enable', 'default_value' => false]),
                    $this->setting_field(__('Profile name or url', 'wpfs'), 'social.wikipedia.url', 'text', ['depend' => '!social.sameUsername.enable'])
                )
            );

            $fields['webmaster'] = $this->group_setting_fields(
                $this->group_setting_fields(
                    $this->setting_field(__('Google:', 'wpfs'), false, 'separator'),
                    $this->setting_field(__('Verification code', 'wpfs'), 'webmaster.google.vercode'),
                ),
                $this->group_setting_fields(
                    $this->setting_field(__('Bing:', 'wpfs'), false, 'separator'),
                    $this->setting_field(__('Verification code', 'wpfs'), 'webmaster.bing.vercode'),
                ),
                $this->group_setting_fields(
                    $this->setting_field(__('Yandex:', 'wpfs'), false, 'separator'),
                    $this->setting_field(__('Verification code', 'wpfs'), 'webmaster.yandex.vercode'),
                ),
                $this->group_setting_fields(
                    $this->setting_field(__('Baidu:', 'wpfs'), false, 'separator'),
                    $this->setting_field(__('Verification code', 'wpfs'), 'webmaster.baidu.vercode')
                ),
            );

            $fields['archives'] = $this->group_setting_fields(
                $this->group_setting_fields(
                    $this->setting_field(__('Date archive:', 'wpfs'), false, 'separator'),
                    $this->setting_field(__('Status', 'wpfs'), 'archives.date.active', 'checkbox', ['default_value' => false]),
                    $this->setting_field(__('Show date archive in search results', 'wpfs'), 'archives.date.show', 'checkbox', ['default_value' => false, 'parent' => 'archives.date.active']),
                    $this->setting_field(__('Title', 'wpfs'), 'archives.date.title', 'text', ['parent' => 'archives.date.active']),
                    $this->setting_field(__('Meta description', 'wpfs'), 'archives.date.meta_desc', 'textarea', ['parent' => 'archives.date.active']),
                    $this->setting_field(__('Keywords', 'wpfs'), 'archives.date.keywords', 'text', ['parent' => 'archives.date.active']),
                ),
                $this->group_setting_fields(
                    $this->setting_field(__('Author archive:', 'wpfs'), false, 'separator'),
                    $this->setting_field(__('Status', 'wpfs'), 'archives.author.active', 'checkbox', ['default_value' => true]),
                    $this->setting_field(__('Show author archive in search results', 'wpfs'), 'archives.author.show', 'checkbox', ['default_value' => true, 'parent' => 'archives.author.active']),
                    $this->setting_field(__('Title', 'wpfs'), 'archives.author.title', 'text', ['parent' => 'archives.author.active']),
                    $this->setting_field(__('Meta description', 'wpfs'), 'archives.author.meta_desc', 'textarea', ['parent' => 'archives.author.active']),
                    $this->setting_field(__('Keywords', 'wpfs'), 'archives.author.keywords', 'text', ['parent' => 'archives.author.active'])
                ),
            );

            $fields['schema.org'] = $this->group_setting_fields(
                $this->setting_field(__('Schema settings:', 'wpfs'), false, 'separator'),
                $this->setting_field(__('Active', 'wpfs'), 'schema.enabled', 'checkbox', ['default_value' => true]),
                $this->setting_field(__('Organization', 'wpfs'), 'schema.organization.is', 'checkbox', ['parent' => 'schema.enabled', 'default_value' => false]),
                $this->setting_field(__('Organization Type', 'wpfs'), 'schema.organization.type', 'dropdown', ['parent' => 'schema.organization.is', 'default_value' => 'Corporation', 'list' => [
                    "Airline",
                    "Consortium",
                    "Corporation",
                    "EducationalOrganization",
                    "FundingScheme",
                    "GovernmentOrganization",
                    "LibrarySystem",
                    "LocalBusiness",
                    "MedicalOrganization",
                    "NewsMediaOrganization",
                    "OnlineBusiness",
                    "PerformingGroup",
                    "Project",
                    "ResearchOrganization",
                    "SearchRescueOrganization",
                    "SportsOrganization",
                    "WorkersUnion"
                ]]),
                $this->setting_field(__('Name', 'wpfs'), 'schema.organization.name', 'text', ['parent' => 'schema.organization.is']),
                $this->setting_field(__('Description', 'wpfs'), 'schema.organization.description', 'text', ['parent' => 'schema.organization.is']),
                $this->setting_field(__('Address', 'wpfs'), 'schema.organization.address', 'text', ['parent' => 'schema.organization.is']),
                $this->setting_field(__('Founders (comma separated)', 'wpfs'), 'schema.organization.founder', 'text', ['parent' => 'schema.organization.is']),
                $this->setting_field(__('Logo', 'wpfs'), 'schema.organization.logo', 'upload-input', ['parent' => 'schema.organization.is', 'placeholder' => __('Paste your image URL or select a new image', 'wpfs')]),
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
                $this->setting_field(__('Phone', 'wpfs'), 'schema.organization.phone', 'text', ['parent' => 'schema.organization.is']),
                $this->setting_field(__('E-mail', 'wpfs'), 'schema.organization.email', 'text', ['parent' => 'schema.organization.is']),
                $this->setting_field(__('Enable Sitelinks Search Box', 'wpfs'), 'schema.sitelink', 'checkbox', ['parent' => 'schema.enabled', 'default_value' => false])
            );

            //settings for each taxonomy
            foreach (get_taxonomies(array('public' => true), 'objects') as $tax_type_object) {

                $field_name = "tax.$tax_type_object->name";

                $fields[$tax_type_object->name] = $this->group_setting_fields(
                    $this->group_setting_fields(
                        $this->setting_field(ucwords($tax_type_object->label) . ($tax_type_object->_builtin ? "" : " ({$tax_type_object->name})"), false, 'separator'),
                        $this->setting_field(__('Status', 'wpfs'), "$field_name.active", 'checkbox', ['default_value' => true]),
                        $this->setting_field(sprintf(__("Show \"<i>%s</i>\" in search results", 'wpfs'), $tax_type_object->label), "$field_name.show", 'checkbox', ['default_value' => true, 'parent' => "$field_name.active"]),
                        $this->setting_field(__('Title', 'wpfs'), "$field_name.title", 'text', ['default_value' => '%%title%%', 'parent' => "$field_name.active"]),
                        $this->setting_field(__('Meta description', 'wpfs'), "$field_name.meta_desc", 'textarea', ['parent' => "$field_name.active"]),
                        $this->setting_field(__('Keywords', 'wpfs'), "$field_name.keywords", 'text', ['parent' => "$field_name.active"]),
                        $this->setting_field(__('Remove type prefix', 'wpfs'), "$field_name.prefix", 'checkbox', ['default_value' => false, 'parent' => "$field_name.active"])
                    )
                );
            }

            //settings for each post type
            foreach (get_post_types(array('public' => true), 'objects') as $post_type_object) {

                $post_type = $post_type_object->name;

                $post_type_sanitized = str_replace(array('-', '_'), ' ', $post_type);

                if ($post_type_object->has_archive) {

                    $archive_name = ucwords($post_type_object->label) . ($post_type_object->_builtin ? "" : " ({$post_type_object->name})");

                    $fields['archives'][] = $this->group_setting_fields(
                        $this->setting_field(sprintf(__('%s archive:', 'wpfs'), $archive_name), false, 'separator'),
                        $this->setting_field(__('Status', 'wpfs'), "archives.$post_type.active", 'checkbox', ['default_value' => true]),
                        $this->setting_field(sprintf(__('Show %s archive in search results', 'wpfs'), $archive_name), "archives.$post_type.show", 'checkbox', ['default_value' => true, 'parent' => "archives.$post_type.active"]),
                        $this->setting_field(__('Title', 'wpfs'), "archives.$post_type.title", 'text', ['parent' => "archives.$post_type.active"]),
                        $this->setting_field(__('Meta description', 'wpfs'), "archives.$post_type.meta_desc", 'textarea', ['parent' => "archives.$post_type.active"]),
                        $this->setting_field(__('Keywords', 'wpfs'), "archives.$post_type.keywords", 'text', ['parent' => "archives.$post_type.active"])
                    );
                }

                $fields[$post_type] = $this->group_setting_fields(
                    $this->group_setting_fields(
                        $this->setting_field(ucwords($post_type_object->label) . ($post_type_object->_builtin ? "" : " ($post_type_object->name)"), false, 'separator'),
                        $this->setting_field(sprintf(__("Show <i>\"%s\"</i> in search results", 'wpfs'), $post_type_sanitized), "post_type.$post_type.show", 'checkbox', ['default_value' => true]),
                        $this->setting_field(__("Follow link directive", 'wpfs'), "post_type.$post_type.follow", 'checkbox', ['default_value' => true]),
                        $this->setting_field(__('Title', 'wpfs'), "post_type.$post_type.title", 'text', ['default_value' => '%%title%%']),
                        $this->setting_field(__('Meta description', 'wpfs'), "post_type.$post_type.meta_desc", 'textarea'),
                        $this->setting_field(__('Keywords', 'wpfs'), "post_type.$post_type.keywords"),
                        $post_type === 'page' ?
                            $this->setting_field(sprintf(__('Predefined %s schema.org type', 'wpfs'), $post_type_sanitized), "post_type.$post_type.schema.PageType", 'dropdown', ['list' => Schema::$webPageGraphs, 'default_value' => 'WebPage']) :
                            $this->setting_field(sprintf(__('Predefined %s schema.org type', 'wpfs'), $post_type_sanitized), "post_type.$post_type.schema.ArticleType", 'dropdown', ['list' => Schema::$webArticleGraphs, 'default_value' => 'Article']),
                    )
                );
            }
        }

        return $this->group_setting_sections($fields, $filter);
    }

    /**
     * Handle the gui for exec-sql panel
     */
    public function render_replacers(): string
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
                <td><strong>%%search%%</strong></td>
                <td><?php echo __('The search query.', 'wpfs'); ?></td>
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
                <td><strong>%%excerpt%%</strong></td>
                <td><?php echo __('The excerpt of the current queried post.', 'wpfs'); ?></td>
            </tr>
            <tr>
                <td><strong>%%title%%</strong></td>
                <td><?php echo __('The default WordPress title of the current queried object.', 'wpfs'); ?></td>
            </tr>
            <tr>
                <td><strong>%%found_post%%</strong></td>
                <td><?php echo __('The number of found posts.', 'wpfs'); ?></td>
            </tr>
            <tr>
                <td><strong>%%pagetotal%%</strong></td>
                <td><?php echo __('The total number of pages for the current query.', 'wpfs'); ?></td>
            </tr>
            <tr>
                <td><strong>%%pagenumber%%</strong></td>
                <td><?php echo __('The current page number for the current query.', 'wpfs'); ?></td>
            </tr>
            <tr>
                <td><strong>%%language%%</strong></td>
                <td><?php echo __('The site language.', 'wpfs'); ?></td>
            </tr>
            <tr>
                <td><strong>%%date%%</strong></td>
                <td><?php echo __('Current date in Y-m-d format.', 'wpfs'); ?></td>
            </tr>
            <tr>
                <td><strong>%%time%%</strong></td>
                <td><?php echo __('Current time in H:i:s format.', 'wpfs'); ?></td>
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

    public function restricted_access($context = 'settings'): bool
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

    protected function init(): void
    {
        require_once WPFS_MODULES . 'seo/WPFS_SEO.php';

        WPFS_SEO::Init();
    }
}

return __NAMESPACE__;