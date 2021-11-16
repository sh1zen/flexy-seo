<?php

namespace FlexySEO\Engine\Generators\Schema\Graphs;

/**
 * WebSite graph class.
 *
 * @since 1.2.0
 */
class WebSite extends Graph
{
    /**
     * Returns the graph data.
     *
     * @return array $data The graph data.
     * @since 1.2.0
     *
     */
    public function get($type = '')
    {
        $homeUrl = shzn()->utility->home_url;
        $data = [
            '@type'       => 'WebSite',
            '@id'         => $homeUrl . '#website',
            'url'         => $homeUrl,
            'name'        => wpfseo()->string->decodeHtmlEntities(get_bloginfo('name')),
            'description' => wpfseo()->string->decodeHtmlEntities(get_bloginfo('description')),
            'inLanguage'  => wpfseo()->language->currentLanguageCodeBCP47(),
            'publisher'   => ['@id' => $homeUrl . '#' . (shzn('wpfs')->settings->get('seo.schema.organization', false) ? 'organization' : 'person')]
        ];

        if (is_front_page() and shzn('wpfs')->settings->get('seo.schema.sitelink', false)) {
            $data['potentialAction'] = [
                '@type'       => 'SearchAction',
                'target'      => [
                    '@type'       => 'EntryPoint',
                    'urlTemplate' => $homeUrl . '?s={search_term_string}'
                ],
                'query-input' => 'required name=search_term_string',
            ];
        }

        return $data;
    }
}