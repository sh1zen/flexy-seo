<?php
/**
 * @author    sh1zen
 * @copyright Copyright (C) 2024.
 * @license   http://www.gnu.org/licenses/gpl.html GNU/GPL
 */

namespace FlexySeo\core;

use WPS\core\StringHelper;
use WPS\core\UtilEnv;

/**
 * Creates the menu page for the plugin.
 *
 * Provides the functionality necessary for rendering the page corresponding
 * to the menu with which this page is associated.
 */
class PagesHandler
{
    public function __construct()
    {
        add_action('admin_menu', array($this, 'add_plugin_pages'));
        add_action('admin_enqueue_scripts', array($this, 'register_assets'));

        add_action("admin_print_styles-post.php", array($this, 'enqueue_scripts_edit_page'));
        add_action('admin_print_styles-post-new.php', array($this, 'enqueue_scripts_edit_page'));

        //add_action('admin_notices', [$this, 'notice'], 10, 0);
    }

    public function notice()
    {
        global $pagenow;

        if (isset($_GET['wpfs-dismiss-notice'])) {

            wps('wpfs')->options->add(wps_utils()->get_cuID(), 'dismissed', true, 'admin-notice', MONTH_IN_SECONDS);
        }
        elseif ($pagenow == 'index.php' and !wps('wpfs')->options->get(wps_utils()->get_cuID(), 'dismissed', 'admin-notice', false)) {
            ?>
            <div class="notice notice-info is-dismissible">
                <h3>Help me to build <a href="<?php echo admin_url('admin.php?page=wp-flexyseo'); ?>">Flexy SEO</a>.
                </h3>
                <p><?php echo sprintf(__("Buy me a coffe <a href='%s'>here</a> or leave a review <a href='%s'>here</a>.", 'wpfs'), "https://www.paypal.com/donate?business=dev.sh1zen%40outlook.it&item_name=Thank+you+in+advanced+for+the+kind+donations.+You+will+sustain+me+developing+Flexy+SEO.&currency_code=EUR", "https://wordpress.org/support/plugin/flexy-seo/reviews/?filter=5"); ?></p>
                <a href="?wpfs-dismiss-notice"><?php echo __('Dismiss', 'wpfs') ?></a>
            </div>
            <?php
        }
    }

    public function add_plugin_pages()
    {
        add_menu_page('Flexy SEO', 'Flexy SEO', 'customize', 'wp-flexyseo', array($this, 'render_main'), 'dashicons-admin-site');

        /**
         * Modules - sub pages
         */
        foreach (wps('wpfs')->moduleHandler->get_modules(array('scopes' => 'admin-page')) as $module) {

            add_submenu_page('wp-flexyseo', 'WPFS ' . $module['name'], $module['name'], 'customize', $module['slug'], array($this, 'render_module'));
        }

        /**
         * Plugin core settings
         */
        add_submenu_page('wp-flexyseo', __('WPFS Settings', 'wpfs'), __('Settings', 'wpfs'), 'manage_options', 'wpfs-settings', array($this, 'render_core_settings'));

        /**
         * Plugin core settings
         */
        add_submenu_page('wp-flexyseo', __('WPFS FAQ', 'wpfs'), __('FAQ', 'wpfs'), 'edit_posts', 'wpfs-faqs', array($this, 'render_faqs'));
    }

    public function render_modules_settings()
    {
        $this->enqueue_scripts();

        wps('wpfs')->settings->render_modules_settings();
    }

    public function enqueue_scripts()
    {
        wp_enqueue_style('wpfs_css');
        wp_enqueue_script('vendor-wps-js');
    }

    public function enqueue_scripts_edit_page()
    {
        wp_enqueue_style('wpfs_css');
        wp_enqueue_script('vendor-wps-js');
    }

    public function render_core_settings()
    {
        $this->enqueue_scripts();

        wps('wpfs')->settings->render_core_settings();
    }

    public function render_module()
    {
        $module_slug = StringHelper::sanitize_text($_GET['page']);

        $object = wps('wpfs')->moduleHandler->get_module_instance($module_slug);

        if (is_null($object)) {
            return;
        }

        $this->enqueue_scripts();

        $object->render_admin_page();
    }

    public function register_assets()
    {
        $assets_url = PluginInit::getInstance()->plugin_base_url;

        $min = wps_utils()->online ? '.min' : '';

        wp_register_style('wpfs_css', "{$assets_url}assets/style{$min}.css", ['vendor-wps-css']);

        wps_localize([
            'text_na' => __('N/A', 'wpfs'),
            'saved'   => __('Settings Saved', 'wpfs'),
            'error'   => __('Request fail', 'wpfs'),
            'success' => __('Request succeed', 'wpfs'),
            'running' => __('Running', 'wpfs'),
        ]);
    }

