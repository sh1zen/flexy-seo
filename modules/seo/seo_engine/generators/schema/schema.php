<?php
/**
 * @author    sh1zen
 * @copyright Copyright (C) 2023.
 * @license   http://www.gnu.org/licenses/gpl.html GNU/GPL
 */

namespace FlexySEO\Engine\Generators;

use FlexySEO\Engine\Generator;

class Schema
{
    /**
     * All existing page graphs.
     */
    public static array $webPageGraphs = [
        'WebPage',
        'AboutPage',
        'CheckoutPage',
        'CollectionPage',
        'ContactPage',
        'FAQPage',
        'ItemPage',
        'MedicalWebPage',
        'ProfilePage',
        'QAPage',
        'RealEstateListing',
        'SearchResultsPage'
    ];

    /**
     * All existing article graphs.
     */
    public static array $webArticleGraphs = [
        'Article',
        'BlogPosting',
        'SocialMediaPosting',
        'NewsArticle',
        'AdvertiserContentArticle',
        'SatiricalArticle',
        'ScholarlyArticle',
        'TechArticle',
        'Report',
        'None'
    ];

    public array $nullableFields = [
        'price' // Needs to be 0 if free for Software Application.
    ];

    private array $schema;

    private array $graphs;

    private Generator $generator;

    public function __construct(Generator $generator)
    {
        $this->generator = $generator;

        $this->schema = [
            "@context" => "https://schema.org",
            "@graph"   => []
        ];

        $this->graphs = [];
    }

    public function build()
    {
        $this->load_graphs();

        $this->parse_graphs();
    }

    private function load_graphs()
    {
        if ($this->generator->getContext()->is_404()) {
            return;
        }

        $this->add('WebSite');

        if ($this->generator->getContext()->is_homepage()) {

            if (wps('wpfs')->settings->get("seo.schema.organization.is", false)) {
                $this->add('Organization');
            }

            if ($this->generator->getContext()->is_home_posts_page()) {
                $this->add('CollectionPage');
            }
            else {
                $this->add('WebPage');
            }
        }
        elseif ($this->generator->getContext()->is_posts_page() or $this->generator->getContext()->is_home_posts_page() or wpfseo('helpers')->ecommerce->isWooCommerceShopPage()) {

            $this->add('CollectionPage');
        }
        elseif ($this->generator->getContext()->is_simple_page()) {

            $this->add($this->getPostGraphs($this->generator->getContext()->get_queried_object()), 'page');
        }
        elseif ($this->generator->getContext()->is_author_archive()) {

            $this->add(['ProfilePage', 'Person']);
        }
        elseif ($this->generator->getContext()->is_search()) {

            $this->add('SearchResultsPage');
        }
        elseif ($this->generator->getContext()->is_post_type_archive() or $this->generator->getContext()->is_date_archive() or $this->generator->getContext()->is_term_archive()) {

            $this->add('CollectionPage');
        }

        $this->add('BreadcrumbList');
    }

    public function add($graphType)
    {
        if (is_array($graphType)) {

            foreach ($graphType as $graph) {
                $this->add($graph);
            }
            return;
        }

        $namespace = $this->get_graph_generator($graphType);

        if ($namespace) {

            //if graph is actually a fully qualified class name lets use it
            if (class_exists($graphType)) {
                $namespace = $graphType;
            }

            $graphBuild = (new $namespace($this->generator))->get($this->generator->getContext(), $graphType);

            $graphBuild = apply_filters("wpfs_schema_" . strtolower($graphType), $graphBuild, $this->generator->getContext());

            $this->graphs[] = $graphBuild->export();
        }
    }

    private function get_graph_generator($graphType)
    {
        $namespace = false;

        if (file_exists(WPFS_SEO_ENGINE . 'generators/schema/graph/' . $graphType . '.php')) {

            require_once WPFS_SEO_ENGINE . "generators/schema/graph/{$graphType}.php";

            if (class_exists("FlexySEO\Engine\Generators\Schema\Graphs\\$graphType")) {
                $namespace = "FlexySEO\Engine\Generators\Schema\Graphs\\$graphType";
            }
        }
        elseif (in_array($graphType, self::$webPageGraphs)) {

            $namespace = "FlexySEO\Engine\Generators\Schema\Graphs\WebPage";

        }
        elseif (in_array($graphType, self::$webArticleGraphs)) {

            $namespace = "FlexySEO\Engine\Generators\Schema\Graphs\Article";
        }

        return $namespace;
    }

    /**
     * Returns the graph types that are set for the current post.
     *
     * @param  $post \WP_Post  The post object.
     * @return string[] The graph name(s).
     */
    public function getPostGraphs($post): array
    {
        $postGraphs = [];

        if ($post instanceof \WP_Post) {

            $postGraphs[] = ucfirst(self::get_post_graphType($post));
        }
        else {
            $postGraphs[] = 'WebPage';
        }

        return $postGraphs;
    }

    /**
     * requires for page / article
     * return graphArticleType or graphPageType
     */
    public static function get_post_graphType($post)
    {
        $for = $post->post_type === 'page' ? 'Page' : 'Article';

        return wpfs_get_post_meta_graphType($post, false, '') ?: wps('wpfs')->settings->get("seo.post_type.{$post->post_type}.schema.{$for}Type", $post->post_type === 'page' ? "WebPage" : "Article");
    }

    private function parse_graphs()
    {
        $graphs = apply_filters('wpfs_schema_parse_' . $this->generator->getContext()->get_page_type(), $this->graphs, $this->generator->getContext());

        $graphs = $this->validate_type($graphs);

        $graphs = array_values($this->cleanData($graphs));

        $this->schema['@graph'] = $graphs;
    }

    /**
     * Validates a graph piece's type.
     *
     * Ensure the values are unique.
     * Only 1 value? Use that value without the array wrapping.
     */
    private function validate_type(array $piece): array
    {
        if (!isset($piece['@type']) or !\is_array($piece['@type'])) {
            // No type to validate.
            return $piece;
        }

        /*
         * Ensure the types are unique.
         * Use array_values to reset the indices (e.g. no 0, 2 because 1 was a duplicate).
         */
        $piece['@type'] = \array_values(\array_unique($piece['@type']));

        // Use the first value if there is only 1 type.
        if (\count($piece['@type']) === 1) {
            $piece['@type'] = \reset($piece['@type']);
        }

        return $piece;
    }

    /**
     * Strips HTML and removes all blank properties in each of our graphs.
     */
    private function cleanData(array $data): array
    {
        foreach ($data as $k => $v) {

            if (is_array($v)) {
                $v = $this->cleanData($v);
            }
            else {
                $v = preg_replace('@<(script|style)[^>]*?>.*?</\\1>@si', '', $v);
                $v = strip_tags($v);
                $v = trim($v);
            }

            if (empty($v) and !in_array($k, $this->nullableFields, true)) {
                unset($data[$k]);
            }
            else {
                $data[$k] = $v;
            }
        }

        return $data;
    }

    public function __toString(): string
    {
        return "<script type=application/ld+json>" . $this->export() . "</script>";
    }

    /**
     * Returns the JSON schema for the requested page.
     */
    public function export(): string
    {
        return WPFS_DEBUG ? wp_json_encode($this->schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : wp_json_encode($this->schema);
    }
}


