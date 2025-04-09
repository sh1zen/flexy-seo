<?php
/**
 * @author    sh1zen
 * @copyright Copyright (C) 2025.
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
        return wps_core()->home_url . '#organization';
    }

    /**
     * Returns the graph data.
     * @param \FlexySEO\Engine\Helpers\CurrentPage $currentPage
     * @param string $type
     * @param ...$args
     * @return GraphBuilder $data The graph data.
     */
    public function get(CurrentPage $currentPage, string $type = '', ...$args): GraphBuilder
    {
        $homeUrl = wps_core()->home_url;

        $schema = new GraphBuilder(
            [
                '@type'       => ucfirst(wps('wpfs')->settings->get('seo.schema.organization.type', 'organization')),
                '@id'         => self::getSchemaID(),
                'url'         => $homeUrl,
                'name'        => wps('wpfs')->settings->get('seo.schema.organization.name', html_entity_decode((string)get_bloginfo('name'), ENT_QUOTES)),
                'description' => wps('wpfs')->settings->get('seo.schema.organization.description', html_entity_decode((string)get_bloginfo('description'), ENT_QUOTES)),
                'sameAs'      => GraphUtility::socialUrls(),
                'address'     => wps('wpfs')->settings->get('seo.schema.organization.address', ''),
                //'founder'     => array_map('trim', explode(',', wps('wpfs')->settings->get('seo.schema.organization.founder', ''))),
            ]
        );

        $logo = self::logo();

        if (!empty($logo)) {
            $schema->set('logo', $logo);
        }

        $phone = wps('wpfs')->settings->get('seo.schema.organization.phone', '');
        $contactType = wps('wpfs')->settings->get('seo.schema.organization.contact_type', '');

        if (!empty($phone) and !empty($contactType)) {

            $schema->set(
                'contactPoint',
                [
                    '@type'       => 'ContactPoint',
                    'telephone'   => $phone,
                    'contactType' => ucwords(strtolower($contactType)),
                    'email'       => wps('wpfs')->settings->get('seo.schema.organization.email', get_bloginfo('admin_email')),
                    'url'         => wps('wpfs')->settings->get('seo.schema.organization.contactPage', ''),
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
        $logo = wps('wpfs')->settings->get('seo.schema.organization.logo', '');

        if ($logo) {
            return CommonGraphs::imageObject($logo, ['large', 'medium_large', 'medium'], 'organizationLogo');
        }

        $logo = wps('wpfs')->settings->get('org.logo_url.small', '');

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