    public function render_faqs()
    {
        $this->enqueue_scripts();
        ?>
        <section class="wps-wrap">
            <block class="wps">
                <section class='wps-header'><h1>FAQ</h1></section>
                <br>
                <div class="wps-faq-list">
                    <div class="wps-faq-item">
                        <div class="wps-faq-question-wrapper ">
                            <div
                                    class="wps-faq-question wps-collapse-handler"><?php echo __('How Flexy-Breadcrumbs works?', 'wpfs') ?>
                                <icon class="wps-collapse-icon">+</icon>
                            </div>
                            <div class="wps-faq-answer wps-collapse">
                                <p><?php echo __('To use Breadcrumbs put this code in your theme, exactly where you want breadcrumbs to be displayed.', 'wpfs'); ?></p>
                                <code>&lt;?php if(function_exists('wpfs_breadcrumb')) wpfs_breadcrumb($pre='', $after=''); ?&gt;</code><br>
                                <p><?php echo __('You can use your own $pre and $after wrapper, default value is no wrapper.', 'wpfs'); ?></p>
                                <strong><?php echo __('Options:', 'wpfs'); ?></strong>
                                <ul class="wps-list">
                                    <li><?php echo __('In Breadcrumb page configure your options and set up your breadcrumb structure for each entity.', 'wpfs'); ?></li>
                                    <li><?php echo __('With flexed breadcrumb is possible to personalize the crumb structure with personalized url or text:', 'wpfs'); ?>
                                        <ul class="wps-list">
                                            <li>
                                                <strong><?php echo __('>>', 'wpfs'); ?></strong> : <?php echo __('separate each crumb', 'wpfs'); ?>
                                            </li>
                                            <li>
                                                <strong>[<?php echo __('Text', 'wpfs'); ?>]</strong> : <?php echo __('Text will be considered only for the breadcrumb text.', 'wpfs'); ?>
                                            </li>
                                            <li>
                                                <strong>(<?php echo __('Text', 'wpfs'); ?>)</strong> : <?php echo __('Text will be considered only for the breadcrumb link.', 'wpfs'); ?>
                                            </li>
                                            <li>
                                                <strong><?php echo __('/custom/link/', 'wpfs'); ?></strong> : <?php echo __('Specify a custom link structure.', 'wpfs'); ?>
                                            </li>
                                            <li>
                                                <strong>%%{query_var}%%</strong> : <?php echo __('Replace the value of the corresponding query_var specified, if not available it will be discarded.', 'wpfs'); ?>
                                            </li>
                                            <li>
                                                <strong>%%meta_{var}%%</strong> : <?php echo __('Replace the meta variable of the current queried object, if not available it will be discarded.', 'wpfs'); ?>
                                            </li>
                                            <li>
                                                <strong>%%home%%</strong> : <?php echo __('Replace the home text and home url.', 'wpfs'); ?>
                                            </li>
                                            <li>
                                                <strong>%%taxonomy%%</strong>,
                                                <strong>%%category%%</strong>: <?php echo __('Replace the taxonomy, or the category of the current queried object. If it\'s hierarchical will generate multiple crumbs.', 'wpfs'); ?>
                                            </li>
                                            <li>
                                                <strong>%%taxonomy-full%%</strong>,
                                                <strong>%%category-full%%</strong>: <?php echo __('Same as previous but each term link contain also the the taxonomy prefix.', 'wpfs'); ?>
                                            </li>
                                            <li>
                                                <strong>%%post_parent%%</strong> : <?php echo __('Replace the parent post of the current queried object.', 'wpfs'); ?>
                                            </li>
                                            <li>
                                                <strong>%%post_type%%</strong> : <?php echo __('Replace the post type of the current queried object.', 'wpfs'); ?>
                                            </li>
                                            <li>
                                                <strong>%%queried_object%%</strong> : <?php echo __('Replace the title and link of the current queried object.', 'wpfs'); ?>
                                            </li>
                                        </ul>
                                        <br>
                                        <em><strong><?php echo __('Example: %%home%% >> (/%%meta_var%%-%%custom_var%%/)%%category%% >> %%queried_object%%', 'wpfs'); ?></strong></em>
                                        <br>
                                        <br>
                                    </li>
                                    <li><?php echo __('Filter <em>wpfs_breadcrumb_style</em> to change the default css applied', 'wpfs'); ?></li>
                                    <li><?php echo __('Filter <em>wpfs_breadcrumb_replacements</em> to add or change replacements based on flexed breadcrumbs, if enabled.<br><strong>return format must be array(array(\'text\' =>, \'url\' => ))</strong>', 'wpfs'); ?></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <div class="wps-faq-item">
                        <div class="wps-faq-question-wrapper ">
                            <div
                                    class="wps-faq-question wps-collapse-handler"><?php echo __('How Flexy-SEO works?', 'wpfs') ?>
                                <icon class="wps-collapse-icon">+</icon>
                            </div>
                            <div class="wps-faq-answer wps-collapse">
                                <p><?php echo __('Uses replacements to construct the perfect description or keywords for each page.', 'wpfs'); ?></p>
                                <br>
                                <?php echo sprintf(__('See about replacements <a href="%s">here</a>.', 'wpfs'), admin_url('admin.php?page=seo#seo-vars')); ?>
                            </div>
                        </div>
                    </div>
                </div>
            </block>
        </section>
        <?php
    }

