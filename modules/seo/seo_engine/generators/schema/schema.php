<?php
/**
 * @author    sh1zen
 * @copyright Copyright (C)  2021
 * @license   http://www.gnu.org/licenses/gpl.html GNU/GPL
 */

namespace FlexySEO\Engine\Generators;

use FlexySEO\core\Options;
use FlexySEO\Engine\Helpers\CurrentPage;

// C:\Program Files (x86)\EasyPHP-Devserver-17\eds-www\casabitare\cms\extensions\wordpress-seo\src\generators\schema

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
     * @var \FlexySEO\Engine\Generators\Schema\Graph[]
     */
    private $graphs;

    /**
     * @var CurrentPage
     */
    private $current_page;

    public function __construct()
    {
        $this->schema = [
            "@context" => "https://schema.org",
            "@graph"   => []
        ];

        $this->graphs = [];

        $this->current_page = wpfseo()->currentPage;
    }

    public function build()
    {
        $this->load_graphs();

        $this->graphs2schema();
    }

    private function load_graphs()
    {
        $cPage = $this->current_page;

        $this->graphs = [
            'WebSite'
        ];

        if (shzn('wpfs')->settings->get("seo.schema.org.organization", false)) {
            $this->graphs[] = 'Organization';
        }

        $this->graphs[] = 'BreadcrumbList';

        if ($cPage->is_static_posts_page() or $cPage->is_home_posts_page() or wpfseo()->ecommerce->isWooCommerceShopPage()) {
            $this->graphs[] = 'CollectionPage';
            return;
        }

        if ($cPage->is_home_static_page() or $cPage->is_front_page()) {
            $this->graphs[] = 'posts' === get_option('show_on_front') ? 'CollectionPage' : 'WebPage';
            return;
        }

        if ($cPage->is_simple_page()) {

            // Check if we're on a BuddyPress member page.
            if (function_exists('bp_is_user') and bp_is_user()) {
                array_push($this->graphs, 'ProfilePage', 'PersonAuthor');
                return;
            }

            $postGraphs = $this->getPostGraphs($cPage->get_post());

            if (is_array($postGraphs)) {
                $this->graphs = array_merge($this->graphs, $postGraphs);
            }
            else {
                $this->graphs[] = $postGraphs;
            }

            return;
        }

        if ($cPage->is_author_archive()) {
            array_push($this->graphs, 'CollectionPage', 'PersonAuthor');
            return;
        }

        if ($cPage->is_post_type_archive() or $cPage->is_date_archive() or $cPage->is_term_archive()) {
            $this->graphs[] = 'CollectionPage';
            return;
        }

        if ($cPage->is_search()) {
            $this->graphs[] = 'SearchResultsPage';
            return;
        }

        if ($cPage->is_404()) {
            //$this->graphs[] = '404';
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
                $this->graphs[] = 'PersonAuthor';
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
        $graphs = apply_filters('wpfs_schema_graphs', array_unique(array_filter($this->graphs)), $this->current_page);

        flex_var_dump($graphs);

        foreach ($graphs as $graph) {

            if (file_exists(WPFS_SEO_ENGINE . 'generators/schema/graph/' . $graph)) {

                require_once WPFS_SEO_ENGINE . 'generators/schema/graph/' . $graph;

                if (class_exists("FlexySEO\Engine\Generators\Schema\\$graph")) {
                    $namespace = "FlexySEO\Engine\Generators\Schema\\$graph";

                    //if graph is actually a fully qualified class name
                    if (class_exists($graph)) {
                        $namespace = $graph;
                    }

                    $this->add(array_filter((new $namespace)->get()));
                }
            }
        }

        $this->schema['@graph'] = apply_filters('wpfs_schema_build', $this->schema['@graph']);

        $this->schema['@graph'] = $this->cleanData($this->schema['@graph']);
    }

    public function add($property)
    {
        $this->schema["@graph"][] = $property;
    }

    /**
     * Strips HTML and removes all blank properties in each of our graphs.
     *
     * @param array $data The graph data.
     * @return array       The cleaned graph data.
     * @since 4.0.13
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
     * @return string The JSON schema.
     * @since 1.2.0
     *
     */
    public function export()
    {
        return WPFS_DEBUG ? wp_json_encode($this->schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : wp_json_encode($this->schema);

        ?>
        <script type="application/hal+json">
            {
                "@graph": [
                    {
                        "@type": "WebSite",
                        "@id": "https://casabitare.it/news/#website",
                        "url": "https://casabitare.it/news/",
                        "name": "casabitare.it - news",
                        "description": "Importanti notizia da casabitare.it",
                        "potentialAction": [
                            {
                                "@type": "SearchAction",
                                "target": {
                                    "@type": "EntryPoint",
                                    "urlTemplate": "https://casabitare.it/news/?s={search_term_string}"
                                },
                                "query-input": "required name=search_term_string"
                            }
                        ],
                        "inLanguage": "it-IT"
                    },
                    {
                        "@type": "CollectionPage",
                        "@id": "https://casabitare.it/news/category/immobiliare/#webpage",
                        "url": "https://casabitare.it/news/category/immobiliare/",
                        "name": "Immobiliare Archivi - casabitare.it - news",
                        "isPartOf": {
                            "@id": "https://casabitare.it/news/#website"
                        },
                        "breadcrumb": {
                            "@id": "https://casabitare.it/news/category/immobiliare/#breadcrumb"
                        },
                        "inLanguage": "it-IT",
                        "potentialAction": [
                            {
                                "@type": "ReadAction",
                                "target": [
                                    "https://casabitare.it/news/category/immobiliare/"
                                ]
                            }
                        ]
                    },
                    {
                        "@type": "BreadcrumbList",
                        "@id": "https://casabitare.it/news/category/immobiliare/#breadcrumb",
                        "itemListElement": [
                            {
                                "@type": "ListItem",
                                "position": 1,
                                "name": "Home",
                                "item": "https://casabitare.it/news/"
                            },
                            {
                                "@type": "ListItem",
                                "position": 2,
                                "name": "Immobiliare"
                            }
                        ]
                    }
                ]
            }
        </script>
        <?php
    }

    /**
     * Sanitizes a HTML string by stripping all tags except headings, breaks, lists, links, paragraphs and formatting.
     *
     * @param string $html The original HTML.
     *
     * @return string The sanitized HTML.
     */
    private function sanitize($html)
    {
        return \strip_tags($html, '<h1><h2><h3><h4><h5><h6><br><ol><ul><li><a><p><b><strong><i><em>');
    }

    /**
     * Strips the tags in a smart way.
     *
     * @param string $html The original HTML.
     *
     * @return string The sanitized HTML.
     */
    private function smart_strip_tags($html)
    {
        // Replace all new lines with spaces.
        $html = \preg_replace('/(\r|\n)/', ' ', $html);

        // Replace <br> tags with spaces.
        $html = \preg_replace('/<br(\s*)?\/?>/i', ' ', $html);

        // Replace closing </p> and other tags with the same tag with a space after it, so we don't end up connecting words when we remove them later.
        $html = \preg_replace('/<\/(p|div|h\d)>/i', '</$1> ', $html);

        // Replace list items with list identifiers so it still looks natural.
        $html = \preg_replace('/(<li[^>]*>)/i', '$1• ', $html);

        // Strip tags.
        $html = \wp_strip_all_tags($html);

        // Replace multiple spaces with one space.
        $html = \preg_replace('!\s+!', ' ', $html);

        return \trim($html);
    }
}


