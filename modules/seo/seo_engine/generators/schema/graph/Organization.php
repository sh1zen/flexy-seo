<?php
/**
 * @author    sh1zen
 * @copyright Copyright (C)  2022
 * @license   http://www.gnu.org/licenses/gpl.html GNU/GPL
 */

namespace FlexySEO\Engine\Generators\Schema\Graphs;

use FlexySEO\Engine\Generators\CommonGraphs;
use FlexySEO\Engine\Generators\GraphBuilder;
use FlexySEO\Engine\Generators\GraphUtility;
use FlexySEO\Engine\Helpers\CurrentPage;

/**
 * Organization graph class.
 */
class Organization extends Graph
{
    public static function getSchemaID()
    {
        return shzn()->utility->home_url . '#organization';
    }

    /**
     * Returns the graph data.
     * @param \FlexySEO\Engine\Helpers\CurrentPage $currentPage
     * @param string $type
     * @param ...$args
     * @return GraphBuilder $data The graph data.
     */
    public function get(CurrentPage $currentPage, string $type = '', ...$args)
    {
        $homeUrl = shzn()->utility->home_url;

        $schema = new GraphBuilder(
            [
                '@type'       => ucfirst(shzn('wpfs')->settings->get('seo.schema.organization.type', 'organization')),
                '@id'         => self::getSchemaID(),
                'url'         => $homeUrl,
                'name'        => shzn('wpfs')->settings->get('seo.schema.organization.name', wpfseo()->string->decodeHtmlEntities(get_bloginfo('name'))),
                'description' => shzn('wpfs')->settings->get('seo.schema.organization.description', wpfseo()->string->decodeHtmlEntities(get_bloginfo('description'))),
                'sameAs'      => GraphUtility::socialUrls(),
                'address'     => shzn('wpfs')->settings->get('seo.schema.organization.address', ''),
                //'founder'     => array_map('trim', explode(',', shzn('wpfs')->settings->get('seo.schema.organization.founder', ''))),
            ]
        );

        $logo = self::logo();

        if (!empty($logo)) {
            $schema->set('logo', $logo);
        }

        $phone = shzn('wpfs')->settings->get('seo.schema.organization.phone', '');
        $contactType = shzn('wpfs')->settings->get('seo.schema.organization.contact_type', '');

        if (!empty($phone) and !empty($contactType)) {

            $schema->set(
                'contactPoint',
                [
                    '@type'       => 'ContactPoint',
                    'telephone'   => $phone,
                    'contactType' => ucwords(strtolower($contactType)),
                    'email'       => shzn('wpfs')->settings->get('seo.schema.organization.email', get_bloginfo('admin_email')),
                    'url'         => shzn('wpfs')->settings->get('seo.schema.organization.contactPage', ''),
                ]
            );
        }
        return $schema;
    }

    /**
     * Returns the logo data.
     *
     * @return array The logo data.
     */
    public static function logo()
    {
        $logo = shzn('wpfs')->settings->get('seo.schema.organization.logo', '');

        if ($logo) {
            return CommonGraphs::imageObject($logo, ['large', 'medium_large', 'medium'], 'organizationLogo');
        }

        $logo = shzn('wpfs')->settings->get('org.logo_url.small', '');

        if ($logo) {
            return CommonGraphs::imageObject($logo, ['large', 'medium_large', 'medium'], 'organizationLogo');
        }

        if (!get_theme_support('custom-logo')) {
            return [];
        }

        $imageId = get_theme_mod('custom_logo');

        if ($imageId) {
            return CommonGraphs::imageObject($imageId, ['large', 'medium_large', 'medium'], 'organizationLogo');
        }

        return [];
    }
}