    /**
     * This function renders the contents of the page associated with the menu
     * that invokes the render method. In the context of this plugin, this is the
     * menu class.
     */
    public function render_main()
    {
        global $wpdb;

        if (isset($_POST['wpfs-clear-cache'])) {

            if (UtilEnv::verify_nonce('wpfs-nonce')) {
                $wpdb->delete("{$wpdb->prefix}flexy_seo", ['context' => 'cache']);
            }
        }
        $this->enqueue_scripts();

        settings_errors();

        $conf = UtilEnv::array_flatter(wps('wpfs')->settings->get());

        $conf_level = round((count(array_filter($conf)) / count($conf)) * 100);

        ?>
        <section class="wps-wrap-flex wps-wrap wps-home">
            <section class="wps">
                <block class="wps">
                    <block class="wps-header">
                        <h1>Flexy SEO</h1>
                    </block>
                    <h2><?php _e('Configuration:', 'wpfs'); ?></h2>
                    <p>
                        <?php
                        $color = $conf_level > 75 ? '#00e045' : ($conf_level > 45 ? '#e7f100' : '#f10000');
                        echo "<div class='wps-progressbarCircle' data-percent='{$conf_level}' data-stroke='2' data-size='155' data-color='{$color}'></div>";
                        echo '<div class="wps-highlighted">' . sprintf(__('Configure SEO options: <a href="%s">here</a>.', 'wpfs'), admin_url('admin.php?page=seo')) . '</div>';
                        echo '<div class="wps-highlighted">' . sprintf(__('Configure Breadcrumbs options: <a href="%s">here</a>.', 'wpfs'), admin_url('admin.php?page=breadcrumbs')) . '</div>';
                        ?>
                    </p>
                </block>
                <block class="wps">
                    <h2><?php _e('Options:', 'wpfs'); ?></h2>
                    <form method="POST">
                        <?php wp_nonce_field('wpfs-nonce'); ?>
                        <input name="wpfs-clear-cache" type="submit"
                               value="<?php _e('Reset Flexy SEO cache', 'wpfs') ?>"
                               class="button button-primary button-large">
                    </form>
                </block>
                <?php
                if (!is_plugin_active('wp-optimizer/wp-optimizer.php')) {
                    ?>
                    <block class="wps">
                        <h2><?php _e('Tips:', 'wpfs'); ?></h2>
                        <h3>
                            <?php
                            echo '<strong>' . __('For a better SEO optimization, it\'s recommended to install also <a href="https://wordpress.org/plugins/wp-optimizer/">this</a> plugin.', 'wpfs') . '</strong>';
                            ?>
                        </h3>
                    </block>
                    <?php
                }
                ?>
            </section>
            <aside class="wps">
                <section class="wps-box">
                    <div class="wps-donation-wrap">
                        <div
                                class="wps-donation-title"><?php _e('Support this project, buy me a coffee.', 'wpfs'); ?></div>
                        <br>
                        <a href="https://www.paypal.com/donate/?business=dev.sh1zen%40outlook.it&item_name=Thank+you+in+advanced+for+the+kind+donations.+You+will+sustain+me+developing+FlexySEO.&currency_code=EUR">
                            <img src="https://www.paypalobjects.com/en_US/IT/i/btn/btn_donateCC_LG.gif"
                                 title="PayPal - The safer, easier way to pay online!" alt="Donate with PayPal button"/>
                        </a>
                        <div class="wps-donation-hr"></div>
                        <div class="wps-donation-btc">
                            <div class="wps-donation-name">BTC:</div>
                            <p class="wps-donation-value">3QE5CyfTxb5kufKxWtx4QEw4qwQyr9J5eo</p>
                        </div>
                    </div>
                </section>
                <section class="wps-box">
                    <h3><?php _e('Want to support in other ways?', 'wpfs'); ?></h3>
                    <ul class="wps">
                        <li>
                            <a href="https://translate.wordpress.org/projects/wp-plugins/flexy-seo/"><?php _e('Help me translating', 'wpfs'); ?></a>
                        </li>
                        <li>
                            <a href="https://wordpress.org/support/plugin/flexy-seo/reviews/?filter=5"><?php _e('Leave a review', 'wpfs'); ?></a>
                        </li>
                    </ul>
                    <h3>Flexy SEO:</h3>
                    <ul class="wps">
                        <li>
                            <a href="https://github.com/sh1zen/flexy-seo/"><?php _e('Source code', 'wpfs'); ?></a>
                        </li>
                        <li>
                            <a href="https://sh1zen.github.io/"><?php _e('About me', 'wpfs'); ?></a>
                        </li>
                    </ul>
                </section>
            </aside>
        </section>
        <?php
    }
}