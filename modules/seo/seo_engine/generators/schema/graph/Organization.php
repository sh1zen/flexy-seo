<?php

namespace FlexySEO\Engine\Generators\Schema\Graphs;

/**
 * Organization graph class.
 *
 * @since 1.2.0
 */
class Organization extends Graph
{
    /**
     * Returns the graph data.
     * @return array $data The graph data.
     *
     * @since 1.2.0
     */
    public function get($type = '')
    {
        $homeUrl = shzn()->utility->home_url;

        $data = [
            '@type' => 'Organization',
            '@id'   => $homeUrl . '#organization',
            'name'  => shzn('wpfs')->settings->get('seo.schema.organization.name', wpfseo()->string->decodeHtmlEntities(get_bloginfo('name'))),
            'url'   => $homeUrl,
        ];

        $logo = $this->logo();

        if (!empty($logo)) {
            $data['logo'] = $logo;
            $data['image'] = ['@id' => $homeUrl . '#organizationLogo'];
        }

        $socialUrls = $this->socialUrls();
        if ($socialUrls) {
            $data['sameAs'] = $socialUrls;
        }

        $phone = shzn('wpfs')->settings->get('seo.schema.organization.phone', '');
        $contactType = shzn('wpfs')->settings->get('seo.schema.organization.contact_type', '');

        if (!empty($phone) and !empty($contactType)) {

            $data['contactPoint'] = [
                '@type'       => 'ContactPoint',
                'telephone'   => $phone,
                'contactType' => ucwords(strtolower($contactType)),
            ];
        }
        return $data;
    }

    /**
     * Returns the logo data.
     *
     * @return array The logo data.
     * @since 1.2.0
     *
     */
    public function logo()
    {
        $logo = shzn('wpfs')->settings->get('seo.schema.organization.logo', '');

        if ($logo) {
            return $this->image($logo, 'organizationLogo');
        }

        $logo = shzn('wpfs')->settings->get('org.logo_url.small', '');

        if ($logo) {
            return $this->image($logo, 'organizationLogo');
        }

        if (!get_theme_support('custom-logo')) {
            return [];
        }

        $imageId = get_theme_mod('custom_logo');

        if ($imageId) {
            return $this->image($imageId, 'organizationLogo');
        }

        return [];
    }
}