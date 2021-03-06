<?php
/**
 * @author    sh1zen
 * @copyright Copyright (C)  2022
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
    public function get(CurrentPage $currentPage, string $type = '', ...$args)
    {
        $homeUrl = shzn()->utility->home_url;

        $schema = new GraphBuilder([
                '@type'       => 'WebSite',
                '@id'         => self::getSchemaID(),
                'url'         => $homeUrl,
                'name'        => wpfseo()->string->decodeHtmlEntities(get_bloginfo('name')),
                'description' => wpfseo()->string->decodeHtmlEntities(get_bloginfo('description')),
                'inLanguage'  => wpfseo()->language->currentLanguageCodeBCP47()
            ]
        );

        $schema->set(
            'publisher',
            shzn('wpfs')->settings->get("seo.schema.organization.is", false) ?
                ['@id' => Organization::getSchemaID()] :
                Person::build(get_user_by('email', get_bloginfo('admin_email'))->ID)->export()
        );

        if (shzn('wpfs')->settings->get('seo.schema.sitelink', false)) {

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

    public static function getSchemaID()
    {
        return shzn()->utility->home_url . '#website';
    }
}