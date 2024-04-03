<?php
/**
 * @author    sh1zen
 * @copyright Copyright (C) 2024.
 * @license   http://www.gnu.org/licenses/gpl.html GNU/GPL
 */

namespace FlexySEO\Engine\Generators;

use FlexySEO\Engine\Generator;
use FlexySEO\Engine\Helpers\CurrentPage;
use FlexySEO\Engine\Helpers\XRE_MetaBox;

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

    private array $graphTypes;

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
        $this->graphTypes = [];
    }

    public function build(): void
    {
        $this->load_graphs();

        do_action_ref_array('wpfs_schema', array(&$this));

        $this->build_graphs();

        $this->parse_graphs();
    }

    private function load_graphs(): void
    {
        if ($this->getContext()->is_404()) {
            return;
        }

        $this->add('WebSite');

        if ($this->getContext()->is_homepage()) {

            if (wps('wpfs')->settings->get("seo.schema.organization.is", false)) {
                $this->add('Organization');
            }

            if ($this->getContext()->is_home_posts_page()) {
                $this->add('CollectionPage');
            }
            else {
                $this->add('WebPage');
            }
        }
        elseif ($this->getContext()->is_posts_page() or $this->getContext()->is_home_posts_page() or wpfseo('helpers')->ecommerce->isWooCommerceShopPage()) {

            $this->add('CollectionPage');
        }
        elseif ($this->getContext()->is_simple_page()) {

            foreach ($this->getPostGraphs($this->getContext()->get_queried_object()) as $postGraph) {
                $this->add($postGraph);
            }
        }
        elseif ($this->getContext()->is_author_archive()) {

            $this->add('ProfilePage');
            $this->add('Person');
        }
        elseif ($this->getContext()->is_search()) {

            $this->add('SearchResultsPage');
        }
        elseif ($this->getContext()->is_post_type_archive() or $this->getContext()->is_date_archive() or $this->getContext()->is_term_archive()) {

            $this->add('CollectionPage');
        }

        $this->add('BreadcrumbList');
    }

    public function getContext(): CurrentPage
    {
        return $this->generator->getContext();
    }

    public function add($graphType, $build = null, $position = 0): void
    {
        if ($position) {

            // make position human like countable
            $position--;

            // If associative, use the key and value directly
            $keys = array_keys($this->graphTypes);
            $values = array_values($this->graphTypes);

            // Insert the key and value at the specified position
            array_splice($keys, $position, 0, [$graphType]);
            array_splice($values, $position, 0, [$build]);

            // Combine the modified keys and values into a new associative array
            $this->graphTypes = array_combine($keys, $values);
        }
        else {
            // this ensure uniqueness
            $this->graphTypes[$graphType] = $build;
        }
    }

    /**
     * Returns the graph types that are set for the current post.
     *
     * @param $post \WP_Post The post object.
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
    public static function get_post_graphType($post): string
    {
        return wpfs_get_post_meta_graphType($post, false, '') ?: XRE_MetaBox::get_default_GraphType($post->post_type);
    }

    private function build_graphs(): void
    {
        foreach ($this->graphTypes as $graphType => $graphBuild) {

            if (is_object($graphBuild) and $graphBuild instanceof GraphBuilder) {
                $this->graphs[] = $graphBuild->export();
            }
            else {
                $namespace = $this->get_graph_generator($graphType);

                if ($namespace) {

                    //if graph is actually a fully qualified class name lets use it
                    if (class_exists($graphType)) {
                        $namespace = $graphType;
                    }

                    $graphBuild = (new $namespace($this->generator))->get($this->getContext(), $graphType);

                    $this->graphs[] = $graphBuild->export();
                }
            }
        }
    }

    /**
     * Returns the JSON schema for the requested page.
     */
    public function export(): string
    {
        return WPFS_DEBUG ? wp_json_encode($this->schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : wp_json_encode($this->schema);
    }

    private function get_graph_generator($graphType)
    {
        $namespace = false;

        if (file_exists(WPFS_SEO_ENGINE . 'generators/schema/graph/' . $graphType . '.php')) {

            require_once WPFS_SEO_ENGINE . "generators/schema/graph/$graphType.php";

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

    private function parse_graphs(): void
    {
        $graphs = $this->validate_type($this->graphs);

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

    public function remove($graphType): void
    {
        unset($this->graphTypes[$graphType]);
    }

    public function __toString(): string
    {
        return "<script type=application/ld+json>" . $this->export() . "</script>";
    }
}


