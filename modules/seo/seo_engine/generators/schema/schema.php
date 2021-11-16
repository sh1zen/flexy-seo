<?php
/**
 * @author    sh1zen
 * @copyright Copyright (C)  2021
 * @license   http://www.gnu.org/licenses/gpl.html GNU/GPL
 */

namespace FlexySEO\Engine\Generators;

use FlexySEO\core\Options;
use FlexySEO\Engine\Helpers\CurrentPage;
use SHZN\core\UtilEnv;

class Schema
{
    /**
     * All existing graphs.
     *
     * @since 1.2.0
     *
     * @var array
     */
    public static $webPageGraphs = [
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

    public $nullableFields = [
        'price' // Needs to be 0 if free for Software Application.
    ];

    /**
     * @type String[]
     */
    private $schema;

    /**
     * @var String[]
     */
    private $graphTypes;

    /**
     * @var String[]
     */
    private $graphs;

    /**
     * @var CurrentPage
     */
    private $current_page;

    /**
     * @var \FlexySEO\Engine\Generator
     */
    private $generator;

    public function __construct($generator = null)
    {
        $this->current_page = wpfseo()->currentPage;

        $this->schema = [
            "@context" => "https://schema.org",
            "@graph"   => []
        ];

        $this->graphTypes = [];

        $this->graphs = [];

        if (!$generator) {
            UtilEnv::write_log('Generator not valid in schema.org');
            $generator = wpfseo('core')->load_generator($this->current_page);
        }

        $this->generator = $generator;
    }

    public function build()
    {
        $this->load_graphs();

        $this->graphs2schema();
    }

    private function load_graphs()
    {
        $cPage = $this->current_page;

        $this->graphTypes = [
            'WebSite'
        ];

        if (shzn('wpfs')->settings->get("seo.schema.organization", false)) {
            $this->graphTypes[] = 'Organization';
        }

        $this->graphTypes[] = 'BreadcrumbList';

        if ($cPage->is_static_posts_page() or $cPage->is_home_posts_page() or wpfseo()->ecommerce->isWooCommerceShopPage()) {
            $this->graphTypes[] = 'CollectionPage';
            return;
        }

        if ($cPage->is_home_static_page() or $cPage->is_front_page()) {
            $this->graphTypes[] = 'posts' === get_option('show_on_front') ? 'CollectionPage' : 'WebPage';
            return;
        }

        if ($cPage->is_simple_page()) {

            // Check if we're on a BuddyPress member page.
            if (function_exists('bp_is_user') and bp_is_user()) {
                array_push($this->graphTypes, 'ProfilePage', 'PersonAuthor');
                return;
            }

            $postGraphs = $this->getPostGraphs($cPage->get_post());

            if (is_array($postGraphs)) {
                $this->graphTypes = array_merge($this->graphTypes, $postGraphs);
            }
            else {
                $this->graphTypes[] = $postGraphs;
            }

            return;
        }

        if ($cPage->is_author_archive()) {
            array_push($this->graphTypes, 'CollectionPage', 'PersonAuthor');
            return;
        }

        if ($cPage->is_post_type_archive() or $cPage->is_date_archive() or $cPage->is_term_archive()) {
            $this->graphTypes[] = 'CollectionPage';
            return;
        }

        if ($cPage->is_search()) {
            $this->graphTypes[] = 'SearchResultsPage';
            return;
        }

        if ($cPage->is_404()) {
            //$this->graphTypes[] = '404';
        }
    }

    /**
     * Returns the graph types that are set for the current post.
     *
     * @param  $post \WP_Post  The post object.
     * @return string[] The graph name(s).
     * @since 1.2.0
     */
    public function getPostGraphs($post)
    {
        $postGraphs = ['WebPage'];

        if ($post) {

            if ('page' !== $post->post_type) {
                $this->graphTypes[] = 'PersonAuthor';
            }

            if (wpfseo()->post->get_main_image()) {
                $postGraphs[] = "ImageObject";
            }

            $graphType = Options::get($post->ID, "graphType", "schema", "");

            if ($graphType) {
                $postGraphs[] = ucfirst($graphType);
            }
            elseif ($post->post_type !== 'page') {
                $postGraphs[] = "Article";
            }
        }

        return $postGraphs;
    }

    private function graphs2schema()
    {
        require_once WPFS_SEO_ENGINE_GENERATORS . 'schema/graph/Graph.php';
        require_once WPFS_SEO_ENGINE_GENERATORS . 'schema/graph/WebPage.php';
        require_once WPFS_SEO_ENGINE_GENERATORS . 'schema/graph/Person.php';
        require_once WPFS_SEO_ENGINE_GENERATORS . 'schema/graph/Article.php';

        $graphTypes = apply_filters('wpfs_schema_graphs', array_unique(array_filter($this->graphTypes)), $this->current_page);

        foreach ($graphTypes as $graphType) {

            $filter = "wpfs_schema_type_" . strtolower($graphType);

            if (file_exists(WPFS_SEO_ENGINE . 'generators/schema/graph/' . $graphType . '.php')) {

                require_once WPFS_SEO_ENGINE . "generators/schema/graph/{$graphType}.php";

                if (class_exists("FlexySEO\Engine\Generators\Schema\Graphs\\$graphType")) {
                    $namespace = "FlexySEO\Engine\Generators\Schema\Graphs\\$graphType";

                    //if graph is actually a fully qualified class name
                    if (class_exists($graphType)) {
                        $namespace = $graphType;
                    }

                    $this->add(apply_filters($filter, (new $namespace($this->generator))->get(), $graphType));
                }
            }
            elseif (in_array($graphType, self::$webPageGraphs)) {

                $namespace = "FlexySEO\Engine\Generators\Schema\Graphs\WebPage";

                //if graph is actually a fully qualified class name
                if (class_exists($graphType)) {
                    $namespace = $graphType;
                }

                $this->add(apply_filters($filter, (new $namespace($this->generator))->get($graphType), $graphType));
            }
        }

        $graphs = apply_filters('wpfs_schema_build', $this->graphs, $graphTypes);

        $graphs = $this->validate_type($graphs);

        $graphs = array_values($this->cleanData($graphs));

        $this->schema['@graph'] = $graphs;
    }

    public function add($property)
    {
        $this->graphs[] = $property;
    }

    /**
     * Validates a graph piece's type.
     *
     * Ensure the values are unique.
     * Only 1 value? Use that value without the array wrapping.
     *
     * @param array $piece The graph piece.
     *
     * @return array The graph piece.
     */
    private function validate_type($piece)
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
     *
     * @param array $data The graph data.
     * @return array       The cleaned graph data.
     * @since 1.2.0
     *
     */
    private function cleanData($data)
    {
        foreach ($data as $k => &$v) {
            if (is_array($v)) {
                $v = $this->cleanData($v);
            }
            else {
                $v = trim(wp_strip_all_tags($v));
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

    public function __toString()
    {
        return "<script type=application/ld+json>" . $this->export() . "</script>";
    }

    /**
     * Returns the JSON schema for the requested page.
     *
     * @return \string The JSON schema.
     * @since 1.2.0
     *
     */
    public function export()
    {
        return WPFS_DEBUG ? wp_json_encode($this->schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : wp_json_encode($this->schema);
    }
}


