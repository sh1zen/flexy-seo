<?php
/**
 * @author    sh1zen
 * @copyright Copyright (C) 2024.
 * @license   http://www.gnu.org/licenses/gpl.html GNU/GPL
 */

namespace FlexySEO\Engine\Generators\Schema\Graphs;

use FlexySEO\Engine\Generators\GraphBuilder;
use FlexySEO\Engine\Helpers\CurrentPage;

/**
 * WebSite graph class.
 */
class WebSite extends Graph
{
    /**
     * Returns the graph data.
     *
     * @param \FlexySEO\Engine\Helpers\CurrentPage $currentPage
     * @param string $type
     * @param ...$args
     * @return GraphBuilder $data The graph data.
     */
    public function get(CurrentPage $currentPage, string $type = '', ...$args): GraphBuilder
    {
        $homeUrl = wps_core()->home_url;

        $schema = new GraphBuilder([
                '@type'       => 'WebSite',
                '@id'         => self::getSchemaID(),
                'url'         => $homeUrl,
                'name'        => html_entity_decode((string)get_bloginfo('name'), ENT_QUOTES),
                'description' => html_entity_decode((string)get_bloginfo('description'), ENT_QUOTES),
                'inLanguage'  => wpfseo('helpers')->language->currentLanguageCodeBCP47()
            ]
        );

        $schema->set(
            'publisher',
            wps('wpfs')->settings->get("seo.schema.organization.is", false) ?
                ['@id' => Organization::getSchemaID()] :
                Person::build(get_user_by('email', get_bloginfo('admin_email'))->ID)->export()
        );

        if (wps('wpfs')->settings->get('seo.schema.sitelink', false) and $currentPage->is_homepage()) {

            $schema->set('potentialAction',
                [
                    '@type'       => 'SearchAction',
                    'target'      => [
                        '@type'       => 'EntryPoint',
                        'urlTemplate' => $homeUrl . '?s={search_term_string}'
                    ],
                    'query-input' => 'required name=search_term_string',
                ]
            );
        }

        return $schema;
    }

    public static function getSchemaID(): string
    {
        return wps_core()->home_url . '#website';
    }
}