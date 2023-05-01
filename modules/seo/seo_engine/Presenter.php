<?php
/**
 * @author    sh1zen
 * @copyright Copyright (C) 2023.
 * @license   http://www.gnu.org/licenses/gpl.html GNU/GPL
 */

namespace FlexySEO\Engine;

use FlexySEO\Engine\Generators\Schema;
use FlexySEO\Engine\Helpers\SEOScriptTag;
use FlexySEO\Engine\Helpers\SEOTag;

class Presenter
{
    /**
     * @var Indexable
     */
    public $indexable;

    /**
     * @var Generator
     */
    private $generator;

    /**
     * Array containing the tags
     *
     * @var SEOTag[]
     */
    private $tags;

    /**
     * Array containing the script tags
     *
     * @var SEOScriptTag[]
     */
    private $scripts;

    /**
     * Current number of tags
     *
     * @var Int
     */
    private $tag_index = 0;

    /**
     * HTML code of the tag template. {{name}} will be replaced by the variable's name and {{value}} with its value.
     *
     * @var string
     */
    private $templates;

    /**
     * @param Generator $generator
     */
    public function __construct(Generator $generator)
    {
        $this->generator = $generator;

        $this->indexable = new Indexable($generator->getContext());

        $this->templates = array(
            'meta'      => "<meta property='{{name}}' content='{{value}}' />",
            'meta_name' => "<meta name='{{name}}' content='{{value}}' />",
            'link'      => "<link rel='{{name}}' href='{{value}}'>"
        );
    }

    public function build()
    {
        $this->redirect();

        // Filter the title for compatibility with other plugins and themes.
        if (shzn('wpfs')->settings->get('seo.title.rewrite', true)) {
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

        if (shzn('wpfs')->settings->get('seo.schema.enabled', false)) {
            $this->schema_presenter();
        }

        add_action('wp_head', array($this, 'wp_head'), 1);
    }

    public function redirect()
    {
        list($url, $status) = $this->generator->redirect();

        $url = apply_filters('wpfs_redirect', $url, $status, $this->indexable);

        if ($url) {
            Rewriter::redirect($url, $status);
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

        $canonical = apply_filters('wpfs_canonical', $canonical, $this->indexable);

        if ($canonical) {
            $this->add_tag('canonical', $canonical, 'link');
        }

        /**
         * present previous page
         */
        $prev = $this->generator->generate_rel_prev();

        $prev = apply_filters('wpfs_relprev', $prev, $this->indexable);

        if (!empty($prev)) {
            $this->add_tag('rel:prev', $prev, 'link');
        }

        /**
         * present next page
         */
        $next = $this->generator->generate_rel_next();

        $next = apply_filters('wpfs_relnext', $next, $this->indexable);

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

        $robots = apply_filters('wpfs_robots', $robots, $this->indexable);

        $this->add_tag('robots', implode(", ", $robots), 'meta_name');
    }

    /**
     * Prepare the keywords tag.
     */
    private function keywords_presenter()
    {
        $keywords = $this->generator->get_keywords();

        $keywords = apply_filters('wpfs_keywords', $keywords, $this->indexable);

        $this->add_tag('keywords', implode(", ", (array)$keywords));
    }

    /**
     * Prepare the description tag.
     */
    private function description_presenter()
    {
        $description = $this->generator->get_description();

        $description = apply_filters('wpfs_description', $description, $this->indexable);

        $this->add_tag('description', implode(", ", (array)$description), 'meta_name');
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

    private function schema_presenter()
    {
        $schema = new Schema($this->generator);

        $schema->build();

        $schemaGraph = $schema->export();

        $schemaGraph = apply_filters('wpfs_schema', $schemaGraph, $this->indexable);

        $this->add_script(new SEOScriptTag($schemaGraph, "application/ld+json"));
    }

    private function add_script($script)
    {
        $this->scripts[] = $script;
    }

    public function wp_head()
    {
        echo $this->render_tags(true);
        echo $this->render_scripts(true);
    }

    public function render_tags($filter_empty = false)
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

    public function render_scripts($filter_empty = false)
    {
        $output = '';
        foreach ($this->scripts as $script) {

            if ($filter_empty and empty($script->content))
                continue;

            $output .= "<script type='{$script->template}'>{$script->content}</script>";
        }

        return $output;
    }

    public function filter_title($title = '')
    {
        $title = $this->generator->generate_title();

        return apply_filters("wpfs_title", $title);
    }
}