<?php
/**
 * @author    sh1zen
 * @copyright Copyright (C) 2025.
 * @license   http://www.gnu.org/licenses/gpl.html GNU/GPL
 */

namespace FlexySEO\Engine;

use FlexySEO\Engine\Generators\Schema;
use FlexySEO\Engine\Helpers\SEOScriptTag;
use FlexySEO\Engine\Helpers\SEOTag;

use WPS\core\Rewriter;

class Presenter
{
    private Generator $generator;

    /**
     * Array containing the tags
     *
     * @var SEOTag[]
     */
    private array $tags;

    /**
     * Array containing the script tags
     *
     * @var SEOScriptTag[]
     */
    private array $scripts = [];

    /**
     * Current number of tags
     */
    private int $tag_index = 0;

    /**
     * HTML code of the tag template. {{name}} will be replaced by the variable's name and {{value}} with its value.
     */
    private array $templates;

    /**
     * @param Generator $generator
     */
    public function __construct(Generator $generator)
    {
        $this->generator = $generator;

        $this->templates = array(
            'meta'      => "<meta property='{{name}}' content='{{value}}'/>",
            'meta_name' => "<meta name='{{name}}' content='{{value}}'/>",
            'link'      => "<link rel='{{name}}' href='{{value}}'>"
        );
    }

    public function build()
    {
        $this->redirect();

        // Filter the title for compatibility with other plugins and themes.
        if (wps('wpfs')->settings->get('seo.title.rewrite', true)) {
            add_filter('wp_title', array($this, 'filter_title'), 10, 1);
            add_filter('the_title', array($this, 'filter_title'), 10, 1);
        }

        // canonical, rel_prev, rel_next
        $this->link_presenter();
        $this->robots_presenter();

        $this->keywords_presenter();
        $this->description_presenter();

        $this->social_presenter();
        $this->verification_codes_presenter();

        if (wps('wpfs')->settings->get('seo.schema.enabled', false)) {
            $this->schema_presenter();
        }

        add_action('wp_head', array($this, 'wp_head'), 1);
    }

    public function redirect()
    {
        list($url, $status) = $this->generator->redirect();

        $url = apply_filters('wpfs_redirect', $url, $status, $this->generator->getContext()->get_page_type(), $this->generator->getContext());

        if ($url) {
            Rewriter::getInstance()->redirect($url, $status);
        }
    }

    /**
     * Prepare the canonical tag.
     */
    private function link_presenter()
    {
        /**
         * Present canonical link
         */
        $canonical = $this->generator->generate_canonical();

        if ($canonical) {
            $this->add_tag('canonical', $canonical, 'link');
        }

        /**
         * present previous page
         */
        $prev = $this->generator->generate_rel_prev();

        if (!empty($prev)) {
            $this->add_tag('rel:prev', $prev, 'link');
        }

        /**
         * present next page
         */
        $next = $this->generator->generate_rel_next();

        if (!empty($next)) {
            $this->add_tag('rel:next', $next, 'link');
        }
    }

    private function add_tag($name, $value, $template = 'meta')
    {
        $this->tags[$name . '#' . $this->tag_index++] = new SEOTag($name, $value, $template);
    }

    /**
     * Prepare the robots tag.
     */
    public function robots_presenter()
    {
        $robots = $this->generator->get_robots();

        $this->add_tag('robots', implode(", ", $robots), 'meta_name');
    }

    /**
     * Prepare the keywords tag.
     */
    private function keywords_presenter()
    {
        $keywords = $this->generator->get_keywords();

        $this->add_tag('keywords', $keywords);
    }

    /**
     * Prepare the description tag.
     */
    private function description_presenter()
    {
        $description = $this->generator->get_description();

        $this->add_tag('description', $description, 'meta_name');
    }

    /**
     * Present all openGraph metas.
     */
    private function social_presenter()
    {
        $og = $this->generator->openGraph();

        $this->add_tags($og->get_tags());

        $tc = $this->generator->twitterCard();

        foreach ($tc->get_tags() as $tag_name => $tag_value) {
            $this->add_tag($tag_name, $tag_value, 'meta_name');
        }
    }

    private function add_tags($tags)
    {
        foreach ($tags as $tag) {
            $this->tags[$tag->name . '#' . $this->tag_index++] = $tag;
        }
    }

    private function verification_codes_presenter()
    {
        $codes = $this->generator->site_verification_codes();

        foreach ($codes as $code_name => $code_value) {
            $this->add_tag($code_name, $code_value, 'meta_name');
        }
    }

    private function schema_presenter(): void
    {
        $schema = new Schema($this->generator);

        $schema->build();

        $schemaGraph = $schema->export();

        $schemaGraph = apply_filters('wpfs_schema_presenter', $schemaGraph, $this->generator->getContext()->get_page_type(), $this->generator->getContext());

        $this->add_script(new SEOScriptTag($schemaGraph, "application/ld+json"));
    }

    private function add_script($script): void
    {
        $this->scripts[] = $script;
    }

    public function wp_head()
    {
        echo $this->render_tags(true);
        echo $this->render_scripts(true);
    }

    public function render_tags($filter_empty = false): string
    {
        $output = '';
        $vars = ['{{name}}', '{{value}}'];
        foreach ($this->tags as $tag) {

            if ($filter_empty and empty($tag->value))
                continue;

            $output .= str_replace($vars, [$tag->name, $tag->value], $this->templates[$tag->template]);
        }

        return $output;
    }

    public function render_scripts($filter_empty = false): string
    {
        $output = '';
        foreach ($this->scripts as $script) {

            if ($filter_empty and empty($script->content))
                continue;

            $output .= "<script type='$script->template'>$script->content</script>";
        }

        return $output;
    }

    public function filter_title($title = ''): string
    {
        return $this->generator->generate_title($title);
    }
}